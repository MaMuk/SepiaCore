<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="close"
  >
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-funnel me-2"></i>
            {{ modalTitle }}
          </h5>
          <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
        </div>
        <div class="modal-body">
          <div v-if="fieldOptions.length" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
              <label class="form-label small text-muted">Field</label>
              <select v-model="localField" class="form-select form-select-sm">
                <option value="">Select field</option>
                <option v-for="field in fieldOptions" :key="field.value" :value="field.value">
                  {{ field.label }}
                </option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small text-muted">Operator</label>
              <select v-model="localOperator" class="form-select form-select-sm">
                <option v-for="op in operatorOptions" :key="op.value" :value="op.value">
                  {{ op.label }}
                </option>
              </select>
            </div>
            <div v-if="showValueInput" class="col-12 col-md-5">
              <label class="form-label small text-muted">Value</label>
              <select
                v-if="isBooleanField"
                v-model="localValue"
                class="form-select form-select-sm"
              >
                <option value="">Select value</option>
                <option :value="true">True</option>
                <option :value="false">False</option>
              </select>
              <select
                v-else-if="isSelectField && localOperator === 'in'"
                v-model="localValue"
                class="form-select form-select-sm"
                multiple
              >
                <option v-for="option in selectedFieldOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select
                v-else-if="isSelectField"
                v-model="localValue"
                class="form-select form-select-sm"
              >
                <option value="">Select value</option>
                <option v-for="option in selectedFieldOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <input
                v-else-if="isDateField"
                v-model="localValue"
                type="date"
                class="form-control form-control-sm"
              />
              <input
                v-else-if="isDatetimeField"
                v-model="localValue"
                type="datetime-local"
                class="form-control form-control-sm"
              />
              <RelationshipSearchSelect
                v-else-if="isRelationshipField && hasRelationshipEntity"
                v-model="localValue"
                :related-entity="selectedField?.entity"
                :reset-key="relationshipResetKey"
                placeholder="Search related records"
                @select="handleRelationshipSelect"
                @cleared="handleRelationshipClear"
              />
              <input
                v-else
                v-model="localValue"
                type="text"
                class="form-control form-control-sm"
                placeholder="Enter value"
              />
            </div>
          </div>
          <div v-else class="text-muted small">No fields available for this entity.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!conditionReady"
            @click="saveCondition"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="close"></div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import RelationshipSearchSelect from './RelationshipSearchSelect.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  fields: {
    type: Array,
    default: () => []
  },
  condition: {
    type: Object,
    default: null
  },
  mode: {
    type: String,
    default: 'create'
  }
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel'])

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

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const localField = ref('')
const localOperator = ref('contains')
const localValue = ref('')
const relationshipLabel = ref('')
const relationshipResetKey = ref(0)
const isInitializing = ref(false)

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
  return fieldOptions.value.find(field => field.value === localField.value) || null
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

const showValueInput = computed(() => !['not_empty', 'empty'].includes(localOperator.value))

const conditionReady = computed(() => {
  if (!localField.value || !localOperator.value) return false
  if (['not_empty', 'empty'].includes(localOperator.value)) return true
  if (isSelectField.value && localOperator.value === 'in') {
    return Array.isArray(localValue.value) && localValue.value.length > 0
  }
  if (isBooleanField.value) {
    return localValue.value === true || localValue.value === false
  }
  if (localValue.value === null || localValue.value === undefined) return false
  if (typeof localValue.value === 'string') {
    return localValue.value.trim() !== ''
  }
  return true
})

const modalTitle = computed(() => {
  if (props.mode === 'edit' || props.condition) {
    return 'Edit condition'
  }
  return 'Add condition'
})

watch(fieldOptions, (next) => {
  if (!localField.value && next.length) {
    localField.value = next[0].value
  }
}, { immediate: true })

watch(selectedFieldType, () => {
  if (isInitializing.value) return
  const nextOperators = operatorOptions.value
  if (!nextOperators.find(op => op.value === localOperator.value)) {
    localOperator.value = nextOperators[0]?.value || 'eq'
  }
  localValue.value = ''
  relationshipLabel.value = ''
  relationshipResetKey.value += 1
})

watch(localOperator, (next, prev) => {
  if (isInitializing.value) return
  if (isSelectField.value && next === 'in') {
    localValue.value = Array.isArray(localValue.value)
      ? localValue.value
      : (localValue.value ? [localValue.value] : [])
  } else if (isSelectField.value && prev === 'in' && next !== 'in') {
    localValue.value = Array.isArray(localValue.value)
      ? (localValue.value[0] ?? '')
      : localValue.value
  }
  if (next === 'not_empty' || next === 'empty') {
    relationshipLabel.value = ''
  }
})

watch(isVisible, (next) => {
  if (!next) return
  resetFromCondition()
})

function resetFromCondition() {
  isInitializing.value = true
  const condition = props.condition || {}
  localField.value = condition.field || fieldOptions.value[0]?.value || ''
  localOperator.value = condition.operator || 'contains'
  const allowedOperators = operatorOptions.value
  if (!allowedOperators.find(op => op.value === localOperator.value)) {
    localOperator.value = allowedOperators[0]?.value || 'eq'
  }
  let nextValue = condition.value ?? ''
  if (isBooleanField.value) {
    if (nextValue === 'true' || nextValue === '1' || nextValue === 1) {
      nextValue = true
    } else if (nextValue === 'false' || nextValue === '0' || nextValue === 0) {
      nextValue = false
    }
  }
  if (isSelectField.value) {
    if (localOperator.value === 'in' && !Array.isArray(nextValue)) {
      if (typeof nextValue === 'string') {
        nextValue = nextValue.split(',').map(item => item.trim()).filter(Boolean)
      } else if (nextValue) {
        nextValue = [nextValue]
      } else {
        nextValue = []
      }
    } else if (localOperator.value !== 'in' && Array.isArray(nextValue)) {
      nextValue = nextValue[0] ?? ''
    }
  }
  localValue.value = nextValue
  relationshipLabel.value = condition.valueLabel ?? ''
  relationshipResetKey.value += 1
  nextTick(() => {
    isInitializing.value = false
  })
}

function normalizeFilterValue() {
  if (['not_empty', 'empty'].includes(localOperator.value)) return null

  if (isBooleanField.value) {
    if (localValue.value === true || localValue.value === false) {
      return localValue.value
    }
    if (localValue.value === 'true') return true
    if (localValue.value === 'false') return false
    return null
  }

  if (isSelectField.value && localOperator.value === 'in') {
    return Array.isArray(localValue.value)
      ? localValue.value.filter(value => value !== '')
      : []
  }

  if (localValue.value === null || localValue.value === undefined) return null
  if (typeof localValue.value === 'string') {
    const trimmed = localValue.value.trim()
    return trimmed === '' ? null : trimmed
  }

  return localValue.value
}

function saveCondition() {
  if (!conditionReady.value) return
  const normalizedValue = normalizeFilterValue()
  const payload = {
    field: localField.value,
    operator: localOperator.value,
    value: normalizedValue
  }
  if (isRelationshipField.value && relationshipLabel.value) {
    payload.valueLabel = relationshipLabel.value
  }
  emit('save', payload)
  close()
}

function close() {
  isVisible.value = false
  emit('cancel')
}

function handleRelationshipSelect(record) {
  relationshipLabel.value = record?.name || String(record?.id || '')
}

function handleRelationshipClear() {
  relationshipLabel.value = ''
}
</script>
