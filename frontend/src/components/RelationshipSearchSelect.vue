<template>
  <div class="relationship-search-select">
    <div class="input-group">
      <input
        type="text"
        class="form-control"
        v-model="displayQuery"
        :id="inputId"
        :name="inputName"
        :placeholder="placeholder"
        :disabled="isDisabled"
        @input="handleSearch"
        @blur="handleBlur"
      />
      <button
        v-if="showClear"
        type="button"
        class="btn btn-outline-secondary"
        :disabled="isDisabled"
        @click="clearSelection"
        aria-label="Clear selection"
      >
        ×
      </button>
    </div>

    <div v-if="searching" class="mt-2">
      <div class="spinner-border spinner-border-sm" role="status">
        <span class="visually-hidden">Searching...</span>
      </div>
    </div>

    <div v-if="searchResults.length > 0" class="list-group">
      <button
        v-for="result in searchResults"
        :key="result.id"
        type="button"
        class="list-group-item list-group-item-action"
        :disabled="isDisabled || busyId === result.id"
        @click="selectRecord(result)"
      >
        <div class="d-flex justify-content-between align-items-center">
          <span>{{ result.name || result.id }}</span>
          <span
            v-if="busyId === result.id"
            class="spinner-border spinner-border-sm"
            role="status"
          ></span>
          <i v-else class="bi bi-chevron-right"></i>
        </div>
      </button>
    </div>

    <div
      v-else-if="showStatusMessages && isSearchingQuery && displayQuery && !searching"
      class="text-muted text-center py-3"
    >
      No records found
    </div>

    <div
      v-else-if="showStatusMessages && !isSearchingQuery && !selectedRecord && !displayQuery"
      class="text-muted text-center py-3"
    >
      Enter a search term to find records
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useToastStore } from '../stores/toast'
import api from '../services/api'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  relatedEntity: {
    type: String,
    required: true
  },
  inputId: {
    type: String,
    default: null
  },
  inputName: {
    type: String,
    default: null
  },
  initialRecord: {
    type: Object,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  },
  minSearchLength: {
    type: Number,
    default: 2
  },
  debounceMs: {
    type: Number,
    default: 300
  },
  placeholder: {
    type: String,
    default: 'Type to search...'
  },
  showStatusMessages: {
    type: Boolean,
    default: true
  },
  busyId: {
    type: [String, Number],
    default: null
  },
  resetKey: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['update:modelValue', 'select', 'cleared'])

const toastStore = useToastStore()

const searching = ref(false)
const searchResults = ref([])
const selectedRecord = ref(null)
const displayQuery = ref('')
const isSearchingQuery = ref(false)

const isDisabled = computed(() => props.disabled || !props.relatedEntity)
const showClear = computed(() => !!selectedRecord.value && !isDisabled.value)

let searchTimeout = null

function syncFromInitialRecord(record) {
  if (record?.id) {
    selectedRecord.value = record
    displayQuery.value = record.name || String(record.id)
    return true
  }
  return false
}

watch(
  () => props.initialRecord,
  (newRecord) => {
    if (!newRecord) return
    if (!selectedRecord.value || selectedRecord.value.id !== newRecord.id) {
      syncFromInitialRecord(newRecord)
    }
  },
  { immediate: true }
)

watch(
  () => props.modelValue,
  (newValue) => {
    if (!newValue) {
      selectedRecord.value = null
      displayQuery.value = ''
      return
    }

    if (selectedRecord.value?.id === newValue) {
      if (!displayQuery.value) {
        displayQuery.value = selectedRecord.value.name || String(newValue)
      }
      return
    }

    if (props.initialRecord?.id === newValue) {
      syncFromInitialRecord(props.initialRecord)
      return
    }

    selectedRecord.value = { id: newValue, name: String(newValue) }
    displayQuery.value = String(newValue)
  },
  { immediate: true }
)

watch(
  () => props.resetKey,
  () => resetState()
)

function resetState() {
  searching.value = false
  searchResults.value = []
  selectedRecord.value = null
  displayQuery.value = ''
  isSearchingQuery.value = false
  if (searchTimeout) {
    clearTimeout(searchTimeout)
    searchTimeout = null
  }
}

async function handleSearch() {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  if (!props.relatedEntity) {
    searchResults.value = []
    return
  }

  const query = displayQuery.value.trim()
  if (!query || query.length < props.minSearchLength) {
    searchResults.value = []
    isSearchingQuery.value = false
    return
  }

  isSearchingQuery.value = true
  searchTimeout = setTimeout(async () => {
    searching.value = true
    try {
      const response = await api.get(`/relationship/${props.relatedEntity}`, {
        params: { search: query }
      })
      searchResults.value = response.data.filter(item => item.id !== '')
    } catch (err) {
      console.error('Error searching:', err)
      toastStore.error('Failed to search records')
      searchResults.value = []
    } finally {
      searching.value = false
    }
  }, props.debounceMs)
}

function selectRecord(record) {
  selectedRecord.value = record
  displayQuery.value = record.name || String(record.id)
  searchResults.value = []
  isSearchingQuery.value = false
  emit('update:modelValue', record.id)
  emit('select', record)
}

function clearSelection() {
  selectedRecord.value = null
  displayQuery.value = ''
  searchResults.value = []
  isSearchingQuery.value = false
  emit('update:modelValue', '')
  emit('cleared')
}

function handleBlur() {
  isSearchingQuery.value = false
  if (selectedRecord.value) {
    displayQuery.value = selectedRecord.value.name || String(selectedRecord.value.id)
    searchResults.value = []
  }
}
</script>

<style scoped>
.list-group-item {
  cursor: pointer;
}

.list-group-item:hover {
  background-color: #f8f9fa;
}

.list-group-item:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
