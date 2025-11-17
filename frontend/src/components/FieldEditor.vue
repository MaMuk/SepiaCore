<template>
  <div class="field-editor-container">
    <div class="container-fluid px-0">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="d-flex align-items-center gap-2 mb-0">
          <span>{{ entityDisplayName }} - Field Editor</span>
        </h2>
        <button
          class="btn btn-outline-secondary"
          @click="goBack"
        >
          <i class="bi bi-arrow-left me-2"></i>
          Back to Entity
        </button>
      </div>

      <!-- Edit Field Section -->
      <div v-if="editingField" class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">Edit Field: {{ editingField.name }}</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Field Name</label>
              <input
                type="text"
                class="form-control"
                :value="editingField.name"
                disabled
              />
              <div class="form-text">
                Field name cannot be changed
              </div>
            </div>
            <div class="col-md-2">
              <label for="editFieldType" class="form-label">Type</label>
              <select
                id="editFieldType"
                class="form-select"
                v-model="editingField.def.type"
                @change="handleEditTypeChange"
              >
                <option v-for="type in fieldTypes" :key="type" :value="type">
                  {{ type }}
                </option>
              </select>
            </div>
            <div v-if="editingField.def.type === 'relationship'" class="col-md-3">
              <label for="editFieldEntity" class="form-label">Related Entity</label>
              <select
                id="editFieldEntity"
                class="form-select"
                :class="{ 'is-invalid': editFieldErrors.entity }"
                v-model="editingField.def.entity"
                @change="validateEditField"
              >
                <option value="">Select entity...</option>
                <option
                  v-for="entity in relatableEntities"
                  :key="entity"
                  :value="entity"
                >
                  {{ formatEntityName(entity) }}
                </option>
              </select>
              <div v-if="editFieldErrors.entity" class="invalid-feedback">
                {{ editFieldErrors.entity }}
              </div>
            </div>
            <div v-if="editingField.def.type === 'select'" class="col-md-4">
              <label class="form-label">Options</label>
              <div class="select-options-container">
                <div
                  v-for="(option, index) in editingFieldOptions"
                  :key="index"
                  class="d-flex gap-2 mb-2"
                >
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Value"
                    v-model="option.value"
                  />
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Label"
                    v-model="option.label"
                  />
                  <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="removeEditOption(index)"
                  >
                    <i class="bi bi-x"></i>
                  </button>
                </div>
                <button
                  type="button"
                  class="btn btn-sm btn-secondary"
                  @click="addEditOption"
                >
                  <i class="bi bi-plus me-1"></i>Add Option
                </button>
              </div>
            </div>
            <div class="col-md-2">
              <label class="form-label">&nbsp;</label>
              <div class="form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  id="editFieldReadonly"
                  v-model="editingField.def.readonly"
                />
                <label class="form-check-label" for="editFieldReadonly">
                  Readonly
                </label>
              </div>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
              <button
                type="button"
                class="btn btn-success flex-fill"
                @click="saveEditedField"
                :disabled="!canSaveEdit"
              >
                <i class="bi bi-check-circle me-1"></i>Save
              </button>
              <button
                type="button"
                class="btn btn-secondary flex-fill"
                @click="cancelEdit"
              >
                <i class="bi bi-x-circle me-1"></i>Cancel
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add New Field Section -->
      <div v-if="!editingField" class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Add New Field</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label for="newFieldName" class="form-label">Field Name</label>
              <input
                type="text"
                id="newFieldName"
                class="form-control"
                :class="{ 'is-invalid': newFieldErrors.name }"
                v-model="newField.name"
                placeholder="field_name"
                @input="validateNewFieldName"
              />
              <div v-if="newFieldErrors.name" class="invalid-feedback">
                {{ newFieldErrors.name }}
              </div>
              <div class="form-text">
                Lowercase letters, numbers, and underscores only. Must start with a letter or underscore.
              </div>
            </div>
            <div class="col-md-2">
              <label for="newFieldType" class="form-label">Type</label>
              <select
                id="newFieldType"
                class="form-select"
                v-model="newField.type"
                @change="handleTypeChange"
              >
                <option v-for="type in fieldTypes" :key="type" :value="type">
                  {{ type }}
                </option>
              </select>
            </div>
            <div v-if="newField.type === 'relationship'" class="col-md-3">
              <label for="newFieldEntity" class="form-label">Related Entity</label>
              <select
                id="newFieldEntity"
                class="form-select"
                :class="{ 'is-invalid': newFieldErrors.entity }"
                v-model="newField.entity"
                @change="validateNewField"
              >
                <option value="">Select entity...</option>
                <option
                  v-for="entity in relatableEntities"
                  :key="entity"
                  :value="entity"
                >
                  {{ formatEntityName(entity) }}
                </option>
              </select>
              <div v-if="newFieldErrors.entity" class="invalid-feedback">
                {{ newFieldErrors.entity }}
              </div>
            </div>
            <div v-if="newField.type === 'select'" class="col-md-4">
              <label class="form-label">Options</label>
              <div class="select-options-container">
                <div
                  v-for="(option, index) in newField.options"
                  :key="index"
                  class="d-flex gap-2 mb-2"
                >
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Value"
                    v-model="option.value"
                  />
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Label"
                    v-model="option.label"
                  />
                  <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="removeOption(index)"
                  >
                    <i class="bi bi-x"></i>
                  </button>
                </div>
                <button
                  type="button"
                  class="btn btn-sm btn-secondary"
                  @click="addOption"
                >
                  <i class="bi bi-plus me-1"></i>Add Option
                </button>
              </div>
            </div>
            <div class="col-md-2">
              <label class="form-label">&nbsp;</label>
              <div class="form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  id="newFieldReadonly"
                  v-model="newField.readonly"
                />
                <label class="form-check-label" for="newFieldReadonly">
                  Readonly
                </label>
              </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button
                type="button"
                class="btn btn-primary w-100"
                @click="addField"
                :disabled="!canAddField"
              >
                <i class="bi bi-plus-circle me-1"></i>Add Field
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Existing Fields Table -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Existing Fields</h5>
        </div>
        <div class="card-body">
          <div v-if="fields.length === 0" class="text-center text-muted p-4">
            No fields defined yet. Add a field above to get started.
          </div>
          <div v-else class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Field Name</th>
                  <th>Type</th>
                  <th>Related Entity</th>
                  <th>Options</th>
                  <th>Readonly</th>
                  <th>Protected</th>
                  <th style="width: 100px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="field in fields" :key="field.name">
                  <td>
                    <strong>{{ field.name }}</strong>
                  </td>
                  <td>
                    <span class="badge bg-secondary">{{ field.def.type }}</span>
                  </td>
                  <td>
                    <span v-if="field.def.entity">
                      {{ formatEntityName(field.def.entity) }}
                    </span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <span v-if="field.def.options" class="text-muted small">
                      {{ Object.keys(field.def.options).length }} option(s)
                    </span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <i
                      v-if="field.def.readonly"
                      class="bi bi-check-circle text-success"
                    ></i>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <i
                      v-if="field.isProtected"
                      class="bi bi-shield-check text-warning"
                      :title="'This field is protected and cannot be deleted'"
                    ></i>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="startEditField(field)"
                        :disabled="saving || editingField"
                        :title="'Edit field'"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button
                        v-if="!field.isProtected"
                        type="button"
                        class="btn btn-sm btn-danger"
                        @click="confirmDeleteField(field.name)"
                        :disabled="saving || editingField"
                        :title="'Delete field'"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                      <span v-if="field.isProtected" class="text-muted small">Protected</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="d-flex justify-content-end gap-2 mt-4">
        <button
          type="button"
          class="btn btn-secondary"
          @click="goBack"
          :disabled="saving"
        >
          Cancel
        </button>
        <button
          type="button"
          class="btn btn-primary"
          @click="saveFields"
          :disabled="saving || (!hasChanges && !editingField)"
        >
          <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
          <i v-else class="bi bi-save me-2"></i>
          {{ saving ? 'Saving...' : 'Save Fields' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import api from '../services/api'

const props = defineProps({
  entity: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const fields = ref([])
const originalFields = ref({})
const saving = ref(false)
const hasChanges = ref(false)
const editingField = ref(null)
const editingFieldOptions = ref([])
const editFieldErrors = ref({
  entity: null
})

const fieldTypes = [
  'text',
  'textarea',
  'datetime',
  'date',
  'select',
  'collection',
  'checkbox',
  'boolean',
  'int',
  'integer',
  'float',
  'relationship',
  'uuid',
  'string'
]

const newField = ref({
  name: '',
  type: 'text',
  readonly: false,
  entity: '',
  options: []
})

const newFieldErrors = ref({
  name: null,
  entity: null
})

const reservedKeywords = [
  'select', 'from', 'where', 'group', 'order', 'limit', 'join', 'table', 'user',
  'index', 'primary', 'key', 'foreign', 'by', 'as', 'into', 'and', 'or', 'not', 'null'
]

const entityDisplayName = computed(() => {
  return props.entity.displayName || metadataStore.formatEntityName(props.entity.name)
})

const relatableEntities = computed(() => {
  const excluded = ['users', 'tokens', 'modulebuilder', 'dashboards', 'rawendpointdata', 'endpoints']
  const entities = metadataStore.entities || {}
  return Object.keys(entities).filter(name => !excluded.includes(name.toLowerCase()))
})

const protectedFields = computed(() => {
  const entityMeta = metadataStore.getEntityMetadata(props.entity.name)
  const isPerson = entityMeta?.isPerson || false
  
  if (isPerson) {
    return ['id', 'first_name', 'last_name', 'date_created', 'date_modified', 'owner']
  }
  return ['id', 'name', 'date_created', 'date_modified', 'owner']
})

const canAddField = computed(() => {
  return newField.value.name.trim() !== '' &&
         !newFieldErrors.value.name &&
         (newField.value.type !== 'relationship' || newField.value.entity) &&
         (newField.value.type !== 'select' || newField.value.options.length > 0)
})

const canSaveEdit = computed(() => {
  if (!editingField.value) return false
  
  return (editingField.value.def.type !== 'relationship' || editingField.value.def.entity) &&
         (editingField.value.def.type !== 'select' || editingFieldOptions.value.length > 0)
})

function formatEntityName(name) {
  return metadataStore.formatEntityName(name)
}

function validateFieldName(name) {
  if (!name || name.trim() === '') {
    return 'Field name is required'
  }
  
  const trimmed = name.trim()
  
  // Check format: lowercase letters, numbers, underscores, must start with letter or underscore
  if (!/^[a-z_][a-z0-9_]*$/.test(trimmed)) {
    return 'Field name must be lowercase letters, numbers, and underscores only. Must start with a letter or underscore.'
  }
  
  // Check reserved keywords
  if (reservedKeywords.includes(trimmed.toLowerCase())) {
    return `"${trimmed}" is a reserved SQL keyword and cannot be used.`
  }
  
  return null
}

function validateNewFieldName() {
  const name = newField.value.name.trim()
  newFieldErrors.value.name = validateFieldName(name)
  
  // Check if field already exists
  if (!newFieldErrors.value.name && fields.value.some(f => f.name === name)) {
    newFieldErrors.value.name = 'Field already exists'
  }
}

function validateNewField() {
  if (newField.value.type === 'relationship' && !newField.value.entity) {
    newFieldErrors.value.entity = 'Please select a related entity'
  } else {
    newFieldErrors.value.entity = null
  }
}

function handleTypeChange() {
  newFieldErrors.value.entity = null
  
  if (newField.value.type === 'select' && newField.value.options.length === 0) {
    newField.value.options = [{ value: '', label: '' }]
  } else if (newField.value.type !== 'select') {
    newField.value.options = []
  }
  
  if (newField.value.type !== 'relationship') {
    newField.value.entity = ''
  }
}

function addOption() {
  newField.value.options.push({ value: '', label: '' })
}

function removeOption(index) {
  newField.value.options.splice(index, 1)
}

function addField() {
  validateNewFieldName()
  validateNewField()
  
  if (!canAddField.value) {
    return
  }
  
  const fieldName = newField.value.name.trim()
  const fieldDef = {
    type: newField.value.type
  }
  
  if (newField.value.readonly) {
    fieldDef.readonly = true
  }
  
  if (newField.value.type === 'relationship') {
    if (!newField.value.entity) {
      toastStore.error('Please select a related entity')
      return
    }
    fieldDef.entity = newField.value.entity
  }
  
  if (newField.value.type === 'select') {
    const options = {}
    for (const option of newField.value.options) {
      if (option.value.trim() !== '' || option.label.trim() !== '') {
        options[option.value.trim()] = option.label.trim() || option.value.trim()
      }
    }
    
    if (Object.keys(options).length === 0) {
      toastStore.error('Please add at least one option')
      return
    }
    
    fieldDef.options = options
  }
  
  // Add to fields array
  fields.value.push({
    name: fieldName,
    def: fieldDef,
    isProtected: protectedFields.value.includes(fieldName)
  })
  
  hasChanges.value = true
  
  // Reset form
  newField.value = {
    name: '',
    type: 'text',
    readonly: false,
    entity: '',
    options: []
  }
  newFieldErrors.value = {
    name: null,
    entity: null
  }
}

function confirmDeleteField(fieldName) {
  if (confirm(`Are you sure you want to delete the field "${fieldName}"? This action cannot be undone.`)) {
    deleteField(fieldName)
  }
}

function deleteField(fieldName) {
  const index = fields.value.findIndex(f => f.name === fieldName)
  if (index !== -1) {
    fields.value.splice(index, 1)
    hasChanges.value = true
  }
}

function startEditField(field) {
  // Create a deep copy of the field for editing
  editingField.value = {
    name: field.name,
    def: JSON.parse(JSON.stringify(field.def)),
    isProtected: field.isProtected
  }
  
  // Convert options object to array format for editing
  if (editingField.value.def.type === 'select' && editingField.value.def.options) {
    editingFieldOptions.value = Object.keys(editingField.value.def.options).map(key => ({
      value: key,
      label: editingField.value.def.options[key]
    }))
  } else {
    editingFieldOptions.value = []
  }
  
  // Ensure readonly is a boolean
  if (editingField.value.def.readonly === undefined) {
    editingField.value.def.readonly = false
  }
  
  // Ensure entity is set for relationship type
  if (editingField.value.def.type === 'relationship' && !editingField.value.def.entity) {
    editingField.value.def.entity = ''
  }
}

function handleEditTypeChange() {
  editFieldErrors.value.entity = null
  
  if (editingField.value.def.type === 'select') {
    if (editingFieldOptions.value.length === 0) {
      editingFieldOptions.value = [{ value: '', label: '' }]
    }
    // Remove entity if switching from relationship
    if (editingField.value.def.entity) {
      delete editingField.value.def.entity
    }
  } else if (editingField.value.def.type === 'relationship') {
    editingFieldOptions.value = []
    // Remove options if switching from select
    if (editingField.value.def.options) {
      delete editingField.value.def.options
    }
    if (!editingField.value.def.entity) {
      editingField.value.def.entity = ''
    }
  } else {
    editingFieldOptions.value = []
    // Remove entity and options for other types
    if (editingField.value.def.entity) {
      delete editingField.value.def.entity
    }
    if (editingField.value.def.options) {
      delete editingField.value.def.options
    }
  }
}

function addEditOption() {
  editingFieldOptions.value.push({ value: '', label: '' })
}

function removeEditOption(index) {
  editingFieldOptions.value.splice(index, 1)
}

function validateEditField() {
  if (editingField.value.def.type === 'relationship' && !editingField.value.def.entity) {
    editFieldErrors.value.entity = 'Please select a related entity'
  } else {
    editFieldErrors.value.entity = null
  }
}

function saveEditedField() {
  validateEditField()
  
  if (!canSaveEdit.value) {
    return
  }
  
  // Update options for select type
  if (editingField.value.def.type === 'select') {
    const options = {}
    for (const option of editingFieldOptions.value) {
      if (option.value.trim() !== '' || option.label.trim() !== '') {
        options[option.value.trim()] = option.label.trim() || option.value.trim()
      }
    }
    
    if (Object.keys(options).length === 0) {
      toastStore.error('Please add at least one option')
      return
    }
    
    editingField.value.def.options = options
  }
  
  // Find and update the field in the fields array
  const index = fields.value.findIndex(f => f.name === editingField.value.name)
  if (index !== -1) {
    fields.value[index] = {
      name: editingField.value.name,
      def: editingField.value.def,
      isProtected: editingField.value.isProtected
    }
    hasChanges.value = true
  }
  
  // Clear editing state
  editingField.value = null
  editingFieldOptions.value = []
  editFieldErrors.value = { entity: null }
}

function cancelEdit() {
  editingField.value = null
  editingFieldOptions.value = []
  editFieldErrors.value = { entity: null }
}

async function saveFields() {
  if (!hasChanges.value && !editingField.value) {
    return
  }
  
  // Save any active edit first
  if (editingField.value) {
    if (!canSaveEdit.value) {
      toastStore.error('Please complete the field edit before saving')
      return
    }
    // Save the edited field without clearing the edit state yet
    // (we'll clear it after all fields are saved)
    saveEditedField()
  }
  
  if (!hasChanges.value) {
    return
  }
  
  saving.value = true
  
  try {
    // Convert fields array to object format expected by backend
    const fieldDefs = {}
    for (const field of fields.value) {
      fieldDefs[field.name] = field.def
    }
    
    const response = await api.post('/modulebuilder/updateFields', {
      entity: props.entity.name,
      fieldDefs: fieldDefs
    })
    
    if (response.data) {
      toastStore.success('Fields saved successfully!')
      
      // Refresh metadata
      await metadataStore.fetchMetadata()
      
      // Update original fields to mark no changes
      originalFields.value = { ...fieldDefs }
      hasChanges.value = false
      
      // Reload fields from metadata
      loadFields()
    }
  } catch (error) {
    console.error('Error saving fields:', error)
    toastStore.error(error.response?.data?.message || 'Failed to save fields')
  } finally {
    saving.value = false
  }
}

function loadFields() {
  const entityMeta = metadataStore.getEntityMetadata(props.entity.name)
  const entityFields = entityMeta?.fields || {}
  
  fields.value = Object.keys(entityFields).map(fieldName => ({
    name: fieldName,
    def: entityFields[fieldName],
    isProtected: protectedFields.value.includes(fieldName)
  }))
  
  // Store original for change detection
  originalFields.value = { ...entityFields }
  hasChanges.value = false
}

function goBack() {
  if (hasChanges.value) {
    if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
      return
    }
  }
  emit('close')
}

// Watch for changes
watch(() => fields.value, () => {
  // Compare current fields with original
  const currentFieldDefs = {}
  for (const field of fields.value) {
    currentFieldDefs[field.name] = field.def
  }
  
  hasChanges.value = JSON.stringify(currentFieldDefs) !== JSON.stringify(originalFields.value)
}, { deep: true })

onMounted(() => {
  loadFields()
})
</script>

<style scoped>
.field-editor-container {
  max-width: 1400px;
  margin: 0 auto;
}

.select-options-container {
  max-height: 200px;
  overflow-y: auto;
  padding: 0.5rem;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  background-color: #f8f9fa;
}

.table th {
  background-color: #f8f9fa;
  font-weight: 600;
}
</style>

