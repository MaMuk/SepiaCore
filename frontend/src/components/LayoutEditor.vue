<template>
  <div class="layout-editor-container">
    <div class="container-fluid px-0">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="d-flex align-items-center gap-2 mb-0">
          <span>{{ entityDisplayName }} - Layout Editor</span>
        </h2>
        <button
          class="btn btn-outline-secondary"
          @click="goBack"
        >
          <i class="bi bi-arrow-left me-2"></i>
          Back to Entity
        </button>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'record' }"
            @click="activeTab = 'record'"
            type="button"
          >
            Record Layout
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'list' }"
            @click="activeTab = 'list'"
            type="button"
          >
            List Layout
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            :class="{ active: activeTab === 'subpanels' }"
            @click="activeTab = 'subpanels'"
            type="button"
          >
            Subpanel Layouts
          </button>
        </li>
      </ul>

      <!-- Content -->
      <div class="row g-4">
        <!-- Available Fields Panel -->
        <div class="col-md-3">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0">Available Fields</h5>
            </div>
            <div class="card-body">
              <!-- Subpanel selector (only shown in subpanels tab) -->
              <div v-if="activeTab === 'subpanels'" class="mb-3">
                <label class="form-label small text-muted">Select Subpanel:</label>
                <select
                  v-model="selectedSubpanelKey"
                  class="form-select form-select-sm"
                  @change="onSubpanelSelectionChange"
                >
                  <option value="">-- Choose a subpanel --</option>
                  <option
                    v-for="(subpanel, key) in subpanelLayouts"
                    :key="key"
                    :value="key"
                  >
                    {{ formatEntityName(subpanel.entity) }} ({{ subpanel.rel_type }})
                  </option>
                </select>
                <div v-if="selectedSubpanelKey && selectedSubpanelEntity" class="mt-2">
                  <small class="text-muted">
                    Editing fields for: <strong>{{ formatEntityName(selectedSubpanelEntity) }}</strong>
                  </small>
                </div>
              </div>
              <div class="available-fields">
                <div
                  v-for="field in availableFields"
                  :key="field.name"
                  class="field-item"
                  :draggable="true"
                  @dragstart="handleDragStart($event, field.name)"
                  @dragend="handleDragEnd"
                >
                  <i class="bi bi-grip-vertical me-2"></i>
                  <span>{{ formatFieldName(field.name) }}</span>
                </div>
                <div v-if="availableFields.length === 0" class="text-muted text-center p-3">
                  <span v-if="activeTab === 'subpanels' && !selectedSubpanelKey">
                    Select a subpanel above to edit its fields
                  </span>
                  <span v-else>
                    No fields available
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Layout Editor Panel -->
        <div class="col-md-9">
          <!-- Record Layout -->
          <div v-if="activeTab === 'record'" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Record Layout (Rows & Columns)</h5>
              <button class="btn btn-sm btn-primary" @click="addRecordRow">
                <i class="bi bi-plus-circle me-1"></i>Add Row
              </button>
            </div>
            <div class="card-body">
              <div
                v-for="(row, rowIndex) in recordLayout"
                :key="rowIndex"
                class="record-row mb-3 p-3 border rounded"
                @dragover.prevent
                @drop="handleDropRecord($event, rowIndex)"
              >
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div 
                    class="d-flex align-items-center row-drag-handle"
                    :draggable="true"
                    @dragstart="handleDragStartRow($event, rowIndex)"
                    @dragend="handleDragEnd"
                  >
                    <i class="bi bi-grip-vertical me-2 text-muted"></i>
                    <small class="text-muted">Row {{ rowIndex + 1 }}</small>
                  </div>
                  <button
                    class="btn btn-sm btn-danger"
                    @click="removeRecordRow(rowIndex)"
                    :disabled="recordLayout.length === 1"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
                <div class="row g-2">
                  <div
                    v-for="(field, colIndex) in row"
                    :key="colIndex"
                    class="col-md-6"
                  >
                    <div
                      class="field-badge draggable-field"
                      :draggable="true"
                      @dragstart="handleDragStartRecordField($event, rowIndex, colIndex, field)"
                      @dragend="handleDragEnd"
                      @dragover.prevent
                      @drop="handleDropRecordField($event, rowIndex, colIndex)"
                    >
                      <i class="bi bi-grip-vertical me-2 text-muted"></i>
                      <span>{{ formatFieldName(field) }}</span>
                      <button
                        class="btn btn-sm btn-link text-danger p-0 ms-2"
                        @click="removeRecordField(rowIndex, colIndex)"
                      >
                        <i class="bi bi-x"></i>
                      </button>
                    </div>
                  </div>
                  <div
                    class="col-md-6"
                    @dragover.prevent
                    @drop="handleDropRecord($event, rowIndex)"
                  >
                    <div class="drop-zone">
                      <i class="bi bi-plus-circle"></i>
                      <small>Drop field here</small>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-if="recordLayout.length === 0"
                class="empty-layout p-5 text-center border rounded"
                @dragover.prevent
                @drop="handleDropRecord($event, 0)"
              >
                <p class="text-muted">Drop fields here to create your first row</p>
              </div>
            </div>
          </div>

          <!-- List Layout -->
          <div v-if="activeTab === 'list'" class="card">
            <div class="card-header">
              <h5 class="mb-0">List Layout</h5>
            </div>
            <div class="card-body">
              <div
                class="list-layout"
                @dragover.prevent
                @drop="handleDropList"
              >
                <div
                  v-for="(field, index) in listLayout"
                  :key="index"
                  class="list-field-item mb-2"
                  :draggable="true"
                  @dragstart="handleDragStartListField($event, index, field)"
                  @dragend="handleDragEnd"
                  @dragover.prevent
                  @drop="handleDropListField($event, index)"
                >
                  <div class="d-flex align-items-center justify-content-between p-2 border rounded">
                    <div class="d-flex align-items-center">
                      <i class="bi bi-grip-vertical me-2 text-muted"></i>
                      <span>{{ formatFieldName(field) }}</span>
                    </div>
                    <button
                      class="btn btn-sm btn-link text-danger p-0"
                      @click="removeListField(index)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
                <div
                  v-if="listLayout.length === 0"
                  class="empty-layout p-5 text-center border rounded"
                >
                  <p class="text-muted">Drop fields here to add them to the list</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Subpanel Layouts -->
          <div v-if="activeTab === 'subpanels'" class="card">
            <div class="card-header">
              <h5 class="mb-0">Subpanel Layouts</h5>
            </div>
            <div class="card-body">
              <div
                v-for="(subpanel, subpanelKey) in subpanelLayouts"
                :key="subpanelKey"
                class="subpanel-section mb-4 p-3 border rounded"
              >
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="mb-0">
                    {{ formatEntityName(subpanel.entity) }}
                    <small class="text-muted">({{ subpanel.rel_type }})</small>
                  </h6>
                  <button
                    class="btn btn-sm btn-danger"
                    @click="removeSubpanel(subpanelKey)"
                  >
                    <i class="bi bi-trash"></i> Remove
                  </button>
                </div>
                <div
                  class="subpanel-fields"
                  @dragover.prevent
                  @drop="handleDropSubpanel($event, subpanelKey)"
                >
                  <div
                    v-for="(field, index) in subpanel.fields"
                    :key="index"
                    class="list-field-item mb-2"
                    :draggable="true"
                    @dragstart="handleDragStartSubpanelField($event, subpanelKey, index, field)"
                    @dragend="handleDragEnd"
                    @dragover.prevent
                    @drop="handleDropSubpanelField($event, subpanelKey, index)"
                  >
                    <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-light">
                      <div class="d-flex align-items-center">
                        <i class="bi bi-grip-vertical me-2 text-muted"></i>
                        <span>{{ formatFieldName(field) }}</span>
                      </div>
                      <button
                        class="btn btn-sm btn-link text-danger p-0"
                        @click="removeSubpanelField(subpanelKey, index)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                  <div
                    v-if="subpanel.fields.length === 0"
                    class="empty-layout p-3 text-center border rounded"
                  >
                    <p class="text-muted mb-0">Drop fields here</p>
                  </div>
                </div>
              </div>
              <div
                v-if="Object.keys(subpanelLayouts).length === 0"
                class="text-muted text-center p-5"
              >
                <p>No subpanels configured. Create relationships first to add subpanels.</p>
              </div>
            </div>
          </div>

          <!-- Save Button -->
          <div class="mt-4">
            <button
              class="btn btn-success"
              @click="saveLayout"
              :disabled="saving"
            >
              <i class="bi bi-save me-2"></i>
              {{ saving ? 'Saving...' : 'Save Layout' }}
            </button>
            <button
              class="btn btn-outline-secondary ms-2"
              @click="resetLayout"
            >
              <i class="bi bi-arrow-counterclockwise me-2"></i>
              Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import { getIconPath } from '../utils/iconUtils'
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

const entityName = computed(() => props.entity.name)
const activeTab = ref('record')
const saving = ref(false)
const draggedField = ref(null)
const selectedSubpanelKey = ref(null)

// Drag context for reordering
const dragContext = ref(null) // { type: 'field'|'row', source: {...}, fieldName: string, rowIndex: number, colIndex: number, subpanelKey: string }

// Layout data
const recordLayout = ref([])
const listLayout = ref([])
const subpanelLayouts = ref({})

// Original layout data for comparison
const originalRecordLayout = ref([])
const originalListLayout = ref([])
const originalSubpanelLayouts = ref({})

// Computed
const entityData = computed(() => {
  return metadataStore.getEntityMetadata(entityName.value)
})

const entityDisplayName = computed(() => {
  return entityData.value?.displayName || metadataStore.formatEntityName(entityName.value)
})

const selectedSubpanelEntity = computed(() => {
  if (!selectedSubpanelKey.value || !subpanelLayouts.value[selectedSubpanelKey.value]) {
    return null
  }
  return subpanelLayouts.value[selectedSubpanelKey.value].entity
})

const availableFields = computed(() => {
  // For subpanels tab, show fields from the selected subpanel's entity
  if (activeTab.value === 'subpanels') {
    if (!selectedSubpanelKey.value || !selectedSubpanelEntity.value) {
      return []
    }
    
    // Get fields from the related entity (not the parent entity)
    const relatedEntityData = metadataStore.getEntityMetadata(selectedSubpanelEntity.value)
    if (!relatedEntityData?.fields) return []
    
    const fields = Object.keys(relatedEntityData.fields).map(name => ({
      name,
      ...relatedEntityData.fields[name]
    }))
    
    // Get fields already in the selected subpanel
    const selectedSubpanel = subpanelLayouts.value[selectedSubpanelKey.value]
    const usedFields = new Set(selectedSubpanel?.fields || [])
    
    // Filter out already used fields
    return fields.filter(field => !usedFields.has(field.name))
  }
  
  // For record and list tabs, show fields from the parent entity
  if (!entityData.value?.fields) return []
  const fields = Object.keys(entityData.value.fields).map(name => ({
    name,
    ...entityData.value.fields[name]
  }))
  
  // Get fields already in layouts
  const usedFields = new Set()
  recordLayout.value.forEach(row => row.forEach(field => usedFields.add(field)))
  listLayout.value.forEach(field => usedFields.add(field))
  
  // Filter out used fields (or show all - user can decide)
  return fields
})

// Methods
function formatFieldName(name) {
  return name
    .split(/[-_]/)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function formatEntityName(name) {
  return metadataStore.formatEntityName(name)
}

function loadLayouts() {
  if (!entityData.value?.module_views) {
    recordLayout.value = []
    listLayout.value = []
    subpanelLayouts.value = {}
    originalRecordLayout.value = []
    originalListLayout.value = []
    originalSubpanelLayouts.value = {}
    return
  }

  const views = entityData.value.module_views

  // Load record layout
  if (views.record?.layout) {
    recordLayout.value = views.record.layout.map(row => [...row])
    originalRecordLayout.value = JSON.parse(JSON.stringify(views.record.layout))
  } else {
    recordLayout.value = []
    originalRecordLayout.value = []
  }

  // Load list layout
  if (views.list?.layout) {
    listLayout.value = [...views.list.layout]
    originalListLayout.value = JSON.parse(JSON.stringify(views.list.layout))
  } else {
    listLayout.value = []
    originalListLayout.value = []
  }

  // Load subpanel layouts
  if (views.subpanels) {
    subpanelLayouts.value = {}
    originalSubpanelLayouts.value = {}
    Object.keys(views.subpanels).forEach(key => {
      subpanelLayouts.value[key] = {
        ...views.subpanels[key],
        fields: [...views.subpanels[key].fields]
      }
      originalSubpanelLayouts.value[key] = JSON.parse(JSON.stringify(views.subpanels[key]))
    })
  } else {
    subpanelLayouts.value = {}
    originalSubpanelLayouts.value = {}
  }
}

// Drag and Drop Handlers
function handleDragStart(event, fieldName) {
  // Dragging a new field from available fields
  draggedField.value = fieldName
  dragContext.value = null
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
}

function handleDragStartRow(event, rowIndex) {
  // Dragging a row to reorder
  dragContext.value = {
    type: 'row',
    rowIndex: rowIndex
  }
  draggedField.value = null
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
}

function handleDragStartRecordField(event, rowIndex, colIndex, fieldName) {
  // Dragging an existing field in record layout
  dragContext.value = {
    type: 'field',
    source: 'record',
    rowIndex: rowIndex,
    colIndex: colIndex,
    fieldName: fieldName
  }
  draggedField.value = null
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
  event.stopPropagation() // Prevent row drag from triggering
}

function handleDragStartListField(event, index, fieldName) {
  // Dragging an existing field in list layout
  dragContext.value = {
    type: 'field',
    source: 'list',
    index: index,
    fieldName: fieldName
  }
  draggedField.value = null
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
}

function handleDragStartSubpanelField(event, subpanelKey, index, fieldName) {
  // Dragging an existing field in subpanel layout
  dragContext.value = {
    type: 'field',
    source: 'subpanel',
    subpanelKey: subpanelKey,
    index: index,
    fieldName: fieldName
  }
  draggedField.value = null
  event.dataTransfer.effectAllowed = 'move'
  event.target.style.opacity = '0.5'
}

function handleDragEnd(event) {
  event.target.style.opacity = '1'
  draggedField.value = null
  dragContext.value = null
}

// Record Layout Handlers
function handleDropRecord(event, rowIndex) {
  event.preventDefault()
  
  // Prioritize field reordering if a field is being dragged
  // Handle field reordering from another position
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'record') {
    const sourceRowIndex = dragContext.value.rowIndex
    const sourceColIndex = dragContext.value.colIndex
    const fieldName = dragContext.value.fieldName
    
    if (sourceRowIndex !== rowIndex || sourceColIndex !== undefined) {
      // Remove from source position
      if (recordLayout.value[sourceRowIndex]) {
        recordLayout.value[sourceRowIndex].splice(sourceColIndex, 1)
        // Remove empty rows
        if (recordLayout.value[sourceRowIndex].length === 0 && recordLayout.value.length > 1) {
          recordLayout.value.splice(sourceRowIndex, 1)
          // Adjust target row index if source was before target
          if (sourceRowIndex < rowIndex) {
            rowIndex--
          }
        }
      }
      
      // Add to target row
      if (!recordLayout.value[rowIndex]) {
        recordLayout.value[rowIndex] = []
      }
      if (!recordLayout.value[rowIndex].includes(fieldName)) {
        recordLayout.value[rowIndex].push(fieldName)
      }
    }
    dragContext.value = null
    return
  }
  
  // Handle new field from available fields
  if (draggedField.value) {
    // If row doesn't exist, create it
    if (!recordLayout.value[rowIndex]) {
      recordLayout.value[rowIndex] = []
    }

    // Add field to row if not already present
    if (!recordLayout.value[rowIndex].includes(draggedField.value)) {
      recordLayout.value[rowIndex].push(draggedField.value)
    }

    draggedField.value = null
    return
  }
  
  // Handle row reordering (only if no field is being dragged)
  if (dragContext.value?.type === 'row') {
    const sourceRowIndex = dragContext.value.rowIndex
    if (sourceRowIndex !== rowIndex && recordLayout.value[sourceRowIndex]) {
      const row = recordLayout.value.splice(sourceRowIndex, 1)[0]
      recordLayout.value.splice(rowIndex, 0, row)
    }
    dragContext.value = null
  }
}

function handleDropRecordField(event, rowIndex, colIndex) {
  event.preventDefault()
  
  // Handle new field from available fields
  if (draggedField.value) {
    // Replace field at this position
    if (recordLayout.value[rowIndex]) {
      recordLayout.value[rowIndex][colIndex] = draggedField.value
    }
    draggedField.value = null
    return
  }
  
  // Handle field reordering
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'record') {
    const sourceRowIndex = dragContext.value.rowIndex
    const sourceColIndex = dragContext.value.colIndex
    const fieldName = dragContext.value.fieldName
    
    if (sourceRowIndex === rowIndex && sourceColIndex === colIndex) {
      // Same position, do nothing
      dragContext.value = null
      return
    }
    
    // Remove from source position
    if (recordLayout.value[sourceRowIndex]) {
      recordLayout.value[sourceRowIndex].splice(sourceColIndex, 1)
    }
    
    // Insert at target position
    if (!recordLayout.value[rowIndex]) {
      recordLayout.value[rowIndex] = []
    }
    
    // Adjust target column index if moving within same row and source was before target
    let targetColIndex = colIndex
    if (sourceRowIndex === rowIndex && sourceColIndex < colIndex) {
      targetColIndex--
    }
    
    recordLayout.value[rowIndex].splice(targetColIndex, 0, fieldName)
    dragContext.value = null
  }
}

function addRecordRow() {
  recordLayout.value.push([])
}

function removeRecordRow(rowIndex) {
  if (recordLayout.value.length > 1) {
    recordLayout.value.splice(rowIndex, 1)
  }
}

function removeRecordField(rowIndex, colIndex) {
  if (recordLayout.value[rowIndex]) {
    recordLayout.value[rowIndex].splice(colIndex, 1)
    // Remove empty rows
    if (recordLayout.value[rowIndex].length === 0 && recordLayout.value.length > 1) {
      recordLayout.value.splice(rowIndex, 1)
    }
  }
}

// List Layout Handlers
function handleDropList(event) {
  event.preventDefault()
  
  // Handle new field from available fields
  if (draggedField.value) {
    if (!listLayout.value.includes(draggedField.value)) {
      listLayout.value.push(draggedField.value)
    }
    draggedField.value = null
    return
  }
  
  // Handle field reordering - add to end if dropped on empty area
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'list') {
    const sourceIndex = dragContext.value.index
    const fieldName = dragContext.value.fieldName
    
    // Remove from source
    listLayout.value.splice(sourceIndex, 1)
    // Add to end
    listLayout.value.push(fieldName)
    dragContext.value = null
  }
}

function handleDropListField(event, targetIndex) {
  event.preventDefault()
  
  // Handle new field from available fields
  if (draggedField.value) {
    if (!listLayout.value.includes(draggedField.value)) {
      listLayout.value.splice(targetIndex, 0, draggedField.value)
    }
    draggedField.value = null
    return
  }
  
  // Handle field reordering
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'list') {
    const sourceIndex = dragContext.value.index
    const fieldName = dragContext.value.fieldName
    
    if (sourceIndex === targetIndex) {
      // Same position, do nothing
      dragContext.value = null
      return
    }
    
    // Remove from source
    listLayout.value.splice(sourceIndex, 1)
    
    // Adjust target index if source was before target
    let adjustedTargetIndex = targetIndex
    if (sourceIndex < targetIndex) {
      adjustedTargetIndex--
    }
    
    // Insert at target position
    listLayout.value.splice(adjustedTargetIndex, 0, fieldName)
    dragContext.value = null
  }
}

function removeListField(index) {
  listLayout.value.splice(index, 1)
}

// Subpanel Layout Handlers
function handleDropSubpanel(event, subpanelKey) {
  event.preventDefault()
  
  // Only allow dropping if this is the selected subpanel (for new fields)
  if (draggedField.value && selectedSubpanelKey.value !== subpanelKey) {
    return
  }
  
  // Only allow reordering within the same subpanel
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'subpanel') {
    if (dragContext.value.subpanelKey !== subpanelKey) {
      return
    }
  }

  if (!subpanelLayouts.value[subpanelKey]) return

  // Handle new field from available fields
  if (draggedField.value) {
    if (!subpanelLayouts.value[subpanelKey].fields.includes(draggedField.value)) {
      subpanelLayouts.value[subpanelKey].fields.push(draggedField.value)
    }
    draggedField.value = null
    return
  }
  
  // Handle field reordering - add to end if dropped on empty area
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'subpanel') {
    const sourceIndex = dragContext.value.index
    const fieldName = dragContext.value.fieldName
    
    // Remove from source
    subpanelLayouts.value[subpanelKey].fields.splice(sourceIndex, 1)
    // Add to end
    subpanelLayouts.value[subpanelKey].fields.push(fieldName)
    dragContext.value = null
  }
}

function handleDropSubpanelField(event, subpanelKey, targetIndex) {
  event.preventDefault()
  
  // Only allow dropping if this is the selected subpanel (for new fields)
  if (draggedField.value && selectedSubpanelKey.value !== subpanelKey) {
    return
  }
  
  // Only allow reordering within the same subpanel
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'subpanel') {
    if (dragContext.value.subpanelKey !== subpanelKey) {
      return
    }
  }

  if (!subpanelLayouts.value[subpanelKey]) return

  // Handle new field from available fields
  if (draggedField.value) {
    if (!subpanelLayouts.value[subpanelKey].fields.includes(draggedField.value)) {
      subpanelLayouts.value[subpanelKey].fields.splice(targetIndex, 0, draggedField.value)
    }
    draggedField.value = null
    return
  }
  
  // Handle field reordering
  if (dragContext.value?.type === 'field' && dragContext.value.source === 'subpanel') {
    const sourceIndex = dragContext.value.index
    const fieldName = dragContext.value.fieldName
    
    if (sourceIndex === targetIndex) {
      // Same position, do nothing
      dragContext.value = null
      return
    }
    
    // Remove from source
    subpanelLayouts.value[subpanelKey].fields.splice(sourceIndex, 1)
    
    // Adjust target index if source was before target
    let adjustedTargetIndex = targetIndex
    if (sourceIndex < targetIndex) {
      adjustedTargetIndex--
    }
    
    // Insert at target position
    subpanelLayouts.value[subpanelKey].fields.splice(adjustedTargetIndex, 0, fieldName)
    dragContext.value = null
  }
}

function removeSubpanelField(subpanelKey, index) {
  if (subpanelLayouts.value[subpanelKey]) {
    subpanelLayouts.value[subpanelKey].fields.splice(index, 1)
  }
}

function removeSubpanel(subpanelKey) {
  delete subpanelLayouts.value[subpanelKey]
  // Clear selection if the removed subpanel was selected
  if (selectedSubpanelKey.value === subpanelKey) {
    selectedSubpanelKey.value = null
  }
}

function onSubpanelSelectionChange() {
  // Reset dragged field when switching subpanels
  draggedField.value = null
}

// Helper function to deep compare layouts
function layoutsEqual(layout1, layout2) {
  return JSON.stringify(layout1) === JSON.stringify(layout2)
}

// Helper function to check if subpanels have changed
function subpanelsChanged(current, original) {
  const currentKeys = Object.keys(current)
  const originalKeys = Object.keys(original)
  
  // Check if keys are different
  if (currentKeys.length !== originalKeys.length) {
    return true
  }
  
  // Check if any key is missing or different
  for (const key of currentKeys) {
    if (!originalKeys.includes(key)) {
      return true
    }
    // Compare fields array for this subpanel
    if (!layoutsEqual(current[key].fields, original[key]?.fields)) {
      return true
    }
  }
  
  // Check if any original key was removed
  for (const key of originalKeys) {
    if (!currentKeys.includes(key)) {
      return true
    }
  }
  
  return false
}

// Save and Reset
async function saveLayout() {
  if (!entityName.value) return

  saving.value = true
  try {
    const filteredRecordLayout = recordLayout.value.filter(row => row.length > 0)
    const recordChanged = !layoutsEqual(filteredRecordLayout, originalRecordLayout.value)
    
    // Save record layout only if changed
    if (recordChanged) {
      const recordViewDef = {
        layout: filteredRecordLayout
      }
      await api.post('/modulebuilder/updateView', {
        entity: entityName.value,
        view: 'record',
        viewDef: recordViewDef
      })
    }

    // Save list layout only if changed
    const listChanged = !layoutsEqual(listLayout.value, originalListLayout.value)
    if (listChanged) {
      const listViewDef = {
        isdefault: entityData.value?.module_views?.list?.isdefault || false,
        layout: listLayout.value
      }
      await api.post('/modulebuilder/updateView', {
        entity: entityName.value,
        view: 'list',
        viewDef: listViewDef
      })
    }

    // Save subpanels only if changed
    const subpanelsHaveChanged = subpanelsChanged(subpanelLayouts.value, originalSubpanelLayouts.value)
    if (subpanelsHaveChanged) {
      const subpanelsToSave = {}
      if (Object.keys(subpanelLayouts.value).length > 0) {
        Object.keys(subpanelLayouts.value).forEach(key => {
          const subpanel = subpanelLayouts.value[key]
          subpanelsToSave[key] = {
            entity: subpanel.entity,
            fields: subpanel.fields,
            rel_type: subpanel.rel_type,
            rel_table: subpanel.rel_table,
            rel_name: subpanel.rel_name,
            rel_field: subpanel.rel_field
          }
        })
      }

      await api.post('/modulebuilder/updateView', {
        entity: entityName.value,
        view: 'subpanels',
        viewDef: subpanelsToSave
      })
    }

    // Only refresh metadata if something was actually saved
    if (recordChanged || listChanged || subpanelsHaveChanged) {
      // Refresh metadata
      await metadataStore.fetchMetadata()
      
      // Update original layouts to reflect saved state
      if (recordChanged) {
        originalRecordLayout.value = JSON.parse(JSON.stringify(filteredRecordLayout))
      }
      if (listChanged) {
        originalListLayout.value = JSON.parse(JSON.stringify(listLayout.value))
      }
      if (subpanelsHaveChanged) {
        originalSubpanelLayouts.value = JSON.parse(JSON.stringify(subpanelLayouts.value))
      }
      
      toastStore.success('Layout saved successfully!')
    } else {
      toastStore.info('No changes to save')
    }
  } catch (error) {
    console.error('Error saving layout:', error)
    toastStore.error(error.response?.data?.message || 'Failed to save layout')
  } finally {
    saving.value = false
  }
}

function resetLayout() {
  if (confirm('Are you sure you want to reset all changes?')) {
    loadLayouts()
  }
}

function goBack() {
  emit('close')
}

// Watch for entity data changes
watch(() => entityData.value, () => {
  loadLayouts()
}, { deep: true })

watch(() => props.entity, () => {
  loadLayouts()
}, { deep: true })

// Reset subpanel selection when switching tabs
watch(activeTab, (newTab) => {
  if (newTab !== 'subpanels') {
    selectedSubpanelKey.value = null
  }
})

onMounted(async () => {
  if (!metadataStore.metadata) {
    await metadataStore.fetchMetadata()
  }
  loadLayouts()
})
</script>

<style scoped>
.layout-editor-container {
  max-width: 1600px;
  margin: 0 auto;
}

.entity-header-icon {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.available-fields {
  max-height: 600px;
  overflow-y: auto;
}

.field-item {
  padding: 0.5rem;
  margin-bottom: 0.5rem;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  cursor: move;
  user-select: none;
  transition: all 0.2s;
}

.field-item:hover {
  background: #e9ecef;
  transform: translateX(4px);
}

.field-item:active {
  opacity: 0.5;
}

.record-row {
  background: #f8f9fa;
  min-height: 100px;
  transition: all 0.2s;
}

.record-row:hover {
  background: #e9ecef;
  border-color: #0d6efd;
}

.row-drag-handle {
  cursor: move;
  user-select: none;
  transition: all 0.2s;
}

.row-drag-handle:hover {
  color: #0d6efd;
}

.row-drag-handle:active {
  opacity: 0.5;
}

.field-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  width: 100%;
}

.field-badge.draggable-field {
  cursor: move;
  transition: all 0.2s;
}

.field-badge.draggable-field:hover {
  background: #f0f7ff;
  border-color: #0d6efd;
}

.field-badge.draggable-field:active {
  opacity: 0.5;
}

.drop-zone {
  padding: 1rem;
  border: 2px dashed #dee2e6;
  border-radius: 0.25rem;
  text-align: center;
  color: #6c757d;
  min-height: 60px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.drop-zone:hover {
  border-color: #0d6efd;
  background: #f0f7ff;
}

.empty-layout {
  border: 2px dashed #dee2e6;
  background: #f8f9fa;
}

.list-layout,
.subpanel-fields {
  min-height: 200px;
}

.list-field-item {
  cursor: move;
  transition: all 0.2s;
}

.list-field-item:hover {
  background: #f8f9fa;
}

.list-field-item:active {
  opacity: 0.5;
}

.subpanel-section {
  background: #f8f9fa;
}

.nav-tabs .nav-link {
  cursor: pointer;
}

.nav-tabs .nav-link.active {
  font-weight: 600;
}
</style>

