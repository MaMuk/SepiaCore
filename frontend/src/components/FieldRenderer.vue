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

      <template v-else-if="fieldDef?.type === 'file'">
        <div v-if="fileLoading" class="text-muted">Loading file...</div>
        <div v-else-if="fileMetadata" class="d-flex flex-column gap-1">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="File actions">
              <button
                v-if="isImage"
                type="button"
                class="btn btn-outline-secondary"
                @click="openPreview"
                :disabled="fetchingBlob"
                title="Preview"
                aria-label="Preview"
              >
                <i class="bi bi-eye"></i>
              </button>
              <button
                type="button"
                class="btn btn-outline-secondary"
                @click="downloadFile"
                :disabled="fetchingBlob"
                title="Download"
                aria-label="Download"
              >
                <i class="bi bi-download"></i>
              </button>
            </div>
            <span class="fw-semibold file-name" :title="displayFileName">
              {{ displayFileName }}
            </span>
          </div>
          <div v-if="fileMetadata.size" class="text-muted small">
            {{ formatFileSize(fileMetadata.size) }}
          </div>
        </div>
        <span v-else class="text-muted">-</span>
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

      <!-- File -->
      <div v-if="fieldDef?.type === 'file'" class="file-field-wrapper">
        <div v-if="fileMetadata" class="mb-2 d-flex flex-column gap-1">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold file-name" :title="displayFileName">
              {{ displayFileName }}
            </span>
            <div class="btn-group btn-group-sm" role="group" aria-label="File actions">
              <button
                v-if="isImage"
                type="button"
                class="btn btn-outline-secondary"
                @click="openPreview"
                :disabled="fetchingBlob"
                title="Preview"
                aria-label="Preview"
              >
                <i class="bi bi-eye"></i>
              </button>
              <button
                type="button"
                class="btn btn-outline-secondary"
                @click="downloadFile"
                :disabled="fetchingBlob"
                title="Download"
                aria-label="Download"
              >
                <i class="bi bi-download"></i>
              </button>
            </div>
            <button
              v-if="!fieldDef?.readonly"
              type="button"
              class="btn btn-outline-danger btn-sm"
              @click="clearFile"
            >
              Remove
            </button>
          </div>
          <div v-if="fileMetadata.size" class="text-muted small">
            {{ formatFileSize(fileMetadata.size) }}
          </div>
        </div>

        <div v-if="fileError" class="text-danger small mb-2">
          {{ fileError }}
        </div>

        <input
          :key="fileInputKey"
          type="file"
          :id="`${formId}-${fieldName}`"
          :name="fieldName"
          class="form-control"
          :accept="acceptAttribute"
          :disabled="fieldDef?.readonly || uploading"
          @change="handleFileSelect"
        />

        <div v-if="uploading" class="text-muted small mt-2">
          Uploading...
        </div>

        <div v-if="allowedTypesLabel || maxSizeBytes" class="form-text">
          <span v-if="allowedTypesLabel">Allowed: {{ allowedTypesLabel }}</span>
          <span v-if="allowedTypesLabel && maxSizeBytes"> • </span>
          <span v-if="maxSizeBytes">Max size: {{ formatFileSize(maxSizeBytes) }}</span>
        </div>

      </div>

      <!-- Textarea -->
      <textarea
        v-else-if="fieldDef?.type === 'textarea'"
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
      <RelationshipSearchSelect
        v-else-if="fieldDef?.type === 'relationship'"
        :input-id="`${formId}-${fieldName}`"
        :input-name="fieldName"
        :related-entity="fieldDef?.entity"
        :model-value="value || ''"
        :initial-record="relationship"
        :disabled="fieldDef?.readonly"
        @update:modelValue="$emit('update:value', $event)"
      />

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

    <teleport to="body">
      <div
        v-if="showImagePreview"
        class="image-preview-backdrop"
        @click.self="closePreview"
      >
        <div class="image-preview-modal">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-semibold file-name" :title="displayFileName">
              {{ displayFileName }}
            </div>
            <button type="button" class="btn-close" @click="closePreview"></button>
          </div>
          <img :src="previewUrl" alt="Preview" class="image-preview-img" />
          <div v-if="fileMetadata?.size" class="text-muted small mt-2">
            {{ formatFileSize(fileMetadata.size) }}
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import RelationshipSearchSelect from './RelationshipSearchSelect.vue'
import { useToastStore } from '../stores/toast'
import api from '../services/api'

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
  },
  entityName: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:value', 'relationship-click'])

const collectionItems = ref(Array.isArray(props.value) ? [...props.value] : (props.value ? [props.value] : []))
const toastStore = useToastStore()
const fileMetadata = ref(null)
const fileLoading = ref(false)
const fileError = ref(null)
const uploading = ref(false)
const showImagePreview = ref(false)
const fileInputKey = ref(0)
const previewUrl = ref('')
const fetchingBlob = ref(false)

const displayFileName = computed(() => {
  return fileMetadata.value?.name || fileMetadata.value?.originalName || 'File'
})

const allowedTypes = computed(() => {
  const types = props.fieldDef?.allowedTypes
  return Array.isArray(types) ? types : []
})

const maxSizeBytes = computed(() => {
  const value = props.fieldDef?.maxSize
  if (value === null || value === undefined || value === '') return null
  const numeric = Number(value)
  return Number.isFinite(numeric) && numeric > 0 ? numeric : null
})

const isImage = computed(() => {
  return fileMetadata.value?.mimeType?.startsWith('image/')
})

const acceptAttribute = computed(() => {
  return allowedTypes.value.length > 0 ? allowedTypes.value.join(',') : null
})

const allowedTypesLabel = computed(() => {
  return allowedTypes.value.length > 0 ? allowedTypes.value.join(', ') : ''
})

watch(() => props.value, (newValue, oldValue) => {
  if (props.fieldDef?.type === 'collection') {
    collectionItems.value = Array.isArray(newValue) ? [...newValue] : (newValue ? [newValue] : [])
  }
  if (props.fieldDef?.type === 'file') {
    fetchFileMetadata(newValue)
    showImagePreview.value = false
    if (newValue !== oldValue) {
      revokePreviewUrl()
    }
  }
}, { immediate: true })

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

function formatFileSize(size) {
  const numeric = Number(size)
  if (!Number.isFinite(numeric) || numeric <= 0) return ''
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let index = 0
  let value = numeric
  while (value >= 1024 && index < units.length - 1) {
    value /= 1024
    index += 1
  }
  return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`
}

function openPreview() {
  if (!isImage.value) {
    return
  }

  ensurePreviewUrl()
    .then((url) => {
      if (url) {
        showImagePreview.value = true
      }
    })
    .catch((error) => {
      const message = error?.response?.data?.error || error?.message || 'Failed to load preview'
      fileError.value = message
      toastStore.error(message)
    })
}

function closePreview() {
  showImagePreview.value = false
}

function resetFileInput() {
  fileInputKey.value += 1
}

function getFileId() {
  return props.value || fileMetadata.value?.id || null
}

function revokePreviewUrl() {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = ''
  }
}

function isAllowedType(file) {
  if (allowedTypes.value.length === 0) return true
  const fileType = (file.type || '').toLowerCase()
  if (!fileType) return false
  return allowedTypes.value.some((allowed) => {
    const normalized = allowed.toLowerCase()
    if (normalized === fileType) return true
    if (normalized.endsWith('/*')) {
      const prefix = normalized.slice(0, -1)
      return fileType.startsWith(prefix)
    }
    return false
  })
}

async function fetchFileMetadata(fileId) {
  if (!fileId) {
    fileMetadata.value = null
    fileError.value = null
    revokePreviewUrl()
    return
  }

  fileLoading.value = true
  fileError.value = null

  try {
    const response = await api.get(`/system/files/${fileId}/metadata`)
    fileMetadata.value = response.data?.file || null
  } catch (error) {
    fileMetadata.value = null
    fileError.value = error.response?.data?.error || 'Failed to load file metadata'
  } finally {
    fileLoading.value = false
  }
}

async function fetchFileBlob() {
  const fileId = getFileId()
  if (!fileId) {
    throw new Error('No file to download')
  }

  fetchingBlob.value = true
  try {
    const response = await api.get(`/system/files/${fileId}`, {
      responseType: 'blob'
    })
    return response.data
  } finally {
    fetchingBlob.value = false
  }
}

async function ensurePreviewUrl() {
  if (previewUrl.value) {
    return previewUrl.value
  }
  const blob = await fetchFileBlob()
  previewUrl.value = URL.createObjectURL(blob)
  return previewUrl.value
}

async function downloadFile() {
  try {
    const blob = await fetchFileBlob()
    const url = URL.createObjectURL(blob)
    const filename = displayFileName.value || 'download'
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    setTimeout(() => URL.revokeObjectURL(url), 0)
  } catch (error) {
    const message = error?.response?.data?.error || error?.message || 'Failed to download file'
    fileError.value = message
    toastStore.error(message)
  }
}

async function handleFileSelect(event) {
  const files = event.target.files
  if (!files || files.length === 0) return

  if (files.length > 1) {
    toastStore.error('Multiple file uploads are not supported yet')
    resetFileInput()
    return
  }

  if (!props.entityName) {
    toastStore.error('Missing entity context for file upload')
    resetFileInput()
    return
  }

  const file = files[0]
  if (!isAllowedType(file)) {
    toastStore.error('File type is not allowed')
    resetFileInput()
    return
  }

  if (maxSizeBytes.value && file.size > maxSizeBytes.value) {
    toastStore.error('File exceeds the maximum allowed size')
    resetFileInput()
    return
  }

  uploading.value = true
  fileError.value = null

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('entity', props.entityName)
    formData.append('field', props.fieldName)

    const response = await api.post('/system/files', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    const uploaded = response.data?.file

    if (!uploaded?.id) {
      throw new Error('File upload failed')
    }

    fileMetadata.value = uploaded
    revokePreviewUrl()
    emit('update:value', uploaded.id)
  } catch (error) {
    fileError.value = error.response?.data?.error || error.message || 'Failed to upload file'
    toastStore.error(fileError.value)
  } finally {
    uploading.value = false
    resetFileInput()
  }
}

function clearFile() {
  fileMetadata.value = null
  fileError.value = null
  revokePreviewUrl()
  emit('update:value', null)
  resetFileInput()
}

onBeforeUnmount(() => {
  revokePreviewUrl()
})

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

.file-field-wrapper {
  width: 100%;
}

.file-name {
  display: inline-block;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: bottom;
}

.image-preview-modal .file-name {
  max-width: 520px;
}

.image-preview-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1.5rem;
}

.image-preview-modal {
  background: #ffffff;
  border-radius: 0.75rem;
  max-width: 720px;
  width: 100%;
  padding: 1rem;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.image-preview-img {
  max-width: 100%;
  height: auto;
  border-radius: 0.5rem;
  display: block;
}
</style>
