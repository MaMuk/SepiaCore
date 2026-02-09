<template>
  <div class="list-widget h-100 d-flex flex-column">
    <div v-if="editable" class="list-widget-config border-bottom pb-2 mb-2">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label small text-muted">Entity</label>
          <select v-model="localEntity" class="form-select form-select-sm">
            <option value="">Select entity</option>
            <option v-for="option in entityOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted">Page limit</label>
          <input
            v-model.number="localLimit"
            type="number"
            min="1"
            class="form-control form-control-sm"
          />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted">Columns</label>
          <select v-model="localColumns" class="form-select form-select-sm" multiple>
            <option v-for="option in columnOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>
      <EntityFilters
        class="mt-2"
        :entity-name="localEntity"
        :entity-display-name="entityDisplayName"
        :fields="filterFieldOptions"
        :require-stored="true"
        :initial-filter-id="localFilterId"
        @filter-change="handleFilterChange"
      />
    </div>

    <div v-if="!canRenderGrid" class="list-widget-empty text-muted small flex-grow-1 d-flex align-items-center justify-content-center">
      {{ guidanceMessage }}
    </div>

    <div v-else class="flex-grow-1 d-flex flex-column">
      <div v-if="error" class="alert alert-danger py-1 mb-2" role="alert">
        {{ error }}
      </div>
      <div
        ref="gridWrapper"
        class="list-grid-wrapper flex-grow-1"
        :class="{ 'is-searching': isSearching }"
      ></div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Grid, html } from 'gridjs'
import { API_BASE_URL, getListLimit } from '../../config'
import { useMetadataStore } from '../../stores/metadata'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import { useWinbox } from '../../composables/useWinbox'
import entityService from '../../services/entityService'
import { formatFieldValueHTML } from '../../utils/fieldFormatter'
import EntityFilters from '../EntityFilters.vue'
import 'gridjs/dist/theme/mermaid.css'

const props = defineProps({
  widget: { type: Object, required: true },
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['update-widget'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()
const authStore = useAuthStore()
const { openRecordWindow } = useWinbox()

const gridWrapper = ref(null)
const grid = ref(null)
const error = ref(null)
const recordsCache = ref([])
const recordsById = ref({})
const relationshipsCache = ref({})
const isSearching = ref(false)
const activeFilter = ref(null)
const gridSessionId = ref(0)
const currentColumnKeys = ref([])
const isHydrating = ref(false)
const suppressEntityReset = ref(false)

const localEntity = ref('')
const localLimit = ref(getListLimit())
const localColumns = ref([])
const localFilterId = ref(null)
const localFilterLabel = ref('')

const entityOptions = computed(() => {
  const entities = metadataStore.entities || {}
  return Object.keys(entities).map(name => ({
    value: name,
    label: metadataStore.formatEntityName(name)
  }))
})

const entityDisplayName = computed(() => {
  if (!localEntity.value) return ''
  return metadataStore.formatEntityName(localEntity.value)
})

const listLayoutFields = computed(() => {
  if (!localEntity.value) return []
  return metadataStore.getEntityListLayout(localEntity.value)
})

const defaultColumnKeys = computed(() => {
  const layout = listLayoutFields.value || []
  const keys = layout
    .map(col => (typeof col === 'string' ? col : (col.id || col.name)))
    .filter(Boolean)
  if (keys.length) return keys
  const fields = metadataStore.getEntityMetadata(localEntity.value)?.fields || {}
  return Object.keys(fields)
})

const resolvedColumns = computed(() => {
  if (localColumns.value && localColumns.value.length > 0) {
    return localColumns.value
  }
  return defaultColumnKeys.value
})

const columnOptions = computed(() => {
  const fields = metadataStore.getEntityMetadata(localEntity.value)?.fields || {}
  return Object.entries(fields).map(([name, def]) => ({
    value: name,
    label: formatFieldName(name),
    type: def?.type || 'text',
    options: def?.options || null,
    entity: def?.entity || null
  }))
})

const filterFieldOptions = computed(() => {
  const fields = metadataStore.getEntityMetadata(localEntity.value)?.fields || {}
  return Object.entries(fields).map(([name, def]) => ({
    name,
    label: formatFieldName(name),
    type: def?.type || 'text',
    options: def?.options || null,
    entity: def?.entity || null
  }))
})

const canRenderGrid = computed(() => {
  return !!localEntity.value && !!localFilterId.value && resolvedColumns.value.length > 0
})

const guidanceMessage = computed(() => {
  if (!localEntity.value) return 'Select an entity to configure this list.'
  if (!localFilterId.value) return 'Select or save a stored filter to display data.'
  if (!resolvedColumns.value.length) return 'Select at least one column to display.'
  return ''
})

onMounted(async () => {
  if (!metadataStore.metadata) {
    try {
      await metadataStore.fetchMetadata()
    } catch (err) {
      toastStore.error('Failed to load metadata')
    }
  }
  hydrateFromConfig(props.widget?.config)
  await nextTick()
  rebuildGrid()
})

onBeforeUnmount(() => {
  destroyGrid()
  clearGridState()
})

watch(
  () => props.widget?.config,
  (nextConfig) => {
    hydrateFromConfig(nextConfig)
  },
  { deep: true }
)

watch(localEntity, (next, prev) => {
  if (isHydrating.value) return
  if (suppressEntityReset.value) {
    suppressEntityReset.value = false
    return
  }
  if (next !== prev) {
    localFilterId.value = null
    localFilterLabel.value = ''
    activeFilter.value = null
    if (next) {
      localColumns.value = defaultColumnKeys.value
    } else {
      localColumns.value = []
    }
  }
})

watch([localColumns, localLimit], () => {
  if (isHydrating.value) return
  if (localEntity.value && localColumns.value.length === 0) {
    localColumns.value = defaultColumnKeys.value
  }
})

watch(
  [localEntity, localColumns, localLimit, localFilterId],
  async () => {
    if (isHydrating.value) return
    emitConfigUpdateIfNeeded()
    await nextTick()
    rebuildGrid()
  },
  { deep: true }
)

watch([localFilterLabel], () => {
  if (isHydrating.value) return
  emitConfigUpdateIfNeeded()
})

watch(localFilterId, (next) => {
  if (next) {
    activeFilter.value = {
      mode: 'stored',
      payload: { filter_id: next },
      label: localFilterLabel.value || ''
    }
  } else {
    activeFilter.value = null
  }
})

function hydrateFromConfig(config = {}) {
  isHydrating.value = true
  const nextEntity = config?.entity || ''
  const nextLimit = normalizeLimit(config?.limit)
  const nextColumns = Array.isArray(config?.columns) ? [...config.columns] : []
  const nextFilterId = config?.filterId ?? null
  const nextFilterLabel = config?.filterLabel || ''

  suppressEntityReset.value = true
  localEntity.value = nextEntity
  localLimit.value = nextLimit
  localColumns.value = nextColumns
  localFilterId.value = nextFilterId
  localFilterLabel.value = nextFilterLabel

  if (localEntity.value && localColumns.value.length === 0) {
    localColumns.value = defaultColumnKeys.value
  }

  if (localFilterId.value) {
    activeFilter.value = {
      mode: 'stored',
      payload: { filter_id: localFilterId.value },
      label: localFilterLabel.value || ''
    }
  } else {
    activeFilter.value = null
  }
  isHydrating.value = false
  emitConfigUpdateIfNeeded()
}

function emitConfigUpdateIfNeeded() {
  const nextConfig = buildConfigFromState()
  if (!configEquals(nextConfig, props.widget?.config || {})) {
    emit('update-widget', { id: props.widget.id, config: nextConfig })
  }
}

function buildConfigFromState() {
  return {
    entity: localEntity.value || '',
    filterId: localFilterId.value ?? null,
    filterLabel: localFilterLabel.value || '',
    limit: normalizeLimit(localLimit.value),
    columns: Array.isArray(localColumns.value) ? [...localColumns.value] : []
  }
}

function configEquals(a, b) {
  if (!a || !b) return false
  if (a.entity !== (b.entity || '')) return false
  if ((a.filterId ?? null) !== (b.filterId ?? null)) return false
  if ((a.filterLabel || '') !== (b.filterLabel || '')) return false
  if (normalizeLimit(a.limit) !== normalizeLimit(b.limit)) return false
  const aCols = Array.isArray(a.columns) ? a.columns : []
  const bCols = Array.isArray(b.columns) ? b.columns : []
  if (aCols.length !== bCols.length) return false
  for (let i = 0; i < aCols.length; i += 1) {
    if (aCols[i] !== bCols[i]) return false
  }
  return true
}

function normalizeLimit(value) {
  const parsed = Number.parseInt(value, 10)
  if (Number.isNaN(parsed) || parsed <= 0) return getListLimit()
  return parsed
}

function handleFilterChange(nextFilter) {
  if (!nextFilter || nextFilter.mode !== 'stored') {
    localFilterId.value = null
    localFilterLabel.value = ''
    activeFilter.value = null
    emitConfigUpdateIfNeeded()
    return
  }
  localFilterId.value = nextFilter.payload?.filter_id || null
  localFilterLabel.value = nextFilter.label || ''
  activeFilter.value = nextFilter
  emitConfigUpdateIfNeeded()
}

function rebuildGrid() {
  destroyGrid()
  clearGridState()
  if (!canRenderGrid.value) return
  initializeGrid()
}

function initializeGrid() {
  if (!gridWrapper.value || !localEntity.value || !resolvedColumns.value.length) return
  error.value = null
  isSearching.value = false
  clearGridState()
  gridSessionId.value += 1
  const sessionId = gridSessionId.value
  const server = API_BASE_URL
  const token = authStore.token || ''

  const fieldDefinitions = metadataStore.getEntityMetadata(localEntity.value)?.fields || {}
  const relationshipFieldKeys = Object.entries(fieldDefinitions)
    .filter(([, def]) => def?.type === 'relationship')
    .map(([key]) => key)

  const columns = resolvedColumns.value.map((colKey) => {
    const colName = formatFieldName(colKey)
    const fieldDef = fieldDefinitions[colKey]
    return {
      id: colKey,
      name: colName,
      sort: true,
      formatter: (cell, row, column) => {
        let record = null
        let recordId = null

        const idIndex = currentColumnKeys.value.indexOf('id')
        if (idIndex >= 0 && row[idIndex]) {
          recordId = row[idIndex]
          record = recordsById.value[recordId]
        }

        if (!record) {
          for (const cachedRecord of recordsCache.value) {
            let matches = true
            for (let i = 0; i < currentColumnKeys.value.length; i += 1) {
              const key = currentColumnKeys.value[i]
              const rowValue = row[i]
              const recordValue = cachedRecord[key]
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

        if (!record && fieldDef?.type === 'relationship' && cell) {
          for (const [recId, rels] of Object.entries(relationshipsCache.value)) {
            if (rels[colKey]?.id === cell) {
              recordId = recId
              record = recordsById.value[recId]
              break
            }
          }
        }

        if (!record) {
          if (fieldDef?.type === 'relationship' && cell) {
            return html(`<span class=\"text-muted\">${cell}</span>`)
          }
          if (fieldDef) {
            return html(formatFieldValueHTML(cell, fieldDef, null, true))
          }
          return cell || '-'
        }

        const value = record[colKey]
        const relationship = relationshipsCache.value[record.id]?.[colKey] || null
        return html(formatFieldValueHTML(value, fieldDef, relationship, true))
      }
    }
  })

  currentColumnKeys.value = [...resolvedColumns.value]

  const fetchGridData = async (opts) => {
    const params = parseGridParams(opts?.url, server, normalizeLimit(localLimit.value))
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
        if (params.search) {
          payload.search = params.search
        }
        response = await entityService.filter(localEntity.value, payload)
      } else {
        response = await entityService.getList(localEntity.value, {
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
    columns,
    search: {
      server: {
        url: (baseUrl, keyword) => {
          const url = baseUrl.startsWith('http')
            ? new URL(baseUrl)
            : new URL(baseUrl, server)

          const hasKeyword = keyword && keyword.trim()
          isSearching.value = !!hasKeyword && !activeFilter.value

          if (hasKeyword) {
            url.searchParams.set('search', keyword.trim())
          } else {
            url.searchParams.delete('search')
            isSearching.value = false
          }
          return url.toString()
        }
      }
    },
    sort: {
      multiColumn: false,
      server: {
        url: (baseUrl, columns) => {
          if (!columns.length) return baseUrl

          const col = columns[0]
          const dir = col.direction === 1 ? 'asc' : 'desc'
          const colIndex = col.index
          const colName = currentColumnKeys.value[colIndex] || 'date_modified'

          const url = baseUrl.startsWith('http')
            ? new URL(baseUrl)
            : new URL(baseUrl, server)

          const hasSearch = url.searchParams.has('search') && url.searchParams.get('search')
          isSearching.value = !!hasSearch && !activeFilter.value

          url.searchParams.set('sort', colName)
          url.searchParams.set('order', dir.toUpperCase())
          return url.toString()
        }
      }
    },
    pagination: {
      limit: normalizeLimit(localLimit.value),
      server: {
        url: (baseUrl, page, limit) => {
          const url = baseUrl.startsWith('http')
            ? new URL(baseUrl)
            : new URL(baseUrl, server)

          const hasSearch = url.searchParams.has('search') && url.searchParams.get('search')
          isSearching.value = !!hasSearch && !activeFilter.value

          url.searchParams.set('page', page + 1)
          url.searchParams.set('limit', limit)
          return url.toString()
        }
      }
    },
    server: {
      url: `${server}/${localEntity.value}`,
      headers: {
        Authorization: token
      },
      data: fetchGridData
    }
  })

  grid.value.on('error', () => {
    const message = activeFilter.value ? 'Failed to apply filter' : 'Failed to load records'
    error.value = message
    toastStore.error(message)
  })

  grid.value.on('cellClick', (cell, row) => {
    const cellElement = cell?.target || cell?.element || cell
    if (!cellElement) return

    const rowElement = cellElement.closest?.('tr') || cellElement.parentElement?.closest('tr')
    if (!rowElement) return

    const tbody = rowElement.closest('tbody')
    if (!tbody) return

    const dataRows = Array.from(tbody.querySelectorAll('tr'))
    const rowIndex = dataRows.indexOf(rowElement)
    if (rowIndex < 0 || rowIndex >= recordsCache.value.length) return

    const record = recordsCache.value[rowIndex]
    if (record?.id) {
      const entityMeta = metadataStore.getEntityMetadata(localEntity.value)
      const isPerson = entityMeta?.person === true
      let recordName = null

      if (isPerson && record.first_name && record.last_name) {
        recordName = `${record.first_name} ${record.last_name}`.trim()
      } else if (record.name) {
        recordName = record.name
      }

      openRecordWindow(localEntity.value, record.id, 'detail', recordName)
    }
  })

  window.handleRelationshipClick = (entity, recordId) => {
    openRecordWindow(entity, recordId, 'detail')
  }

  grid.value.render(gridWrapper.value)
}

function destroyGrid() {
  if (grid.value) {
    grid.value.destroy()
    grid.value = null
  }
}

function clearGridState() {
  recordsCache.value = []
  recordsById.value = {}
  relationshipsCache.value = {}
  currentColumnKeys.value = []
  if (gridWrapper.value) {
    gridWrapper.value.innerHTML = ''
  }
}

function parseGridParams(urlString, baseUrl, fallbackLimit) {
  const url = urlString ? new URL(urlString, baseUrl) : new URL(baseUrl)
  const page = Number.parseInt(url.searchParams.get('page') || '1', 10)
  const limit = Number.parseInt(url.searchParams.get('limit') || String(fallbackLimit), 10)
  const sort = url.searchParams.get('sort') || 'date_modified'
  const order = (url.searchParams.get('order') || 'DESC').toUpperCase()
  const search = url.searchParams.get('search')
  return {
    page: Number.isNaN(page) ? 1 : page,
    limit: Number.isNaN(limit) ? fallbackLimit : limit,
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
    const rowData = currentColumnKeys.value.map(key => record[key] ?? '')
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
</script>

<style scoped>
.list-widget-config {
  background-color: #f8fafc;
  border-color: #e2e8f0;
}

.list-grid-wrapper {
  min-height: 220px;
}

.list-widget-empty {
  text-align: center;
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

.is-searching :deep(.gridjs-pagination) {
  display: none !important;
}
</style>
