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
          <h5 class="modal-title">
            <i class="bi bi-diagram-3 me-2"></i>
            Relationship Editor
            <span v-if="filterEntity" class="text-muted fs-6 ms-2">
              - {{ getEntityDisplayName(filterEntity) }}
            </span>
          </h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <!-- Filter Toggle -->
          <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="form-check form-switch">
              <input
                class="form-check-input"
                type="checkbox"
                id="showAllRelationships"
                v-model="showAll"
                @change="updateFilteredRelationships"
              />
              <label class="form-check-label" for="showAllRelationships">
                Show all relationships
              </label>
            </div>
            <div class="text-muted small">
              <i class="bi bi-info-circle me-1"></i>
              {{ filteredRelationships.length }} relationship(s) found
            </div>
          </div>

          <!-- Relationships Table -->
          <div v-if="filteredRelationships.length > 0" class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 30%">Relationship Name</th>
                  <th style="width: 25%">Left Hand Entity</th>
                  <th style="width: 25%">Right Hand Entity</th>
                  <th style="width: 15%">Table Name</th>
                  <th style="width: 5%">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="relationship in filteredRelationships"
                  :key="relationship.rel_name"
                >
                  <td>
                    <code class="text-primary">{{ relationship.rel_name }}</code>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <img
                        v-if="getEntityIcon(relationship.lh_entity)"
                        :src="getIconPath(getEntityIcon(relationship.lh_entity))"
                        :alt="getEntityDisplayName(relationship.lh_entity)"
                        class="entity-icon-small"
                      />
                      <span>{{ getEntityDisplayName(relationship.lh_entity) }}</span>
                      <span
                        v-if="filterEntity && filterEntity === relationship.lh_entity"
                        class="badge bg-primary"
                      >
                        This Entity
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <img
                        v-if="getEntityIcon(relationship.rh_entity)"
                        :src="getIconPath(getEntityIcon(relationship.rh_entity))"
                        :alt="getEntityDisplayName(relationship.rh_entity)"
                        class="entity-icon-small"
                      />
                      <span>{{ getEntityDisplayName(relationship.rh_entity) }}</span>
                      <span
                        v-if="filterEntity && filterEntity === relationship.rh_entity"
                        class="badge bg-primary"
                      >
                        This Entity
                      </span>
                    </div>
                  </td>
                  <td>
                    <code class="text-secondary small">{{ relationship.rel_table }}</code>
                  </td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-sm btn-danger"
                      @click="handleDelete(relationship)"
                      :disabled="deleting === relationship.rel_name"
                      :title="`Delete relationship: ${relationship.rel_name}`"
                    >
                      <span
                        v-if="deleting === relationship.rel_name"
                        class="spinner-border spinner-border-sm"
                        role="status"
                        aria-hidden="true"
                      ></span>
                      <i v-else class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-5">
            <i class="bi bi-diagram-3 text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-3 mb-0">
              <span v-if="showAll">No relationships found.</span>
              <span v-else>
                No relationships found for {{ getEntityDisplayName(filterEntity) }}.
              </span>
            </p>
            <p class="text-muted small mt-2">
              Create a new relationship using the "New Relationship" option in Entity Studio.
            </p>
          </div>

          <!-- Future: Fields Section (Placeholder for future functionality) -->
          <div v-if="selectedRelationship" class="mt-4 pt-4 border-top">
            <h6 class="mb-3">
              <i class="bi bi-list-ul me-2"></i>
              Relationship Fields
              <small class="text-muted">(Future functionality)</small>
            </h6>
            <div class="alert alert-info mb-0">
              <i class="bi bi-info-circle me-2"></i>
              Field management for relationship tables will be available in a future update.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            @click="close"
          >
            Close
          </button>
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
import { getIconPath } from '../utils/iconUtils'
import api from '../services/api'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  filterEntity: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const showAll = ref(false)
const deleting = ref(null)
const selectedRelationship = ref(null) // For future field management

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Get all relationships from metadata
const allRelationships = computed(() => {
  const relationships = metadataStore.metadata?.relationships || {}
  return Object.keys(relationships).map(relName => ({
    rel_name: relName,
    ...relationships[relName]
  }))
})

// Filter relationships based on filterEntity and showAll toggle
const filteredRelationships = computed(() => {
  if (showAll.value || !props.filterEntity) {
    return allRelationships.value
  }
  
  // Filter by entity (either lh_entity or rh_entity)
  return allRelationships.value.filter(rel => 
    rel.lh_entity === props.filterEntity || rel.rh_entity === props.filterEntity
  )
})

// Update filtered relationships when showAll changes
function updateFilteredRelationships() {
  // This is handled by the computed property, but we can add logic here if needed
}

// Get entity display name
function getEntityDisplayName(entityName) {
  if (!entityName) return ''
  return metadataStore.formatEntityName(entityName)
}

// Get entity icon
function getEntityIcon(entityName) {
  if (!entityName) return null
  const entity = metadataStore.entities?.[entityName]
  return entity?.icon || null
}

// Handle delete relationship
async function handleDelete(relationship) {
  const relName = relationship.rel_name
  const displayName = `${getEntityDisplayName(relationship.lh_entity)} ↔ ${getEntityDisplayName(relationship.rh_entity)}`
  
  const message = `Are you sure you want to delete the relationship "${relName}"?\n\n` +
    `This will remove the relationship between ${getEntityDisplayName(relationship.lh_entity)} and ${getEntityDisplayName(relationship.rh_entity)}.\n\n` +
    `The relationship table "${relationship.rel_table}" will be deleted if it's not shared with other relationships.\n\n` +
    `This action cannot be undone.`

  if (!confirm(message)) {
    return
  }

  deleting.value = relName

  try {
    const response = await api.post('/modulebuilder/deleteRelationship', {
      relationship: relName
    })

    if (response.data) {
      toastStore.success(`Relationship "${relName}" deleted successfully!`)
      
      // Refresh metadata to get updated relationships
      await metadataStore.fetchMetadata()
      
      emit('saved')
    }
  } catch (error) {
    console.error('Error deleting relationship:', error)
    toastStore.error(error.response?.data?.message || `Failed to delete relationship "${relName}"`)
  } finally {
    deleting.value = null
  }
}

// Reset when modal opens
watch(isVisible, (newVal) => {
  if (newVal) {
    // Initialize showAll based on whether filterEntity is provided
    showAll.value = !props.filterEntity
    selectedRelationship.value = null
  }
})

function close() {
  isVisible.value = false
  showAll.value = false
  selectedRelationship.value = null
  deleting.value = null
}

function handleBackdropClick() {
  close()
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

.entity-icon-small {
  width: 20px;
  height: 20px;
  object-fit: contain;
}

.table {
  margin-bottom: 0;
}

code {
  background-color: #f8f9fa;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}

.badge {
  font-size: 0.7rem;
  font-weight: normal;
}
</style>

