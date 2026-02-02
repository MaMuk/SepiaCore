<template>
  <div
    :class="['sidebar', { 'sidebar-expanded': isExpanded, 'sidebar-hidden': isMobile }]"
  >
    <div class="sidebar-header">
       <button 
        class="btn btn-sm btn-link text-black p-0"
        @click="toggleExpanded"
        :title="isExpanded ? 'Collapse sidebar' : 'Expand sidebar'"
      >
      <!-- <span v-if="!isExpanded" class="badge bg-primary">{{ minimizedInstances.length }}</span>
      <i v-else :class="isExpanded ? 'bi bi-chevron-left' : 'bi bi-chevron-right'"></i> 
      -->

        <i :class="isExpanded ? 'bi bi-chevron-left' : 'bi bi-chevron-right'"></i>

      </button>
    </div>
    
    <div 
      class="minimized-container"
      :class="{ 'minimized-container-collapsed': !isExpanded }"
    >
      <div
        v-for="entry in sidebarEntries"
        :key="entry.id"
        class="sidebar-entry"
        :class="{
          'sidebar-entry-minimized': entry.isMinimized,
          'sidebar-entry-active': entry.isFocused
        }"
        @mousedown="handleEntryPointer(entry, $event)"
        @touchstart="handleEntryPointer(entry, $event)"
        :title="entry.title"
      >
        <div class="sidebar-entry-header">
          <span class="sidebar-entry-icon" :style="entry.iconStyle"></span>
          <span class="sidebar-entry-title">{{ entry.title }}</span>
          <button
            v-if="isExpanded"
            class="sidebar-entry-close"
            type="button"
            @click.stop="closeWindow(entry.instance)"
          ></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSidebar } from '../composables/useSidebar'
import { useWinbox } from '../composables/useWinbox'
import { useViewport } from '../composables/useViewport'

const { 
  isExpanded, 
  toggleExpanded: toggleExpandedSidebar
} = useSidebar()

const { instances: winboxInstances, sidebarPulse } = useWinbox()
const { isMobile } = useViewport()

const sidebarEntries = computed(() => {
  sidebarPulse.value
  return winboxInstances.value
    .filter(winboxInstance => winboxInstance && !winboxInstance.closed)
    .map(winboxInstance => {
      const icon = winboxInstance.__sidebarIcon
      return {
        id: winboxInstance.id,
        title: winboxInstance.title || winboxInstance.id || 'Untitled',
        iconStyle: icon ? { backgroundImage: `url(${icon})` } : {},
        isMinimized: winboxInstance.min === true,
        isFocused: isFocused(winboxInstance),
        instance: winboxInstance
      }
    })
})

function toggleExpanded() {
  toggleExpandedSidebar()
}

function isFocused(winboxInstance) {
  if (!winboxInstance) return false
  if (typeof winboxInstance.__isFocused === 'boolean') {
    return winboxInstance.__isFocused
  }
  if (winboxInstance.focused === true) return true
  const wbWindow = winboxInstance?.dom || winboxInstance?.body?.parentNode
  return !!wbWindow?.classList?.contains('focus')
}

function handleEntryClick(entry) {
  const winboxInstance = entry.instance
  if (!winboxInstance || winboxInstance.closed) return
  const focusedNow = entry.isFocused === true ? true : isFocused(winboxInstance)
  if (entry.isMinimized) {
    if (typeof winboxInstance.restore === 'function') {
      winboxInstance.restore()
    }
    if (typeof winboxInstance.focus === 'function') {
      winboxInstance.focus()
    }
    return
  }
  if (focusedNow) {
    if (typeof winboxInstance.minimize === 'function') {
      winboxInstance.minimize()
    } else if (typeof winboxInstance.min === 'function') {
      winboxInstance.min()
    }
    return
  }
  if (typeof winboxInstance.focus === 'function') {
    winboxInstance.focus()
  }
}

function handleEntryPointer(entry, event) {
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault()
  }
  if (event && typeof event.stopPropagation === 'function') {
    event.stopPropagation()
  }
  handleEntryClick(entry)
}

function closeWindow(winboxInstance) {
  if (!winboxInstance || winboxInstance.closed) return
  if (typeof winboxInstance.close === 'function') {
    winboxInstance.close()
  }
}
</script>

<style scoped>
.sidebar {
  position: fixed;
  left: 0;
  top: var(--navbar-height, 72px);
  bottom: 0;
  width: 42px;
  --sidebar-expanded-width: 268px;
  background-color: var(--pane-bg-color);
  border-right: 2px groove #fff;
  transition: width 0.3s ease, top 0.3s ease;
  display: flex;
  flex-direction: column;
}

.sidebar-expanded {
  width: var(--sidebar-expanded-width);
}

.sidebar-hidden {
  display: none;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: right;
  padding: 0.5rem;
  border-bottom: 2px groove #fff;
  gap: 0.5rem;
  min-height: 32px;
}


.btn-link {
  text-decoration: none;
  padding: 0.25rem;
}

.btn-link:hover {
  color: #adb5bd !important;
}

.minimized-container {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.minimized-container-collapsed {
  overflow: hidden;
  padding-left: 0.5rem;
  gap: 0.25rem;
}

.sidebar-entry {
  width: 100%;
}

.sidebar-entry-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  height: 35px;
  padding: 0 0.5rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.28);
  background-color: rgb(92, 146, 193);
  border-radius: 0;
  cursor: pointer;
  overflow: hidden;
}

.sidebar-entry:hover .sidebar-entry-header {
  background-color: rgb(108, 162, 209);
}

.sidebar-entry-active .sidebar-entry-header {
  background-color: rgb(125, 180, 220);
}

.sidebar-entry-icon {
  width: 20px;
  height: 100%;
  display: inline-block;
  background-size: 100%;
  background-position: center;
  background-repeat: no-repeat;
  flex: 0 0 auto;
  filter: invert(1) brightness(2);
}

.sidebar-entry-title {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.875rem;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-entry-close {
  flex: 0 0 auto;
  width: 30px;
  height: 100%;
  border: none;
  background-color: transparent;
  background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii0xIC0xIDE4IDE4Ij48cGF0aCBmaWxsPSIjZmZmIiBkPSJtMS42MTMuMjEuMDk0LjA4M0w4IDYuNTg1IDE0LjI5My4yOTNsLjA5NC0uMDgzYTEgMSAwIDAgMSAxLjQwMyAxLjQwM2wtLjA4My4wOTRMOS40MTUgOGw2LjI5MiA2LjI5M2ExIDEgMCAwIDEtMS4zMiAxLjQ5N2wtLjA5NC0uMDgzTDggOS40MTVsLTYuMjkzIDYuMjkyLS4wOTQuMDgzQTEgMSAwIDAgMSAuMjEgMTQuMzg3bC4wODMtLjA5NEw2LjU4NSA4IC4yOTMgMS43MDdBMSAxIDAgMCAxIDEuNjEzLjIxeiIvPjwvc3ZnPg==");
  background-size: 15px auto;
  background-position: center;
  background-repeat: no-repeat;
  cursor: pointer;
}

.minimized-container-collapsed .sidebar-entry-header {
  padding: 0 0.35rem;
}

.minimized-container-collapsed .sidebar-entry {
  width: var(--sidebar-expanded-width);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
}

.sidebar-entry:not(.sidebar-entry-minimized) .sidebar-entry-header {
  box-shadow: inset 0 -4px 0 rgba(160, 214, 255, 0.9);
}
</style>
