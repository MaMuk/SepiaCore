<template>
  <div ref="gridContainer" class="grid-stack">
    <div
      v-for="widget in widgets"
      :key="widget.id"
      class="grid-stack-item"
      :gs-id="widget.id"
      :gs-x="widget.x"
      :gs-y="widget.y"
      :gs-w="widget.w"
      :gs-h="widget.h"
    >
      <div class="grid-stack-item-content">
        <DashboardWidget
          :widget="widget"
          :editable="editable"
          @delete="handleDelete"
          @update-title="handleUpdateTitle"
          @update-widget="handleUpdateWidget"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { GridStack } from 'gridstack'
import 'gridstack/dist/gridstack.min.css'
import DashboardWidget from './DashboardWidget.vue'

const props = defineProps({
  widgets: { type: Array, default: () => [] },
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['remove-widget', 'update-title', 'update-widget'])

const gridContainer = ref(null)
const grid = ref(null)

function initGrid() {
  if (!gridContainer.value) return
  grid.value = GridStack.init(
    {
      float: true,
      cellHeight: 80,
      margin: 6,
      disableOneColumnMode: false
    },
    gridContainer.value
  )
  updateEditState(props.editable)

}

function updateEditState(isEditable) {
  if (!grid.value) return
  grid.value.setStatic(!isEditable)
  grid.value.enableMove(isEditable)
  grid.value.enableResize(isEditable)
}

function handleDelete(id) {
  emit('remove-widget', id)
}

function handleUpdateTitle(payload) {
  emit('update-title', payload)
}

function handleUpdateWidget(payload) {
  emit('update-widget', payload)
}

function saveLayout() {
  if (!grid.value) return []
  return grid.value.save(false)
}

async function syncLayout() {
  if (!grid.value) return
  const widgetIds = new Set(props.widgets.map((widget) => String(widget.id)))
  const existing = new Set(grid.value.engine.nodes.map((node) => String(node.id)))

  // Remove nodes that no longer exist
  grid.value.engine.nodes.forEach((node) => {
    const nodeId = String(node.id)
    if (!widgetIds.has(nodeId)) {
      const el = gridContainer.value?.querySelector(`[gs-id="${nodeId}"]`)
      if (el) {
        grid.value.removeWidget(el, true)
      }
    }
  })

  // Add/update nodes without reloading the whole grid
  props.widgets.forEach((widget) => {
    const id = String(widget.id)
    const el = gridContainer.value?.querySelector(`[gs-id="${id}"]`)
    if (!el) return

    if (!existing.has(id)) {
      grid.value.makeWidget(el)
      existing.add(id)
    }

    const updatePayload = {
      w: widget.w,
      h: widget.h
    }

    if (widget.x == null || widget.y == null) {
      updatePayload.autoPosition = true
    } else {
      updatePayload.x = widget.x
      updatePayload.y = widget.y
    }

    grid.value.update(el, updatePayload)
  })
}

defineExpose({ saveLayout })

onMounted(async () => {
  await nextTick()
  initGrid()
})

onBeforeUnmount(() => {
  if (grid.value) {
    grid.value.destroy(false)
    grid.value = null
  }
})

watch(
  () => props.editable,
  (isEditable) => {
    updateEditState(isEditable)
  }
)

watch(
  () => props.widgets,
  async () => {
    await nextTick()
    await syncLayout()
  },
  { deep: true }
)
</script>

<style scoped>
.grid-stack {
  background-color: rgb(255, 255, 255);
  min-height: 480px;
}

.grid-stack-item-content {
  background: transparent;
}
</style>
