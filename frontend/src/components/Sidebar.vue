<template>
  <div 
    :class="['sidebar', { 'sidebar-expanded': isExpanded }]"
  >
    <div class="sidebar-header">
       <button 
        class="btn btn-sm btn-link text-white p-0"
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
      id="minimized-container" 
      ref="minimizedContainer"
      :class="['minimized-container', { 'minimized-container-collapsed': !isExpanded }]"
    ></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useSidebar } from '../composables/useSidebar'

const { 
  minimizedInstances, 
  isExpanded, 
  hasMinimizedWindows, 
  isSidebarVisible,
  registerContainer,
  toggleExpanded: toggleExpandedSidebar
} = useSidebar()

const minimizedContainer = ref(null)

onMounted(() => {
  if (minimizedContainer.value) {
    registerContainer(minimizedContainer.value)
  }
})

watch(minimizedContainer, (newContainer) => {
  if (newContainer) {
    registerContainer(newContainer)
  }
})

function toggleExpanded() {
  toggleExpandedSidebar()
}
</script>

<style scoped>
.sidebar {
  position: fixed;
  left: 0;
  top: var(--navbar-height, 68px);
  bottom: 0;
  width: 42px;
  background-color:rgba(108, 117, 125, 0.75);
  border-right: 1px solid #495057;
  transition: width 0.3s ease, top 0.3s ease;
  display: flex;
  flex-direction: column;
}

.sidebar-expanded {
  width: 268px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: right;
  padding: 0.5rem;
  border-bottom: 2px groove #b0b1b8;
  gap: 0.5rem;
  min-height: 32px;
}


.btn-link {
  color: white !important;
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
}

.minimized-container-collapsed {
  overflow: hidden;
  padding-right: 0.5rem;
}

/* Styles for minimized winbox windows */
.minimized-container :deep(.wb-body) {
  display: none !important;
}

.minimized-container :deep(.wb-header) {
  border-bottom: 1px solid #495057 !important;
  background-color: rgb(92, 146, 193) !important;
  border-radius: 0 !important;
  cursor: pointer;
}

.minimized-container :deep(.wb-header:hover) {
  background-color: rgb(108, 162, 209) !important;
}

.minimized-container :deep(.wb) {
  position: static !important;
  border-radius: 0 !important;
  margin: 0 !important;
  width: 100% !important;
  height: auto !important;
  min-height: 40px;
}

.minimized-container :deep(.wb-title) {
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.minimized-container-collapsed :deep(.winbox) {
  box-shadow: unset;
  margin-bottom: 0.25em;
}
</style>

