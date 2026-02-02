<template>
  <div class="main-layout" :class="{ 'sidebar-expanded': isExpanded, 'mobile-layout': isMobile }" ref="mainLayout">
    <Sidebar />
    <Navbar ref="navbarRef" />

    <div class="main-content-wrapper">
      <div class="main-content-row">
        <main class="container-fluid mt-4 main-view">
          <router-view />
        </main>
        <WindowPane />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useSidebar } from '../composables/useSidebar'
import { useViewport } from '../composables/useViewport'
import Sidebar from './Sidebar.vue'
import Navbar from './Navbar.vue'
import WindowPane from './WindowPane.vue'

const { isExpanded } = useSidebar()
const { isMobile } = useViewport()
const navbarRef = ref(null)
const mainLayout = ref(null)
const navbarHeight = ref(68) // Default height
const resizeObserver = ref(null)

// Initialize default value on document root
document.documentElement.style.setProperty('--navbar-height', '72px')

function updateNavbarHeight() {
  const navbarEl = navbarRef.value?.navbarElement
  if (navbarEl) {
    const height = navbarEl.offsetHeight
    if (height > 0) {
      navbarHeight.value = height
      // Set CSS variable on document root so it's accessible everywhere
      document.documentElement.style.setProperty('--navbar-height', `${height}px`)
    }
  }
}

onMounted(() => {
  // Initial measurement
  nextTick(() => {
    updateNavbarHeight()
    
    // Set up ResizeObserver to watch for navbar height changes
    const navbarEl = navbarRef.value?.navbarElement
    if (navbarEl) {
      resizeObserver.value = new ResizeObserver(() => {
        updateNavbarHeight()
      })
      resizeObserver.value.observe(navbarEl)
    }
    
    // Also watch for window resize (in case navbar collapses/expands)
    window.addEventListener('resize', updateNavbarHeight)
  })
})

onBeforeUnmount(() => {
  if (resizeObserver.value) {
    resizeObserver.value.disconnect()
  }
  window.removeEventListener('resize', updateNavbarHeight)
})
</script>

<style scoped>
.main-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-content-wrapper {
  flex: 1;
  margin-left: 42px; /* Always account for collapsed sidebar width */
  margin-top: var(--navbar-height, 72px); /* Account for fixed navbar height */
  transition: margin-left 0.3s ease;
}

.main-content-row {
  display: flex;
  align-items: stretch;
  min-height: calc(100vh - var(--navbar-height, 72px));
}

.main-view {
  flex: 1 1 auto;
  min-width: 0;
}

/* Adjust main content when sidebar is expanded */
.main-layout.sidebar-expanded .main-content-wrapper {
  margin-left: 268px;
}

.main-layout.mobile-layout .main-content-wrapper {
  margin-left: 0;
}

main {
  flex: 1;
}
</style>
