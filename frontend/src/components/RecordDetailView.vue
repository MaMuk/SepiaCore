<template>
<div class="record-detail-view" v-if="fieldDefinitions && layout">
    <div v-if="loading" class="text-center p-4">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <div v-else-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </div>

    <div v-else>
      <!-- Action buttons -->
      <div class="action-buttons-bar" v-if="currentMode === 'detail' && record?.id">
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-primary btn-sm" @click="switchToEdit">Edit</button>
          <button class="btn btn-danger btn-sm" @click="showDeleteConfirm = true">Delete</button>
        </div>
      </div>

      <div class="action-buttons-bar" v-if="currentMode === 'edit' || currentMode === 'create'">
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-secondary btn-sm" @click="handleCancel">Cancel</button>
          <button class="btn btn-primary btn-sm" @click="handleSave" :disabled="saving">
            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
            Save
          </button>
        </div>
      </div>

      <!-- Form/Detail Content -->
      <form v-if="currentMode === 'edit' || currentMode === 'create'" @submit.prevent="handleSave">
        <div class="row g-3">
          <template v-for="(column, colIndex) in layout" :key="colIndex">
            <template v-for="(fieldName, fieldIndex) in column" :key="fieldIndex">
              <div v-if="fieldName" :class="getColumnClass(column.length)">
                <FieldRenderer
                  :field-name="fieldName"
                  :field-def="fieldDefinitions[fieldName]"
                  :value="formData[fieldName]"
                  :relationship="relationship?.[fieldName]"
                  :mode="currentMode"
                  :form-id="formId"
                  @update:value="updateFormData(fieldName, $event)"
                />
              </div>
            </template>
          </template>
        </div>
      </form>

      <div v-else class="row g-3">
        <template v-for="(column, colIndex) in layout" :key="colIndex">
          <template v-for="(fieldName, fieldIndex) in column" :key="fieldIndex">
            <div v-if="fieldName" :class="getColumnClass(column.length)">
              <div class="mb-3">
                <strong>{{ formatFieldName(fieldName) }}:</strong>
                <div class="mt-1">
                <FieldRenderer
                  :field-name="fieldName"
                  :field-def="fieldDefinitions[fieldName]"
                  :value="record?.[fieldName]"
                  :relationship="relationship?.[fieldName]"
                  :mode="'detail'"
                  :form-id="formId"
                  @relationship-click="handleRelationshipClick"
                />
                </div>
              </div>
            </div>
          </template>
        </template>
      </div>

      <!-- Subpanels -->
      <div v-if="currentMode === 'detail' && record?.id && subpanels.length > 0" class="subpanels-container">
        <Subpanel
          v-for="(subpanelDef, index) in subpanels"
          :key="`subpanel-${index}`"
          :parent-entity="entityName"
          :parent-id="record.id"
          :subpanel-def="subpanelDef"
          :subpanel-key="`subpanel-${index}`"
          @record-click="handleSubpanelRecordClick"
        />
      </div>
    </div>

    <!-- Delete Confirmation Popup -->
    <div v-if="showDeleteConfirm" class="delete-confirm-overlay" @click.self="showDeleteConfirm = false">
      <div class="delete-confirm-popup">
        <h5 class="mb-3">Confirm Delete</h5>
        <p class="mb-4">Are you sure you want to delete this record? This action cannot be undone.</p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-secondary btn-sm" @click="showDeleteConfirm = false">Cancel</button>
          <button class="btn btn-danger btn-sm" @click="handleDelete" :disabled="deleting">
            <span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>
            Yes, Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import api from '../services/api'
import FieldRenderer from './FieldRenderer.vue'
import Subpanel from './Subpanel.vue'

const props = defineProps({
  entityName: {
    type: String,
    required: true
  },
  recordId: {
    type: String,
    default: null
  },
  initialMode: {
    type: String,
    default: 'detail', // 'detail', 'edit', 'create'
    validator: (value) => ['detail', 'edit', 'create'].includes(value)
  }
})

const emit = defineEmits(['saved', 'cancel', 'update:recordId', 'update:mode', 'recordLoaded', 'deleted', 'recordClick'])

const currentMode = ref(props.initialMode)
const internalRecordId = ref(props.recordId)

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const showDeleteConfirm = ref(false)
const error = ref(null)
const record = ref(null)
const relationship = ref({})
const formData = ref({})
const formId = ref(`form-${Date.now()}`)

const fieldDefinitions = computed(() => {
  const entityMeta = metadataStore.getEntityMetadata(props.entityName)
  return entityMeta?.fields || {}
})

const layout = computed(() => {
  const entityMeta = metadataStore.getEntityMetadata(props.entityName)
  return entityMeta?.module_views?.record?.layout || []
})

const settings = computed(() => {
  return metadataStore.metadata?.settings || { datetime_format: 'Y-m-d H:i:s' }
})

const subpanels = computed(() => {
  if (currentMode.value !== 'detail' || !record.value?.id) {
    return []
  }
  
  const entityMeta = metadataStore.getEntityMetadata(props.entityName)
  const subpanelsDef = entityMeta?.module_views?.subpanels || {}
  
  // Convert subpanels object to array with keys
  return Object.keys(subpanelsDef).map(key => ({
    ...subpanelsDef[key],
    key: key
  }))
})

onMounted(async () => {
  if (props.initialMode === 'create') {
    initializeCreateMode()
  } else if (props.recordId) {
    await loadRecord()
  }
})

watch(() => props.recordId, async (newId) => {
  if (newId && props.initialMode !== 'create') {
    internalRecordId.value = newId
    await loadRecord()
  }
})

watch(internalRecordId, async (newId) => {
  if (newId && currentMode.value !== 'create') {
    await loadRecord()
  }
})

watch(() => props.initialMode, (newMode) => {
  currentMode.value = newMode
  if (newMode === 'create') {
    initializeCreateMode()
  } else if (newMode === 'edit' && record.value) {
    initializeEditMode()
  } else if (newMode === 'detail' && record.value) {
    // Reset form data when switching back to detail
    formData.value = {}
  }
})

watch(currentMode, (newMode) => {
  emit('update:mode', newMode)
  if (newMode === 'create') {
    initializeCreateMode()
  } else if (newMode === 'edit' && record.value) {
    initializeEditMode()
  } else if (newMode === 'detail' && record.value) {
    // Reset form data when switching back to detail
    formData.value = {}
  }
})

async function loadRecord() {
  const recordIdToLoad = internalRecordId.value || props.recordId
  if (!recordIdToLoad) return
  
  loading.value = true
  error.value = null
  try {
    const response = await api.get(`/${props.entityName}/${recordIdToLoad}`)
    record.value = response.data.record
    relationship.value = response.data.relationship || {}
    
    // Emit record loaded event to update title if needed
    if (record.value) {
      const entityMeta = metadataStore.getEntityMetadata(props.entityName)
      const isPerson = entityMeta?.person === true
      let loadedRecordName = null
      
      if (isPerson && record.value.first_name && record.value.last_name) {
        loadedRecordName = `${record.value.first_name} ${record.value.last_name}`.trim()
      } else if (record.value.name) {
        loadedRecordName = record.value.name
      }
      
      if (loadedRecordName) {
        emit('recordLoaded', { recordName: loadedRecordName })
      }
    }
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load record'
    toastStore.error('Failed to load record')
  } finally {
    loading.value = false
  }
}

function initializeCreateMode() {
  record.value = {}
  relationship.value = {}
  formData.value = {}
  
  // Initialize form data with empty values for all fields
  Object.keys(fieldDefinitions.value).forEach(fieldName => {
    const fieldDef = fieldDefinitions.value[fieldName]
    if (fieldDef.type === 'boolean' || fieldDef.type === 'checkbox') {
      formData.value[fieldName] = false
    } else if (fieldDef.type === 'collection') {
      formData.value[fieldName] = []
    } else {
      formData.value[fieldName] = ''
    }
  })
}

function initializeEditMode() {
  if (!record.value) return
  
  // Copy only defined fields to form data to avoid sending extras (e.g. name on person entities)
  const nextFormData = {}
  Object.keys(fieldDefinitions.value).forEach(fieldName => {
    const fieldDef = fieldDefinitions.value[fieldName]
    const recordValue = record.value[fieldName]
    if (recordValue !== undefined && recordValue !== null) {
      nextFormData[fieldName] = recordValue
      return
    }

    if (fieldDef.type === 'boolean' || fieldDef.type === 'checkbox') {
      nextFormData[fieldName] = false
    } else if (fieldDef.type === 'collection') {
      nextFormData[fieldName] = []
    } else {
      nextFormData[fieldName] = ''
    }
  })

  formData.value = nextFormData
}

function switchToEdit() {
  initializeEditMode()
  currentMode.value = 'edit'
}

function updateFormData(fieldName, value) {
  formData.value[fieldName] = value
}

async function handleSave() {
  saving.value = true
  error.value = null
  
  try {
    // Prepare data for submission (exclude id, readonly fields)
    const submitData = {}
    Object.keys(fieldDefinitions.value).forEach(fieldName => {
      const fieldDef = fieldDefinitions.value[fieldName]
      if (fieldDef?.readonly) return
      if (fieldName === 'id') return
      
      submitData[fieldName] = formData.value[fieldName]
    })

    const recordIdToUse = internalRecordId.value || props.recordId
    let response
    if (currentMode.value === 'create') {
      response = await api.post(`/create/${props.entityName}`, submitData)
    } else {
      response = await api.put(`/${props.entityName}/${recordIdToUse}`, submitData)
    }

    // Update record with response data
    record.value = response.data.record
    relationship.value = response.data.relationship || {}
    
    // Store mode before switching
    const wasCreate = currentMode.value === 'create'
    
    // Switch to detail mode after save
    if (wasCreate && record.value?.id) {
      // Update recordId for new record
      internalRecordId.value = record.value.id
      emit('update:recordId', record.value.id)
    }
    currentMode.value = 'detail'
    formData.value = {}
    
    toastStore.success(wasCreate ? 'Record created successfully' : 'Record updated successfully')
    emit('saved', response.data)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to save record'
    toastStore.error(error.value)
  } finally {
    saving.value = false
  }
}

function handleCancel() {
  if (currentMode.value === 'create') {
    emit('cancel')
  } else {
    // Switch back to detail view
    formData.value = {}
    currentMode.value = 'detail'
  }
}

function getColumnClass(columnLength) {
  const cols = 12 / columnLength
  return `col-md-${cols}`
}

function formatFieldName(fieldName) {
  return fieldName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

async function handleDelete() {
  const recordIdToDelete = internalRecordId.value || props.recordId
  if (!recordIdToDelete) return

  deleting.value = true
  error.value = null

  try {
    await api.delete(`/delete/${props.entityName}/${recordIdToDelete}`)
    
    toastStore.success('Record deleted successfully')
    emit('deleted', { recordId: recordIdToDelete })
    showDeleteConfirm.value = false
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to delete record'
    toastStore.error(error.value)
    showDeleteConfirm.value = false
  } finally {
    deleting.value = false
  }
}

function handleSubpanelRecordClick({ entity, recordId }) {
  emit('recordClick', { entity, recordId })
}

function handleRelationshipClick({ entity, recordId }) {
  emit('recordClick', { entity, recordId })
}
</script>

<style scoped>
.record-detail-view {
  min-height: 200px;
}

.action-buttons-bar {
  background-color: #f5f5f5;
  margin: -1rem -1rem 1rem -1rem;
  padding: 0.75rem 1rem;
}

.delete-confirm-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
}

.delete-confirm-popup {
  background-color: #000;
  padding: 1.5rem;
  border-radius: 0.5rem;
  min-width: 400px;
  max-width: 90%;
}

.delete-confirm-popup h5 {
  margin: 0;
  color: #fff;
}

.delete-confirm-popup p {
  margin: 0;
  color: #ccc;
}
</style>
