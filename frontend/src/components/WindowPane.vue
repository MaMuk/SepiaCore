<template>
  <aside
    :class="['window-pane', { 'window-pane-expanded': isExpanded, 'window-pane-hidden': isMobile }]"
    :style="paneStyle"
  >
    <button
      class="windowpane-toggle"
      type="button"
      :title="isExpanded ? 'Collapse window pane' : 'Expand window pane'"
      @click="toggleExpanded"
    >
      <span class="toggle-arrows">
        <i :class="isExpanded ? 'bi bi-chevron-right' : 'bi bi-chevron-left'"></i>
        <i :class="isExpanded ? 'bi bi-chevron-right' : 'bi bi-chevron-left'"></i>
      </span>
    </button>
    <div
      class="windowpane-resize-handle"
      @mousedown="startResize"
      @touchstart.prevent="startResize"
    ></div>
    <div ref="paneContainer" class="window-pane-inner"></div>
  </aside>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useWindowPane } from '../composables/useWindowPane'
import { useViewport } from '../composables/useViewport'

const {
  isExpanded,
  paneWidth,
  registerPaneContainer,
  toggleExpanded
} = useWindowPane()
const { isMobile } = useViewport()
const paneContainer = ref(null)
const isResizing = ref(false)
const minWidth = 120
const maxWidthRatio = 0.7

const paneStyle = computed(() => {
  if (!isExpanded.value) {
    return { width: '0px' }
  }
  return { width: `${paneWidth.value}px` }
})

onMounted(() => {
  if (paneContainer.value) {
    registerPaneContainer(paneContainer.value)
  }
})

watch(paneContainer, (newContainer) => {
  if (newContainer) {
    registerPaneContainer(newContainer)
  }
})

function startResize(event) {
  if (!isExpanded.value) return
  isResizing.value = true
  const startX = event.touches ? event.touches[0].clientX : event.clientX
  const startWidth = paneWidth.value
  const maxWidth = Math.floor(window.innerWidth * maxWidthRatio)

  function onMove(moveEvent) {
    if (!isResizing.value) return
    const clientX = moveEvent.touches ? moveEvent.touches[0].clientX : moveEvent.clientX
    const delta = startX - clientX
    const nextWidth = Math.min(maxWidth, Math.max(minWidth, startWidth + delta))
    paneWidth.value = nextWidth
  }

  function onUp() {
    isResizing.value = false
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
    window.removeEventListener('touchmove', onMove)
    window.removeEventListener('touchend', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
  window.addEventListener('touchmove', onMove, { passive: false })
  window.addEventListener('touchend', onUp)
}

</script>

<style scoped>
.window-pane {
  position: relative;
  overflow: visible;
  background: var(--pane-bg-color);
  border-left: 2px groove #fff;
  transition: width 0.3s ease;
  pointer-events: none;
}

.window-pane-expanded {
  pointer-events: auto;
}

.window-pane-hidden {
  display: none;
}

.window-pane-inner {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.window-pane :deep(.windowpane-attached) {
  position: absolute !important;
  inset: 0 !important;
  top: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  left: 0 !important;
  width: 100% !important;
  height: 100% !important;
  margin: 0 !important;
}

.window-pane :deep(.windowpane-attached .wb-drag) {
  cursor: default !important;
}

.windowpane-toggle {
  position: absolute;
  top: 50%;
  left: -12px;
  transform: translateY(-50%);
  width: 12px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(108, 117, 125, 0.85);
  border: 1px solid #495057;
  border-left: none;
  border-radius: 8px 0 0 8px;
  color: #fff;
  cursor: pointer;
  z-index: 5;
  transition: background 0.2s ease, color 0.2s ease;
  pointer-events: auto;
}

.windowpane-resize-handle {
  position: absolute;
  left: -10px;
  top: 0;
  bottom: 0;
  width: 10px;
  cursor: ew-resize;
  z-index: 4;
  pointer-events: auto;
}

.toggle-arrows {
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1;
  font-size: 0.7rem;
  gap: 2px;
}

.windowpane-toggle:hover {
  background: rgba(108, 117, 125, 1);
  color: #e9ecef;
}

@media (max-width: 767.98px) {
  .window-pane-expanded {
    width: 100%;
    max-width: none;
    position: fixed;
    right: 0;
    top: var(--navbar-height, 72px);
    bottom: 0;
    z-index: 950;
    box-shadow: -8px 0 16px rgba(0, 0, 0, 0.2);
  }
}
</style>
