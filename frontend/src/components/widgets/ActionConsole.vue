<template>
  <div class="action-console">
    <div ref="outputRef" class="console-output">
      <div v-for="(entry, index) in history" :key="index" class="console-line">
        <span v-if="entry.prefix" class="console-prefix">{{ entry.prefix }}</span>
        <span class="console-text">{{ entry.text }}</span>
      </div>
      <div class="console-line console-active">
        <span class="console-prefix">$</span>
        <div class="console-input-wrapper">
          <span v-if="ghostSuffix" class="console-ghost">
            <span class="console-ghost-prefix">{{ input }}</span>
            <span class="console-ghost-suffix">{{ ghostSuffix }}</span>
          </span>
          <input
            v-model="input"
            type="text"
            class="console-input-field"
            placeholder="Type a command..."
            @input="updateSuggestions"
            @keydown="handleKeydown"
          />
        </div>
      </div>
      <div v-if="showHistoryNav && historyWindow.length" class="console-suggestions console-history">
        <div
          v-for="(entry, index) in historyWindow"
          :key="`history-${index}`"
          class="console-suggestion history-entry"
          :class="{ active: index === 1 }"
        >
          {{ entry || '' }}
        </div>
      </div>
      <div v-else-if="searchResults.length || searchLoading" class="console-suggestions console-search-results">
        <div v-if="searchLoading" class="console-suggestion console-search-loading">
          Searching...
        </div>
        <button
          v-for="(result, index) in searchResults"
          :key="`${result.entityName}-${result.id}`"
          class="console-suggestion"
          type="button"
          :class="{ active: index === activeSearchResult }"
          @click="openSearchResult(result, { auto: false })"
        >
          {{ result.label || result.name || result.id }}
        </button>
      </div>
      <div v-else-if="suggestions.length" class="console-suggestions">
        <button
          v-for="(suggestion, index) in suggestions"
          :key="suggestion"
          class="console-suggestion"
          type="button"
          :class="{ active: index === activeSuggestion }"
          @click="applySuggestion(suggestion)"
        >
          {{ suggestion }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { useMetadataStore } from '../../stores/metadata'
import { useAuthStore } from '../../stores/auth'
import { useWinbox } from '../../composables/useWinbox'
import entityService from '../../services/entityService'
import { LIST_LIMIT } from '../../config'

const metadataStore = useMetadataStore()
const authStore = useAuthStore()
const { openRecordWindow } = useWinbox()

const input = ref('')
const history = ref([])
const suggestions = ref([])
const activeSuggestion = ref(0)
const outputRef = ref(null)
const historyNavIndex = ref(null)
const showHistoryNav = ref(false)
const searchResults = ref([])
const activeSearchResult = ref(0)
const searchLoading = ref(false)
let searchTimeout = null
let latestSearchKey = ''
const lastAutoOpenedKey = ref('')

const commands = ['search', 'new', 'filter', 'help', 'clear']

const actionConsoleEntities = computed(() => {
  const entityMap = metadataStore.entities || {}
  return Object.keys(entityMap).filter((entityName) => {
    return isEntityAllowed(entityName)
  })
})

function ensureMetadataLoaded() {
  if (!metadataStore.metadata && !metadataStore.loading) {
    return metadataStore.fetchMetadata().catch(() => {})
  }
  return Promise.resolve()
}

function updateSuggestions() {
  const value = input.value
  suggestions.value = buildSuggestions(value)
  activeSuggestion.value = 0
  if (!value) {
    showHistoryNav.value = false
    historyNavIndex.value = null
  }
  scheduleSearch(value)
}

function buildSuggestions(value) {
  const trimmed = value.trimStart()
  if (!trimmed) {
    return commands.slice(0, 5)
  }

  const parts = trimmed.split(/\s+/)
  const commandPart = parts[0]
  const hasTrailingSpace = value.endsWith(' ')
  const currentToken = hasTrailingSpace ? '' : (parts[parts.length - 1] || '')

  if (parts.length === 1) {
    if ((commandPart === 'search' || commandPart === 'new') && hasTrailingSpace) {
      return actionConsoleEntities.value
    }
    return commands.filter((cmd) => cmd.startsWith(currentToken))
  }

  if (commandPart === 'search' || commandPart === 'new') {
    if (parts.length === 2 && !hasTrailingSpace) {
      return actionConsoleEntities.value.filter((entity) => entity.startsWith(currentToken))
    }
    if (parts.length === 2 && hasTrailingSpace && !currentToken) {
      return actionConsoleEntities.value
    }
    return []
  }

  if (commandPart === 'filter') {
    return ['not implemented yet'];
    if (!value.includes(':')) {
      return [':filtername']
    }
    if (currentToken.startsWith(':')) {
      return [':filtername']
    }
  }

  return []
}

function applyTopSuggestion() {
  if (!suggestions.value.length) return
  applySuggestion(suggestions.value[activeSuggestion.value] || suggestions.value[0])
}

function applySuggestion(suggestion) {
  input.value = replaceCurrentToken(input.value, suggestion, true)
  updateSuggestions()
}

async function handleEnter() {
  const command = input.value.trim()
  if (!command) return

  const searchContext = parseSearchInput(command)
  if (searchContext && searchResults.value.length === 1) {
    openSearchResult(searchResults.value[0], { auto: false })
    return
  }

  history.value.push({ prefix: '$', text: command })
  pushHistory(command)
  await runCommand(command)
  input.value = ''
  updateSuggestions()
  scrollToBottom()
  showHistoryNav.value = false
  historyNavIndex.value = null
}

function handleKeydown(event) {
  if (event.key === 'Tab') {
    event.preventDefault()
    if (searchResults.value.length) {
      if (searchResults.value.length === 1) {
        openSearchResult(searchResults.value[0], { auto: false })
      } else {
        cycleSearchResult(event.shiftKey ? -1 : 1)
      }
    } else if (suggestions.value.length === 1) {
      applyTopSuggestion()
    } else if (suggestions.value.length) {
      cycleSuggestion(event.shiftKey ? -1 : 1)
    }
    return
  }
  if (event.key === ' ' && suggestions.value.length) {
    event.preventDefault()
    applyTopSuggestion()
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    navigateHistory(-1)
    return
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    navigateHistory(1)
    return
  }
  if (event.key === 'Delete') {
    if (historyNavIndex.value !== null) {
      event.preventDefault()
      deleteHighlightedHistory()
    }
    return
  }
  if (event.key === 'Escape') {
    event.preventDefault()
    clearHistoryNav()
    return
  }
  if (event.key === 'Enter') {
    event.preventDefault()
    if (searchResults.value.length) {
      const result = searchResults.value[activeSearchResult.value]
      if (result) {
        openSearchResult(result, { auto: false })
        return
      }
    }
    handleEnter()
  }
}

function cycleSuggestion(direction) {
  if (!suggestions.value.length) return
  const total = suggestions.value.length
  const nextIndex = (activeSuggestion.value + direction + total) % total
  activeSuggestion.value = nextIndex
}

function replaceCurrentToken(value, suggestion, addSpace) {
  const endsWithSpace = value.endsWith(' ')
  if (endsWithSpace) {
    return `${value}${suggestion}${addSpace ? ' ' : ''}`
  }

  const lastSpace = value.lastIndexOf(' ')
  if (lastSpace === -1) {
    return `${suggestion}${addSpace ? ' ' : ''}`
  }

  const before = value.slice(0, lastSpace + 1)
  return `${before}${suggestion}${addSpace ? ' ' : ''}`
}

async function runCommand(command) {
  if (command === 'clear') {
    history.value = []
    return
  }

  if (command === 'help') {
    history.value.push({ prefix: '>', text: 'search [entity] query - Find records. Results appear below as you type.'})
    history.value.push({ prefix: '>', text: 'new [entity] - Open a create window for the entity.' })
    history.value.push({ prefix: '>', text: 'filter [filter name] - Not available yet.' })
    history.value.push({ prefix: '>', text: 'help - Show this help.' })
    history.value.push({ prefix: '>', text: 'clear - Clear the console output.' })
    return
  }

  if (command.startsWith('search ')) {
    await runSearchCommand(command)
    return
  }

  if (command.startsWith('new ')) {
    await runNewCommand(command)
    return
  }

  if (command.startsWith('filter ')) {
    history.value.push({ prefix: '>', text: 'Filters are not available yet.' })
    return
  }

  history.value.push({ prefix: '>', text: `Unknown command: ${command}` })
}

function isEntityAllowed(entityName) {
  const entityMeta = metadataStore.entities?.[entityName]
  if (!entityMeta) return false
  const capability = entityMeta?.capabilities?.['action-console'] || null
  const activeValue = capability?.active
  const requiresAdminValue = capability?.requires_admin
  const isActive =
    activeValue === undefined ||
    activeValue === null ||
    activeValue === true ||
    activeValue === 'true'
  const requiresAdmin =
    requiresAdminValue === true || requiresAdminValue === 'true'
  if (!isActive) return false
  if (requiresAdmin && !authStore.isAdmin) return false
  return true
}

function getRecordDisplayName(entityName, record) {
  if (!record) return null
  const entityMeta = metadataStore.getEntityMetadata(entityName)
  const isPerson = entityMeta?.person === true
  if (isPerson && record.first_name && record.last_name) {
    return `${record.first_name} ${record.last_name}`.trim()
  }
  if (record.name) {
    return record.name
  }
  return null
}

function parseSearchCommand(command) {
  const match = command.match(/^search\s+(\S+)\s*(.*)$/i)
  if (!match) return null
  return {
    entityName: match[1],
    query: (match[2] || '').trim()
  }
}

async function runSearchCommand(command) {
  await ensureMetadataLoaded()
  const parsed = parseSearchCommand(command)
  if (!parsed) {
    history.value.push({ prefix: '>', text: 'Usage: search [entity] query' })
    return
  }

  const { entityName, query } = parsed
  if (!query) {
    history.value.push({ prefix: '>', text: 'Search requires a query value.' })
    return
  }

  if (!isEntityAllowed(entityName)) {
    history.value.push({
      prefix: '>',
      text: `Entity "${entityName}" is not available in the action console.`
    })
    return
  }

  history.value.push({
    prefix: '>',
    text: `Searching ${entityName} for "${query}"...`
  })

  try {
    const response = await entityService.getList(entityName, {
      page: 1,
      limit: LIST_LIMIT,
      search: query
    })
    const records = response?.records || []
    if (!records.length) {
      history.value.push({
        prefix: '>',
        text: `No ${entityName} records found for "${query}".`
      })
      return
    }

    if (records.length === 1) {
      const record = records[0]
      if (record?.id) {
        const recordName = getRecordDisplayName(entityName, record)
        openRecordWindow(entityName, record.id, 'detail', recordName)
        return
      }
    }

    history.value.push({
      prefix: '>',
      text: `Found ${records.length} ${entityName} records. Refine your search to open a single result.`
    })
    records.slice(0, 5).forEach((record) => {
      const recordName = getRecordDisplayName(entityName, record)
      const label = recordName ? `${recordName} (${record.id})` : record.id
      history.value.push({ prefix: '>', text: `- ${label}` })
    })
  } catch (err) {
    history.value.push({
      prefix: '>',
      text: `Search failed for ${entityName}.`
    })
  }
}

async function runNewCommand(command) {
  await ensureMetadataLoaded()
  const match = command.match(/^new\s+(\S+)$/i)
  if (!match) {
    history.value.push({ prefix: '>', text: 'Usage: new [entity]' })
    return
  }

  const entityName = match[1]
  if (!isEntityAllowed(entityName)) {
    history.value.push({
      prefix: '>',
      text: `Entity "${entityName}" is not available in the action console.`
    })
    return
  }

  openRecordWindow(entityName, null, 'create')
}

function parseSearchInput(value) {
  const trimmed = value.trimStart()
  const match = trimmed.match(/^search\s+(\S+)\s*(.*)$/i)
  if (!match) return null
  return {
    entityName: match[1],
    query: (match[2] || '').trim()
  }
}

function scheduleSearch(value) {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
    searchTimeout = null
  }

  const parsed = parseSearchInput(value)
  if (!parsed || !parsed.query) {
    resetSearchResults()
    return
  }

  const { entityName, query } = parsed
  if (!isEntityAllowed(entityName)) {
    resetSearchResults()
    return
  }

  const searchKey = `${entityName}::${query}`
  latestSearchKey = searchKey
  searchTimeout = setTimeout(() => {
    runLiveSearch(entityName, query, searchKey)
  }, 200)
}

function resetSearchResults() {
  searchResults.value = []
  activeSearchResult.value = 0
  searchLoading.value = false
}

async function runLiveSearch(entityName, query, searchKey) {
  searchLoading.value = true
  try {
    const response = await entityService.getList(entityName, {
      page: 1,
      limit: LIST_LIMIT,
      search: query
    })
    if (latestSearchKey !== searchKey) return
    const records = response?.records || []
    searchResults.value = records.map((record) => {
      const recordName = getRecordDisplayName(entityName, record)
      const label = recordName ? `${recordName} (${record.id})` : record.id
      return {
        id: record.id,
        name: recordName,
        label,
        entityName,
        record
      }
    })
    activeSearchResult.value = 0
    maybeAutoOpenSingleResult(searchKey)
  } catch (err) {
    if (latestSearchKey !== searchKey) return
    searchResults.value = []
  } finally {
    if (latestSearchKey === searchKey) {
      searchLoading.value = false
    }
  }
}

function maybeAutoOpenSingleResult(searchKey) {
  if (searchResults.value.length !== 1) return
  const result = searchResults.value[0]
  if (!result?.id) return
  const openKey = `${searchKey}::${result.id}`
  if (openKey === lastAutoOpenedKey.value) return
  lastAutoOpenedKey.value = openKey
  openSearchResult(result, { auto: true })
}

function cycleSearchResult(direction) {
  const total = searchResults.value.length
  if (!total) return
  const nextIndex = (activeSearchResult.value + direction + total) % total
  activeSearchResult.value = nextIndex
}

function openSearchResult(result, { auto }) {
  if (!result?.id) return
  const recordName = result.name
  openRecordWindow(result.entityName, result.id, 'detail', recordName)
  if (auto) {
    scrollToBottom()
  }
}

const ghostSuffix = computed(() => {
  if (!input.value) return ''
  if (showHistoryNav.value) return ''
  if (!suggestions.value.length) return ''
  if (searchResults.value.length) return ''
  const suggestion = suggestions.value[activeSuggestion.value] || ''
  if (!suggestion) return ''
  return getGhostSuffix(input.value, suggestion)
})

function getGhostSuffix(value, suggestion) {
  const endsWithSpace = value.endsWith(' ')
  if (endsWithSpace) {
    return `${suggestion} `
  }
  const lastSpace = value.lastIndexOf(' ')
  const currentToken = lastSpace === -1 ? value : value.slice(lastSpace + 1)
  if (!suggestion.startsWith(currentToken)) return ''
  return `${suggestion.slice(currentToken.length)} `
}

function navigateHistory(direction) {
  const commandsHistory = getHistoryCommands()
  if (!commandsHistory.length) return

  if (historyNavIndex.value === null) {
    if (direction > 0) {
      return
    }
    historyNavIndex.value = commandsHistory.length - 1
  } else {
    const nextIndex = historyNavIndex.value + direction
    if (nextIndex >= commandsHistory.length) {
      historyNavIndex.value = null
      input.value = ''
      showHistoryNav.value = false
      updateSuggestions()
      return
    }
    historyNavIndex.value = Math.max(nextIndex, 0)
  }

  const entry = commandsHistory[historyNavIndex.value] || ''
  input.value = entry
  showHistoryNav.value = true
  updateSuggestions()
}

const historyWindow = computed(() => {
  const commandsHistory = getHistoryCommands()
  if (!commandsHistory.length || historyNavIndex.value === null) return []
  const index = historyNavIndex.value
  return [
    commandsHistory[index - 1] || '',
    commandsHistory[index] || '',
    commandsHistory[index + 1] || ''
  ]
})

function deleteHighlightedHistory() {
  const store = loadHistoryStore()
  const userId = getUserId()
  const commandsHistory = Array.isArray(store[userId]) ? store[userId] : []
  if (historyNavIndex.value === null || !commandsHistory.length) {
    clearHistoryNav()
    return
  }

  commandsHistory.splice(historyNavIndex.value, 1)
  store[userId] = commandsHistory
  saveHistoryStore(store)
  clearHistoryNav()
}

function clearHistoryNav() {
  historyNavIndex.value = null
  showHistoryNav.value = false
  input.value = ''
  updateSuggestions()
}

function getHistoryKey() {
  return 'action_console_history'
}

function getUserId() {
  return authStore.username || 'anonymous'
}

function loadHistoryStore() {
  const key = getHistoryKey()
  const raw = localStorage.getItem(key)
  if (!raw) return {}
  try {
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function saveHistoryStore(store) {
  const key = getHistoryKey()
  localStorage.setItem(key, JSON.stringify(store))
}

function getHistoryCommands() {
  const store = loadHistoryStore()
  const userId = getUserId()
  const entries = Array.isArray(store[userId]) ? store[userId] : []
  return entries.map((entry) => entry.command || '')
}

function pushHistory(command) {
  const store = loadHistoryStore()
  const userId = getUserId()
  const historyList = Array.isArray(store[userId]) ? store[userId] : []
  historyList.push({ command, date: new Date().toISOString() })
  store[userId] = historyList.slice(-100)
  saveHistoryStore(store)
}

function scrollToBottom() {
  nextTick(() => {
    if (!outputRef.value) return
    outputRef.value.scrollTop = outputRef.value.scrollHeight
  })
}

onMounted(() => {
  ensureMetadataLoaded()
  updateSuggestions()
})
</script>

<style scoped>
.action-console {
  font-family: "Courier New", Courier, monospace;
  font-size: 0.85rem;
  height: 100%;
}

.console-output {
  background: #0f1216;
  color: #e5e7eb;
  border-radius: 0.4rem;
  padding: 0.5rem;
  height: 100%;
  min-height: 120px;
  overflow-y: auto;
}

.console-line {
  display: flex;
  gap: 0.4rem;
  line-height: 1.4;
}

.console-active {
  margin-top: 0.4rem;
}

.console-prefix {
  color: #94a3b8;
}

.console-input-field {
  background: transparent;
  border: none;
  color: inherit;
  flex: 1;
  min-width: 120px;
  outline: none;
  font-family: inherit;
  position: relative;
  z-index: 2;
  width: 100%;
}

.console-input-wrapper {
  position: relative;
  flex: 1;
  min-width: 120px;
}

.console-ghost {
  position: absolute;
  top: 0;
  left: 0;
  color: rgba(148, 163, 184, 0.5);
  white-space: pre;
  pointer-events: none;
  font-family: inherit;
  z-index: 1;
}

.console-ghost-prefix {
  color: transparent;
}

.console-ghost-suffix {
  color: rgba(148, 163, 184, 0.5);
}

.console-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.3rem;
  padding: 0.2rem 0.3rem;
  border-radius: 0;
  background: rgba(15, 18, 22, 0.5);
}

.console-suggestion {
  border: 1px solid rgba(226, 232, 240, 0.35);
  background: transparent;
  color: #e2e8f0;
  border-radius: 0;
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
}

.console-suggestion.active,
.console-suggestion:hover {
  background: rgba(148, 163, 184, 0.2);
}

.console-search-loading {
  border-style: dashed;
  color: rgba(226, 232, 240, 0.7);
}

.console-history .history-entry {
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: transparent;
  color: #cbd5f5;
}

.console-history .history-entry.active {
  background: rgba(148, 163, 184, 0.25);
  color: #e2e8f0;
}
</style>
