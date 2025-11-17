<template>
  <div class="toast-container position-fixed p-3" style="z-index: 9999; bottom: 0; left: 0;">
    <div
      v-for="toast in toasts"
      :key="toast.id"
      :class="getToastClass(toast.type)"
      class="toast show"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
    >
      <div class="toast-header">
        <span :class="getIconClass(toast.type)" class="me-2" style="font-size: 1.2rem;">{{ getIcon(toast.type) }}</span>
        <strong class="me-auto">{{ getTitle(toast.type) }}</strong>
        <button
          type="button"
          class="btn-close"
          @click="remove(toast.id)"
          aria-label="Close"
        ></button>
      </div>
      <div class="toast-body">
        {{ toast.message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useToastStore } from '../stores/toast'

const toastStore = useToastStore()
const toasts = computed(() => toastStore.toasts)

function remove(id) {
  toastStore.remove(id)
}

function getToastClass(type) {
  const classes = {
    success: 'bg-success text-white',
    error: 'bg-danger text-white',
    warning: 'bg-warning text-dark',
    info: 'bg-info text-white'
  }
  return classes[type] || classes.info
}

function getIconClass(type) {
  return ''
}

function getIcon(type) {
  const icons = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ'
  }
  return icons[type] || icons.info
}

function getTitle(type) {
  const titles = {
    success: 'Success',
    error: 'Error',
    warning: 'Warning',
    info: 'Information'
  }
  return titles[type] || titles.info
}
</script>

<style scoped>
.toast {
  min-width: 300px;
  margin-bottom: 0.5rem;
}

.toast-header {
  display: flex;
  align-items: center;
}

.toast-header i {
  font-size: 1.2rem;
}
</style>

