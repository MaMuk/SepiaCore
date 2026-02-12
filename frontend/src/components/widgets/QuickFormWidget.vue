<template>
  <div class="quick-form-widget h-100 d-flex flex-column">
    <div v-if="editable" class="quick-form-config border-bottom pb-2 mb-2">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label small text-muted">Entity</label>
          <select v-model="localEntity" class="form-select form-select-sm">
            <option value="">Select entity</option>
            <option
              v-for="option in entityOptions"
              :key="option.value"
              :value="option.value"
              :disabled="!option.allowed"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-12 col-md-7">
          <label class="form-label small text-muted">Fields</label>
          <select v-model="localFields" class="form-select form-select-sm" multiple>
            <option v-for="option in fieldOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>
      <div class="form-text text-muted small mt-1">
        Select the fields users should fill out. Defaults to name fields when available.
      </div>
    </div>

    <div v-if="!canRenderForm" class="quick-form-empty text-muted small flex-grow-1 d-flex align-items-center justify-content-center">
      {{ guidanceMessage }}
    </div>

    <div v-else class="flex-grow-1 d-flex flex-column quick-form-content">
      <div v-if="error" class="alert alert-danger py-1 mb-2" role="alert">
        {{ error }}
      </div>
      <form class="quick-form-body d-flex flex-column" @submit.prevent="handleSave">
        <div class="quick-form-fields">
          <div class="row g-1">
            <div v-for="fieldName in localFields" :key="fieldName" class="col-12 col-md-6">
            <FieldRenderer
              :field-name="fieldName"
              :field-def="fieldDefinitions[fieldName]"
              :value="formData[fieldName]"
              :mode="'create'"
              :form-id="formId"
              :entity-name="localEntity"
              @update:value="updateFormData(fieldName, $event)"
            />
            </div>
          </div>
        </div>
        <div class="quick-form-footer d-flex justify-content-end pt-2">
          <button class="btn btn-sm btn-primary" type="submit" :disabled="saving">
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            Save
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useMetadataStore } from '../../stores/metadata'
import { useToastStore } from '../../stores/toast'
import { useWinbox } from '../../composables/useWinbox'
import { useAuthStore } from '../../stores/auth'
import api from '../../services/api'
import FieldRenderer from '../FieldRenderer.vue'

const props = defineProps({
  widget: { type: Object, required: true },
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['update-widget'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()
const { openRecordWindow } = useWinbox()
const authStore = useAuthStore()

const localEntity = ref('')
const localFields = ref([])
const formData = ref({})
const saving = ref(false)
const error = ref('')
const isHydrating = ref(false)
const suppressEntityReset = ref(false)
const formId = ref(`quick-form-${Date.now()}`)

const entityOptions = computed(() => {
  const entities = metadataStore.entities || {}
  const protectedEntities = metadataStore.protectedEntities || []
  const protectedSet = new Set(protectedEntities.map((name) => name.toLowerCase()))
  return Object.keys(entities)
    .filter((name) => !protectedSet.has(name.toLowerCase()))
    .map((name) => ({
      value: name,
      label: metadataStore.formatEntityName(name),
      allowed: isEntityAllowedForCapability(name, 'quick-form')
    }))
})

const entityMeta = computed(() => {
  if (!localEntity.value) return null
  return metadataStore.getEntityMetadata(localEntity.value)
})

const fieldDefinitions = computed(() => {
  return entityMeta.value?.fields || {}
})

const fieldOptions = computed(() => {
  const fields = fieldDefinitions.value || {}
  return Object.entries(fields)
    .filter(([name, def]) => name !== 'id' && !def?.readonly)
    .map(([name]) => ({
      value: name,
      label: formatFieldName(name)
    }))
})

const canRenderForm = computed(() => {
  return !!localEntity.value && localFields.value.length > 0 && isEntityAllowedForCapability(localEntity.value, 'quick-form')
})

const guidanceMessage = computed(() => {
  if (!localEntity.value) return 'Select an entity to configure this quick form.'
  if (!isEntityAllowedForCapability(localEntity.value, 'quick-form')) {
    return 'You do not have access to create records for this entity via quick form.'
  }
  if (!localFields.value.length) return 'Select at least one field to display.'
  return ''
})

onMounted(async () => {
  if (!metadataStore.metadata) {
    try {
      await metadataStore.fetchMetadata()
    } catch (err) {
      toastStore.error('Failed to load metadata')
    }
  }
  hydrateFromConfig(props.widget?.config)
})

watch(
  () => props.widget?.config,
  (nextConfig) => {
    hydrateFromConfig(nextConfig)
  },
  { deep: true }
)

watch(localEntity, (next, prev) => {
  if (isHydrating.value) return
  if (suppressEntityReset.value) {
    suppressEntityReset.value = false
    return
  }
  if (!next || next !== prev) {
    const defaults = next ? resolveDefaultFields(next) : []
    localFields.value = defaults
    resetFormData()
    emitConfigUpdateIfNeeded()
  }
})

watch(localFields, () => {
  if (isHydrating.value) return
  normalizeFormData()
  emitConfigUpdateIfNeeded()
})

function hydrateFromConfig(config = {}) {
  isHydrating.value = true
  const nextEntity = config?.entity || ''
  const nextFields = normalizeFieldList(config?.fields)

  suppressEntityReset.value = true
  localEntity.value = nextEntity
  localFields.value = nextFields.length > 0 && nextEntity ? nextFields : (nextEntity ? resolveDefaultFields(nextEntity) : [])

  normalizeFormData()
  isHydrating.value = false
  emitConfigUpdateIfNeeded()
}

function emitConfigUpdateIfNeeded() {
  const nextConfig = buildConfigFromState()
  if (!configEquals(nextConfig, props.widget?.config || {})) {
    emit('update-widget', { id: props.widget.id, config: nextConfig })
  }
}

function buildConfigFromState() {
  return {
    entity: localEntity.value || '',
    fields: Array.isArray(localFields.value) ? [...localFields.value] : []
  }
}

function configEquals(a, b) {
  if (!a || !b) return false
  if (a.entity !== (b.entity || '')) return false
  const aFields = Array.isArray(a.fields) ? a.fields : []
  const bFields = Array.isArray(b.fields) ? b.fields : []
  if (aFields.length !== bFields.length) return false
  for (let i = 0; i < aFields.length; i += 1) {
    if (aFields[i] !== bFields[i]) return false
  }
  return true
}

function resolveDefaultFields(entityName) {
  const meta = metadataStore.getEntityMetadata(entityName)
  const fields = meta?.fields || {}
  const defaults = []
  if (meta?.person) {
    if (fields.first_name && !fields.first_name?.readonly) defaults.push('first_name')
    if (fields.last_name && !fields.last_name?.readonly) defaults.push('last_name')
  } else if (fields.name && !fields.name?.readonly) {
    defaults.push('name')
  }

  if (defaults.length > 0) return defaults

  return Object.entries(fields)
    .filter(([name, def]) => name !== 'id' && !def?.readonly)
    .slice(0, 2)
    .map(([name]) => name)
}

function normalizeFieldList(fields) {
  if (Array.isArray(fields)) return [...fields]
  if (fields && typeof fields === 'object') {
    return Object.keys(fields)
      .sort((a, b) => Number(a) - Number(b))
      .map((key) => fields[key])
      .filter(Boolean)
  }
  return []
}

function normalizeFormData() {
  const next = {}
  localFields.value.forEach((field) => {
    next[field] = formData.value?.[field] ?? ''
  })
  formData.value = next
}

function resetFormData() {
  formData.value = {}
  normalizeFormData()
}

function updateFormData(fieldName, value) {
  formData.value[fieldName] = value
}

function formatFieldName(fieldName) {
  return fieldName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function toBoolean(value) {
  return value === true || value === 'true' || value === 1 || value === '1'
}

function isEntityAllowedForCapability(entityName, capabilityKey) {
  const entityMeta = metadataStore.getEntityMetadata(entityName)
  if (!entityMeta) return false

  const capability = entityMeta.capabilities?.[capabilityKey]
  let active = true
  let requiresAdmin = false

  if (capability !== undefined) {
    if (typeof capability === 'boolean' || typeof capability === 'string') {
      active = toBoolean(capability)
    } else if (typeof capability === 'object') {
      if (capability.active !== undefined) {
        active = toBoolean(capability.active)
      }
      if (capability.requires_admin !== undefined) {
        requiresAdmin = toBoolean(capability.requires_admin)
      }
    }
  }

  if (requiresAdmin && !authStore.isAdmin) return false
  return active
}

async function handleSave() {
  if (!localEntity.value || localFields.value.length === 0) return
  if (!isEntityAllowedForCapability(localEntity.value, 'quick-form')) {
    error.value = 'You do not have access to create records for this entity via quick form.'
    toastStore.error(error.value)
    return
  }
  saving.value = true
  error.value = ''
  try {
    const submitData = {}
    localFields.value.forEach((fieldName) => {
      const def = fieldDefinitions.value[fieldName]
      if (def?.readonly || fieldName === 'id') return
      submitData[fieldName] = formData.value[fieldName]
    })

    const response = await api.post(`/create/${localEntity.value}`, submitData)
    const record = response.data?.record

    if (record?.id) {
      const meta = metadataStore.getEntityMetadata(localEntity.value)
      let recordName = null
      if (meta?.person && (record.first_name || record.last_name)) {
        recordName = `${record.first_name || ''} ${record.last_name || ''}`.trim()
      } else if (record.name) {
        recordName = record.name
      }

      openRecordWindow(localEntity.value, record.id, 'detail', recordName || null)
    }

    resetFormData()
    toastStore.success('Record created successfully')
  } catch (err) {
    error.value = err?.response?.data?.error || 'Failed to create record'
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.quick-form-widget {
  min-height: 120px;
  height: 100%;
}

.quick-form-body {
  flex: 1 1 auto;
  min-height: 0;
}

.quick-form-content {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
}

.quick-form-fields {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  padding-right: 0.25rem;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-y;
}

.quick-form-footer {
  flex: 0 0 auto;
  margin-top: 0.75rem;
}

.quick-form-body :deep(.form-label) {
  margin-bottom: 0.25rem;
}

.quick-form-body :deep(.mb-3) {
  margin-bottom: 0.5rem !important;
}
</style>
