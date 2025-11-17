<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Entity</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="row g-3">
              <!-- Entity Name -->
              <div class="col-12 col-md-6">
                <label for="entityName" class="form-label">Entity Name</label>
                <input
                  type="text"
                  class="form-control"
                  id="entityName"
                  v-model="form.entityName"
                  @input="handleEntityNameInput"
                  required
                  :class="{ 'is-invalid': errors.entityName }"
                />
                <div v-if="errors.entityName" class="invalid-feedback">
                  {{ errors.entityName }}
                </div>
                <div class="form-text text-info">
                  <ul class="mb-0">
                    <li>Must be between <strong>1 and 20 characters</strong> long.</li>
                    <li>Must <strong>not end with a space or a dot (.)</strong>.</li>
                    <li>Only letters allowed (A-Z, a-z).</li>
                  </ul>
                </div>
              </div>

              <!-- Table Name -->
              <div class="col-12 col-md-6">
                <label for="entityTableName" class="form-label">Entity Table Name</label>
                <input
                  type="text"
                  class="form-control"
                  id="entityTableName"
                  v-model="form.tableName"
                  @input="handleTableNameInput"
                  required
                  :class="{ 'is-invalid': errors.tableName }"
                />
                <div v-if="errors.tableName" class="invalid-feedback">
                  {{ errors.tableName }}
                </div>
              </div>

              <!-- Class Name -->
              <div class="col-12 col-md-6">
                <label for="entityClassName" class="form-label">Entity Class Name</label>
                <input
                  type="text"
                  class="form-control"
                  id="entityClassName"
                  v-model="form.className"
                  disabled
                  required
                />
              </div>

              <!-- Entity Type -->
              <div class="col-12 col-md-6">
                <label for="entityType" class="form-label">Entity Type</label>
                <select
                  class="form-select"
                  id="entityType"
                  v-model="form.type"
                  @change="handleTypeChange"
                >
                  <option value="basic">Basic</option>
                  <option value="person">Person</option>
                </select>
              </div>

              <!-- Icon Selection -->
              <div class="col-12 col-md-6">
                <label for="entityIcon" class="form-label">Entity Icon</label>
                <div class="input-group">
                  <span class="input-group-text icon-preview-wrapper">
                    <img
                      :src="getIconPath(form.icon || 'bi-box')"
                      :alt="form.icon || 'bi-box'"
                      class="selected-icon-preview"
                      :class="{ 'icon-placeholder': !form.icon }"
                    />
                  </span>
                  <input
                    type="text"
                    class="form-control"
                    id="entityIcon"
                    v-model="form.icon"
                    placeholder="bi-person-vcard-fill"
                    @input="updateIconPreview"
                  />
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="showIconSelector = true"
                  >
                    <i class="bi bi-image me-1"></i>
                    Select Icon
                  </button>
                </div>
                <div class="form-text">
                  Enter icon name (e.g., bi-person-vcard-fill) or click "Select Icon" to browse
                </div>
              </div>

              <!-- Fields Table -->
              <div class="col-12">
                <label class="form-label">Entity Fields</label>
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Field Name</th>
                      <th>Field Type</th>
                      <th style="width: 100px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(field, index) in defaultFields" :key="`default-${index}`">
                      <td>{{ field.name }}</td>
                      <td>{{ field.type }}</td>
                      <td></td>
                    </tr>
                    <tr v-for="(field, index) in customFields" :key="`custom-${index}`">
                      <td>
                        <input
                          type="text"
                          class="form-control"
                          v-model="field.name"
                          placeholder="field_name"
                          required
                        />
                      </td>
                      <td>
                        <select class="form-select" v-model="field.type" required>
                          <option value="text">text</option>
                          <option value="textarea">textarea</option>
                          <option value="number">number</option>
                          <option value="select">select</option>
                          <option value="multiselect">multiselect</option>
                        </select>
                      </td>
                      <td>
                        <button
                          type="button"
                          class="btn btn-sm btn-danger"
                          @click="removeCustomField(index)"
                        >
                          Remove
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary"
                  @click="addCustomField"
                >
                  Add Custom Field
                </button>
              </div>

              <!-- Submit -->
              <div class="col-12">
                <button
                  type="submit"
                  class="btn btn-outline-success"
                  :disabled="saving || !isFormValid"
                >
                  <span
                    v-if="saving"
                    class="spinner-border spinner-border-sm me-2"
                    role="status"
                    aria-hidden="true"
                  ></span>
                  Create Entity
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>

  <!-- Icon Selector -->
  <IconSelector
    v-model="showIconSelector"
    :selected-icon="form.icon"
    @select="handleIconSelect"
  />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import { getIconPath } from '../utils/iconUtils'
import IconSelector from './IconSelector.vue'
import api from '../services/api'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const form = ref({
  entityName: '',
  tableName: '',
  className: '',
  type: 'basic',
  icon: ''
})

const showIconSelector = ref(false)

const customFields = ref([])
const defaultFields = ref([
  { name: 'id', type: 'text' },
  { name: 'name', type: 'text' },
  { name: 'date_created', type: 'datetime' },
  { name: 'date_modified', type: 'datetime' },
  { name: 'owner', type: 'text' }
])

const errors = ref({})
const saving = ref(false)
const originalNameField = ref(null)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const isFormValid = computed(() => {
  return form.value.entityName.trim().length > 0 &&
         form.value.entityName.trim().length <= 20 &&
         !form.value.entityName.trim().endsWith(' ') &&
         !form.value.entityName.trim().endsWith('.') &&
         form.value.tableName.trim().length > 0 &&
         form.value.className.trim().length > 0
})

// Reset form when modal opens
watch(isVisible, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

function sanitizeInput(input) {
  return input.replace(/[^a-zA-Z]/g, '')
}

function handleEntityNameInput(event) {
  const rawValue = event.target.value
  const cleanValue = sanitizeInput(rawValue)
  
  if (rawValue !== cleanValue) {
    form.value.entityName = cleanValue
  }

  // Auto-generate table name and class name
  form.value.tableName = cleanValue.toLowerCase()
  
  if (cleanValue.length > 0) {
    form.value.className = cleanValue.charAt(0).toUpperCase() + cleanValue.slice(1).toLowerCase()
  } else {
    form.value.className = ''
  }

  validateEntityName()
}

function handleTableNameInput(event) {
  const rawValue = event.target.value
  const cleanValue = sanitizeInput(rawValue)
  
  if (rawValue !== cleanValue) {
    form.value.tableName = cleanValue.toLowerCase()
  }
}

function validateEntityName() {
  const name = form.value.entityName.trim()
  errors.value.entityName = null

  if (name.length === 0) {
    errors.value.entityName = 'Entity name is required'
  } else if (name.length > 20) {
    errors.value.entityName = 'Entity name must be 20 characters or less'
  } else if (name.endsWith(' ') || name.endsWith('.')) {
    errors.value.entityName = 'Entity name cannot end with a space or dot'
  }
}

function handleTypeChange() {
  const selectedType = form.value.type
  
  if (selectedType === 'person') {
    // Find and remove the 'name' field
    const nameIndex = defaultFields.value.findIndex(f => f.name === 'name')
    if (nameIndex !== -1) {
      originalNameField.value = { ...defaultFields.value[nameIndex] }
      defaultFields.value.splice(nameIndex, 1)
    }

    // Add first_name and last_name after id
    const idIndex = defaultFields.value.findIndex(f => f.name === 'id')
    if (idIndex !== -1) {
      defaultFields.value.splice(idIndex + 1, 0,
        { name: 'first_name', type: 'text' },
        { name: 'last_name', type: 'text' }
      )
    }
  } else {
    // Remove first_name and last_name
    defaultFields.value = defaultFields.value.filter(
      f => f.name !== 'first_name' && f.name !== 'last_name'
    )

    // Re-insert original 'name' field if it was removed
    if (originalNameField.value) {
      const idIndex = defaultFields.value.findIndex(f => f.name === 'id')
      if (idIndex !== -1) {
        defaultFields.value.splice(idIndex + 1, 0, originalNameField.value)
      }
      originalNameField.value = null
    }
  }
}

function addCustomField() {
  customFields.value.push({
    name: '',
    type: 'text'
  })
}

function removeCustomField(index) {
  customFields.value.splice(index, 1)
}

async function handleSubmit() {
  validateEntityName()
  
  if (!isFormValid.value) {
    return
  }

  saving.value = true

  try {
    // Only send custom fields - backend automatically adds default fields (id, name/first_name/last_name, date_created, date_modified, owner)
    const fields = customFields.value
      .filter(f => f.name.trim() && f.type)
      .map(field => ({
        name: field.name.trim(),
        type: field.type
      }))

    // Default views structure
    const views = {
      record: {},
      list: { isdefault: true },
      subpanels: {}
    }

    // Construct the payload
    const payload = {
      name: form.value.entityName.trim(),
      tableName: form.value.tableName.trim(),
      className: form.value.className.trim(),
      type: form.value.type,
      icon: form.value.icon.trim() || null,
      fields: fields,
      views: views
    }

    const response = await api.post('/modulebuilder/newEntity', payload)

    if (response.data) {
      toastStore.success('Entity created successfully!')
      
      // Refresh metadata to get the new entity
      await metadataStore.fetchMetadata()
      
      emit('saved')
      close()
    }
  } catch (error) {
    console.error('Error creating entity:', error)
    toastStore.error(error.response?.data?.message || 'Failed to create entity')
  } finally {
    saving.value = false
  }
}

function handleIconSelect(iconName) {
  form.value.icon = iconName
  showIconSelector.value = false
  updateIconPreview()
}

function updateIconPreview() {
  // Icon preview updates automatically via reactive binding
  // This function can be used for validation if needed
}

function resetForm() {
  form.value = {
    entityName: '',
    tableName: '',
    className: '',
    type: 'basic',
    icon: ''
  }
  customFields.value = []
  errors.value = {}
  originalNameField.value = null
  showIconSelector.value = false
  
  // Reset default fields
  defaultFields.value = [
    { name: 'id', type: 'text' },
    { name: 'name', type: 'text' },
    { name: 'date_created', type: 'datetime' },
    { name: 'date_modified', type: 'datetime' },
    { name: 'owner', type: 'text' }
  ]
}

function close() {
  isVisible.value = false
  resetForm()
}

function handleBackdropClick() {
  close()
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

.table {
  margin-bottom: 1rem;
}

.form-text ul {
  font-size: 0.875rem;
}

.selected-icon-preview {
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.icon-preview-wrapper {
  padding: 0.375rem 0.75rem;
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-right: none;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 50px;
}

.icon-placeholder {
  opacity: 0.5;
}
</style>

