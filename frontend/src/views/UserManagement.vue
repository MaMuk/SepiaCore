<template>
  <div class="user-management-view">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>User Management</h2>
      <button class="btn btn-primary" @click="openCreateModal">
        <span class="me-2">+</span>Create User
      </button>
    </div>

    <div v-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </div>

    <div id="grid-wrapper" ref="gridWrapper"></div>

    <!-- Create/Edit User Modal -->
    <UserEditModal
      v-model="showUserModal"
      :user="selectedUser"
      :isEdit="isEditMode"
      @saved="handleUserSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { Grid, html } from 'gridjs'
import { LIST_LIMIT, API_BASE_URL } from '../config'
import api from '../services/api'
import UserEditModal from '../components/UserEditModal.vue'
import 'gridjs/dist/theme/mermaid.css'

const route = useRoute()
const toastStore = useToastStore()
const authStore = useAuthStore()

const gridWrapper = ref(null)
const grid = ref(null)
const error = ref(null)
const recordsCache = ref([])
const showUserModal = ref(false)
const selectedUser = ref(null)
const isEditMode = ref(false)

const columns = [
  { id: 'name', name: 'Username' },
  { id: 'isadmin', name: 'Admin' },
  { id: 'date_created', name: 'Created' },
  { id: 'date_modified', name: 'Modified' }
]

onMounted(() => {
  initializeGrid()
})

onBeforeUnmount(() => {
  if (grid.value) {
    grid.value.destroy()
  }
})

function initializeGrid() {
  if (!gridWrapper.value) return

  error.value = null
  const server = API_BASE_URL
  const token = authStore.token || ''

  grid.value = new Grid({
    columns: [
      ...columns.map(col => ({
        id: col.id,
        name: col.name,
        sort: true,
        formatter: (cell, row) => {
          if (col.id === 'isadmin') {
            return cell ? 'Yes' : 'No'
          }
          if (col.id === 'date_created' || col.id === 'date_modified') {
            return cell ? new Date(cell).toLocaleString() : ''
          }
          return cell || ''
        }
      })),
      {
        id: 'actions',
        name: 'Actions',
        sort: false,
        formatter: (cell, row) => {
          // Get the record ID from the first column (name) to find the record
          const userName = row.cells[0].data
          const record = recordsCache.value.find(r => r.name === userName)
          if (!record || !record.id) return ''
          
          // Use html function to render HTML content
          return html(`<button class="btn btn-sm btn-danger" data-user-id="${record.id}" data-action="delete">Delete</button>`)
        }
      }
    ],

    search: {
      server: {
        url: (baseUrl, keyword) => {
          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          if (keyword) {
            url.searchParams.set('search', keyword)
          }
          return url.toString()
        }
      }
    },

    sort: {
      multiColumn: false,
      server: {
        url: (baseUrl, gridColumns) => {
          if (!gridColumns.length) return baseUrl

          const col = gridColumns[0]
          const dir = col.direction === 1 ? 'asc' : 'desc'
          const colIndex = col.index
          const colName = columns[colIndex]?.id || 'date_modified'

          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          url.searchParams.set('sort', colName)
          url.searchParams.set('order', dir.toUpperCase())
          return url.toString()
        }
      }
    },

    pagination: {
      limit: LIST_LIMIT,
      server: {
        url: (baseUrl, page, limit) => {
          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          url.searchParams.set('page', page + 1)
          url.searchParams.set('limit', limit)
          return url.toString()
        }
      }
    },

    server: {
      url: `${server}/users`,
      headers: {
        Authorization: token
      },
      then: (json) => {
        const records = json.records || []
        recordsCache.value = records
        return records.map(record => [
          record.name || '',
          record.isadmin || false,
          record.date_created || '',
          record.date_modified || '',
          '' // Actions column
        ])
      },
      total: (json) => json.total || 0
    }
  })

  grid.value.on('ready', () => {
    // Grid is ready
  })

  grid.value.on('error', (err) => {
    error.value = 'Failed to load users'
    toastStore.error('Failed to load users')
  })

  grid.value.on('cellClick', (cell, row, column) => {
    const cellElement = cell?.target || cell?.element || cell
    if (!cellElement) return
    
    // Check if delete button was clicked
    const deleteBtn = cellElement.closest('[data-action="delete"]')
    if (deleteBtn) {
      const userId = deleteBtn.getAttribute('data-user-id')
      if (userId) {
        handleDelete(userId)
        return
      }
    }
    
    // Otherwise, open edit modal
    const rowElement = cellElement.closest?.('tr') || cellElement.parentElement?.closest('tr')
    if (!rowElement) return
    
    const tbody = rowElement.closest('tbody')
    if (!tbody) return
    
    const dataRows = Array.from(tbody.querySelectorAll('tr'))
    const rowIndex = dataRows.indexOf(rowElement)
    
    if (rowIndex < 0 || rowIndex >= recordsCache.value.length) return
    
    const record = recordsCache.value[rowIndex]
    if (record?.id) {
      openEditModal(record)
    }
  })

  grid.value.render(gridWrapper.value)
}

async function handleDelete(userId) {
  if (!confirm('Are you sure you want to delete this user?')) {
    return
  }

  try {
    await api.delete(`/users/${userId}`)
    toastStore.success('User deleted successfully')
    // Refresh grid
    if (grid.value) {
      grid.value.forceRender()
    }
  } catch (err) {
    const errorMessage = err.response?.data?.error || 'Failed to delete user'
    toastStore.error(errorMessage)
  }
}

function openCreateModal() {
  selectedUser.value = null
  isEditMode.value = false
  showUserModal.value = true
}

function openEditModal(user) {
  selectedUser.value = user
  isEditMode.value = true
  showUserModal.value = true
}

async function handleUserSaved() {
  // Refresh grid
  if (grid.value) {
    grid.value.forceRender()
  }
  showUserModal.value = false
}
</script>

<style scoped>
.user-management-view {
  padding: 1rem;
}

#grid-wrapper {
  margin-top: 1rem;
}

:deep(.gridjs-container) {
  font-size: 0.875rem;
}

:deep(.gridjs-table) {
  width: 100%;
}

:deep(.gridjs-th) {
  background-color: #f8f9fa;
  font-weight: 600;
}

:deep(.gridjs-td) {
  padding: 0.75rem;
}

:deep(.gridjs-tr:not(.gridjs-header-row)) {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

:deep(.gridjs-tr:not(.gridjs-header-row):hover) {
  background-color: #e3f2fd !important;
}

:deep(.gridjs-tr:not(.gridjs-header-row):hover .gridjs-td) {
  background-color: transparent !important;
}

:deep(.gridjs-search) {
  margin-bottom: 1rem;
}

:deep(.gridjs-pagination) {
  margin-top: 1rem;
}
</style>

