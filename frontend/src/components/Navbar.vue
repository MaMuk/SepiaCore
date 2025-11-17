<template>
  <nav ref="navbarElement" class="navbar navbar-expand-lg navbar-dark bg-secondary">
    <div class="container-fluid">
      <a class="navbar-brand" href="#" @click.prevent="router.push('/app')"><i class="bi bi-house-door-fill"></i></a>
      
      <div class="navbar-nav me-auto">
        <div
          v-for="entity in navigationEntities"
          :key="entity.name"
          class="nav-item dropdown"
        >
          <button
            class="btn btn-link text-white text-decoration-none nav-link d-flex align-items-center"
            type="button"
            :id="`entityDropdown-${entity.name}`"
            @click="handleEntityClick(entity.name, $event)"
          >
            <span @click="navigateToList(entity.name)">{{ entity.displayName }}</span>
            <i
              :class="getCaretClass(entity.name)"
              class="ms-1 caret-icon"
              style="font-size: 0.75rem; cursor: pointer;"
              title="Open Dropdown Menu"
              @click.stop="toggleDropdown(entity.name)"
            ></i>
          </button>
          <ul
            :class="{ show: openDropdowns[entity.name] }"
            class="dropdown-menu"
            :aria-labelledby="`entityDropdown-${entity.name}`"
          >
            <li>
              <a
                class="dropdown-item"
                href="#"
                @click.prevent="handleCreate(entity.name)"
              >
                Create
              </a>
            </li>
          </ul>
        </div>
      </div>
      
      <div class="ms-auto">
        <div class="dropdown">
          <button
            class="btn btn-link text-white text-decoration-none dropdown-toggle d-flex align-items-center"
            type="button"
            id="userDropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false"
          >
            <span class="me-2" style="font-size: 1.5rem;"><i class="bi bi-person-fill"></i></span>
            <span>{{ username }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li>
              <a class="dropdown-item" href="#" @click.prevent="handleSettings">
                <i class="bi bi-gear-fill me-2"></i>Settings
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="#" @click.prevent="handleLogout">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useMetadataStore } from '../stores/metadata'
import { useWinbox } from '../composables/useWinbox'
import authService from '../services/authService'

const navbarElement = ref(null)

// Expose the navbar element for parent components
defineExpose({
  navbarElement
})

const router = useRouter()
const authStore = useAuthStore()
const toastStore = useToastStore()
const metadataStore = useMetadataStore()
const { openRecordWindow } = useWinbox()

const username = computed(() => authStore.username || 'User')
const navigationEntities = computed(() => metadataStore.navigationEntities)
const openDropdowns = ref({})

onMounted(async () => {
  if (!metadataStore.metadata) {
    try {
      await metadataStore.fetchMetadata()
    } catch (error) {
      toastStore.error('Failed to load metadata')
    }
  }
})

function toggleDropdown(entityName) {
  openDropdowns.value[entityName] = !openDropdowns.value[entityName]
}

function getCaretClass(entityName) {
  return openDropdowns.value[entityName]
    ? 'bi bi-caret-down-fill'
    : 'bi bi-caret-right-fill'
}

function handleEntityClick(entityName, event) {
  // Don't navigate if clicking the caret
  if (event.target.classList.contains('caret-icon')) {
    toggleDropdown(entityName)
  }
}

function navigateToList(entityName) {
  router.push(`/app/entity/${entityName}`)
}

function handleCreate(entityName) {
  openDropdowns.value[entityName] = false
  openRecordWindow(entityName, null, 'create')
}

function handleSettings() {
  router.push('/app/settings')
}

async function handleLogout() {
  toastStore.info('You have been logged out')
  await authService.logout(router)
}
</script>

<style scoped>
.navbar {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  z-index: 1000;
}

.navbar-brand {
  font-weight: 600;
  font-size: 1.5rem;
}

.btn-link {
  color: white !important;
  border: none;
  padding: 0.5rem 1rem;
}

.btn-link:hover {
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 0.375rem;
}

.dropdown-toggle::after {
  margin-left: 0.5rem;
}

.nav-item.dropdown {
  position: relative;
}

.nav-item .dropdown-menu {
  margin-top: 0;
}

.nav-item .dropdown-menu.show {
  display: block;
}

.nav-link {
  padding: 0.5rem 1rem;
}

.caret-icon {
  transition: color 0.2s ease, transform 0.2s ease;
}

.caret-icon:hover {
  color: var(--bs-info) !important;
}
</style>

