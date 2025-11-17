import { ref, computed, watch } from 'vue'

const minimizedInstances = ref([])
const isExpanded = ref(false)
const minimizedContainerRef = ref(null)

export function useSidebar() {
  const hasMinimizedWindows = computed(() => minimizedInstances.value.length > 0)
  
  const isSidebarVisible = computed(() => {
    // Sidebar is always visible now
    return true
  })

  // Auto-collapse when all windows are restored
  watch(hasMinimizedWindows, (hasWindows) => {
    if (!hasWindows) {
      isExpanded.value = false
    }
  })

  function registerContainer(container) {
    minimizedContainerRef.value = container
  }

  function addMinimizedInstance(winboxInstance) {
    if (!minimizedInstances.value.find(w => w.id === winboxInstance.id)) {
      minimizedInstances.value.push(winboxInstance)
    }
  }

  function removeMinimizedInstance(winboxInstance) {
    const idx = minimizedInstances.value.findIndex(w => w.id === winboxInstance.id)
    if (idx !== -1) {
      minimizedInstances.value.splice(idx, 1)
    }
  }

  function toggleExpanded() {
    isExpanded.value = !isExpanded.value
  }

  function expand() {
    isExpanded.value = true
  }

  function collapse() {
    isExpanded.value = false
  }

  return {
    minimizedInstances,
    isExpanded,
    hasMinimizedWindows,
    isSidebarVisible,
    minimizedContainerRef,
    registerContainer,
    addMinimizedInstance,
    removeMinimizedInstance,
    toggleExpanded,
    expand,
    collapse
  }
}

