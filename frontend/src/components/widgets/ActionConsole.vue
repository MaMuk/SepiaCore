<template>
  <div class="action-console h-100 d-flex flex-column">
    <div class="console-input-row">
      <span class="console-prefix input-prefix">$</span>
      <div class="input-shell">
        <input
            ref="inputRef"
            v-model="inputValue"
            type="text"
            class="console-input"
            autocomplete="off"
            spellcheck="false"
            placeholder="Type a command..."
            @keydown="handleKeyDown"
            @input="handleInputChange"
        />
        <div class="ghost-layer" aria-hidden="true">
          <span class="ghost-base">{{ inputValue }}</span>
          <span v-if="ghostText" class="ghost-suffix">{{ ghostText }}</span>
        </div>
      </div>
    </div>

    <div v-if="panelMode" class="console-panel">
      <template v-if="panelMode === 'history'">
        <div class="panel-label">History</div>
        <div
            v-for="item in historyPreview"
            :key="`preview-${item.role}`"
            class="preview-line"
            :class="{ active: item.role === 'current' }"
        >
          <span class="preview-role">{{ item.role === 'current' ? '•' : '' }}</span>
          <span class="preview-text">{{ item.value }}</span>
        </div>
      </template>

      <template v-else-if="panelMode === 'results'">
        <div class="panel-label">
          {{ resultsPersisted ? 'Results' : 'Live Results' }}
        </div>
        <div class="chip-row">
          <button
              v-for="(result, index) in resultItems"
              :key="`result-${result.entityName}-${result.id}`"
              class="chip"
              :class="{ active: index === activeResultIndex }"
              type="button"
              @mousedown.prevent
              @click="openResult(result, index)"
          >
            <span class="chip-label">{{ result.label }}</span>
            <small class="chip-meta">{{ formatEntityName(result.entityName) }}</small>
          </button>
        </div>
        <div v-if="!resultItems.length && resultStatusMessage" class="panel-empty">
          {{ resultStatusMessage }}
        </div>
      </template>

      <template v-else-if="panelMode === 'suggestions'">
        <div class="chip-row">
          <button
              v-for="(suggestion, index) in suggestions"
              :key="`suggestion-${suggestion.type}-${suggestion.value}-${index}`"
              class="chip"
              :class="{ active: index === activeSuggestionIndex }"
              type="button"
              @mousedown.prevent
              @click="acceptSuggestion(suggestion, index)"
          >
            <span class="chip-label">{{ suggestion.label }}</span>
          </button>
        </div>
      </template>
    </div>

    <div ref="outputRef" class="console-output flex-grow-1">
      <div
          v-for="(line, index) in historyLines"
          :key="`history-${index}-${line.text}`"
          class="console-line"
      >
        <span class="console-prefix" :class="line.prefix === '$' ? 'prefix-user' : 'prefix-system'">
          {{ line.prefix }}
        </span>
        <span class="console-text">{{ line.text }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useMetadataStore } from '../../stores/metadata'
import { useAuthStore } from '../../stores/auth'
import { useToastStore } from '../../stores/toast'
import { useWinbox } from '../../composables/useWinbox'
import entityService from '../../services/entityService'
import api from '../../services/api'
import { getListLimit } from '../../config'

const COMMANDS = ['search', 'filter', 'new', 'help', 'clear']
const OPERATORS = ['eq', 'contains', 'starts_with', 'ends_with', 'not_empty', 'empty', 'gt', 'gte', 'lt', 'lte', 'in']
const HISTORY_STORAGE_PREFIX = 'action_console_history'
const LIVE_SEARCH_DEBOUNCE = 200
const LIVE_FILTER_DEBOUNCE = 250
const RELATIONSHIP_SUGGEST_DEBOUNCE = 200
const RELATIONSHIP_MIN_SEARCH = 2
const RESULT_PREVIEW_LIMIT = 5

const metadataStore = useMetadataStore()
const authStore = useAuthStore()
const toastStore = useToastStore()
const { openRecordWindow } = useWinbox()

const inputValue = ref('')
const historyLines = ref([])
const suggestions = ref([])
const activeSuggestionIndex = ref(0)
const resultItems = ref([])
const activeResultIndex = ref(0)
const resultsSource = ref(null) // 'live-search', 'live-filter', 'command-search', 'command-filter'
const resultsPersisted = ref(false)
const persistedResultsCommand = ref('')
const resultStatusMessage = ref('')

const commandHistory = ref([])
const historyNavActive = ref(false)
const historyNavIndex = ref(-1)

const storedFilters = ref({})
const storedFiltersLoading = ref({})
const listLimit = ref(getListLimit())

const inputRef = ref(null)
const outputRef = ref(null)

let liveSearchTimeout = null
let liveFilterTimeout = null
let liveSearchRequestId = 0
let liveFilterRequestId = 0
let relationshipSuggestTimeout = null
let relationshipSuggestRequestId = 0
let lastRelationshipSuggestSignature = ''
let lastRelationshipSuggestIds = []

const availableEntities = computed(() => {
  const entities = metadataStore.entities || {}
  return Object.keys(entities)
      .filter(name => isEntityAllowed(name))
      .map(name => ({
        name,
        displayName: formatEntityName(name),
        meta: entities[name]
      }))
})

const filterSuggestionEntities = computed(() => {
  return availableEntities.value.filter(entity => isEntityAllowedForCapability(entity.name, 'list-filter-suggestions'))
})

const historyStorageKey = computed(() => {
  const user = authStore.username || 'anonymous'
  return `${HISTORY_STORAGE_PREFIX}_${user}`
})

const panelMode = computed(() => {
  if (historyNavActive.value && historyPreview.value.length) return 'history'
  if (resultItems.value.length > 0 || resultStatusMessage.value) return 'results'
  if (suggestions.value.length > 0) return 'suggestions'
  return null
})

const historyPreview = computed(() => {
  if (!historyNavActive.value || historyNavIndex.value < 0) return []
  const items = []
  const prev = commandHistory.value[historyNavIndex.value - 1]
  const current = commandHistory.value[historyNavIndex.value]
  const next = commandHistory.value[historyNavIndex.value + 1]
  if (prev !== undefined) items.push({ role: 'previous', value: prev })
  if (current !== undefined) items.push({ role: 'current', value: current })
  if (next !== undefined) items.push({ role: 'next', value: next })
  return items
})

const ghostText = computed(() => {
  if (panelMode.value !== 'suggestions') return ''
  if (!inputValue.value.trim()) return ''
  const suggestion = suggestions.value[activeSuggestionIndex.value]
  if (!suggestion) return ''
  const token = getCurrentToken()
  const insertValue = suggestion.insertValue || suggestion.value || ''
  if (!insertValue.toLowerCase().startsWith(token.toLowerCase())) return ''
  return insertValue.slice(token.length)
})

onMounted(async () => {
  loadCommandHistory()
  try {
    if (!metadataStore.metadata) {
      await metadataStore.fetchMetadata()
    }
  } catch (error) {
    toastStore.error('Failed to load metadata for Action Console')
  }
  updateSuggestions()
  focusInput()
})

onBeforeUnmount(() => {
  clearTimeout(liveSearchTimeout)
  clearTimeout(liveFilterTimeout)
  clearTimeout(relationshipSuggestTimeout)
})

watch(historyStorageKey, () => loadCommandHistory())

watch(
    () => historyLines.value.length,
    () => nextTick(scrollOutputToBottom)
)

watch(
    resultItems,
    (items) => {
      if (!items.length) {
        activeResultIndex.value = 0
        return
      }
      activeResultIndex.value = Math.min(activeResultIndex.value, items.length - 1)
    },
    { deep: true }
)

watch(
    inputValue,
    (newVal, oldVal) => {
      updateSuggestions()
      scheduleLiveQueries()
      if (resultsPersisted.value && newVal !== persistedResultsCommand.value) {
        clearResults()
      }
    }
)

function handleInputChange() {
  historyNavActive.value = false
}

function handleKeyDown(event) {
  if (event.key === 'Tab') {
    event.preventDefault()
    if (panelMode.value === 'results' && resultItems.value.length) {
      cycleResult(event.shiftKey ? -1 : 1)
    } else if (panelMode.value === 'suggestions' && suggestions.value.length) {
      if (suggestions.value.length === 1) {
        acceptSuggestion(suggestions.value[0], 0)
      } else {
        cycleSuggestion(event.shiftKey ? -1 : 1)
      }
    }
    return
  }

  if (event.key === ' ' || event.key === 'Spacebar') {
    if (panelMode.value === 'suggestions' && suggestions.value.length) {
      const suggestion = suggestions.value[activeSuggestionIndex.value]
      const tokens = tokenize(inputValue.value || '')
      const firstToken = (tokens[0] || '').toLowerCase()
      const isCommandOnly =
          tokens.length === 1 &&
          COMMANDS.includes(firstToken) &&
          inputValue.value.trim().toLowerCase() === firstToken

      if (isCommandOnly) {
        return
      }

      event.preventDefault()
      acceptSuggestion(suggestion, activeSuggestionIndex.value)
      return
    }
    return
  }

  if (event.key === 'Enter') {
    if (panelMode.value === 'results' && resultItems.value.length) {
      event.preventDefault()
      openResult(resultItems.value[activeResultIndex.value] || resultItems.value[0], activeResultIndex.value)
      return
    }
    event.preventDefault()
    executeCommand()
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    navigateHistory('up')
    return
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    navigateHistory('down')
    return
  }

  if (event.key === 'Escape') {
    event.preventDefault()
    resetHistoryNavigation()
    inputValue.value = ''
    clearLiveResults()
    return
  }

  if (event.key === 'Delete') {
    if (historyNavActive.value) {
      event.preventDefault()
      deleteHistoryEntry()
    }
  }
}

function updateSuggestions() {
  const input = inputValue.value || ''
  const tokens = tokenize(input)
  const commandToken = (tokens[0] || '').toLowerCase()
  const entityToken = tokens[1] || ''
  const fieldToken = tokens[2] || ''
  const operatorToken = tokens[3] || ''
  const valueToken = tokens.slice(4).join(' ')
  const usingStoredFilter = commandToken === 'filter' && fieldToken.startsWith('?')

  let list = []

  if (!commandToken || (!COMMANDS.includes(commandToken) && tokens.length === 1)) {
    list = filterByPrefix(COMMANDS, getCurrentToken()).map(value => ({
      value,
      label: value,
      type: 'command'
    }))
  } else if (commandToken === 'new') {
    if (!entityToken) {
      list = buildEntitySuggestions(entityToken)
    } else {
      list = []
    }
  } else if (commandToken === 'search') {
    if (tokens.length <= 2) {
      list = buildEntitySuggestions(entityToken)
    }
  } else if (commandToken === 'filter') {
    if (tokens.length <= 2) {
      list = buildEntitySuggestions(entityToken, 'filter')
    } else if (usingStoredFilter) {
      if (tokens.length <= 3) {
        list = buildStoredFilterSuggestions(entityToken, fieldToken)
      }
    } else {
      if (tokens.length === 3) {
        list = buildFieldSuggestions(entityToken, fieldToken)
      } else if (tokens.length === 4) {
        list = buildOperatorSuggestions(entityToken, fieldToken, operatorToken)
      } else if (tokens.length >= 5) {
        list = buildValueSuggestions(entityToken, fieldToken, operatorToken, valueToken, input)
      }
    }
  }

  suggestions.value = list
  activeSuggestionIndex.value = 0
}

function buildEntitySuggestions(token, mode = 'default') {
  const lower = (token || '').toLowerCase()
  const source = mode === 'filter' ? filterSuggestionEntities.value : availableEntities.value
  return source
      .filter(entity => !lower || entity.name.toLowerCase().startsWith(lower))
      .map(entity => ({
        value: entity.name,
        label: entity.name,
        type: 'entity'
      }))
}

function buildFieldSuggestions(entityName, token) {
  const entityMeta = metadataStore.getEntityMetadata(entityName) || {}
  const fields = Object.keys(entityMeta.fields || {})
  return filterByPrefix(fields, token).map(field => ({
    value: field,
    label: field,
    subLabel: 'field',
    type: 'field'
  }))
}

function buildOperatorSuggestions(entityName, fieldName, token) {
  const allowed = getAllowedOperators(entityName, fieldName)
  return filterByPrefix(allowed, token).map(op => ({
    value: op,
    label: op,
    subLabel: 'operator',
    type: 'operator'
  }))
}

function buildStoredFilterSuggestions(entityName, token) {
  const filters = ensureStoredFilters(entityName)
  const needle = (token || '?').replace('?', '').toLowerCase()
  return filters
      .filter(filter => !needle || filter.name.toLowerCase().startsWith(needle))
      .map(filter => ({
        value: filter.name,
        insertValue: `?${filter.name}`,
        label: filter.name,
        subLabel: 'stored filter',
        type: 'stored-filter'
      }))
}

function buildValueSuggestions(entityName, fieldName, operator, token, signature) {
  const fieldDef = getFieldDef(entityName, fieldName)
  if (!fieldDef) return []
  if (isOperatorWithoutValue(operator)) return []
  const allowed = getAllowedOperators(entityName, fieldName)
  if (!allowed.includes(operator)) return []

  const fieldType = fieldDef.type || 'text'
  const trimmedToken = token ?? ''

  if (fieldType === 'select') {
    return buildSelectValueSuggestions(fieldDef, operator, trimmedToken)
  }

  if (fieldType === 'relationship') {
    scheduleRelationshipValueSuggestions(fieldDef, operator, trimmedToken, signature)
    return []
  }

  return []
}

function buildSelectValueSuggestions(fieldDef, operator, token) {
  const options = normalizeSelectOptions(fieldDef?.options)
  if (!options.length) return []

  const { prefix, needle } = splitValueToken(token, operator)
  return filterByPrefix(options.map(option => option.value), needle).map(value => ({
    value,
    insertValue: `${prefix}${value}`,
    label: value,
    subLabel: 'select value',
    type: 'select-value'
  }))
}

function scheduleRelationshipValueSuggestions(fieldDef, operator, token, signature) {
  if (!fieldDef?.entity) {
    return
  }
  if (operator === 'in') {
    return
  }
  const query = token.trim()
  if (query.length < RELATIONSHIP_MIN_SEARCH) {
    return
  }

  clearTimeout(relationshipSuggestTimeout)
  relationshipSuggestTimeout = setTimeout(async () => {
    relationshipSuggestRequestId += 1
    const requestId = relationshipSuggestRequestId
    try {
      lastRelationshipSuggestSignature = ''
      lastRelationshipSuggestIds = []
      const response = await api.get(`/relationship/${fieldDef.entity}`, {
        params: { search: query }
      })
      if (requestId !== relationshipSuggestRequestId) return
      if (inputValue.value !== signature) return
      const results = Array.isArray(response.data) ? response.data : []
      const suggestionsList = results
        .filter(item => item?.id !== '')
        .map(item => ({
          value: String(item.id),
          insertValue: String(item.id),
          label: item?.name ? String(item.name) : String(item.id),
          subLabel: String(item.id),
          type: 'relationship-value'
        }))
      lastRelationshipSuggestSignature = signature
      lastRelationshipSuggestIds = suggestionsList.map(item => item.value)
      if (inputValue.value === signature) {
        suggestions.value = suggestionsList
        activeSuggestionIndex.value = 0
      }
    } catch (error) {
      if (requestId !== relationshipSuggestRequestId) return
    }
  }, RELATIONSHIP_SUGGEST_DEBOUNCE)
}

function acceptSuggestion(suggestion, index) {
  if (!suggestion) return
  activeSuggestionIndex.value = index
  const insertValue = suggestion.insertValue || suggestion.value
  const current = inputValue.value || ''
  const endsWithSpace = current.endsWith(' ')
  const lastSpace = current.lastIndexOf(' ')
  let nextInput = ''

  if (endsWithSpace) {
    nextInput = `${current}${insertValue} `
  } else if (lastSpace === -1) {
    nextInput = `${insertValue} `
  } else {
    nextInput = `${current.slice(0, lastSpace + 1)}${insertValue} `
  }

  inputValue.value = nextInput
  focusInput()
}

function cycleSuggestion(delta) {
  if (!suggestions.value.length) return
  const total = suggestions.value.length
  activeSuggestionIndex.value = (activeSuggestionIndex.value + delta + total) % total
}

function cycleResult(delta) {
  if (!resultItems.value.length) return
  const total = resultItems.value.length
  activeResultIndex.value = (activeResultIndex.value + delta + total) % total
}

function navigateHistory(direction) {
  if (!commandHistory.value.length) return
  if (!historyNavActive.value) {
    historyNavActive.value = true
    historyNavIndex.value = direction === 'up' ? commandHistory.value.length - 1 : 0
  } else {
    const delta = direction === 'up' ? -1 : 1
    const nextIndex = historyNavIndex.value + delta
    if (nextIndex < 0 || nextIndex >= commandHistory.value.length) {
      historyNavActive.value = false
      historyNavIndex.value = -1
      inputValue.value = ''
      return
    }
    historyNavIndex.value = nextIndex
  }
  inputValue.value = commandHistory.value[historyNavIndex.value] || ''
}

function deleteHistoryEntry() {
  if (!historyNavActive.value || historyNavIndex.value < 0) return
  commandHistory.value.splice(historyNavIndex.value, 1)
  persistCommandHistory()
  if (!commandHistory.value.length) {
    resetHistoryNavigation()
    inputValue.value = ''
    return
  }
  historyNavIndex.value = Math.min(historyNavIndex.value, commandHistory.value.length - 1)
  inputValue.value = commandHistory.value[historyNavIndex.value] || ''
}

function resetHistoryNavigation() {
  historyNavActive.value = false
  historyNavIndex.value = -1
}

async function executeCommand() {
  const rawInput = inputValue.value
  const trimmed = rawInput.trim()
  if (!trimmed) return

  addHistoryLine('$', rawInput)
  addCommandToHistory(trimmed)
  resetHistoryNavigation()

  const tokens = trimmed.split(' ')
  const command = (tokens[0] || '').toLowerCase()

  if (command === 'help') {
    printHelp()
    inputValue.value = ''
    return
  }

  if (command === 'clear') {
    historyLines.value = []
    clearResults()
    inputValue.value = ''
    return
  }

  if (command === 'new') {
    const entityName = tokens[1]
    if (!entityName) {
      addHistoryLine('>', 'Missing entity name')
      return
    }
    if (!isEntityAllowed(entityName)) {
      addHistoryLine('>', `${formatEntityName(entityName)} is not available`)
      return
    }
    openRecordWindow(entityName, null, 'create')
    addHistoryLine('>', `Opened create window for ${formatEntityName(entityName)}`)
    inputValue.value = ''
    return
  }

  if (command === 'search') {
    const entityName = tokens[1]
    const query = tokens.slice(2).join(' ').trim()
    if (!entityName) {
      addHistoryLine('>', 'Missing entity name')
      return
    }
    if (!isEntityAllowed(entityName)) {
      addHistoryLine('>', `${formatEntityName(entityName)} is not available`)
      return
    }
    if (!query) {
      addHistoryLine('>', 'Search query cannot be empty')
      return
    }
    runSearchCommand(entityName, query)
    return
  }

  if (command === 'filter') {
    const entityName = tokens[1]
    if (!entityName) {
      addHistoryLine('>', 'Missing entity name')
      return
    }
    if (!isEntityAllowed(entityName)) {
      addHistoryLine('>', `${formatEntityName(entityName)} is not available`)
      return
    }

    const remainder = tokens.slice(2)
    if (!remainder.length) {
      addHistoryLine('>', 'Filter requires a field/operator/value or stored filter')
      return
    }

    if (remainder[0].startsWith('?')) {
      const filterName = remainder.join(' ').replace(/^\?/, '')
      let filterDef = getStoredFilterByName(entityName, filterName)
      if (!filterDef) {
        await fetchStoredFilters(entityName)
        filterDef = getStoredFilterByName(entityName, filterName)
      }
      if (!filterDef) {
        addHistoryLine('>', `Stored filter "${filterName}" not found`)
        return
      }
      runStoredFilterCommand(entityName, filterDef)
      return
    }

    const field = remainder[0]
    const operator = remainder[1]
    const value = remainder.slice(2).join(' ')

    if (!field || !operator) {
      addHistoryLine('>', 'Filter requires field, operator, and value')
      return
    }
    if (!OPERATORS.includes(operator)) {
      addHistoryLine('>', `Operator must be one of: ${OPERATORS.join(', ')}`)
      return
    }
    const allowedOperators = getAllowedOperators(entityName, field)
    if (!allowedOperators.includes(operator)) {
      addHistoryLine('>', `Operator "${operator}" is not supported for ${field}`)
      return
    }
    if (!value && !isOperatorWithoutValue(operator)) {
      addHistoryLine('>', 'Filter value cannot be empty (except for not_empty/empty)')
      return
    }

    const normalizedValue = normalizeFilterValue(entityName, field, operator, value)
    if (normalizedValue === undefined) {
      addHistoryLine('>', 'Invalid filter value')
      return
    }
    runAdhocFilterCommand(entityName, field, operator, normalizedValue)
    return
  }

  addHistoryLine('>', 'Unknown command. Type "help" to see options.')
  inputValue.value = ''
}

async function runSearchCommand(entityName, query) {
  addHistoryLine('>', `Searching ${formatEntityName(entityName)}...`)
  try {
    const response = await entityService.getList(entityName, {
      page: 1,
      limit: listLimit.value,
      search: query
    })
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    applyResultState(items, 'command-search')

    const total = response?.total ?? items.length
    addHistoryLine('>', `Found ${total} record${total === 1 ? '' : 's'}`)
    if (items.length === 0) {
      addHistoryLine('>', 'No records found')
    } else {
      addHistoryLine('>', previewLabels(items))
    }
  } catch (error) {
    addHistoryLine('>', 'Search failed')
    clearResults()
  } finally {
    persistedResultsCommand.value = ''
    inputValue.value = ''
  }
}

async function runStoredFilterCommand(entityName, filterDef) {
  addHistoryLine('>', 'Running stored filter...')
  try {
    const response = await entityService.filter(entityName, {
      filter_id: filterDef.id,
      limit: listLimit.value,
      page: 1
    })
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    applyResultState(items, 'command-filter')

    const total = response?.total ?? items.length
    addHistoryLine('>', `Found ${total} record${total === 1 ? '' : 's'}`)
    if (items.length === 0) {
      addHistoryLine('>', 'No records found')
    } else {
      addHistoryLine('>', previewLabels(items))
    }
  } catch (error) {
    addHistoryLine('>', 'Filter failed')
    clearResults()
  } finally {
    persistedResultsCommand.value = ''
    inputValue.value = ''
  }
}

async function runAdhocFilterCommand(entityName, field, operator, value) {
  addHistoryLine('>', 'Running filter...')
  const payload = {
    filters: [
      { field, operator, value }
    ],
    limit: listLimit.value,
    page: 1
  }
  try {
    const response = await entityService.filter(entityName, payload)
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    applyResultState(items, 'command-filter')

    const total = response?.total ?? items.length
    addHistoryLine('>', `Found ${total} record${total === 1 ? '' : 's'}`)
    if (items.length === 0) {
      addHistoryLine('>', 'No records found')
    } else {
      addHistoryLine('>', previewLabels(items))
    }
  } catch (error) {
    addHistoryLine('>', 'Filter failed')
    clearResults()
  } finally {
    persistedResultsCommand.value = ''
    inputValue.value = ''
  }
}

function applyResultState(items, source) {
  resultItems.value = items
  resultsSource.value = source
  resultsPersisted.value = source.startsWith('command')
  activeResultIndex.value = 0
  resultStatusMessage.value = items.length ? '' : 'No records found'
}

function clearResults() {
  resultItems.value = []
  resultsSource.value = null
  resultsPersisted.value = false
  resultStatusMessage.value = ''
  activeResultIndex.value = 0
}

function clearLiveResults() {
  if (resultsSource.value && resultsSource.value.startsWith('live')) {
    clearResults()
  }
}

function scheduleLiveQueries() {
  clearTimeout(liveSearchTimeout)
  clearTimeout(liveFilterTimeout)

  const input = inputValue.value || ''
  const tokens = tokenize(input)
  const command = (tokens[0] || '').toLowerCase()

  if (command === 'search') {
    const entityName = tokens[1]
    const query = tokens.slice(2).join(' ').trim()
    if (entityName && query && isEntityAllowed(entityName)) {
      liveSearchTimeout = setTimeout(() => runLiveSearch(entityName, query), LIVE_SEARCH_DEBOUNCE)
    } else if (resultsSource.value === 'live-search') {
      clearResults()
    }
    return
  }

  if (command === 'filter') {
    const entityName = tokens[1]
    if (!entityName || !isEntityAllowed(entityName)) {
      if (resultsSource.value === 'live-filter') clearResults()
      return
    }

    const fieldToken = tokens[2] || ''
    const operatorToken = tokens[3] || ''

    if (fieldToken.startsWith('?')) {
      const filterDef = getStoredFilterByName(entityName, fieldToken.replace('?', ''))
      if (filterDef) {
        liveFilterTimeout = setTimeout(
            () => runLiveStoredFilter(entityName, filterDef),
            LIVE_FILTER_DEBOUNCE
        )
      } else if (resultsSource.value === 'live-filter') {
        clearResults()
      }
      return
    }

    const valueToken = tokens.slice(4).join(' ')
    const hasValue = valueToken || isOperatorWithoutValue(operatorToken)
    const allowedOperators = getAllowedOperators(entityName, fieldToken)

    if (fieldToken && operatorToken && OPERATORS.includes(operatorToken) && hasValue && allowedOperators.includes(operatorToken)) {
      if (shouldDeferRelationshipLiveFilter(entityName, fieldToken, operatorToken, valueToken)) {
        if (resultsSource.value === 'live-filter') clearResults()
        return
      }
      const normalizedValue = normalizeFilterValue(entityName, fieldToken, operatorToken, valueToken)
      if (normalizedValue === undefined) {
        if (resultsSource.value === 'live-filter') clearResults()
        return
      }
      const payload = {
        filters: [
          { field: fieldToken, operator: operatorToken, value: normalizedValue }
        ],
        limit: listLimit.value,
        page: 1
      }
      liveFilterTimeout = setTimeout(
          () => runLiveAdhocFilter(entityName, payload),
          LIVE_FILTER_DEBOUNCE
      )
    } else if (resultsSource.value === 'live-filter') {
      clearResults()
    }
    return
  }

  clearLiveResults()
}

async function runLiveSearch(entityName, query) {
  liveSearchRequestId += 1
  const requestId = liveSearchRequestId
  try {
    const response = await entityService.getList(entityName, {
      page: 1,
      limit: listLimit.value,
      search: query
    })
    if (requestId !== liveSearchRequestId) return
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    resultItems.value = items
    resultsSource.value = 'live-search'
    resultsPersisted.value = false
    activeResultIndex.value = 0
    resultStatusMessage.value = items.length ? '' : 'No records found'
  } catch (error) {
    if (requestId !== liveSearchRequestId) return
    clearResults()
  }
}

async function runLiveStoredFilter(entityName, filterDef) {
  liveFilterRequestId += 1
  const requestId = liveFilterRequestId
  try {
    const response = await entityService.filter(entityName, {
      filter_id: filterDef.id,
      limit: listLimit.value,
      page: 1
    })
    if (requestId !== liveFilterRequestId) return
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    resultItems.value = items
    resultsSource.value = 'live-filter'
    resultsPersisted.value = false
    activeResultIndex.value = 0
    resultStatusMessage.value = items.length ? '' : 'No records found'
  } catch (error) {
    if (requestId !== liveFilterRequestId) return
    clearResults()
  }
}

async function runLiveAdhocFilter(entityName, payload) {
  liveFilterRequestId += 1
  const requestId = liveFilterRequestId
  try {
    const response = await entityService.filter(entityName, payload)
    if (requestId !== liveFilterRequestId) return
    const records = extractRecords(response)
    const items = mapRecordsToResults(entityName, records)
    resultItems.value = items
    resultsSource.value = 'live-filter'
    resultsPersisted.value = false
    activeResultIndex.value = 0
    resultStatusMessage.value = items.length ? '' : 'No records found'
  } catch (error) {
    if (requestId !== liveFilterRequestId) return
    clearResults()
  }
}

function openResult(result, index) {
  if (!result) return
  activeResultIndex.value = index
  openRecordWindow(result.entityName, result.id, 'detail', result.name)
  addHistoryLine('>', `Opened ${result.label}`)
  focusInput()
}

function addHistoryLine(prefix, text) {
  historyLines.value.push({ prefix, text })
}

function addCommandToHistory(command) {
  commandHistory.value.push(command)
  if (commandHistory.value.length > 100) {
    commandHistory.value = commandHistory.value.slice(-100)
  }
  persistCommandHistory()
}

function persistCommandHistory() {
  try {
    localStorage.setItem(historyStorageKey.value, JSON.stringify(commandHistory.value))
  } catch (error) {
    // Ignore persistence errors
  }
}

function loadCommandHistory() {
  try {
    const raw = localStorage.getItem(historyStorageKey.value)
    if (raw) {
      commandHistory.value = JSON.parse(raw) || []
    } else {
      commandHistory.value = []
    }
  } catch (error) {
    commandHistory.value = []
  }
}

function printHelp() {
  addHistoryLine('>', 'Available commands:')
  addHistoryLine('>', 'search [entity] [query]')
  addHistoryLine('>', 'filter [entity] [field operator value]')
  addHistoryLine('>', 'filter [entity] ?[stored filter name]')
  addHistoryLine('>', 'new [entity]')
  addHistoryLine('>', 'help')
  addHistoryLine('>', 'clear')
}

function ensureStoredFilters(entityName) {
  if (!entityName) return []
  if (!storedFilters.value[entityName]) {
    storedFilters.value = {
      ...storedFilters.value,
      [entityName]: []
    }
    fetchStoredFilters(entityName)
  }
  return storedFilters.value[entityName]
}

async function fetchStoredFilters(entityName) {
  if (!entityName) return []
  if (storedFiltersLoading.value[entityName]) {
    return storedFilters.value[entityName] || []
  }

  storedFiltersLoading.value = {
    ...storedFiltersLoading.value,
    [entityName]: true
  }

  try {
    const response = await entityService.listFilters(entityName)
    const records = response?.records ?? response ?? []
    storedFilters.value = {
      ...storedFilters.value,
      [entityName]: Array.isArray(records) ? records : []
    }
    if (inputValue.value) {
      updateSuggestions()
    }
  } catch (error) {
    storedFilters.value = {
      ...storedFilters.value,
      [entityName]: storedFilters.value[entityName] || []
    }
  } finally {
    storedFiltersLoading.value = {
      ...storedFiltersLoading.value,
      [entityName]: false
    }
  }

  return storedFilters.value[entityName]
}

function getStoredFilterByName(entityName, name) {
  const filters = ensureStoredFilters(entityName)
  const lower = (name || '').toLowerCase()
  return filters.find(filter => filter.name.toLowerCase() === lower) || null
}

function getFieldDef(entityName, fieldName) {
  const entityMeta = metadataStore.getEntityMetadata(entityName) || {}
  return entityMeta.fields?.[fieldName] || null
}

function getAllowedOperators(entityName, fieldName) {
  const fieldDef = getFieldDef(entityName, fieldName)
  const fieldType = fieldDef?.type || 'text'

  const map = {
    boolean: ['eq'],
    checkbox: ['eq'],
    select: ['eq', 'in', 'not_empty', 'empty'],
    date: ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty', 'empty'],
    datetime: ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty', 'empty'],
    relationship: ['eq', 'not_empty', 'empty']
  }

  if (map[fieldType]) return map[fieldType]
  return ['eq', 'contains', 'starts_with', 'ends_with', 'not_empty', 'empty', 'gt', 'gte', 'lt', 'lte']
}

function isOperatorWithoutValue(operator) {
  return operator === 'not_empty' || operator === 'empty'
}

function normalizeFilterValue(entityName, fieldName, operator, rawValue) {
  if (isOperatorWithoutValue(operator)) return null
  const value = (rawValue ?? '').trim()
  if (!value) return undefined

  const fieldDef = getFieldDef(entityName, fieldName)
  const fieldType = fieldDef?.type || 'text'

  if (fieldType === 'boolean' || fieldType === 'checkbox') {
    const normalized = value.toLowerCase()
    if (['true', '1', 'yes', 'y', 'on'].includes(normalized)) return true
    if (['false', '0', 'no', 'n', 'off'].includes(normalized)) return false
    return undefined
  }

  if (operator === 'in') {
    const list = value.split(',').map(item => item.trim()).filter(Boolean)
    return list.length ? list : undefined
  }

  return value
}

function normalizeSelectOptions(options) {
  if (!options) return []
  if (Array.isArray(options)) {
    return options.map(option => ({
      value: String(option),
      label: String(option)
    }))
  }
  return Object.entries(options).map(([value, label]) => ({
    value: String(value),
    label: String(label)
  }))
}

function splitValueToken(token, operator) {
  if (operator !== 'in') {
    return { prefix: '', needle: token.trim() }
  }
  const raw = token || ''
  const lastComma = raw.lastIndexOf(',')
  if (lastComma === -1) {
    return { prefix: '', needle: raw.trim() }
  }
  const prefix = `${raw.slice(0, lastComma + 1)} `
  const needle = raw.slice(lastComma + 1).trim()
  return { prefix, needle }
}

function shouldDeferRelationshipLiveFilter(entityName, fieldName, operator, rawValue) {
  const fieldDef = getFieldDef(entityName, fieldName)
  if (!fieldDef || fieldDef.type !== 'relationship') return false
  if (isOperatorWithoutValue(operator)) return false
  if (operator !== 'eq') return false

  const value = (rawValue ?? '').trim()
  if (!value) return true

  if (value.length < RELATIONSHIP_MIN_SEARCH) {
    return true
  }

  if (lastRelationshipSuggestSignature !== inputValue.value) {
    return true
  }

  return !lastRelationshipSuggestIds.includes(value)
}

function extractRecords(response) {
  if (!response) return []
  if (Array.isArray(response)) return response
  if (Array.isArray(response.records)) return response.records
  if (Array.isArray(response.data)) return response.data
  if (Array.isArray(response.list)) return response.list
  return []
}

function mapRecordsToResults(entityName, records) {
  const entityMeta = metadataStore.getEntityMetadata(entityName) || {}
  return (records || []).map(record => {
    const name = buildRecordName(record, entityMeta)
    const label = name ? `${name} (${record.id})` : String(record.id)
    return {
      id: record.id,
      name,
      label,
      entityName,
      record
    }
  })
}

function buildRecordName(record, entityMeta) {
  const isPerson = toBoolean(entityMeta?.person)
  if (isPerson && record?.first_name && record?.last_name) {
    return `${record.first_name} ${record.last_name}`.trim()
  }
  if (record?.name) return record.name
  return record?.id ?? null
}

function previewLabels(items) {
  const preview = items.slice(0, RESULT_PREVIEW_LIMIT).map(item => item.label)
  return preview.join(' | ')
}

function isLiveQuery(input) {
  const tokens = tokenize(input)
  const command = (tokens[0] || '').toLowerCase()
  if (command === 'search') {
    return tokens[1] && tokens.slice(2).join(' ').trim()
  }
  if (command === 'filter') {
    const fieldToken = tokens[2] || ''
    if (fieldToken.startsWith('?')) {
      return true
    }
    return tokens.length >= 4
  }
  return false
}

function scrollOutputToBottom() {
  nextTick(() => {
    if (!outputRef.value) return
    outputRef.value.scrollTop = outputRef.value.scrollHeight
  })
}

function focusInput() {
  nextTick(() => {
    inputRef.value?.focus()
  })
}

function tokenize(value) {
  if (value === '') return ['']
  return value.split(' ')
}

function getCurrentToken() {
  const tokens = tokenize(inputValue.value || '')
  return tokens[tokens.length - 1] || ''
}

function filterByPrefix(list, token) {
  const lower = (token || '').toLowerCase()
  return list.filter(item => !lower || item.toLowerCase().startsWith(lower))
}

function formatEntityName(name) {
  return metadataStore.formatEntityName(name)
}

function formatFieldName(field) {
  return field
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ')
}

function toBoolean(value) {
  return value === true || value === 'true' || value === 1 || value === '1'
}

function isEntityAllowed(entityName) {
  const entityMeta = metadataStore.getEntityMetadata(entityName)
  if (!entityMeta) return false

  const capability = entityMeta.capabilities?.['action-console']
  let active = true
  let requiresAdmin = false

  if (capability !== undefined) {
    if (typeof capability === 'boolean' || typeof capability === 'string') {
      active = toBoolean(capability)
    } else if (typeof capability === 'object') {
      if (capability.active !== undefined) {
        active = toBoolean(capability.active)
      }
      if (capability.requires_admin !== undefined) {
        requiresAdmin = toBoolean(capability.requires_admin)
      }
    }
  }

  if (requiresAdmin && !authStore.isAdmin) return false
  return active
}

function isEntityAllowedForCapability(entityName, capabilityKey) {
  const entityMeta = metadataStore.getEntityMetadata(entityName)
  if (!entityMeta) return false

  const capability = entityMeta.capabilities?.[capabilityKey]
  let active = true
  let requiresAdmin = false

  if (capability !== undefined) {
    if (typeof capability === 'boolean' || typeof capability === 'string') {
      active = toBoolean(capability)
    } else if (typeof capability === 'object') {
      if (capability.active !== undefined) {
        active = toBoolean(capability.active)
      }
      if (capability.requires_admin !== undefined) {
        requiresAdmin = toBoolean(capability.requires_admin)
      }
    }
  }

  if (requiresAdmin && !authStore.isAdmin) return false
  return active
}
</script>

<style scoped>
.action-console {
  background: radial-gradient(circle at 20% 20%, #111827, #0b1223 65%);
  color: #e2e8f0;
  border-radius: 10px;
  border: 1px solid #1f2937;
  overflow: hidden;
  font-family: "SFMono-Regular", "JetBrains Mono", Menlo, Consolas, monospace;
}

.console-output {
  padding: 0.75rem 1rem 1rem;
  overflow-y: auto;
}

.console-line {
  display: flex;
  gap: 0.5rem;
  line-height: 1.4;
  font-size: 0.95rem;
}

.console-prefix {
  flex-shrink: 0;
  width: 1rem;
  text-align: right;
  opacity: 0.85;
}

.prefix-user {
  color: #38bdf8;
}

.prefix-system {
  color: #a5b4fc;
}

.console-text {
  flex: 1;
  white-space: pre-wrap;
}

.console-input-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 0.75rem 0.5rem 0.9rem;
  border-top: 1px solid #1e293b;
  position: relative;
  background: rgba(15, 23, 42, 0.9);
}

.input-prefix {
  color: #38bdf8;
}

.input-shell {
  position: relative;
  flex: 1;
}

.console-input {
  width: 100%;
  background: transparent;
  border: none;
  color: #e2e8f0;
  outline: none;
  font: inherit;
  padding: 0.2rem 0;
  z-index: 2;
  position: relative;
}

.console-input::placeholder {
  color: #475569;
}

.ghost-layer {
  position: absolute;
  inset: 0;
  color: #64748b;
  pointer-events: none;
  z-index: 1;
  overflow: hidden;
  white-space: pre;
  font: inherit;
  padding: 0.2rem 0;
}

.ghost-base {
  color: transparent;
}

.ghost-suffix {
  color: #475569;
}

.console-panel {
  padding: 0.5rem 0.75rem 0.75rem 0.75rem;
  border-top: 1px solid #1e293b;
  background: rgba(12, 19, 34, 0.95);
}

.panel-label {
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #94a3b8;
  margin-bottom: 0.25rem;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.chip {
  border: 1px solid #334155;
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 4px;
  padding: 0.35rem 0.65rem;
  font-size: 0.85rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  transition: border-color 0.12s ease, background-color 0.12s ease;
}

.chip:hover {
  border-color: #cbd5e1;
}

.chip.active {
  border-color: #e2e8f0;
  background: #111827;
}

.panel-empty {
  color: #94a3b8;
  font-size: 0.9rem;
  padding: 0.25rem 0.1rem 0;
}

.preview-line {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.2rem 0;
  color: #cbd5e1;
}

.preview-line.active {
  color: #ffffff;
  font-weight: 600;
}

.preview-role {
  width: 0.7rem;
  color: #38bdf8;
}

@media (max-width: 768px) {
  .chip-row {
    max-height: 140px;
    overflow-y: auto;
  }
}
</style>
