import { ref } from 'vue'

const MOBILE_MEDIA_QUERY = '(max-width: 767.98px)'

const isMobile = ref(false)
let mediaQueryList = null
let initialized = false

function applyMatch(match) {
  isMobile.value = match
}

function initViewportWatcher() {
  if (initialized || typeof window === 'undefined') return
  initialized = true
  mediaQueryList = window.matchMedia(MOBILE_MEDIA_QUERY)
  applyMatch(mediaQueryList.matches)

  const handler = (event) => {
    applyMatch(event.matches)
  }

  if (typeof mediaQueryList.addEventListener === 'function') {
    mediaQueryList.addEventListener('change', handler)
  } else if (typeof mediaQueryList.addListener === 'function') {
    mediaQueryList.addListener(handler)
  }
}

export function useViewport() {
  initViewportWatcher()

  return {
    isMobile,
    mobileQuery: MOBILE_MEDIA_QUERY
  }
}
