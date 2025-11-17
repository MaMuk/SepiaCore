<template>
  <div class="main-layout" :class="{ 'sidebar-expanded': isExpanded }" ref="mainLayout">
    <Sidebar />
    <Navbar ref="navbarRef" />

    <div class="main-content-wrapper" >
      <main class="container-fluid mt-4">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useSidebar } from '../composables/useSidebar'
import Sidebar from './Sidebar.vue'
import Navbar from './Navbar.vue'

const { isSidebarVisible, isExpanded } = useSidebar()
const navbarRef = ref(null)
const mainLayout = ref(null)
const navbarHeight = ref(68) // Default height
const resizeObserver = ref(null)

// Initialize default value on document root
document.documentElement.style.setProperty('--navbar-height', '68px')

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
  margin-top: var(--navbar-height, 68px); /* Account for fixed navbar height */
  transition: margin-left 0.3s ease;
}

/* Adjust main content when sidebar is expanded */
.main-layout.sidebar-expanded .main-content-wrapper {
  margin-left: 268px;
}

main {
  flex: 1;
}
</style>

