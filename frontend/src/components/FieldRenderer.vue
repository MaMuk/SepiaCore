<template>
  <div class="field-renderer">
    <!-- Detail Mode (Read-only) -->
    <template v-if="mode === 'detail'">
      <template v-if="fieldDef?.type === 'select'">
        <span v-if="fieldDef?.options && value">
          {{ fieldDef.options[value] || value }}
        </span>
        <span v-else class="text-muted">-</span>
      </template>

      <template v-else-if="fieldDef?.type === 'relationship'">
        <span v-if="relationship?.name">
          <a 
            href="#"
            @click.prevent="handleRelationshipClick"
            class="text-decoration-none link-primary"
          >
            {{ relationship.name }}
          </a>
        </span>
        <span v-else-if="relationship?.id" class="text-muted">{{ relationship.id }}</span>
        <span v-else class="text-muted">-</span>
      </template>

      <template v-else-if="fieldDef?.type === 'datetime'">
        <span v-if="value">{{ formatDateTime(value) }}</span>
        <span v-else class="text-muted">-</span>
      </template>

      <template v-else-if="fieldDef?.type === 'date'">
        <span v-if="value">{{ formatDate(value) }}</span>
        <span v-else class="text-muted">-</span>
      </template>

      <template v-else-if="fieldDef?.type === 'collection'">
        <div class="collection-display">
          <template v-if="Array.isArray(value) && value.length > 0">
            <span 
              v-for="(item, index) in value" 
              :key="index"
              class="badge bg-secondary me-1 mb-1"
            >
              {{ item }}
            </span>
          </template>
          <span v-else class="text-muted">No items</span>
        </div>
      </template>

      <template v-else-if="fieldDef?.type === 'boolean' || fieldDef?.type === 'checkbox'">
        <span :class="value ? 'text-success' : 'text-muted'">
          {{ value ? 'Yes' : 'No' }}
        </span>
      </template>

      <template v-else>
        <span v-if="value !== null && value !== undefined">{{ value }}</span>
        <span v-else class="text-muted">-</span>
      </template>
    </template>

    <!-- Edit/Create Mode (Form inputs) -->
    <template v-else>
      <label :for="`${formId}-${fieldName}`" class="form-label">
        {{ formatFieldName(fieldName) }}
      </label>

      <!-- Textarea -->
      <textarea
        v-if="fieldDef?.type === 'textarea'"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-control"
        :readonly="fieldDef?.readonly"
        :value="value"
        @input="$emit('update:value', $event.target.value)"
      ></textarea>

      <!-- Datetime -->
      <input
        v-else-if="fieldDef?.type === 'datetime'"
        type="datetime-local"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-control"
        :readonly="fieldDef?.readonly"
        :value="formatDateTimeLocal(value)"
        @input="$emit('update:value', $event.target.value)"
      />

      <!-- Date -->
      <input
        v-else-if="fieldDef?.type === 'date'"
        type="date"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-control"
        :readonly="fieldDef?.readonly"
        :value="formatDateLocal(value)"
        @input="$emit('update:value', $event.target.value)"
      />

      <!-- Checkbox/Boolean -->
      <div v-else-if="fieldDef?.type === 'checkbox' || fieldDef?.type === 'boolean'" class="form-check">
        <input
          type="checkbox"
          :id="`${formId}-${fieldName}`"
          :name="fieldName"
          class="form-check-input"
          :readonly="fieldDef?.readonly"
          :checked="value"
          @change="$emit('update:value', $event.target.checked)"
        />
      </div>

      <!-- Select -->
      <select
        v-else-if="fieldDef?.type === 'select'"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-select"
        :readonly="fieldDef?.readonly"
        :value="value"
        @change="$emit('update:value', $event.target.value)"
      >
        <option value=""></option>
        <option
          v-for="(displayValue, key) in fieldDef?.options || {}"
          :key="key"
          :value="key"
        >
          {{ displayValue }}
        </option>
      </select>

      <!-- Relationship -->
      <select
        v-else-if="fieldDef?.type === 'relationship'"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-select"
        :readonly="fieldDef?.readonly"
        :value="value || ''"
        @change="$emit('update:value', $event.target.value)"
      >
        <option value=""></option>
        <option
          v-if="relationship?.id"
          :value="relationship.id"
        >
          {{ relationship.name }}
        </option>
      </select>

      <!-- Collection -->
      <div v-else-if="fieldDef?.type === 'collection'" class="collection-wrapper">
        <div
          v-for="(item, index) in collectionItems"
          :key="index"
          class="input-group mb-2"
        >
          <input
            type="text"
            class="form-control"
            :readonly="fieldDef?.readonly"
            :value="item"
            @input="updateCollectionItem(index, $event.target.value)"
          />
          <button
            v-if="!fieldDef?.readonly"
            type="button"
            class="btn btn-outline-danger"
            @click="removeCollectionItem(index)"
          >
            ×
          </button>
        </div>
        <button
          v-if="!fieldDef?.readonly"
          type="button"
          class="btn btn-outline-secondary btn-sm"
          @click="addCollectionItem"
        >
          Add Item
        </button>
      </div>

      <!-- Default input (text, string, int, integer, float, uuid) -->
      <input
        v-else
        :type="getInputType(fieldDef?.type)"
        :id="`${formId}-${fieldName}`"
        :name="fieldName"
        class="form-control"
        :readonly="fieldDef?.readonly"
        :value="value"
        @input="$emit('update:value', $event.target.value)"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  fieldName: {
    type: String,
    required: true
  },
  fieldDef: {
    type: Object,
    default: null
  },
  value: {
    type: [String, Number, Boolean, Array, Object],
    default: null
  },
  relationship: {
    type: Object,
    default: null
  },
  mode: {
    type: String,
    default: 'detail'
  },
  formId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:value', 'relationship-click'])

const collectionItems = ref(Array.isArray(props.value) ? [...props.value] : (props.value ? [props.value] : []))

watch(() => props.value, (newValue) => {
  if (props.fieldDef?.type === 'collection') {
    collectionItems.value = Array.isArray(newValue) ? [...newValue] : (newValue ? [newValue] : [])
  }
})

function formatFieldName(fieldName) {
  return fieldName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function formatDateTime(value) {
  if (!value) return ''
  const date = new Date(value)
  return date.toLocaleString()
}

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  return date.toLocaleDateString()
}

function formatDateTimeLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

function formatDateLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getInputType(fieldType) {
  switch (fieldType) {
    case 'int':
    case 'integer':
      return 'number'
    case 'float':
      return 'number'
    case 'uuid':
    case 'string':
    case 'text':
    default:
      return 'text'
  }
}

function updateCollectionItem(index, newValue) {
  collectionItems.value[index] = newValue
  emit('update:value', [...collectionItems.value])
}

function addCollectionItem() {
  collectionItems.value.push('')
  emit('update:value', [...collectionItems.value])
}

function removeCollectionItem(index) {
  collectionItems.value.splice(index, 1)
  emit('update:value', [...collectionItems.value])
}

function handleRelationshipClick() {
  if (props.relationship?.entity && props.relationship?.id) {
    emit('relationship-click', {
      entity: props.relationship.entity,
      recordId: props.relationship.id
    })
  }
}
</script>

<style scoped>
.collection-display {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.collection-wrapper {
  width: 100%;
}
</style>

