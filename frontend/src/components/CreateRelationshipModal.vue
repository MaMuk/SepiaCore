<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Relationship</h5>
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
              <!-- Left Hand Entity Selection -->
              <div class="col-12 col-md-6">
                <label for="lhEntity" class="form-label">
                  Left Hand Entity <span class="text-danger">*</span>
                </label>
                <select
                  id="lhEntity"
                  class="form-select"
                  v-model="form.lhEntity"
                  @change="updateRelationshipName"
                  :class="{ 'is-invalid': errors.lhEntity }"
                  required
                >
                  <option value="">Select an entity...</option>
                  <option
                    v-for="entity in relatableEntities"
                    :key="entity.name"
                    :value="entity.name"
                  >
                    {{ entity.displayName }}
                  </option>
                </select>
                <div v-if="errors.lhEntity" class="invalid-feedback">
                  {{ errors.lhEntity }}
                </div>
                <div class="form-text">
                  The primary entity in this relationship
                </div>
              </div>

              <!-- Right Hand Entity Selection -->
              <div class="col-12 col-md-6">
                <label for="rhEntity" class="form-label">
                  Right Hand Entity <span class="text-danger">*</span>
                </label>
                <select
                  id="rhEntity"
                  class="form-select"
                  v-model="form.rhEntity"
                  @change="updateRelationshipName"
                  :class="{ 'is-invalid': errors.rhEntity }"
                  required
                >
                  <option value="">Select an entity...</option>
                  <option
                    v-for="entity in relatableEntities"
                    :key="entity.name"
                    :value="entity.name"
                  >
                    {{ entity.displayName }}
                  </option>
                </select>
                <div v-if="errors.rhEntity" class="invalid-feedback">
                  {{ errors.rhEntity }}
                </div>
                <div class="form-text">
                  The related entity in this relationship
                </div>
              </div>

              <!-- Relationship Name (Auto-generated, editable) -->
              <div class="col-12">
                <label for="relName" class="form-label">
                  Relationship Name <span class="text-danger">*</span>
                </label>
                <input
                  type="text"
                  id="relName"
                  class="form-control"
                  v-model="form.relName"
                  :class="{ 'is-invalid': errors.relName }"
                  required
                  placeholder="entity1_entity2"
                />
                <div v-if="errors.relName" class="invalid-feedback">
                  {{ errors.relName }}
                </div>
                <div class="form-text">
                  Auto-generated from selected entities. You can edit this if needed.
                  <span v-if="relationshipExists" class="text-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    A relationship with this name already exists. A number will be appended.
                  </span>
                </div>
              </div>

              <!-- Relationship Table Name (Auto-generated, editable) -->
              <div class="col-12">
                <label for="relTable" class="form-label">
                  Relationship Table Name <span class="text-danger">*</span>
                </label>
                <input
                  type="text"
                  id="relTable"
                  class="form-control"
                  v-model="form.relTable"
                  :class="{ 'is-invalid': errors.relTable }"
                  required
                  placeholder="entity1_entity2"
                />
                <div v-if="errors.relTable" class="invalid-feedback">
                  {{ errors.relTable }}
                </div>
                <div class="form-text">
                  The database table name for this relationship. Usually matches the relationship name.
                </div>
              </div>

              <!-- Relationship Preview -->
              <div v-if="form.lhEntity && form.rhEntity" class="col-12">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title mb-3">
                      <i class="bi bi-info-circle me-2"></i>
                      Relationship Preview
                    </h6>
                    <div class="d-flex align-items-center justify-content-center gap-3">
                      <div class="text-center">
                        <div class="badge bg-primary fs-6 p-2">
                          {{ getEntityDisplayName(form.lhEntity) }}
                        </div>
                        <div class="small text-muted mt-1">Left Hand</div>
                      </div>
                      <div class="text-center">
                        <i class="bi bi-arrow-left-right fs-4 text-secondary"></i>
                        <div class="small text-muted">Many-to-Many</div>
                      </div>
                      <div class="text-center">
                        <div class="badge bg-success fs-6 p-2">
                          {{ getEntityDisplayName(form.rhEntity) }}
                        </div>
                        <div class="small text-muted mt-1">Right Hand</div>
                      </div>
                    </div>
                    <div class="mt-3 text-center">
                      <small class="text-muted">
                        Table: <code>{{ form.relTable || 'N/A' }}</code>
                      </small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Validation Warning -->
              <div v-if="form.lhEntity === form.rhEntity && form.lhEntity" class="col-12">
                <div class="alert alert-warning mb-0">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  You cannot create a relationship between an entity and itself.
                </div>
              </div>

              <!-- Submit Button -->
              <div class="col-12">
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="saving || !isFormValid"
                >
                  <span
                    v-if="saving"
                    class="spinner-border spinner-border-sm me-2"
                    role="status"
                    aria-hidden="true"
                  ></span>
                  <i v-else class="bi bi-plus-circle me-2"></i>
                  Create Relationship
                </button>
                <button
                  type="button"
                  class="btn btn-secondary ms-2"
                  @click="close"
                  :disabled="saving"
                >
                  Cancel
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
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

// Protected entities that should be excluded from relationships
const PROTECTED_ENTITIES = ['users', 'tokens', 'rawendpointdata', 'modulebuilder', 'endpoints']

const form = ref({
  lhEntity: '',
  rhEntity: '',
  relName: '',
  relTable: ''
})

const errors = ref({})
const saving = ref(false)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Get all relatable entities (exclude protected entities)
const relatableEntities = computed(() => {
  const entities = metadataStore.entities || {}
  return Object.keys(entities)
    .filter(entityName => !PROTECTED_ENTITIES.includes(entityName.toLowerCase()))
    .map(entityName => ({
      name: entityName,
      displayName: metadataStore.formatEntityName(entityName)
    }))
    .sort((a, b) => a.displayName.localeCompare(b.displayName))
})

// Check if relationship already exists
const relationshipExists = computed(() => {
  if (!form.value.relName) return false
  const relationships = metadataStore.metadata?.relationships || {}
  return relationships.hasOwnProperty(form.value.relName)
})

// Check if form is valid
const isFormValid = computed(() => {
  return form.value.lhEntity.trim() !== '' &&
         form.value.rhEntity.trim() !== '' &&
         form.value.lhEntity !== form.value.rhEntity &&
         form.value.relName.trim() !== '' &&
         form.value.relTable.trim() !== ''
})

// Get entity display name
function getEntityDisplayName(entityName) {
  if (!entityName) return ''
  return metadataStore.formatEntityName(entityName)
}

// Update relationship name and table when entities change
function updateRelationshipName() {
  if (form.value.lhEntity && form.value.rhEntity && form.value.lhEntity !== form.value.rhEntity) {
    const baseName = `${form.value.lhEntity}_${form.value.rhEntity}`
    let relName = baseName
    let relTable = baseName
    
    // Check if relationship already exists and find next available name
    const relationships = metadataStore.metadata?.relationships || {}
    if (relationships[relName]) {
      let counter = 1
      while (relationships[`${baseName}_${counter}`]) {
        counter++
      }
      relName = `${baseName}_${counter}`
      relTable = relName
    }
    
    form.value.relName = relName
    form.value.relTable = relTable
  } else {
    form.value.relName = ''
    form.value.relTable = ''
  }
  
  validateForm()
}

// Validate form
function validateForm() {
  errors.value = {}
  
  if (!form.value.lhEntity) {
    errors.value.lhEntity = 'Left hand entity is required'
  }
  
  if (!form.value.rhEntity) {
    errors.value.rhEntity = 'Right hand entity is required'
  }
  
  if (form.value.lhEntity === form.value.rhEntity && form.value.lhEntity) {
    errors.value.rhEntity = 'Entities must be different'
  }
  
  if (!form.value.relName.trim()) {
    errors.value.relName = 'Relationship name is required'
  }
  
  if (!form.value.relTable.trim()) {
    errors.value.relTable = 'Relationship table name is required'
  }
}

// Handle form submission
async function handleSubmit() {
  validateForm()
  
  if (!isFormValid.value) {
    return
  }

  saving.value = true

  try {
    const relDef = {
      rel_name: form.value.relName.trim(),
      rh_entity: form.value.rhEntity.trim(),
      lh_entity: form.value.lhEntity.trim(),
      rel_table: form.value.relTable.trim()
    }

    const response = await api.post('/modulebuilder/newRelationship', relDef)

    if (response.data) {
      toastStore.success('Relationship created successfully!')
      
      // Refresh metadata to get the new relationship
      await metadataStore.fetchMetadata()
      
      emit('saved')
      close()
    }
  } catch (error) {
    console.error('Error creating relationship:', error)
    toastStore.error(error.response?.data?.message || 'Failed to create relationship')
  } finally {
    saving.value = false
  }
}

// Reset form when modal opens
watch(isVisible, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

function resetForm() {
  form.value = {
    lhEntity: '',
    rhEntity: '',
    relName: '',
    relTable: ''
  }
  errors.value = {}
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

.card {
  border: 1px solid #dee2e6;
}

.badge {
  min-width: 120px;
}

code {
  background-color: #f8f9fa;
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}
</style>

