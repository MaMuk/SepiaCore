<template>
  <div class="entity-studio-container p-4">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="d-flex align-items-center gap-2 mb-0">
          <template v-if="selectedEntity">
            <div class="btn-group" role="group">
              <span class="btn btn-sm btn-outline-secondary entity-icon-display">
                <img
                  :src="getIconPath(selectedEntity.icon || 'bi-box')"
                  :alt="selectedEntity.displayName || selectedEntity.name"
                  class="entity-header-icon"
                />
              </span>
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary icon-edit-btn"
                @click="showIconSelector = true"
                :title="'Change entity icon'"
              >
                <i class="bi bi-pencil"></i>
              </button>
            </div>
            <span>{{ selectedEntity.displayName || formatEntityName(selectedEntity.name) }}</span>
            <i
              v-if="isProtectedEntity(selectedEntity.name)"
              class="bi bi-exclamation-triangle text-warning protected-entity-icon"
              :title="getProtectedEntityTooltip()"
            ></i>
          </template>
          <template v-else>
            Entity Studio
          </template>
        </h2>
        <button
          v-if="selectedEntity"
          class="btn btn-outline-secondary"
          @click="goBack"
        >
          <i class="bi bi-arrow-left me-2"></i>
          Back to Entities
        </button>
      </div>

      <!-- Main Panel: Entity List -->
      <div v-if="!selectedEntity">
        <!-- Studio Actions Section -->
        <div class="row g-4 mb-4">
          <!-- New Entity Card -->
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div
              class="card h-100 shadow-sm entity-card clickable"
              @click="openNewEntity"
            >
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
                <img
                  :src="getIconPath('bi-plus-square-dotted')"
                  alt="New Entity"
                  class="entity-icon mb-3"
                />
                <h5 class="card-title mb-0">New Entity</h5>
              </div>
            </div>
          </div>

          <!-- Edit Navigation Card -->
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div
              class="card h-100 shadow-sm action-card clickable"
              @click="openEditNavigation"
            >
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
                <i class="bi bi-list-ul action-icon mb-3"></i>
                <h5 class="card-title mb-0">Edit Navigation</h5>
              </div>
            </div>
          </div>

          <!-- New Relationship Card -->
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div
              class="card h-100 shadow-sm action-card clickable"
              @click="openNewRelationship"
            >
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
                <i class="bi bi-plus-circle action-icon mb-3"></i>
                <h5 class="card-title mb-0">New Relationship</h5>
              </div>
            </div>
          </div>

          <!-- Relationship Editor Card -->
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div
              class="card h-100 shadow-sm action-card clickable"
              @click="openRelationshipEditorUnfiltered"
            >
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
                <i class="bi bi-diagram-3 action-icon mb-3"></i>
                <h5 class="card-title mb-0">Relationship Editor</h5>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-4" />

        <!-- Entity Cards -->
        <div class="row g-4">
          <div
            v-for="entity in allEntities"
            :key="entity.name"
            class="col-md-6 col-lg-4 col-xl-3"
          >
            <div
              :class="[
                'card h-100 shadow-sm entity-card clickable',
                { 'protected-entity-card': isProtectedEntity(entity.name) }
              ]"
              @click="selectEntity(entity)"
            >
              <div class="card-body p-0 d-flex flex-column">
                <div class="entity-icon-container">
                  <img
                    :src="getIconPath(entity.icon || 'bi-box')"
                    :alt="entity.displayName || entity.name"
                    class="entity-icon"
                  />
                </div>
                <div class="p-3">
                  <h5 class="card-title mb-0 text-center d-flex align-items-center justify-content-center gap-2">
                    <span>{{ entity.displayName || formatEntityName(entity.name) }}</span>
                    <i
                      v-if="isProtectedEntity(entity.name)"
                      class="bi bi-exclamation-triangle text-warning protected-entity-icon"
                      :title="getProtectedEntityTooltip()"
                    ></i>
                  </h5>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Layout Editor -->
      <LayoutEditor
        v-if="showLayoutEditor && selectedEntity"
        :entity="selectedEntity"
        @close="closeLayoutEditor"
      />

      <!-- Field Editor -->
      <FieldEditor
        v-if="showFieldEditor && selectedEntity"
        :entity="selectedEntity"
        @close="closeFieldEditor"
      />

      <!-- Entity Actions Panel -->
      <div v-if="selectedEntity && !showLayoutEditor && !showFieldEditor" class="row g-4">
        <!-- Field Editor Card -->
        <div class="col-md-6 col-lg-4 col-xl-3">
          <div
            class="card h-100 shadow-sm action-card clickable"
            @click="openFieldEditor"
          >
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
              <i class="bi bi-pencil-square action-icon mb-3"></i>
              <h5 class="card-title mb-0">Field Editor</h5>
            </div>
          </div>
        </div>

        <!-- Relationship Editor Card -->
        <div class="col-md-6 col-lg-4 col-xl-3">
          <div
            class="card h-100 shadow-sm action-card clickable"
            @click="openRelationshipEditor"
          >
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
              <i class="bi bi-diagram-3 action-icon mb-3"></i>
              <h5 class="card-title mb-0">Relationship Editor</h5>
            </div>
          </div>
        </div>

        <!-- Layout Editor Card -->
        <div class="col-md-6 col-lg-4 col-xl-3">
          <div
            class="card h-100 shadow-sm action-card clickable"
            @click="openLayoutEditor"
          >
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
              <i class="bi bi-grid-3x3-gap action-icon mb-3"></i>
              <h5 class="card-title mb-0">Layout Editor</h5>
            </div>
          </div>
        </div>

        <!-- Delete Entity Card -->
        <div class="col-md-6 col-lg-4 col-xl-3">
          <div
            :class="[
              'card h-100 shadow-sm action-card',
              isEntityProtected ? 'disabled-card' : 'clickable'
            ]"
            @click="handleDeleteClick"
          >
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
              <i
                :class="[
                  'bi bi-trash action-icon mb-3',
                  isEntityProtected ? 'text-muted' : 'text-danger'
                ]"
              ></i>
              <h5 class="card-title mb-0">Delete Entity</h5>
              <small v-if="isEntityProtected" class="text-muted mt-2">
                System entities cannot be deleted
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Editor Modal -->
    <NavigationEditorModal
      v-model="showNavigationModal"
      @saved="handleNavigationSaved"
    />

    <!-- Create Entity Modal -->
    <CreateEntityModal
      v-model="showCreateEntityModal"
      @saved="handleEntityCreated"
    />

    <!-- Create Relationship Modal -->
    <CreateRelationshipModal
      v-model="showCreateRelationshipModal"
      @saved="handleRelationshipCreated"
    />

    <!-- Relationship Editor Modal -->
    <RelationshipEditorModal
      v-model="showRelationshipEditorModal"
      :filter-entity="relationshipEditorFilterEntity"
      @saved="handleRelationshipSaved"
    />

    <!-- Icon Selector -->
    <IconSelector
      v-model="showIconSelector"
      :selected-icon="selectedEntity?.icon || ''"
      @select="handleIconSelect"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import { getIconPath } from '../utils/iconUtils'
import NavigationEditorModal from '../components/NavigationEditorModal.vue'
import CreateEntityModal from '../components/CreateEntityModal.vue'
import CreateRelationshipModal from '../components/CreateRelationshipModal.vue'
import RelationshipEditorModal from '../components/RelationshipEditorModal.vue'
import LayoutEditor from '../components/LayoutEditor.vue'
import FieldEditor from '../components/FieldEditor.vue'
import IconSelector from '../components/IconSelector.vue'
import entityService from '../services/entityService'
import api from '../services/api'

const metadataStore = useMetadataStore()
const toastStore = useToastStore()
const selectedEntity = ref(null)
const showNavigationModal = ref(false)
const showCreateEntityModal = ref(false)
const showCreateRelationshipModal = ref(false)
const showRelationshipEditorModal = ref(false)
const showLayoutEditor = ref(false)
const showFieldEditor = ref(false)
const showIconSelector = ref(false)
const relationshipEditorFilterEntity = ref(null)

// Protected entities that cannot be deleted or have fields removed
// TODO: Block delete and field removal functions in UI for protected entities (backend will also enforce this)
const PROTECTED_ENTITIES = ['users', 'tokens', 'rawendpointdata', 'modulebuilder', 'endpoints']

const allEntities = computed(() => {
  const entities = metadataStore.entities || {}
  const entityList = Object.keys(entities).map(entityName => ({
    name: entityName,
    displayName: metadataStore.formatEntityName(entityName),
    ...entities[entityName]
  }))
  // Reverse order to show custom modules (which come last in metadata) first
  return [...entityList].reverse()
})

function formatEntityName(name) {
  return metadataStore.formatEntityName(name)
}

function isProtectedEntity(entityName) {
  return PROTECTED_ENTITIES.includes(entityName.toLowerCase())
}

const isEntityProtected = computed(() => {
  return selectedEntity.value ? isProtectedEntity(selectedEntity.value.name) : false
})

function getProtectedEntityTooltip() {
  return 'This is a system module that requires careful consideration. Deletion and field removal are restricted to maintain system integrity.'
}

function selectEntity(entity) {
  selectedEntity.value = entity
}

function goBack() {
  selectedEntity.value = null
}

// Dummy functions for editors/creators
function openNewEntity() {
  showCreateEntityModal.value = true
}

function handleEntityCreated() {
  // Metadata will be refreshed by the modal
}

function openEditNavigation() {
  showNavigationModal.value = true
}

function handleNavigationSaved() {
  // Metadata will be refreshed by the modal
}

function openFieldEditor() {
  if (selectedEntity.value) {
    showFieldEditor.value = true
  }
}

function closeFieldEditor() {
  showFieldEditor.value = false
}

function openRelationshipEditor() {
  if (selectedEntity.value) {
    relationshipEditorFilterEntity.value = selectedEntity.value.name
    showRelationshipEditorModal.value = true
  }
}

function openRelationshipEditorUnfiltered() {
  relationshipEditorFilterEntity.value = null
  showRelationshipEditorModal.value = true
}

function handleRelationshipSaved() {
  // Metadata will be refreshed by the modal
}

function openLayoutEditor() {
  if (selectedEntity.value) {
    showLayoutEditor.value = true
  }
}

function closeLayoutEditor() {
  showLayoutEditor.value = false
}

function openNewRelationship() {
  showCreateRelationshipModal.value = true
}

function handleRelationshipCreated() {
  // Metadata will be refreshed by the modal
}

async function handleIconSelect(iconName) {
  if (!selectedEntity.value) return

  try {
    const response = await api.post('/modulebuilder/updateIcon', {
      entity: selectedEntity.value.name,
      icon: iconName
    })

    if (response.data) {
      toastStore.success('Icon updated successfully!')
      
      // Refresh metadata
      await metadataStore.fetchMetadata()
      
      // Update selected entity with new icon
      const updatedEntity = metadataStore.getEntityMetadata(selectedEntity.value.name)
      if (updatedEntity) {
        selectedEntity.value = {
          ...selectedEntity.value,
          icon: iconName,
          ...updatedEntity
        }
      }
    }
  } catch (error) {
    console.error('Error updating icon:', error)
    toastStore.error(error.response?.data?.message || 'Failed to update icon')
  }
}

function handleDeleteClick() {
  if (!isEntityProtected.value) {
    openDeleteEntity()
  }
}

async function openDeleteEntity() {
  if (!selectedEntity.value) return

  const entityName = selectedEntity.value.name
  const displayName = selectedEntity.value.displayName || formatEntityName(entityName)

  try {
    // Get record count
    const countResponse = await entityService.getCount(entityName)
    const recordCount = countResponse.count || 0

    // Show confirmation with record count
    const message = `Are you sure you want to delete "${displayName}"?\n\n` +
      `Currently has ${recordCount} record(s). Make sure to back them up. The table will be deleted as well.\n\n` +
      `This action cannot be undone.`

    if (!confirm(message)) {
      return
    }

    // Delete the entity
    const response = await api.post('/modulebuilder/deleteEntity', {
      entity: entityName
    })

    if (response.data) {
      toastStore.success(`${displayName} deleted successfully!`)
      
      // Refresh metadata
      await metadataStore.fetchMetadata()
      
      // Return to root
      selectedEntity.value = null
    }
  } catch (error) {
    console.error('Error deleting entity:', error)
    toastStore.error(error.response?.data?.message || `Failed to delete ${displayName}`)
  }
}

// Watch for selectedEntity changes and close editors
watch(selectedEntity, (next, prev) => {
  if (next?.name !== prev?.name) {
    showLayoutEditor.value = false
    showFieldEditor.value = false
  }
})

watch(
  () => metadataStore.metadata,
  () => {
    if (!selectedEntity.value) {
      return
    }

    const updatedEntity = metadataStore.getEntityMetadata(selectedEntity.value.name)
    if (updatedEntity) {
      Object.assign(selectedEntity.value, updatedEntity, {
        displayName: metadataStore.formatEntityName(selectedEntity.value.name)
      })
    }
  }
)

onMounted(async () => {
  if (!metadataStore.metadata) {
    await metadataStore.fetchMetadata()
  }
})
</script>

<style scoped>
.entity-studio-container {
  max-width: 1400px;
  margin: 0 auto;
}

.entity-card,
.action-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}

.entity-card:hover,
.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
}

.clickable {
  cursor: pointer;
}

.entity-icon-container {
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 0.375rem 0.375rem 0 0;
}

.entity-icon {
  height: 100%;
  max-width: 100%;
  object-fit: contain;
  padding: 1rem;
}

.action-icon {
  font-size: 4rem;
  color: #6c757d;
}

.card-title {
  color: #333;
  font-weight: 600;
}

.disabled-card {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.entity-header-icon {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.entity-icon-display {
  padding: 0.25rem 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  cursor: default;
}

.protected-entity-icon {
  font-size: 1.2rem;
  cursor: help;
}

.protected-entity-card {
  opacity: 0.25;
}

.icon-edit-btn {
  padding: 0.25rem 0.5rem;
  line-height: 1;
  opacity: 0.7;
  transition: opacity 0.2s ease;
}

.icon-edit-btn:hover {
  opacity: 1;
}
</style>
