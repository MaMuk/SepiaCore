import { ref, computed, watch } from 'vue'
import { useViewport } from './useViewport'

const isExpanded = ref(false)
const lastNonMobileExpanded = ref(true)
const { isMobile } = useViewport()

export function useSidebar() {
  const isSidebarVisible = computed(() => {
    // Sidebar is always visible now
    return true
  })

  watch(isExpanded, (expanded) => {
    if (isMobile.value) return
    lastNonMobileExpanded.value = expanded
  })

  watch(isMobile, (mobile) => {
    if (mobile) {
      isExpanded.value = false
      return
    }
    isExpanded.value = lastNonMobileExpanded.value
  })

  function toggleExpanded() {
    if (isMobile.value) return
    isExpanded.value = !isExpanded.value
  }

  function expand() {
    if (isMobile.value) return
    isExpanded.value = true
  }

  function collapse() {
    isExpanded.value = false
  }

  return {
    isExpanded,
    isSidebarVisible,
    toggleExpanded,
    expand,
    collapse
  }
}
