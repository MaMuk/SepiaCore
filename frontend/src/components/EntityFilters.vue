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
        <div v-if="fieldOptions.length" class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small text-muted">Field</label>
            <select v-model="builderField" class="form-select form-select-sm">
              <option value="">Select field</option>
              <option v-for="field in fieldOptions" :key="field.value" :value="field.value">
                {{ field.label }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small text-muted">Operator</label>
            <select v-model="builderOperator" class="form-select form-select-sm">
              <option v-for="op in operatorOptions" :key="op.value" :value="op.value">
                {{ op.label }}
              </option>
            </select>
          </div>
          <div v-if="showValueInput" class="col-12 col-md-5">
            <label class="form-label small text-muted">Value</label>
            <select
              v-if="isBooleanField"
              v-model="builderValue"
              class="form-select form-select-sm"
            >
              <option value="">Select value</option>
              <option :value="true">True</option>
              <option :value="false">False</option>
            </select>
            <select
              v-else-if="isSelectField && builderOperator === 'in'"
              v-model="builderValue"
              class="form-select form-select-sm"
              multiple
            >
              <option v-for="option in selectedFieldOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <select
              v-else-if="isSelectField"
              v-model="builderValue"
              class="form-select form-select-sm"
            >
              <option value="">Select value</option>
              <option v-for="option in selectedFieldOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <input
              v-else-if="isDateField"
              v-model="builderValue"
              type="date"
              class="form-control form-control-sm"
            />
            <input
              v-else-if="isDatetimeField"
              v-model="builderValue"
              type="datetime-local"
              class="form-control form-control-sm"
            />
            <RelationshipSearchSelect
              v-else-if="isRelationshipField && hasRelationshipEntity"
              v-model="builderValue"
              :related-entity="selectedField?.entity"
              :reset-key="relationshipResetKey"
              placeholder="Search related records"
              @select="handleRelationshipSelect"
              @cleared="handleRelationshipClear"
            />
            <input
              v-else
              v-model="builderValue"
              type="text"
              class="form-control form-control-sm"
              placeholder="Enter value"
            />
          </div>
        </div>
        <div v-else class="text-muted small">No fields available for this entity.</div>

        <div class="d-flex align-items-center mt-2">
          <small v-if="!builderReady" class="text-muted">Set a field, operator, and value to apply.</small>
          <button
            class="btn btn-outline-primary btn-sm ms-auto"
            type="button"
            :disabled="!builderReady || savingFilter"
            @click="openSaveMode"
          >
            Save
          </button>
          <button class="btn btn-link btn-sm" type="button" @click="clearFilter">
            Clear
          </button>
        </div>

        <div v-if="saveMode" class="mt-2 d-flex align-items-center gap-2">
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
import RelationshipSearchSelect from './RelationshipSearchSelect.vue'
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
  }
})

const emit = defineEmits(['filter-change'])

const isOpen = ref(false)
const mode = ref('builder')

const builderField = ref('')
const builderOperator = ref('contains')
const builderValue = ref('')
const relationshipLabel = ref('')
const relationshipResetKey = ref(0)

const storedQuery = ref('')
const selectedStoredId = ref(null)
const storedFilters = ref([])
const storedLoading = ref(false)
const storedError = ref('')
const savingFilter = ref(false)
const saveMode = ref(false)
const saveName = ref('')
const deletingIds = ref([])

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

const defaultOperatorOrder = [
  'eq',
  'contains',
  'starts_with',
  'ends_with',
  'not_empty',
  'empty',
  'gt',
  'gte',
  'lt',
  'lte'
]

const operatorSetsByType = {
  boolean: ['eq'],
  checkbox: ['eq'],
  select: ['eq', 'in', 'not_empty', 'empty'],
  date: ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty', 'empty'],
  datetime: ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty', 'empty'],
  relationship: ['eq', 'not_empty', 'empty']
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

const selectedField = computed(() => {
  return fieldOptions.value.find(field => field.value === builderField.value) || null
})

const selectedFieldType = computed(() => selectedField.value?.type || 'text')

const selectedFieldOptions = computed(() => {
  const options = selectedField.value?.options
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
})

const operatorOptions = computed(() => {
  const fieldType = selectedFieldType.value
  const allowed = operatorSetsByType[fieldType] || defaultOperatorOrder
  return allowed.map(value => ({
    value,
    label: operatorLabelMap[value] || value
  }))
})

const isBooleanField = computed(() => ['boolean', 'checkbox'].includes(selectedFieldType.value))
const isSelectField = computed(() => selectedFieldType.value === 'select')
const isDateField = computed(() => selectedFieldType.value === 'date')
const isDatetimeField = computed(() => selectedFieldType.value === 'datetime')
const isRelationshipField = computed(() => selectedFieldType.value === 'relationship')
const hasRelationshipEntity = computed(() => !!selectedField.value?.entity)

const showValueInput = computed(() => !['not_empty', 'empty'].includes(builderOperator.value))

const builderReady = computed(() => {
  if (!builderField.value || !builderOperator.value) return false
  if (['not_empty', 'empty'].includes(builderOperator.value)) return true
  if (isSelectField.value && builderOperator.value === 'in') {
    return Array.isArray(builderValue.value) && builderValue.value.length > 0
  }
  if (isBooleanField.value) {
    return builderValue.value === true || builderValue.value === false
  }
  if (builderValue.value === null || builderValue.value === undefined) return false
  if (typeof builderValue.value === 'string') {
    return builderValue.value.trim() !== ''
  }
  return true
})

const builderFieldLabel = computed(() => {
  const match = fieldOptions.value.find(field => field.value === builderField.value)
  return match?.label || builderField.value
})

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
    if (mode.value !== 'builder') return
    if (!builderReady.value) {
      if (activeMode.value === 'builder') {
        activeMode.value = null
        activeLabel.value = ''
        emit('filter-change', null)
      }
      return
    }

    const filterValue = normalizeFilterValue()
    const payload = {
      filters: [
        {
          field: builderField.value,
          operator: builderOperator.value,
          value: filterValue
        }
      ]
    }

    const labelValueText = builderOperator.value === 'not_empty'
      ? ''
      : formatValueLabel(filterValue)

    const labelValue = builderOperator.value === 'not_empty'
      ? `${builderFieldLabel.value} is not empty`
      : builderOperator.value === 'empty'
        ? `${builderFieldLabel.value} is empty`
      : `${builderFieldLabel.value} ${operatorLabelMap[builderOperator.value] || builderOperator.value} ${labelValueText}`

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
  builderValue.value = ''
  relationshipLabel.value = ''
  selectedStoredId.value = null
  activeMode.value = null
  activeLabel.value = ''
  saveMode.value = false
  saveName.value = ''
  emit('filter-change', null)
}

function resetState() {
  builderField.value = ''
  builderOperator.value = 'contains'
  builderValue.value = ''
  relationshipLabel.value = ''
  storedQuery.value = ''
  selectedStoredId.value = null
  activeMode.value = null
  activeLabel.value = ''
  saveMode.value = false
  saveName.value = ''
}

watch(fieldOptions, (next) => {
  if (!builderField.value && next.length) {
    builderField.value = next[0].value
  }
}, { immediate: true })

watch(selectedFieldType, () => {
  const nextOperators = operatorOptions.value
  if (!nextOperators.find(op => op.value === builderOperator.value)) {
    builderOperator.value = nextOperators[0]?.value || 'eq'
  }
  builderValue.value = ''
  relationshipLabel.value = ''
  relationshipResetKey.value += 1
})

watch(builderOperator, (next, prev) => {
  if (isSelectField.value && next === 'in') {
    builderValue.value = Array.isArray(builderValue.value)
      ? builderValue.value
      : (builderValue.value ? [builderValue.value] : [])
  } else if (isSelectField.value && prev === 'in' && next !== 'in') {
    builderValue.value = Array.isArray(builderValue.value)
      ? (builderValue.value[0] ?? '')
      : builderValue.value
  }
  if (next === 'not_empty') {
    relationshipLabel.value = ''
  }
  if (next === 'empty') {
    relationshipLabel.value = ''
  }
})

watch([builderField, builderOperator, builderValue], () => {
  if (mode.value === 'builder') {
    scheduleBuilderEmit()
  }
})

watch(mode, (next) => {
  if (next === 'builder') {
    selectedStoredId.value = null
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
  emit('filter-change', null)
  loadStoredFilters()
})

onBeforeUnmount(() => {
  clearTimeout(emitTimeout)
})

onMounted(() => {
  loadStoredFilters()
})

function normalizeFilterValue() {
  if (['not_empty', 'empty'].includes(builderOperator.value)) return null

  if (isBooleanField.value) {
    if (builderValue.value === true || builderValue.value === false) {
      return builderValue.value
    }
    if (builderValue.value === 'true') return true
    if (builderValue.value === 'false') return false
    return null
  }

  if (isSelectField.value && builderOperator.value === 'in') {
    return Array.isArray(builderValue.value)
      ? builderValue.value.filter(value => value !== '')
      : []
  }

  if (builderValue.value === null || builderValue.value === undefined) return null
  if (typeof builderValue.value === 'string') {
    const trimmed = builderValue.value.trim()
    return trimmed === '' ? null : trimmed
  }

  return builderValue.value
}

function formatValueLabel(filterValue) {
  if (isBooleanField.value) {
    return filterValue ? 'True' : 'False'
  }

  if (isSelectField.value) {
    const options = selectedFieldOptions.value
    if (builderOperator.value === 'in') {
      const labels = Array.isArray(filterValue)
        ? filterValue.map(value => options.find(option => option.value === value)?.label ?? value)
        : []
      return labels.join(', ')
    }
    return options.find(option => option.value === filterValue)?.label ?? filterValue
  }

  if (isRelationshipField.value) {
    return relationshipLabel.value || filterValue
  }

  return filterValue
}

function handleRelationshipSelect(record) {
  relationshipLabel.value = record?.name || String(record?.id || '')
}

function handleRelationshipClear() {
  relationshipLabel.value = ''
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

  const filterValue = normalizeFilterValue()
  const definition = {
    filters: [
      {
        field: builderField.value,
        operator: builderOperator.value,
        value: filterValue
      }
    ]
  }

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
  if (!builderReady.value) return
  saveMode.value = true
  saveName.value = ''
}

function cancelSaveMode() {
  saveMode.value = false
  saveName.value = ''
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
