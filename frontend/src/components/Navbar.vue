<template>
  <nav ref="navbarElement" class="navbar fixed-top bg-white">
    <div class="container-fluid flex-nowrap nav-container">
      <div class="navbar-left-group icon-cluster">
        <button
          class="icon-btn icon-btn-round d-flex align-items-center"
          :class="{ active: isDashboardActive }"
          type="button"
          @click.prevent="router.push('/app')"><i class="bi bi-grid"></i>
        </button>
        <div class="dropdown navbar-user">
          <button
            class="icon-btn icon-btn-round"
            :class="{ active: openUserDropdown }"
            type="button"
            id="userDropdown"
            :aria-expanded="openUserDropdown"
            aria-haspopup="true"
            @click="toggleUserDropdown"
          >
            <i class="bi bi-person-fill"></i>
<!--              <span>{{ username }}</span>-->
          </button>
          <ul
            :class="{ show: openUserDropdown }"
            class="navbar-dropdown"
            aria-labelledby="userDropdown"
          >
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
        <button
          class="icon-btn icon-btn-round custom-navbar-toggler"
          :class="{ active: isNavOpen }"
          type="button"
          :aria-expanded="isNavOpen"
          aria-label="Toggle navigation"
          @click="toggleNav"
        >
          <i class="bi bi-list"></i>
        </button>
      </div>

      <div
        class="navbar-center-group"
        :class="{ 'is-open': isNavOpen }"
      >
        <div class="entity-scroll entity-button-group" @scroll="closeEntityDropdowns">
          <div
            v-for="entity in navigationEntities"
            :key="entity.name"
            class="entity-item dropdown"
          >
            <div class="entity-tab" role="group" :aria-label="`${entity.displayName} navigation`">
              <button
                class="icon-btn icon-btn-grouped icon-btn-grouped--left entity-tab-button d-flex align-items-center"
                :class="{ active: isEntityActive(entity.name) }"
                type="button"
                :title="entity.displayName"
                :aria-label="`Open ${entity.displayName}`"
                @click="navigateToList(entity.name)"
              >
                <i
                  :class="entity.icon || 'bi-box'"
                  class="entity-icon-glyph"
                  aria-hidden="true"
                ></i>
              </button>
              <button
                class="icon-btn icon-btn-grouped icon-btn-grouped--right icon-btn--caret entity-tab-caret d-flex align-items-center"
                :class="{ active: isEntityActive(entity.name) }"
                type="button"
                :id="`entityDropdown-${entity.name}`"
                title="Open Dropdown Menu"
                aria-haspopup="true"
                :aria-expanded="Boolean(openDropdowns[entity.name])"
                @click="toggleDropdown(entity.name)"
              >
                <i
                  :class="getCaretClass(entity.name)"
                  class="caret-icon"
                  aria-hidden="true"
                ></i>
              </button>
            </div>
            <ul
              :class="{ show: openDropdowns[entity.name] }"
              class="navbar-dropdown entity-dropdown"
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
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
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
const route = useRoute()
const authStore = useAuthStore()
const toastStore = useToastStore()
const metadataStore = useMetadataStore()
const { openRecordWindow } = useWinbox()

const navigationEntities = computed(() => metadataStore.navigationEntities)
const openDropdowns = ref({})
const openUserDropdown = ref(false)
const isNavOpen = ref(false)

onMounted(async () => {
  if (!metadataStore.metadata) {
    try {
      await metadataStore.fetchMetadata()
    } catch (error) {
      toastStore.error('Failed to load metadata')
    }
  }
})

function handleDocumentClick(event) {
  const navbar = navbarElement.value
  if (!navbar) {
    return
  }

  if (!navbar.contains(event.target)) {
    openUserDropdown.value = false
    closeEntityDropdowns()
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick)
})

function toggleNav() {
  isNavOpen.value = !isNavOpen.value
  if (!isNavOpen.value) {
    closeEntityDropdowns()
  }
}

function toggleDropdown(entityName) {
  openDropdowns.value[entityName] = !openDropdowns.value[entityName]
  if (openDropdowns.value[entityName]) {
    openUserDropdown.value = false
  }
}

function toggleUserDropdown() {
  openUserDropdown.value = !openUserDropdown.value
  if (openUserDropdown.value) {
    closeEntityDropdowns()
  }
}

function closeEntityDropdowns() {
  openDropdowns.value = {}
}

function getCaretClass(entityName) {
  return openDropdowns.value[entityName]
    ? 'bi bi-caret-down-fill'
    : 'bi bi-caret-right-fill'
}

function navigateToList(entityName) {
  router.push(`/app/entity/${entityName}`)
}

const isDashboardActive = computed(() => route.path === '/app/dashboard')

function isEntityActive(entityName) {
  return route.path === `/app/entity/${entityName}`
}

function handleCreate(entityName) {
  openDropdowns.value[entityName] = false
  openRecordWindow(entityName, null, 'create')
}

function handleSettings() {
  openUserDropdown.value = false
  router.push('/app/settings')
}

async function handleLogout() {
  openUserDropdown.value = false
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
  min-height: var(--navbar-height, 72px);
  overflow-x: clip;
}

.nav-container {
  display: flex;
  width: 100%;
  max-width: 100%;
  overflow-x: visible;
  overflow-y: visible;
  align-items: center;
  gap: 0.75rem;
}

.navbar-left-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nav-container .custom-navbar-toggler {
  display: none;
}

.navbar-center-group {
  display: flex;
  flex: 1 1 auto;
  align-items: center;
  justify-content: center;
  flex-wrap: nowrap;
  min-width: 0;
}

.entity-scroll {
  display: flex;
  align-items: center;
  gap: 0.35rem 0.5rem;
  flex-wrap: nowrap;
  overflow-x: auto;
  overflow-y: visible;
  max-width: 100%;
  padding: 0.25rem 0.5rem;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.navbar-user {
  display: flex;
  align-items: center;
}

.entity-item.dropdown {
  position: relative;
}

.navbar-dropdown {
  display: none;
  position: fixed;
  margin-top: 0;
  top: 72px;
  left: 50%;
  transform: translateX(-50%);
  min-width: 14rem;
  padding: 0.5rem;
  border-radius: 0 0 0.9rem 0.9rem;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-top: 0;
  background: #fff;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
  z-index: 1060;
  list-style: none;
  padding-left: 0;
}

.navbar-dropdown.show {
  display: block;
}

.navbar-dropdown .dropdown-item {
  border-radius: 0.6rem;
  padding: 0.55rem 0.7rem;
}

.navbar-dropdown .dropdown-item:hover,
.navbar-dropdown .dropdown-item:focus {
  background: #f1f5ff;
}

.navbar-dropdown .dropdown-divider {
  margin: 0.4rem 0;
}

.entity-scroll::-webkit-scrollbar {
  height: 0;
  width: 0;
}

.entity-tab {
  display: inline-flex;
  align-items: center;
  gap: 0;
}

.entity-tab-button,
.entity-tab-caret {
  appearance: none;
  border: none;
}

.entity-tab-caret {
  border-left: 0;
  margin-left: -1px;
}

.entity-icon-glyph {
  font-size: 1.5rem;
  line-height: 1;
}

.caret-icon {
  font-size: 0.7rem;
  transition: color 0.2s ease, transform 0.2s ease;
}

.caret-icon:hover {
  color: var(--bs-info) !important;
}

.icon-btn {
  width: 42px;
  height: 42px;
  border-radius: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: #fff;
  color: #334155;
  transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
}
.icon-btn:hover { background: #f1f5ff; color: #1e3a8a; }
.icon-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(47, 91, 255, 0.35); }
.icon-btn.active { background: #fff; box-shadow: 0 6px 20px rgba(12, 26, 75, 0.08); border-color: rgba(47, 91, 255, 0.35); color: #2f5bff; }

.icon-btn-round {
  border-radius: 999px;
}

.icon-btn-grouped {
  border-radius: 0;
}

.icon-btn-grouped--left {
  border-radius: 999px 0 0 999px;
}

.icon-btn-grouped--right {
  border-radius: 0 999px 999px 0;
}

.icon-btn--caret {
  width: 34px;
  height: 42px;
  padding: 0;
}

.icon-cluster {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f1f5ff;
  padding: 6px;
  border-radius: 999px;
  border: 1px solid rgba(47, 91, 255, 0.2);
}

.entity-button-group {
  background: #f1f5ff;
  border: 1px solid rgba(47, 91, 255, 0.2);
  border-radius: 999px;
  padding: 6px;
}

@media (max-width: 767.98px) {
  .nav-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .nav-container .custom-navbar-toggler {
    display: inline-flex;
  }

  .nav-container .navbar-center-group {
    order: 2;
    flex-basis: 100%;
    display: none;
    margin-left: 0;
  }

  .navbar-center-group.is-open {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
  }

  .navbar-center-group.is-open .entity-scroll {
    justify-content: flex-start;
    width: 100%;
  }
}
</style>
