<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">API Base URL Settings</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="apiBaseUrl" class="form-label">API Base URL</label>
            <input
              type="text"
              class="form-control"
              id="apiBaseUrl"
              v-model="apiUrl"
              placeholder="e.g., http://localhost:8001"
              :class="{ 'is-invalid': error }"
            />
            <div v-if="error" class="invalid-feedback">{{ error }}</div>
            <div class="form-text">
              Current value: <code>{{ currentValue || 'Not set' }}</code>
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
            @click="testConnection"
            :disabled="testing || !apiUrl"
          >
            <span
              v-if="testing"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Test
          </button>
          <button
            type="button"
            class="btn btn-success"
            @click="save"
            :disabled="saving || !apiUrl || !!error"
          >
            <span
              v-if="saving"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { getApiBaseUrl, setApiBaseUrl } from '../config'
import { useToastStore } from '../stores/toast'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toastStore = useToastStore()
const apiUrl = ref('')
const error = ref('')
const testing = ref(false)
const saving = ref(false)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const currentValue = computed(() => getApiBaseUrl())

watch(isVisible, (newVal) => {
  if (newVal) {
    apiUrl.value = getApiBaseUrl() || ''
    error.value = ''
  }
})

function close() {
  isVisible.value = false
  apiUrl.value = ''
  error.value = ''
}

function handleBackdropClick() {
  close()
}

async function testConnection() {
  if (!apiUrl.value) {
    error.value = 'Please enter an API URL'
    return
  }

  // Basic URL validation
  try {
    new URL(apiUrl.value)
  } catch (e) {
    error.value = 'Invalid URL format'
    return
  }

  testing.value = true
  error.value = ''

  try {
    const response = await fetch(`${apiUrl.value}/ping`)
    const data = await response.json()
    
    if (response.ok) {
      toastStore.success('Connection test successful!')
      error.value = ''
    } else {
      error.value = 'Server responded with an error'
      toastStore.error('Connection test failed')
    }
  } catch (err) {
    error.value = `Connection failed: ${err.message}`
    toastStore.error('Connection test failed: ' + err.message)
  } finally {
    testing.value = false
  }
}

function save() {
  if (!apiUrl.value) {
    error.value = 'Please enter an API URL'
    return
  }

  // Basic URL validation
  try {
    new URL(apiUrl.value)
  } catch (e) {
    error.value = 'Invalid URL format'
    return
  }

  if (error.value) {
    return
  }

  saving.value = true

  try {
    setApiBaseUrl(apiUrl.value)
    toastStore.success('API Base URL saved successfully')
    emit('saved')
    close()
    
    // Reload the page to apply the new configuration
    setTimeout(() => {
      window.location.reload()
    }, 500)
  } catch (err) {
    error.value = `Failed to save: ${err.message}`
    toastStore.error('Failed to save API Base URL')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}
</style>

