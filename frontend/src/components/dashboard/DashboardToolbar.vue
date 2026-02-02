<template>
  <nav class="dashboard-toolbar navbar navbar-expand-lg">
    <div class="container-fluid align-items-center">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <button
          class="btn btn-sm btn-secondary"
          type="button"
          :disabled="isLoading || !activeDashboard"
          @click="$emit('toggle-default')"
          :title="isDefault ? 'Unset default' : 'Set as default'"
        >
          <span class="dashboard-star" aria-hidden="true">
            {{ isDefault ? '★' : '☆' }}
          </span>
        </button>

        <div class="dropdown">
        <button
          class="btn btn-sm btn-outline-secondary dropdown-toggle"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          :disabled="isLoading || dashboards.length === 0"
        >
          {{ activeLabel }}
        </button>
        <ul class="dropdown-menu">
          <li v-for="dashboard in dashboards" :key="dashboard.id">
            <button
              class="dropdown-item"
              type="button"
              @click="$emit('select', dashboard.id)"
            >
              {{ dashboard.name || 'Untitled' }}
            </button>
          </li>
        </ul>
        </div>

        <button
          class="btn btn-sm btn-outline-primary"
          type="button"
          :disabled="isLoading || !activeDashboard"
          @click="$emit('toggle-edit')"
        >
          {{ isEditMode ? 'Save' : 'Edit' }}
        </button>

        <button
          v-if="isEditMode"
          class="btn btn-sm btn-outline-secondary"
          type="button"
          :disabled="isLoading"
          @click="$emit('cancel-edit')"
        >
          Cancel
        </button>

        <button
          class="btn btn-sm btn-primary"
          type="button"
          :disabled="isLoading"
          @click="$emit('create')"
        >
          New Dashboard
        </button>
      </div>

      <div class="ms-auto">
        <button
          class="btn btn-sm btn-outline-danger"
          type="button"
          :disabled="isLoading || !activeDashboard"
          @click="$emit('delete')"
        >
          Delete
        </button>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  dashboards: { type: Array, default: () => [] },
  activeDashboard: { type: Object, default: null },
  isEditMode: { type: Boolean, default: false },
  isLoading: { type: Boolean, default: false },
  isDefault: { type: Boolean, default: false }
})

defineEmits([
  'select',
  'toggle-edit',
  'cancel-edit',
  'create',
  'add-widget',
  'update:selectedWidgetType',
  'toggle-default',
  'delete'
])

const activeLabel = computed(() => {
  if (props.activeDashboard?.name) return props.activeDashboard.name
  if (props.dashboards.length > 0) return props.dashboards[0].name || 'Untitled'
  return 'Dashboard'
})
</script>

<style scoped>
.dashboard-toolbar {
  background-color: var(--actionbar-bg-color);
}

.dashboard-star {
  font-size: 1rem;
  line-height: 1;
}
</style>
