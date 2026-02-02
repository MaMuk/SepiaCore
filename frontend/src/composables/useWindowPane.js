import { ref, watch } from 'vue'
import { useViewport } from './useViewport'

const STORAGE_KEY = 'sepia.windowPane.expanded'
const DEFAULT_WIDTH = 576

const isExpanded = ref(false)
const paneWidth = ref(DEFAULT_WIDTH)
const paneContainerRef = ref(null)
const lastNonMobileExpanded = ref(true)

const { isMobile } = useViewport()

if (typeof window !== 'undefined') {
  const storedValue = window.localStorage.getItem(STORAGE_KEY)
  isExpanded.value = storedValue === null ? true : storedValue === 'true'
  lastNonMobileExpanded.value = isExpanded.value
}

watch(isExpanded, (expanded) => {
  if (isMobile.value) return
  lastNonMobileExpanded.value = expanded
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(STORAGE_KEY, expanded ? 'true' : 'false')
  }
})

watch(isMobile, (mobile) => {
  if (mobile) {
    isExpanded.value = false
    return
  }
  isExpanded.value = lastNonMobileExpanded.value
})

export function useWindowPane() {
  function registerPaneContainer(container) {
    paneContainerRef.value = container
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
    isExpanded,
    paneWidth,
    paneContainerRef,
    registerPaneContainer,
    toggleExpanded,
    expand,
    collapse
  }
}
