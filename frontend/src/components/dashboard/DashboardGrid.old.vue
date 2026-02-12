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
const resizeObserver = ref(null)
const itemObservers = new Map()
const isUserResizing = ref(false)
const isUserDragging = ref(false)
const forceStopTimer = ref(null)

const CELL_HEIGHT = 80
const GRID_MARGIN = 6
const VIEWPORT_PADDING = 24

function initGrid() {
  if (!gridContainer.value) return
  grid.value = GridStack.init(
    {
      float: true,
      cellHeight: CELL_HEIGHT,
      margin: GRID_MARGIN,
      disableOneColumnMode: false,
      alwaysShowResizeHandle: true
    },
    gridContainer.value
  )
  bindGridEvents()
  updateEditState(props.editable)
  updateViewportMinHeight()

}

function updateEditState(isEditable) {
  if (!grid.value) return
  if (!isEditable) {
    forceStopInteractions(false)
  }
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
      const observer = itemObservers.get(nodeId)
      if (observer) {
        observer.disconnect()
        itemObservers.delete(nodeId)
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
    growItemToContent(el)

    if (!itemObservers.has(id)) {
      const contentEl = el.querySelector('.grid-stack-item-content')
      if (contentEl) {
        const observer = new ResizeObserver(() => {
          const gridItem = contentEl.closest('.grid-stack-item')
          if (gridItem) {
            growItemToContent(gridItem)
          }
        })
        observer.observe(contentEl)
        itemObservers.set(id, observer)
      }
    }
  })
  updateViewportMinHeight()
}

defineExpose({ saveLayout })

onMounted(async () => {
  await nextTick()
  initGrid()
  window.addEventListener('resize', updateViewportMinHeight)
  if (gridContainer.value) {
    resizeObserver.value = new ResizeObserver(() => {
      updateViewportMinHeight()
    })
    resizeObserver.value.observe(gridContainer.value)
  }
  window.addEventListener('mouseup', handleGlobalPointerUp)
  window.addEventListener('touchend', handleGlobalPointerUp)
  window.addEventListener('pointerup', handleGlobalPointerUp)
  window.addEventListener('blur', handleGlobalPointerUp)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateViewportMinHeight)
  if (resizeObserver.value) {
    resizeObserver.value.disconnect()
  }
  window.removeEventListener('mouseup', handleGlobalPointerUp)
  window.removeEventListener('touchend', handleGlobalPointerUp)
  window.removeEventListener('pointerup', handleGlobalPointerUp)
  window.removeEventListener('blur', handleGlobalPointerUp)
  itemObservers.forEach((observer) => observer.disconnect())
  itemObservers.clear()
  if (forceStopTimer.value) {
    clearTimeout(forceStopTimer.value)
    forceStopTimer.value = null
  }
  unbindGridEvents()
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

function updateViewportMinHeight() {
  if (!gridContainer.value) return
  if (isUserResizing.value || isUserDragging.value) return
  const rect = gridContainer.value.getBoundingClientRect()
  const viewportHeight = window.innerHeight
  const top = Math.max(0, rect.top)
  const available = Math.max(0, viewportHeight - top - VIEWPORT_PADDING)
  const maxAvailable = Math.max(0, viewportHeight - VIEWPORT_PADDING)
  const minHeight = Math.min(available, maxAvailable)
  gridContainer.value.style.setProperty('--dashboard-grid-min-height', `${minHeight}px`)
}

function growItemToContent(el) {
  if (!grid.value || !el) return
  if (isUserResizing.value || isUserDragging.value) return
  const node = el.gridstackNode
  if (!node) return
  const cellHeight = grid.value.getCellHeight(true)
  if (!cellHeight) return

  const content = el.querySelector('.grid-stack-item-content')
  const child = content?.firstElementChild
  if (!content || !child) return
  if (child.querySelector('.graph-widget')) return

  const padding = content.clientHeight - child.clientHeight
  const desiredHeight = child.scrollHeight + padding
  if (desiredHeight <= content.clientHeight + 8) return
  const desiredRows = Math.ceil(desiredHeight / cellHeight)
  const currentRows = node.h || 1
  const minRows = node.minH || 1

  if (desiredRows > currentRows) {
    grid.value.update(el, { h: Math.max(desiredRows, minRows) })
  }
}

function bindGridEvents() {
  if (!grid.value) return
  grid.value.on('resizestart', () => {
    isUserResizing.value = true
  })
  grid.value.on('resizestop', () => {
    isUserResizing.value = false
    updateViewportMinHeight()
  })
  grid.value.on('dragstart', () => {
    isUserDragging.value = true
  })
  grid.value.on('dragstop', () => {
    isUserDragging.value = false
    updateViewportMinHeight()
  })
}

function unbindGridEvents() {
  if (!grid.value) return
  grid.value.off('resizestart')
  grid.value.off('resizestop')
  grid.value.off('dragstart')
  grid.value.off('dragstop')
}

function handleGlobalPointerUp() {
  if (!isUserResizing.value && !isUserDragging.value) return
  if (forceStopTimer.value) clearTimeout(forceStopTimer.value)
  forceStopTimer.value = setTimeout(() => {
    forceStopTimer.value = null
    if (!isUserResizing.value && !isUserDragging.value) return
    forceStopInteractions(props.editable)
  }, 0)
}

function forceStopInteractions(shouldReenable) {
  if (!grid.value) return
  isUserResizing.value = false
  isUserDragging.value = false
  if (grid.value._extraDragRow != null) {
    grid.value._extraDragRow = 0
  }
  if (grid.value.engine?.batchMode) {
    grid.value.engine.endUpdate()
  }
  if (gridContainer.value) {
    gridContainer.value
      .querySelectorAll('.ui-resizable-resizing, .ui-draggable-dragging')
      .forEach((el) => {
        el.classList.remove('ui-resizable-resizing', 'ui-draggable-dragging')
      })
  }
  grid.value.enableMove(false)
  grid.value.enableResize(false)
  if (shouldReenable) {
    requestAnimationFrame(() => {
      if (!grid.value) return
      grid.value.enableMove(true)
      grid.value.enableResize(true)
    })
  }
  if (typeof grid.value._updateContainerHeight === 'function') {
    grid.value._updateContainerHeight()
  }
  updateViewportMinHeight()
}
</script>

<style scoped>
.grid-stack {
  background-color: rgb(255, 255, 255);
  min-height: var(--dashboard-grid-min-height, 480px);
}

.grid-stack-item-content {
  background: transparent;
}
</style>
