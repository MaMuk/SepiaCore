<template>
  <div ref="gridContainer" class="grid-stack">
    <div
      v-for="widget in widgets"
      :key="widget.id"
      class="grid-stack-item"
      :gs-id="String(widget.id)"
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
const resizeObserver = ref(null)

const GRID_OPTIONS = {
  float: true,
  cellHeight: 80,
  margin: 6,
  disableOneColumnMode: false,
  alwaysShowResizeHandle: true,
  handle: '.card-header'
}

const VIEWPORT_PADDING = 24

function initGrid() {
  if (!gridContainer.value) return
  grid.value = GridStack.init(GRID_OPTIONS, gridContainer.value)
  setEditable(props.editable)
  rebuildGrid()
  updateViewportMinHeight()
}

function destroyGrid() {
  if (!grid.value) return
  grid.value.destroy(false)
  grid.value = null
}

function setEditable(isEditable) {
  if (!grid.value) return
  grid.value.setStatic(!isEditable)
  grid.value.enableMove(isEditable)
  grid.value.enableResize(isEditable)
}

function rebuildGrid() {
  if (!grid.value || !gridContainer.value) return
  grid.value.batchUpdate()
  grid.value.removeAll(false, false)

  const elements = gridContainer.value.querySelectorAll('.grid-stack-item')
  elements.forEach((el) => {
    grid.value.makeWidget(el)
  })

  const layout = props.widgets.map((widget) => ({
    id: String(widget.id),
    x: widget.x,
    y: widget.y,
    w: widget.w || 1,
    h: widget.h || 1,
    autoPosition: widget.x == null || widget.y == null
  }))

  grid.value.load(layout, false)
  grid.value.batchUpdate(false)
}

function saveLayout() {
  if (!grid.value) return []
  return grid.value.save(false)
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

function updateViewportMinHeight() {
  if (!gridContainer.value) return
  const rect = gridContainer.value.getBoundingClientRect()
  const viewportHeight = window.innerHeight
  const top = Math.max(0, rect.top)
  const minHeight = Math.max(0, viewportHeight - top - VIEWPORT_PADDING)
  gridContainer.value.style.setProperty('--dashboard-grid-min-height', `${minHeight}px`)
}

defineExpose({ saveLayout })

onMounted(async () => {
  await nextTick()
  initGrid()
  window.addEventListener('resize', updateViewportMinHeight)
  if (gridContainer.value) {
    resizeObserver.value = new ResizeObserver(updateViewportMinHeight)
    resizeObserver.value.observe(gridContainer.value)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateViewportMinHeight)
  if (resizeObserver.value) {
    resizeObserver.value.disconnect()
  }
  destroyGrid()
})

watch(
  () => props.editable,
  (isEditable) => {
    setEditable(isEditable)
  }
)

watch(
  () => props.widgets,
  async () => {
    await nextTick()
    rebuildGrid()
    updateViewportMinHeight()
  },
  { deep: true }
)
</script>

<style scoped>
.grid-stack {
  min-height: var(--dashboard-grid-min-height, 320px);
}

.grid-stack-item-content {
  background: transparent;
}
</style>
