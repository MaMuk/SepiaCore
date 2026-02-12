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
            @click="toggleUserDropdown($event)"
          >
            <i class="bi bi-person-fill"></i>
<!--              <span>{{ username }}</span>-->
          </button>
          <ul
            :class="{ show: openUserDropdown }"
            class="navbar-dropdown"
            aria-labelledby="userDropdown"
            :style="dropdownInlineStyles.user"
          >
            <li>
              <a class="dropdown-item dropdown-item-grouped" href="#" @click.prevent="handleSettings">
                <span class="dropdown-item-icon" aria-hidden="true">
                  <i class="bi bi-gear-fill"></i>
                </span>
                <span class="dropdown-item-text">Settings</span>
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item dropdown-item-grouped" href="#" @click.prevent="handleLogout">
                <span class="dropdown-item-icon" aria-hidden="true">
                  <i class="bi bi-box-arrow-right"></i>
                </span>
                <span class="dropdown-item-text">Logout</span>
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
        :class="{ 'is-open': isNavOpen || showEmptyNavNotice }"
      >
        <div v-if="showEmptyNavNotice" class="nav-empty-state entity-scroll entity-button-group">
          <i class="bi bi-info-circle nav-empty-icon" aria-hidden="true"></i>
          <div class="nav-empty-text">
            <span class="nav-empty-title">No entities in navigation.</span>
            <span v-if="isAdmin" class="nav-empty-subtitle">
              Go to Settings &gt; Entity Studio to create entities, then use Edit Navigation to add them here.
            </span>
            <span v-else class="nav-empty-subtitle">
              No entities have been added to the navigation yet.
            </span>
          </div>
          <button
            v-if="isAdmin"
            type="button"
            class="nav-empty-cta"
            @click.prevent="handleSettings"
          >
            Open Settings
          </button>
        </div>
        <div v-else class="entity-scroll entity-button-group" @scroll="closeEntityDropdowns">
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
                @click="toggleDropdown(entity.name, $event)"
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
              :style="dropdownInlineStyles[entity.name]"
            >
              <li>
                <a
                  class="dropdown-item dropdown-item-grouped"
                  href="#"
                  @click.prevent="handleCreate(entity.name)"
                >
                  <span class="dropdown-item-icon" aria-hidden="true">
                    <i class="bi bi-plus-lg"></i>
                  </span>
                  <span class="dropdown-item-text">Create</span>
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
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
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
const showEmptyNavNotice = computed(() => metadataStore.metadata && navigationEntities.value.length === 0)
const isAdmin = computed(() => authStore.isAdmin)
const openDropdowns = ref({})
const openUserDropdown = ref(false)
const isNavOpen = ref(false)
const dropdownInlineStyles = ref({})

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

function getDropdownElement(target) {
  if (!target) {
    return null
  }

  const dropdownRoot = target.closest('.dropdown')
  if (!dropdownRoot) {
    return null
  }

  return dropdownRoot.querySelector('.navbar-dropdown')
}

async function setDropdownInlineStyle(key, target) {
  if (!target) {
    return
  }

  await nextTick()

  const dropdownElement = getDropdownElement(target)
  const dropdownWidth = dropdownElement?.offsetWidth || 0
  const { left, right } = target.getBoundingClientRect()
  const viewportWidth = window.innerWidth || 0
  const leftOverflows = dropdownWidth > 0 && left + dropdownWidth > viewportWidth
  const rightOverflows = dropdownWidth > 0 && right - dropdownWidth < 0

  if (leftOverflows && rightOverflows) {
    const navbarRect = navbarElement.value?.getBoundingClientRect()
    const navbarCenter = navbarRect ? navbarRect.left + navbarRect.width / 2 : viewportWidth / 2
    dropdownInlineStyles.value[key] = {
      left: `${navbarCenter}px`,
      right: 'auto',
      transform: 'translateX(-50%)'
    }
    return
  }

  dropdownInlineStyles.value[key] = leftOverflows
    ? {
        right: `${Math.max(viewportWidth - right, 0)}px`,
        left: 'auto',
        transform: 'none'
      }
    : {
        left: `${left}px`,
        right: 'auto',
        transform: 'none'
      }
}

function toggleDropdown(entityName, event) {
  openDropdowns.value[entityName] = !openDropdowns.value[entityName]
  if (openDropdowns.value[entityName]) {
    openUserDropdown.value = false
    setDropdownInlineStyle(entityName, event?.currentTarget)
  }
}

function toggleUserDropdown(event) {
  openUserDropdown.value = !openUserDropdown.value
  if (openUserDropdown.value) {
    closeEntityDropdowns()
    setDropdownInlineStyle('user', event?.currentTarget)
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
  box-shadow: 0 2px 4px var(--navbar-shadow-color);
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
  border: 1px solid var(--navbar-border-soft);
  border-top: 0;
  background: linear-gradient(
    to bottom,
    var(--navbar-button-bg) 0,
    var(--navbar-dropdown-bg) 12px,
    var(--navbar-dropdown-bg) 100%
  );
  box-shadow: 0 16px 40px var(--navbar-dropdown-shadow);
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
  background: var(--navbar-dropdown-bg);
}

.navbar-dropdown .dropdown-item.dropdown-item-grouped {
  display: inline-flex;
  align-items: center;
  gap: 0;
  padding: 0;
  border-radius: 999px;
  border: 1px solid var(--navbar-pill-border);
  background: var(--navbar-button-bg);
  color: var(--navbar-icon-color);
  text-decoration: none;
}

.navbar-dropdown .dropdown-item.dropdown-item-grouped .dropdown-item-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-right: 1px solid var(--navbar-pill-border);
  border-radius: 999px 0 0 999px;
}

.navbar-dropdown .dropdown-item.dropdown-item-grouped .dropdown-item-text {
  display: inline-flex;
  align-items: center;
  padding: 0 0.95rem;
  height: 42px;
  font-weight: 600;
  border-radius: 0 999px 999px 0;
}

.navbar-dropdown .dropdown-item.dropdown-item-grouped:hover,
.navbar-dropdown .dropdown-item.dropdown-item-grouped:focus {
  background: var(--navbar-pill-bg);
  color: var(--navbar-icon-hover-color);
}

.navbar-dropdown .dropdown-item.dropdown-item-grouped:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--navbar-focus-ring);
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
  border: 1px solid var(--navbar-border-soft);
  background: var(--navbar-bg);
  color: var(--navbar-icon-color);
  transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
}
.icon-btn:hover { background: var(--navbar-pill-bg); color: var(--navbar-icon-hover-color); }
.icon-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--navbar-focus-ring); }
.icon-btn.active { background: var(--navbar-bg); box-shadow: 0 6px 20px var(--navbar-active-shadow); border-color: var(--navbar-active-border); color: var(--navbar-active-color); }

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
  background: var(--navbar-pill-bg);
  padding: 6px;
  border-radius: 999px;
  border: 1px solid var(--navbar-pill-border);
}

.entity-button-group {
  background: var(--navbar-pill-bg);
  border: 1px solid var(--navbar-pill-border);
  border-radius: 999px;
  padding: 6px;
}

.nav-empty-state {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: var(--navbar-empty-bg);
  border: 1px solid var(--navbar-empty-border);
  border-radius: 999px;
  padding: 0.35rem 0.9rem;
  color: var(--navbar-empty-text);
  box-shadow: 0 8px 20px var(--navbar-empty-shadow);
  max-width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  white-space: nowrap;
  scrollbar-width: none;
}

.nav-empty-state::-webkit-scrollbar {
  height: 0;
  width: 0;
}

.nav-empty-icon {
  font-size: 1.1rem;
  flex: 0 0 auto;
}

.nav-empty-text {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
  flex: 0 0 auto;
  min-width: 0;
  white-space: nowrap;
}

.nav-empty-title {
  font-weight: 600;
  font-size: 0.9rem;
  flex: 0 0 auto;
}

.nav-empty-subtitle {
  font-size: 0.78rem;
  color: var(--navbar-empty-subtitle);
  flex: 0 0 auto;
}

.nav-empty-cta {
  border: none;
  background: var(--navbar-cta-bg);
  color: var(--navbar-cta-text);
  font-weight: 600;
  border-radius: 999px;
  padding: 0.35rem 0.85rem;
  transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
  flex: 0 0 auto;
}

.nav-empty-cta:hover {
  background: var(--navbar-cta-hover-bg);
  box-shadow: 0 8px 16px var(--navbar-cta-hover-shadow);
  transform: translateY(-1px);
}

.nav-empty-cta:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--navbar-cta-focus-ring);
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

  .nav-empty-state {
    border-radius: 18px;
  }
}
</style>
