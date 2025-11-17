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
          <h5 class="modal-title">List Limit Settings</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="listLimit" class="form-label">List Limit</label>
            <input
              type="number"
              class="form-control"
              id="listLimit"
              v-model.number="listLimit"
              min="1"
              max="1000"
              :class="{ 'is-invalid': error }"
            />
            <div v-if="error" class="invalid-feedback">{{ error }}</div>
            <div class="form-text">
              Current value: <code>{{ currentValue }}</code>
              <br />
              This setting controls how many records are displayed per page in list views.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-success"
            @click="save"
            :disabled="saving || !!error"
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
import { getListLimit, setListLimit } from '../config'
import { useToastStore } from '../stores/toast'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toastStore = useToastStore()
const listLimit = ref(10)
const error = ref('')
const saving = ref(false)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const currentValue = computed(() => getListLimit())

watch(isVisible, (newVal) => {
  if (newVal) {
    listLimit.value = getListLimit()
    error.value = ''
  }
})

watch(listLimit, (newVal) => {
  if (newVal < 1) {
    error.value = 'List limit must be at least 1'
  } else if (newVal > 1000) {
    error.value = 'List limit cannot exceed 1000'
  } else if (!Number.isInteger(newVal)) {
    error.value = 'List limit must be a whole number'
  } else {
    error.value = ''
  }
})

function close() {
  isVisible.value = false
  listLimit.value = 10
  error.value = ''
}

function handleBackdropClick() {
  close()
}

function save() {
  if (error.value) {
    return
  }

  if (!listLimit.value || listLimit.value < 1) {
    error.value = 'Please enter a valid list limit'
    return
  }

  saving.value = true

  try {
    setListLimit(listLimit.value)
    toastStore.success('List limit saved successfully')
    emit('saved')
    close()
  } catch (err) {
    error.value = `Failed to save: ${err.message}`
    toastStore.error('Failed to save list limit')
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

