<template>
  <div class="dashboard-widget card h-100 border-0 shadow">
    <div class="card-header border-0 fw-semibold d-flex align-items-center justify-content-between gap-2">
      <div class="flex-grow-1 d-flex align-items-center gap-2">
        <input
          v-if="editable"
          v-model="localTitle"
          type="text"
          class="form-control form-control-sm widget-title-input"
          @input="handleTitleInput"
        />
        <span v-else class="text-truncate">{{ widget.title || 'Widget' }}</span>
      </div>
      <button
        v-if="editable"
        class="btn btn-sm btn-outline-danger"
        type="button"
        @click="$emit('delete', widget.id)"
      >
        ×
      </button>
    </div>
      <div class="card-body">
        <component
          v-if="widgetComponent"
          :is="widgetComponent"
          v-bind="widgetProps"
          @update-widget="handleUpdateWidget"
        />
        <div v-else class="text-muted small">
          Widget type {{ widget.type }} not implemented yet.
        </div>
      </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import ActionConsole from '../widgets/ActionConsole.vue'
import ListWidget from '../widgets/ListWidget.vue'
import GraphWidget from '../widgets/GraphWidget.vue'
import QuickFormWidget from '../widgets/QuickFormWidget.vue'

const props = defineProps({
  widget: { type: Object, required: true },
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['update-title', 'delete', 'update-widget'])

const localTitle = ref(props.widget.title || 'Widget')

const widgetComponent = computed(() => {
  if (props.widget.type === 'list') {
    return ListWidget
  }
  if (props.widget.type === 'action-console') {
    return ActionConsole
  }
  if (props.widget.type === 'graph') {
    return GraphWidget
  }
  if (props.widget.type === 'quickForm') {
    return QuickFormWidget
  }
  return null
})

const widgetProps = computed(() => {
  if (props.widget.type === 'list') {
    return {
      widget: props.widget,
      editable: props.editable
    }
  }
  if (props.widget.type === 'graph') {
    return {
      widget: props.widget,
      editable: props.editable
    }
  }
  if (props.widget.type === 'quickForm') {
    return {
      widget: props.widget,
      editable: props.editable
    }
  }
  return {}
})

watch(
  () => props.widget.title,
  (value) => {
    localTitle.value = value || 'Widget'
  }
)

function handleTitleInput() {
  emit('update-title', { id: props.widget.id, title: localTitle.value })
}

function handleUpdateWidget(payload) {
  emit('update-widget', payload)
}
</script>

<style scoped>
.dashboard-widget {
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.widget-title-input {
  min-width: 140px;
}

.dashboard-widget :deep(.card-header) {
  flex: 0 0 auto;
}

.dashboard-widget :deep(.card-body) {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
</style>
