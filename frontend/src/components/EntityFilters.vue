<template>
  <div class="entity-filters">
    <button class="btn btn-outline-secondary btn-sm filter-toggle" type="button" @click="toggleOpen">
      <i class="bi bi-funnel me-1"></i>
      Filters
      <span v-if="hasActiveFilter" class="badge bg-primary ms-2">Active</span>
    </button>

    <div v-if="isOpen" class="filter-panel card card-body mt-2">
      <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Filter mode">
        <button
          type="button"
          class="btn"
          :class="mode === 'builder' ? 'btn-primary' : 'btn-outline-primary'"
          @click="mode = 'builder'"
        >
          Build Filter
        </button>
        <button
          type="button"
          class="btn"
          :class="mode === 'stored' ? 'btn-primary' : 'btn-outline-primary'"
          @click="mode = 'stored'"
        >
          Saved Filters
        </button>
      </div>

      <div v-if="mode === 'builder'" class="filter-builder">
        <div v-if="fieldOptions.length" class="d-flex flex-wrap align-items-center gap-2">
          <div class="btn-group btn-group-sm" role="group" aria-label="Filter group">
            <button
              type="button"
              class="btn"
              :class="builderGroup.group === 'AND' ? 'btn-primary' : 'btn-outline-primary'"
              @click="setGroupOperator('AND')"
            >
              AND
            </button>
            <button
              type="button"
              class="btn"
              :class="builderGroup.group === 'OR' ? 'btn-primary' : 'btn-outline-primary'"
              @click="setGroupOperator('OR')"
            >
              OR
            </button>
          </div>
          <button
            class="btn btn-outline-primary btn-sm"
            type="button"
            @click="openAddCondition"
          >
            <i class="bi bi-plus-circle me-1"></i>
            Add condition
          </button>
        </div>
        <div v-else class="text-muted small">No fields available for this entity.</div>

        <div v-if="builderGroup.filters.length" class="chip-row mt-2">
          <div
            v-for="(condition, index) in builderGroup.filters"
            :key="`condition-${index}`"
            class="filter-chip"
          >
            <button class="chip-button" type="button" @click="openEditCondition(index)">
              {{ getConditionLabel(condition) }}
            </button>
            <button
              class="chip-remove"
              type="button"
              aria-label="Remove condition"
              @click.stop="removeCondition(index)"
            >
              <i class="bi bi-x"></i>
            </button>
          </div>
        </div>
        <div v-else class="text-muted small mt-2">No conditions yet.</div>

        <div class="d-flex align-items-center mt-3">
          <small v-if="!builderReady" class="text-muted">Add at least one condition to apply.</small>
          <button
            v-if="!editMode"
            class="btn btn-outline-primary btn-sm ms-auto"
            type="button"
            :disabled="!builderReady || savingFilter"
            @click="openSaveMode"
          >
            Save
          </button>
          <button
            v-else
            class="btn btn-primary btn-sm ms-auto"
            type="button"
            :disabled="!builderReady || updatingFilter"
            @click="updateStoredFilter"
          >
            Update
          </button>
          <button class="btn btn-link btn-sm" type="button" @click="clearFilter">
            Clear
          </button>
        </div>

        <div v-if="saveMode && !editMode" class="mt-2 d-flex align-items-center gap-2">
          <input
            v-model="saveName"
            type="text"
            class="form-control form-control-sm"
            placeholder="Filter name"
            @keyup.enter="saveCurrentFilter"
          />
          <button
            class="btn btn-primary btn-sm"
            type="button"
            :disabled="!saveName.trim() || savingFilter"
            @click="saveCurrentFilter"
          >
            Save
          </button>
          <button class="btn btn-link btn-sm" type="button" @click="cancelSaveMode">
            Cancel
          </button>
        </div>

        <div v-if="editMode" class="mt-2 d-flex align-items-center gap-2">
          <input
            v-model="editName"
            type="text"
            class="form-control form-control-sm"
            placeholder="Filter name"
            @keyup.enter="updateStoredFilter"
          />
          <button
            class="btn btn-primary btn-sm"
            type="button"
            :disabled="!editName.trim() || !builderReady || updatingFilter"
            @click="updateStoredFilter"
          >
            Update
          </button>
          <button class="btn btn-link btn-sm" type="button" @click="cancelEditMode">
            Cancel
          </button>
        </div>

        <FilterConditionModal
          v-model="conditionModalOpen"
          :fields="fields"
          :condition="modalCondition"
          :mode="conditionModalMode"
          @save="handleConditionSave"
          @cancel="handleConditionCancel"
        />
      </div>

      <div v-else class="filter-stored">
        <div class="row g-2">
          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Search saved filters</label>
            <input
              v-model="storedQuery"
              type="text"
              class="form-control form-control-sm"
              placeholder="Filter name"
            />
          </div>
        </div>
        <div class="stored-list mt-2">
          <div
            v-for="filter in filteredStoredFilters"
            :key="filter.id"
            class="btn-group btn-group-sm me-2 mb-2"
          >
            <button
              type="button"
              class="btn btn-outline-secondary"
              :class="{ active: filter.id === selectedStoredId }"
              @click="selectStoredFilter(filter)"
            >
              {{ filter.name }}
            </button>
            <button
              type="button"
              class="btn btn-outline-primary"
              @click.stop="startEditFilter(filter)"
            >
              <i class="bi bi-pencil"></i>
            </button>
            <button
              type="button"
              class="btn btn-outline-danger"
              :disabled="deletingIds.includes(filter.id)"
              @click.stop="deleteStoredFilter(filter)"
            >
              <i class="bi bi-trash"></i>
            </button>
          </div>
          <div v-if="storedLoading" class="text-muted small">
            Loading saved filters...
          </div>
          <div v-else-if="storedError" class="text-danger small">
            {{ storedError }}
          </div>
          <div v-else-if="!filteredStoredFilters.length" class="text-muted small">
            No saved filters found.
          </div>
        </div>

        <div class="d-flex align-items-center mt-2">
          <button class="btn btn-link btn-sm ms-auto" type="button" @click="clearFilter">
            Clear
          </button>
        </div>
      </div>

      <div v-if="hasActiveFilter" class="active-filter mt-3">
        <small class="text-muted">Active filter: {{ activeLabel }}</small>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, onMounted } from 'vue'
import FilterConditionModal from './FilterConditionModal.vue'
import entityService from '../services/entityService'

const props = defineProps({
  entityName: {
    type: String,
    required: true
  },
  entityDisplayName: {
    type: String,
    default: ''
  },
  fields: {
    type: Array,
    default: () => []
  },
  requireStored: {
    type: Boolean,
    default: false
  },
  initialFilterId: {
    type: [String, Number],
    default: null
  },
  initialFilters: {
    type: [Object, Array],
    default: null
  }
})

const emit = defineEmits(['filter-change'])

const isOpen = ref(false)
const mode = ref(props.requireStored ? 'stored' : 'builder')

const builderGroup = ref({ group: 'AND', filters: [] })
const conditionModalOpen = ref(false)
const conditionModalMode = ref('create')
const modalCondition = ref(null)
const editingIndex = ref(null)

const storedQuery = ref('')
const selectedStoredId = ref(null)
const storedFilters = ref([])
const storedLoading = ref(false)
const storedError = ref('')
const savingFilter = ref(false)
const saveMode = ref(false)
const saveName = ref('')
const deletingIds = ref([])
const editMode = ref(false)
const editFilterId = ref(null)
const editName = ref('')
const updatingFilter = ref(false)

const activeLabel = ref('')
const activeMode = ref(null)

const operatorLabelMap = {
  eq: 'Equals',
  contains: 'Contains',
  starts_with: 'Starts with',
  ends_with: 'Ends with',
  not_empty: 'Is not empty',
  empty: 'Is empty',
  gt: 'Greater than',
  gte: 'Greater or equal',
  lt: 'Less than',
  lte: 'Less or equal',
  in: 'In'
}

const fieldOptions = computed(() => {
  return (props.fields || [])
    .map(field => ({
      value: field.name ?? field.value ?? '',
      label: field.label ?? field.name ?? field.value ?? '',
      type: field.type ?? 'text',
      options: field.options ?? null,
      entity: field.entity ?? null
    }))
    .filter(field => field.value)
})

const fieldOptionMap = computed(() => {
  const map = {}
  fieldOptions.value.forEach(field => {
    map[field.value] = field
  })
  return map
})

const builderReady = computed(() => getValidConditions().length > 0)

const filteredStoredFilters = computed(() => {
  const needle = storedQuery.value.trim().toLowerCase()
  if (!needle) return storedFilters.value
  return storedFilters.value.filter(filter => filter.name.toLowerCase().includes(needle))
})

const hasActiveFilter = computed(() => !!activeMode.value)

let emitTimeout = null

function toggleOpen() {
  isOpen.value = !isOpen.value
}

function scheduleBuilderEmit() {
  clearTimeout(emitTimeout)
  emitTimeout = setTimeout(() => {
    if (props.requireStored) return
    if (mode.value !== 'builder') return
    const conditions = getValidConditions()
    if (!conditions.length) {
      if (activeMode.value === 'builder') {
        activeMode.value = null
        activeLabel.value = ''
        emit('filter-change', null)
      }
      return
    }

    const payload = buildPayloadFromConditions(conditions)
    const labelValue = buildGroupLabel(conditions)

    activeMode.value = 'builder'
    activeLabel.value = labelValue
    emit('filter-change', { mode: 'adhoc', payload, label: labelValue })
  }, 250)
}

function selectStoredFilter(filter) {
  selectedStoredId.value = filter.id
  activeMode.value = 'stored'
  activeLabel.value = filter.name
  emit('filter-change', {
    mode: 'stored',
    payload: { filter_id: filter.id },
    label: filter.name
  })
}

function clearFilter() {
  builderGroup.value = { group: 'AND', filters: [] }
  conditionModalOpen.value = false
  modalCondition.value = null
  editingIndex.value = null
  selectedStoredId.value = null
  activeMode.value = null
  activeLabel.value = ''
  saveMode.value = false
  saveName.value = ''
  editMode.value = false
  editFilterId.value = null
  editName.value = ''
  emit('filter-change', null)
}

function resetState() {
  builderGroup.value = { group: 'AND', filters: [] }
  conditionModalOpen.value = false
  modalCondition.value = null
  editingIndex.value = null
  storedQuery.value = ''
  selectedStoredId.value = null
  activeMode.value = null
  activeLabel.value = ''
  saveMode.value = false
  saveName.value = ''
  editMode.value = false
  editFilterId.value = null
  editName.value = ''
}

watch(builderGroup, () => {
  if (mode.value === 'builder') {
    scheduleBuilderEmit()
  }
}, { deep: true })

watch(mode, (next) => {
  if (next === 'builder') {
    selectedStoredId.value = null
    if (props.requireStored) {
      if (activeMode.value) {
        activeMode.value = null
        activeLabel.value = ''
        emit('filter-change', null)
      }
      return
    }
    if (builderReady.value) {
      scheduleBuilderEmit()
    } else if (activeMode.value) {
      activeMode.value = null
      activeLabel.value = ''
      emit('filter-change', null)
    }
  } else if (next === 'stored') {
    saveMode.value = false
    saveName.value = ''
    if (selectedStoredId.value) {
      const selected = storedFilters.value.find(filter => filter.id === selectedStoredId.value)
      if (selected) selectStoredFilter(selected)
    } else if (activeMode.value) {
      activeMode.value = null
      activeLabel.value = ''
      emit('filter-change', null)
    }
  }
})

watch(() => props.entityName, () => {
  resetState()
  if (props.requireStored && props.initialFilterId) {
    selectedStoredId.value = props.initialFilterId
  } else {
    emit('filter-change', null)
  }
  loadStoredFilters()
})

watch(() => props.initialFilterId, (next) => {
  if (!next) {
    if (selectedStoredId.value) {
      selectedStoredId.value = null
    }
    if (props.requireStored) {
      activeMode.value = null
      activeLabel.value = ''
      emit('filter-change', null)
    }
    return
  }
  applyInitialFilterSelection(next)
}, { immediate: true })

watch(() => props.initialFilters, (next) => {
  if (!next || props.requireStored) return
  builderGroup.value = normalizeFilterDefinition(next)
  mode.value = 'builder'
  scheduleBuilderEmit()
}, { immediate: true })

onBeforeUnmount(() => {
  clearTimeout(emitTimeout)
})

onMounted(() => {
  loadStoredFilters()
})

function setGroupOperator(nextGroup) {
  const normalized = String(nextGroup || '').toUpperCase() === 'OR' ? 'OR' : 'AND'
  builderGroup.value = {
    ...builderGroup.value,
    group: normalized
  }
}

function openAddCondition() {
  conditionModalMode.value = 'create'
  editingIndex.value = null
  modalCondition.value = null
  conditionModalOpen.value = true
}

function openEditCondition(index) {
  const current = builderGroup.value.filters[index]
  if (!current) return
  conditionModalMode.value = 'edit'
  editingIndex.value = index
  modalCondition.value = { ...current }
  conditionModalOpen.value = true
}

function handleConditionSave(condition) {
  if (!condition) return
  const nextCondition = { ...condition }
  if (editingIndex.value !== null && editingIndex.value !== undefined) {
    builderGroup.value.filters.splice(editingIndex.value, 1, nextCondition)
  } else {
    builderGroup.value.filters.push(nextCondition)
  }
  modalCondition.value = null
  editingIndex.value = null
}

function handleConditionCancel() {
  modalCondition.value = null
  editingIndex.value = null
}

function removeCondition(index) {
  builderGroup.value.filters.splice(index, 1)
}

function getValidConditions() {
  return (builderGroup.value.filters || []).filter(condition => isConditionValid(condition))
}

function isConditionValid(condition) {
  if (!condition?.field || !condition?.operator) return false
  const operator = condition.operator
  if (operator === 'not_empty' || operator === 'empty') return true
  const fieldType = fieldOptionMap.value[condition.field]?.type || 'text'
  if (fieldType === 'boolean' || fieldType === 'checkbox') {
    if (condition.value === true || condition.value === false) return true
    if (['true', 'false', '1', '0', 1, 0].includes(condition.value)) return true
    return false
  }
  if (Array.isArray(condition.value)) {
    return condition.value.length > 0
  }
  if (condition.value === null || condition.value === undefined) return false
  if (typeof condition.value === 'string') {
    return condition.value.trim() !== ''
  }
  return true
}

function buildPayloadFromConditions(conditions) {
  const group = builderGroup.value.group === 'OR' ? 'OR' : 'AND'
  return {
    group,
    filters: conditions.map(condition => ({
      field: condition.field,
      operator: condition.operator,
      value: condition.value ?? null
    }))
  }
}

function buildGroupLabel(conditions) {
  const labels = conditions.map(condition => getConditionLabel(condition)).filter(Boolean)
  if (!labels.length) return ''
  const joiner = builderGroup.value.group === 'OR' ? ' OR ' : ' AND '
  return labels.join(joiner)
}

function getConditionLabel(condition) {
  const fieldLabel = getFieldLabel(condition?.field)
  const operator = condition?.operator || 'eq'
  if (operator === 'not_empty') return `${fieldLabel} is not empty`
  if (operator === 'empty') return `${fieldLabel} is empty`
  const valueLabel = formatConditionValueLabel(condition)
  const operatorLabel = operatorLabelMap[operator] || operator
  return `${fieldLabel} ${operatorLabel} ${valueLabel}`
}

function getFieldLabel(fieldName) {
  const field = fieldOptionMap.value[fieldName]
  return field?.label || fieldName || 'Field'
}

function formatConditionValueLabel(condition) {
  const field = fieldOptionMap.value[condition?.field]
  const fieldType = field?.type || 'text'
  const operator = condition?.operator || 'eq'
  const value = condition?.value

  if (fieldType === 'boolean' || fieldType === 'checkbox') {
    return normalizeBooleanLabel(value)
  }

  if (fieldType === 'select') {
    const options = normalizeSelectOptions(field?.options)
    if (operator === 'in') {
      const list = Array.isArray(value) ? value : (value ? [value] : [])
      const labels = list.map(item => options.find(option => option.value === item)?.label ?? item)
      return labels.join(', ')
    }
    return options.find(option => option.value === value)?.label ?? value
  }

  if (fieldType === 'relationship') {
    return condition?.valueLabel || value
  }

  if (Array.isArray(value)) {
    return value.join(', ')
  }

  return value ?? ''
}

function normalizeSelectOptions(options) {
  if (!options) return []
  if (Array.isArray(options)) {
    return options.map(option => ({
      value: option,
      label: String(option)
    }))
  }
  return Object.entries(options).map(([value, label]) => ({
    value,
    label: String(label)
  }))
}

function normalizeBooleanLabel(value) {
  if (value === true || value === 'true' || value === 1 || value === '1') return 'True'
  if (value === false || value === 'false' || value === 0 || value === '0') return 'False'
  return String(value ?? '')
}

function normalizeFilterDefinition(definition) {
  if (!definition || typeof definition !== 'object') {
    return { group: 'AND', filters: [] }
  }

  if (definition.group && Array.isArray(definition.filters)) {
    return {
      group: String(definition.group).toUpperCase() === 'OR' ? 'OR' : 'AND',
      filters: normalizeConditionList(definition.filters)
    }
  }

  if (Array.isArray(definition.filters)) {
    return {
      group: 'AND',
      filters: normalizeConditionList(definition.filters)
    }
  }

  if (Array.isArray(definition)) {
    return {
      group: 'AND',
      filters: normalizeConditionList(definition)
    }
  }

  const entries = Object.entries(definition)
  if (entries.length) {
    const filters = entries.map(([field, value]) => ({
      field,
      operator: 'eq',
      value
    }))
    return { group: 'AND', filters }
  }

  return { group: 'AND', filters: [] }
}

function normalizeConditionList(filters) {
  return (filters || [])
    .filter(filter => filter && typeof filter === 'object' && filter.field)
    .map(filter => ({
      field: filter.field,
      operator: filter.operator || 'eq',
      value: filter.value ?? null,
      valueLabel: filter.valueLabel || filter.value_label || undefined
    }))
}

async function loadStoredFilters() {
  if (!props.entityName) {
    storedFilters.value = []
    return
  }
  storedLoading.value = true
  storedError.value = ''
  try {
    const response = await entityService.listFilters(props.entityName)
    const records = response?.records ?? response ?? []
    storedFilters.value = Array.isArray(records) ? records : []
    if (selectedStoredId.value) {
      const exists = storedFilters.value.find(filter => filter.id === selectedStoredId.value)
      if (!exists) {
        selectedStoredId.value = null
        if (activeMode.value === 'stored') {
          activeMode.value = null
          activeLabel.value = ''
          emit('filter-change', null)
        }
      }
    }
    if (props.initialFilterId) {
      applyInitialFilterSelection(props.initialFilterId)
    }
  } catch (error) {
    storedFilters.value = []
    storedError.value = 'Failed to load saved filters.'
  } finally {
    storedLoading.value = false
  }
}

async function saveCurrentFilter() {
  if (!builderReady.value || !props.entityName) return
  const name = saveName.value.trim()
  if (!name) return

  savingFilter.value = true
  storedError.value = ''

  const conditions = getValidConditions()
  const definition = buildPayloadFromConditions(conditions)

  try {
    const record = await entityService.createFilter({
      name,
      entity: props.entityName,
      definition
    })
    await loadStoredFilters()
    saveMode.value = false
    saveName.value = ''
    if (record?.id) {
      mode.value = 'stored'
      selectStoredFilter(record)
    }
  } catch (error) {
    storedError.value = 'Failed to save filter.'
  } finally {
    savingFilter.value = false
  }
}

async function deleteStoredFilter(filter) {
  if (!filter?.id) return
  if (deletingIds.value.includes(filter.id)) return
  deletingIds.value = [...deletingIds.value, filter.id]
  storedError.value = ''
  try {
    await entityService.deleteFilter(filter.id)
    await loadStoredFilters()
    if (selectedStoredId.value === filter.id) {
      selectedStoredId.value = null
      if (activeMode.value === 'stored') {
        activeMode.value = null
        activeLabel.value = ''
        emit('filter-change', null)
      }
    }
  } catch (error) {
    storedError.value = 'Failed to delete filter.'
  } finally {
    deletingIds.value = deletingIds.value.filter(id => id !== filter.id)
  }
}

function openSaveMode() {
  if (!builderReady.value || editMode.value) return
  saveMode.value = true
  saveName.value = ''
}

function cancelSaveMode() {
  saveMode.value = false
  saveName.value = ''
}

function applyInitialFilterSelection(filterId) {
  if (!filterId) return
  selectedStoredId.value = filterId
  const stored = storedFilters.value.find(filter => String(filter.id) === String(filterId))
  if (stored) {
    mode.value = 'stored'
    selectStoredFilter(stored)
  }
}

function startEditFilter(filter) {
  if (!filter?.id) return
  mode.value = 'builder'
  saveMode.value = false
  saveName.value = ''
  editMode.value = true
  editFilterId.value = filter.id
  editName.value = filter.name || ''

  const definition = filter.definition || filter?.definition_json || null
  builderGroup.value = normalizeFilterDefinition(definition)
}

async function updateStoredFilter() {
  if (!builderReady.value || !editFilterId.value || !props.entityName) return
  const name = editName.value.trim()
  if (!name) return
  updatingFilter.value = true
  storedError.value = ''

  const conditions = getValidConditions()
  const definition = buildPayloadFromConditions(conditions)

  try {
    await entityService.updateFilter(editFilterId.value, {
      name,
      entity: props.entityName,
      definition
    })
    await loadStoredFilters()
    editMode.value = false
    editFilterId.value = null
    editName.value = ''
    mode.value = 'stored'
    if (selectedStoredId.value) {
      const selected = storedFilters.value.find(filter => filter.id === selectedStoredId.value)
      if (selected) {
        selectStoredFilter(selected)
      }
    }
  } catch (error) {
    storedError.value = 'Failed to update filter.'
  } finally {
    updatingFilter.value = false
  }
}

function cancelEditMode() {
  editMode.value = false
  editFilterId.value = null
  editName.value = ''
  mode.value = 'stored'
}
</script>

<style scoped>
.entity-filters {
  display: flex;
  flex-direction: column;
}

.filter-panel {
  border: 1px solid #e2e8f0;
  background-color: #f8fafc;
}

.filter-toggle {
  align-self: flex-start;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.filter-chip {
  display: inline-flex;
  align-items: center;
  background: #ffffff;
  border: 1px solid #cbd5f5;
  border-radius: 999px;
  padding: 0.1rem 0.35rem;
}

.chip-button {
  background: transparent;
  border: none;
  padding: 0.25rem 0.5rem;
  color: #0f172a;
  font-size: 0.85rem;
}

.chip-button:hover {
  color: #0b5ed7;
}

.chip-remove {
  background: transparent;
  border: none;
  padding: 0 0.35rem 0 0.1rem;
  color: #6b7280;
}

.chip-remove:hover {
  color: #dc3545;
}

.stored-list .btn.active {
  background-color: #0d6efd;
  border-color: #0d6efd;
  color: #fff;
}

.active-filter {
  padding-top: 0.5rem;
  border-top: 1px dashed #d0d7de;
}
</style>
