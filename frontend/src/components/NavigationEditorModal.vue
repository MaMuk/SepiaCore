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
          <h5 class="modal-title">Configure Navigation Entities</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <div class="row mt-2">
            <div class="col-md-6">
              <h5>Available Entities</h5>
              <div
                class="entity-list"
                :class="{ 'drag-over': dragOverTarget === 'available' }"
                @dragover.prevent="handleDragOver('available', $event)"
                @drop.prevent="handleDrop('available', $event)"
                @dragleave="handleDragLeave"
              >
                <div
                  v-for="entity in availableEntities"
                  :key="entity.name"
                  class="entity-item"
                  :draggable="true"
                  @dragstart="handleDragStart($event, entity, 'available')"
                  @dragend="handleDragEnd"
                >
                  {{ entity.displayName }}
                </div>
                <div v-if="availableEntities.length === 0" class="text-muted text-center p-3">
                  No available entities
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <h5>Entities in Navigation</h5>
              <div
                class="entity-list"
                :class="{ 'drag-over': dragOverTarget === 'navigation' }"
                @dragover.prevent="handleDragOver('navigation', $event)"
                @drop.prevent="handleDrop('navigation', $event)"
                @dragleave="handleDragLeave"
              >
                <div
                  v-for="(entity, index) in navigationEntities"
                  :key="`nav-${entity.name}-${index}`"
                  class="entity-item"
                  :draggable="true"
                  @dragstart="handleDragStart($event, entity, 'navigation', index)"
                  @dragover.prevent="handleDragOverItem($event, index)"
                  @drop.prevent="handleDropOnItem($event, index)"
                  @dragend="handleDragEnd"
                >
                  {{ entity.displayName }}
                </div>
                <div v-if="navigationEntities.length === 0" class="text-muted text-center p-3">
                  No entities in navigation
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary"
            @click="saveNavigation"
            :disabled="saving"
          >
            <span
              v-if="saving"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Save Navigation
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

// Protected entities that should be excluded from navigation editor
const PROTECTED_ENTITIES = ['users', 'tokens', 'rawendpointdata', 'modulebuilder', 'endpoints']

const navigationEntities = ref([])
const availableEntities = ref([])
const saving = ref(false)
const draggedItem = ref(null)
const draggedFrom = ref(null)
const draggedIndex = ref(null)
const dragOverTarget = ref(null)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Initialize lists when modal opens
watch(isVisible, (newVal) => {
  if (newVal) {
    initializeLists()
  }
})

function initializeLists() {
  const allEntities = metadataStore.entities || {}
  const currentNavigation = metadataStore.navigationEntities || []
  
  // Get current navigation entity names
  const navigationNames = currentNavigation.map(e => e.name)
  
  // Filter available entities (not in navigation, not protected)
  const available = Object.keys(allEntities)
    .filter(entityName => 
      !navigationNames.includes(entityName) &&
      !PROTECTED_ENTITIES.includes(entityName.toLowerCase())
    )
    .map(entityName => ({
      name: entityName,
      displayName: metadataStore.formatEntityName(entityName)
    }))
  
  // Get navigation entities with display names
  const navigation = currentNavigation.map(entity => ({
    name: entity.name,
    displayName: entity.displayName || metadataStore.formatEntityName(entity.name)
  }))
  
  availableEntities.value = available
  navigationEntities.value = navigation
}

function handleDragStart(event, entity, source, index = null) {
  draggedItem.value = entity
  draggedFrom.value = source
  draggedIndex.value = index
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
}

function handleDragEnd(event) {
  event.target.style.opacity = '1'
  draggedItem.value = null
  draggedFrom.value = null
  draggedIndex.value = null
  dragOverTarget.value = null
}

function handleDragOver(target, event) {
  dragOverTarget.value = target
}

function handleDragLeave() {
  dragOverTarget.value = null
}

function handleDrop(target, event) {
  dragOverTarget.value = null
  
  if (!draggedItem.value) return
  
  const source = draggedFrom.value
  
  // Move from available to navigation
  if (source === 'available' && target === 'navigation') {
    const index = availableEntities.value.findIndex(e => e.name === draggedItem.value.name)
    if (index !== -1) {
      availableEntities.value.splice(index, 1)
      navigationEntities.value.push(draggedItem.value)
    }
  }
  // Move from navigation to available
  else if (source === 'navigation' && target === 'available') {
    const index = navigationEntities.value.findIndex(e => e.name === draggedItem.value.name)
    if (index !== -1) {
      navigationEntities.value.splice(index, 1)
      availableEntities.value.push(draggedItem.value)
    }
  }
  
  draggedItem.value = null
  draggedFrom.value = null
  draggedIndex.value = null
}

function handleDragOverItem(event, dropIndex) {
  event.dataTransfer.dropEffect = 'move'
}

function handleDropOnItem(event, dropIndex) {
  if (!draggedItem.value || draggedFrom.value !== 'navigation') return
  
  const dragIndex = draggedIndex.value
  if (dragIndex === null || dragIndex === dropIndex) return
  
  // Remove from old position
  const [movedItem] = navigationEntities.value.splice(dragIndex, 1)
  
  // Insert at new position
  if (dragIndex < dropIndex) {
    navigationEntities.value.splice(dropIndex, 0, movedItem)
  } else {
    navigationEntities.value.splice(dropIndex, 0, movedItem)
  }
  
  draggedItem.value = null
  draggedFrom.value = null
  draggedIndex.value = null
}

async function saveNavigation() {
  saving.value = true
  
  try {
    // Format navigation entities as object with numeric keys (1, 2, 3...)
    const navigationData = {}
    navigationEntities.value.forEach((entity, index) => {
      navigationData[index + 1] = entity.name
    })
    
    const response = await api.post('/modulebuilder/setNavigationEntities', navigationData)
    
    if (response.data) {
      toastStore.success('Navigation saved successfully!')
      
      // Refresh metadata to get updated navigation
      await metadataStore.fetchMetadata()
      
      emit('saved')
      close()
    }
  } catch (error) {
    console.error('Error saving navigation:', error)
    toastStore.error(error.response?.data?.message || 'Failed to save navigation')
  } finally {
    saving.value = false
  }
}

function close() {
  isVisible.value = false
  navigationEntities.value = []
  availableEntities.value = []
  draggedItem.value = null
  draggedFrom.value = null
  draggedIndex.value = null
  dragOverTarget.value = null
}

function handleBackdropClick() {
  close()
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

.entity-list {
  min-height: 300px;
  max-height: 500px;
  overflow-y: auto;
  border: 2px dashed #dee2e6;
  border-radius: 0.375rem;
  padding: 0.5rem;
  background-color: #f8f9fa;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.entity-list.drag-over {
  border-color: #0d6efd;
  background-color: #e7f1ff;
}

.entity-item {
  padding: 0.75rem 1rem;
  margin-bottom: 0.5rem;
  background-color: white;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  cursor: move;
  user-select: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.entity-item:hover {
  transform: translateX(4px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.entity-item:active {
  cursor: grabbing;
}

.entity-item:last-child {
  margin-bottom: 0;
}
</style>

