<template>
  <div class="entity-list-view">
    <!-- 404 View -->
    <div v-if="entityNotFound" class="not-found-container">
      <div class="not-found-content">
        <h1 class="display-1">404</h1>
        <h2>Entity Not Found</h2>
        <p class="text-muted">The entity "{{ entityName }}" does not exist.</p>
        <button class="btn btn-primary" @click="goHome">
          Go to Home
        </button>
      </div>
    </div>

    <!-- Normal View -->
    <template v-else>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ entityDisplayName }}</h2>
        <button class="btn btn-primary" @click="handleCreate">
          <span class="me-2">+</span>Create
        </button>
      </div>

      <EntityFilters
        class="mb-3"
        :entity-name="entityName"
        :entity-display-name="entityDisplayName"
        :fields="filterFieldOptions"
        @filter-change="handleFilterChange"
      />

      <div v-if="error" class="alert alert-danger" role="alert">
        {{ error }}
      </div>

      <div 
        id="grid-wrapper" 
        ref="gridWrapper"
        :class="{ 'is-searching': isSearching }"
      ></div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { Grid, html } from 'gridjs'
import { LIST_LIMIT, API_BASE_URL } from '../config'
import { useWinbox } from '../composables/useWinbox'
import entityService from '../services/entityService'
import { formatFieldValueHTML } from '../utils/fieldFormatter'
import EntityFilters from '../components/EntityFilters.vue'
import 'gridjs/dist/theme/mermaid.css'

const route = useRoute()
const router = useRouter()
const metadataStore = useMetadataStore()
const toastStore = useToastStore()
const authStore = useAuthStore()
const { openRecordWindow } = useWinbox()

const entityName = computed(() => route.params.entity)
const entityDisplayName = computed(() => {
  return metadataStore.formatEntityName(entityName.value)
})

const gridWrapper = ref(null)
const grid = ref(null)
const error = ref(null)
const recordsCache = ref([])
const recordsById = ref({})
const relationshipsCache = ref({})
const isSearching = ref(false)
const entityNotFound = ref(false)
const activeFilter = ref(null)
const gridSessionId = ref(0)

const listFields = computed(() => {
  return metadataStore.getEntityListLayout(entityName.value)
})

const columnKeys = computed(() => {
  return listFields.value.map(col => typeof col === 'string' ? col : (col.id || col.name))
})

const filterFieldOptions = computed(() => {
  const fields = metadataStore.getEntityMetadata(entityName.value)?.fields || {}
  return Object.entries(fields).map(([name, def]) => ({
    name,
    label: formatFieldName(name),
    type: def?.type || 'text',
    options: def?.options || null,
    entity: def?.entity || null
  }))
})

onMounted(async () => {
  await loadMetadata()
  checkEntityExists()
  if (!entityNotFound.value && listFields.value.length > 0) {
    initializeGrid()
  }
})

onBeforeUnmount(() => {
  if (grid.value) {
    grid.value.destroy()
  }
  grid.value = null
  clearGridState()
})

watch(() => route.params.entity, async () => {
  if (grid.value) {
    grid.value.destroy()
  }
  grid.value = null
  // Reset search state when entity changes
  isSearching.value = false
  entityNotFound.value = false
  activeFilter.value = null
  clearGridState()
  await loadMetadata()
  checkEntityExists()
  if (!entityNotFound.value && listFields.value.length > 0) {
    await nextTick()
    initializeGrid()
  }
})

async function loadMetadata() {
  if (!metadataStore.metadata) {
    try {
      await metadataStore.fetchMetadata()
    } catch (err) {
      error.value = 'Failed to load metadata'
      toastStore.error('Failed to load metadata')
    }
  }
}

function checkEntityExists() {
  if (metadataStore.metadata) {
    const entityMeta = metadataStore.getEntityMetadata(entityName.value)
    entityNotFound.value = entityMeta === null
  }
}

function handleFilterChange(nextFilter) {
  activeFilter.value = nextFilter
  error.value = null
  refreshGrid()
}

function refreshGrid() {
  if (grid.value) {
    grid.value.config.pipeline.clearCache()
    grid.value.forceRender()
  }
}

function goHome() {
  router.push('/')
}

function initializeGrid() {
  if (!gridWrapper.value || !entityName.value) return

  error.value = null
  // Reset search state when initializing grid
  isSearching.value = false
  clearGridState()
  gridSessionId.value += 1
  const sessionId = gridSessionId.value
  const server = API_BASE_URL
  const token = authStore.token || ''

  // Get field definitions
  const fieldDefinitions = metadataStore.getEntityMetadata(entityName.value)?.fields || {}
  const relationshipFieldKeys = Object.entries(fieldDefinitions)
    .filter(([, def]) => def?.type === 'relationship')
    .map(([key]) => key)

  // Create columns from listFields with formatters
  const columns = listFields.value.map((col, index) => {
    const colKey = typeof col === 'string' ? col : (col.id || col.name)
    const colName = typeof col === 'string' 
      ? formatFieldName(col) 
      : (col.name || formatFieldName(col.id || col.name))
    
    const fieldDef = fieldDefinitions[colKey]
    
    return {
      id: colKey,
      name: colName,
      sort: true,
      formatter: (cell, row, column) => {
        // Find record by ID
        let record = null
        let recordId = null
        
        // Method 1: Try to find ID in the row data by column index (most reliable)
        const idIndex = columnKeys.value.indexOf('id')
        if (idIndex >= 0 && row[idIndex]) {
          recordId = row[idIndex]
          record = recordsById.value[recordId]
        }
        
        // Method 2: Try to match row data to cached records by comparing all values
        if (!record) {
          for (const cachedRecord of recordsCache.value) {
            let matches = true
            for (let i = 0; i < columnKeys.value.length; i++) {
              const key = columnKeys.value[i]
              const rowValue = row[i]
              const recordValue = cachedRecord[key]
              
              // Handle null/undefined comparisons - convert to strings for comparison
              const rowStr = rowValue == null ? '' : String(rowValue)
              const recordStr = recordValue == null ? '' : String(recordValue)
              
              if (rowStr !== recordStr) {
                matches = false
                break
              }
            }
            if (matches) {
              record = cachedRecord
              recordId = record.id
              break
            }
          }
        }
        
        // If we still don't have a record, check if this is a relationship field
        // and if we have relationship data for the cell value (UUID)
        if (!record && fieldDef?.type === 'relationship' && cell) {
          // The cell value is the UUID, try to find it in relationship cache
          // We need to search through all records to find which one has this relationship
          for (const [recId, rels] of Object.entries(relationshipsCache.value)) {
            if (rels[colKey]?.id === cell) {
              recordId = recId
              record = recordsById.value[recId]
              break
            }
          }
        }
        
        if (!record) {
          // If we can't find the record and this is a relationship field,
          // return the UUID as-is (fallback)
          if (fieldDef?.type === 'relationship' && cell) {
            return html(`<span class="text-muted">${cell}</span>`)
          }
          if (fieldDef) {
            return html(formatFieldValueHTML(cell, fieldDef, null, true))
          }
          return cell || '-'
        }
        
        const value = record[colKey]
        // Get relationship data - structure is relationshipsCache[recordId][fieldName]
        const relationship = relationshipsCache.value[record.id]?.[colKey] || null
        
        // Format the field value as HTML
        return html(formatFieldValueHTML(value, fieldDef, relationship, true))
      }
    }
  })

  const fetchGridData = async (opts) => {
    const params = parseGridParams(opts?.url, server)
    const hasActiveFilter = !!activeFilter.value?.payload
    isSearching.value = !hasActiveFilter && !!params.search

    try {
      let response = null
      if (hasActiveFilter) {
        const payload = {
          ...activeFilter.value.payload,
          page: params.page,
          limit: params.limit,
          sort: params.sort,
          order: params.order
        }
        response = await entityService.filter(entityName.value, payload)
      } else {
        response = await entityService.getList(entityName.value, {
          page: params.page,
          limit: params.limit,
          search: params.search,
          sort: params.sort,
          order: params.order
        })
      }

      if (sessionId !== gridSessionId.value) {
        return { data: [], total: 0 }
      }

      const records = extractRecordsFromResponse(response)
      const relationships = response?.relationship || buildRelationshipFallback(records, relationshipFieldKeys)
      setRecordCaches(records, relationships, sessionId)
      const total = (!hasActiveFilter && params.search) ? records.length : (response?.total ?? records.length)

      return {
        data: mapRecordsToRows(records),
        total
      }
    } catch (err) {
      throw err
    }
  }

  grid.value = new Grid({
    columns: columns,

    // server-side search
    search: {
      server: {
        url: (baseUrl, keyword) => {
          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          
          // Track search state - only true if keyword exists and is not empty
          const hasKeyword = keyword && keyword.trim()
          isSearching.value = !!hasKeyword && !activeFilter.value
          
          if (hasKeyword) {
            url.searchParams.set('search', keyword.trim())
          } else {
            url.searchParams.delete('search')
            // Explicitly reset search state when keyword is cleared
            isSearching.value = false
          }
          return url.toString()
        }
      }
    },

    // server-side sorting
    sort: {
      multiColumn: false,
      server: {
        url: (baseUrl, columns) => {
          if (!columns.length) return baseUrl

          const col = columns[0]
          const dir = col.direction === 1 ? 'asc' : 'desc'
          const colIndex = col.index
          const colName = columnKeys.value[colIndex] || 'date_modified'

          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          
          // Check if search parameter exists in URL
          const hasSearch = url.searchParams.has('search') && url.searchParams.get('search')
          isSearching.value = !!hasSearch && !activeFilter.value
          
          url.searchParams.set('sort', colName)
          url.searchParams.set('order', dir.toUpperCase())
          return url.toString()
        }
      }
    },

    // server-side pagination
    pagination: {
      limit: LIST_LIMIT,
      server: {
        url: (baseUrl, page, limit) => {
          const url = baseUrl.startsWith('http') 
            ? new URL(baseUrl) 
            : new URL(baseUrl, server)
          
          // Check if search parameter exists in URL
          const hasSearch = url.searchParams.has('search') && url.searchParams.get('search')
          isSearching.value = !!hasSearch && !activeFilter.value
          
          url.searchParams.set('page', page + 1)
          url.searchParams.set('limit', limit)
          return url.toString()
        }
      }
    },

    // main server configuration
    server: {
      url: `${server}/${entityName.value}`,
      headers: {
        Authorization: token
      },
      data: fetchGridData
    }
  })

  // Grid event handlers
  grid.value.on('ready', () => {
    // Grid is ready
  })

  grid.value.on('error', (err) => {
    const message = activeFilter.value ? 'Failed to apply filter' : 'Failed to load records'
    error.value = message
    toastStore.error(message)
  })

  grid.value.on('cellClick', (cell, row, column) => {
    // Get the row index from the DOM
    // cell might be an element or an object with target property
    const cellElement = cell?.target || cell?.element || cell
    if (!cellElement) return
    
    const rowElement = cellElement.closest?.('tr') || cellElement.parentElement?.closest('tr')
    if (!rowElement) return
    
    // Get all data rows (excluding header)
    const tbody = rowElement.closest('tbody')
    if (!tbody) return
    
    const dataRows = Array.from(tbody.querySelectorAll('tr'))
    const rowIndex = dataRows.indexOf(rowElement)
    
    if (rowIndex < 0 || rowIndex >= recordsCache.value.length) return
    
    // Get the record from cache using the row index
    const record = recordsCache.value[rowIndex]
    
    if (record?.id) {
      // Extract record name for title
      const entityMeta = metadataStore.getEntityMetadata(entityName.value)
      const isPerson = entityMeta?.person === true
      let recordName = null
      
      if (isPerson && record.first_name && record.last_name) {
        recordName = `${record.first_name} ${record.last_name}`.trim()
      } else if (record.name) {
        recordName = record.name
      }
      
      openRecordWindow(entityName.value, record.id, 'detail', recordName)
    }
  })

  // Set up global handler for relationship clicks
  window.handleRelationshipClick = (entity, recordId) => {
    openRecordWindow(entity, recordId, 'detail')
  }

  grid.value.render(gridWrapper.value)
}

function clearGridState() {
  recordsCache.value = []
  recordsById.value = {}
  relationshipsCache.value = {}
  if (gridWrapper.value) {
    gridWrapper.value.innerHTML = ''
  }
}

function parseGridParams(urlString, baseUrl) {
  const url = urlString ? new URL(urlString, baseUrl) : new URL(baseUrl)
  const page = Number.parseInt(url.searchParams.get('page') || '1', 10)
  const limit = Number.parseInt(url.searchParams.get('limit') || String(LIST_LIMIT), 10)
  const sort = url.searchParams.get('sort') || 'date_modified'
  const order = (url.searchParams.get('order') || 'DESC').toUpperCase()
  const search = url.searchParams.get('search')
  return {
    page: Number.isNaN(page) ? 1 : page,
    limit: Number.isNaN(limit) ? LIST_LIMIT : limit,
    sort,
    order,
    search: search ? search.trim() : null
  }
}

function extractRecordsFromResponse(response) {
  if (!response) return []
  if (Array.isArray(response)) return response
  if (Array.isArray(response.records)) return response.records
  if (Array.isArray(response.data)) return response.data
  if (Array.isArray(response.list)) return response.list
  return []
}

function buildRelationshipFallback(records, relationshipFieldKeys) {
  if (!records?.length || !relationshipFieldKeys?.length) return {}
  const fallback = {}
  records.forEach(record => {
    if (!record?.id) return
    const relMap = {}
    relationshipFieldKeys.forEach((fieldKey) => {
      const value = record[fieldKey]
      if (value) {
        relMap[fieldKey] = { id: value }
      }
    })
    if (Object.keys(relMap).length > 0) {
      fallback[record.id] = relMap
    }
  })
  return fallback
}

function setRecordCaches(records, relationships, sessionId) {
  if (sessionId && sessionId !== gridSessionId.value) return
  recordsCache.value = records
  recordsById.value = {}
  records.forEach(record => {
    if (record.id) {
      recordsById.value[record.id] = record
    }
  })
  relationshipsCache.value = relationships || {}
}

function mapRecordsToRows(records) {
  return records.map(record => {
    const rowData = columnKeys.value.map(key => record[key] ?? '')
    rowData._recordId = record.id
    return rowData
  })
}

function formatFieldName(field) {
  return field
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function handleCreate() {
  openRecordWindow(entityName.value, null, 'create')
}
</script>

<style scoped>
.entity-list-view {
  position: relative;
  padding: 1rem;
}

#grid-wrapper {
  margin-top: 1rem;
}

/* Grid.js custom styles */
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

/* Hide pagination when searching */
.is-searching :deep(.gridjs-pagination) {
  display: none !important;
}

/* 404 Not Found Styles */
.not-found-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 2rem;
}

.not-found-content {
  text-align: center;
}

.not-found-content h1 {
  font-size: 6rem;
  font-weight: 700;
  color: #6c757d;
  margin-bottom: 1rem;
}

.not-found-content h2 {
  margin-bottom: 1rem;
}

.not-found-content p {
  margin-bottom: 2rem;
  font-size: 1.1rem;
}
</style>
