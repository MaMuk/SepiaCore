<template>
  <div class="user-owned-records-view">
    <div v-if="loading" class="text-center p-4">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <div v-else-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </div>

    <div v-else>
      <div class="mb-4">
        <h5 class="mb-1">{{ user?.name || 'User' }}</h5>
        <p class="text-muted mb-0">Owned Records</p>
      </div>

      <div v-if="ownedRecords.length === 0" class="text-center text-muted p-4">
        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
        <p class="mt-2 mb-0">No owned records</p>
      </div>

      <div v-else class="accordion" id="ownedRecordsAccordion">
        <div
          v-for="(item, index) in ownedRecords"
          :key="item.entity"
          class="accordion-item"
        >
          <h2 class="accordion-header" :id="`heading-${index}`">
            <button
              class="accordion-button"
              :class="{ collapsed: !item.expanded }"
              type="button"
              :data-bs-toggle="'collapse'"
              :data-bs-target="`#collapse-${index}`"
              :aria-expanded="item.expanded"
              :aria-controls="`collapse-${index}`"
              @click="item.expanded = !item.expanded"
            >
              <div class="d-flex justify-content-between align-items-center w-100 me-3">
                <span>{{ item.displayName }}</span>
                <span class="badge bg-secondary ms-2">{{ item.count }}</span>
              </div>
            </button>
          </h2>
          <div
            :id="`collapse-${index}`"
            class="accordion-collapse collapse"
            :class="{ show: item.expanded }"
            :aria-labelledby="`heading-${index}`"
            data-bs-parent="#ownedRecordsAccordion"
          >
            <div class="accordion-body p-0">
              <div class="list-group list-group-flush">
                <a
                  v-for="record in item.records"
                  :key="record.id"
                  href="#"
                  class="list-group-item list-group-item-action"
                  @click.prevent="handleRecordClick(item.entity, record.id)"
                >
                  <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">
                      {{ getRecordName(record, item.entity) }}
                    </h6>
                    <small class="text-muted">
                      {{ formatDate(record.date_modified || record.date_created) }}
                    </small>
                  </div>
                </a>
                <div v-if="item.loading" class="list-group-item text-center p-3">
                  <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
                <div v-else-if="item.error" class="list-group-item text-danger">
                  {{ item.error }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import api from '../services/api'

const props = defineProps({
  userId: {
    type: String,
    required: true
  },
  onRecordClick: {
    type: Function,
    default: null
  }
})

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const loading = ref(true)
const error = ref(null)
const user = ref(null)
const ownedRecords = ref([])

onMounted(async () => {
  await loadOwnedRecords()
  
  // Set up accordion expansion listener
  nextTick(() => {
    const accordion = document.getElementById('ownedRecordsAccordion')
    if (accordion) {
      accordion.addEventListener('show.bs.collapse', (event) => {
        const index = parseInt(event.target.id.replace('collapse-', ''))
        const item = ownedRecords.value[index]
        if (item && item.records.length === 0) {
          loadEntityRecords(item)
        }
      })
    }
  })
})

async function loadOwnedRecords() {
  loading.value = true
  error.value = null

  try {
    const response = await api.get(`/users/${props.userId}/owned-records`)
    user.value = response.data.user
    ownedRecords.value = Object.values(response.data.ownedRecords).map(item => ({
      ...item,
      expanded: false,
      records: [],
      loading: false,
      error: null
    }))
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load owned records'
    toastStore.error('Failed to load owned records')
  } finally {
    loading.value = false
  }
}

async function loadEntityRecords(item) {
  if (item.records.length > 0) {
    // Already loaded
    return
  }

  item.loading = true
  item.error = null

  try {
    // Load all records and filter by owner on the frontend
    // The API doesn't support owner filtering, so we'll get all and filter
    const response = await api.get(`/${item.entity}?limit=1000`)
    const allRecords = response.data.records || []
    // Filter records by owner
    item.records = allRecords.filter(record => record.owner === props.userId)
  } catch (err) {
    item.error = err.response?.data?.error || 'Failed to load records'
    toastStore.error(`Failed to load ${item.displayName} records`)
  } finally {
    item.loading = false
  }
}

function handleRecordClick(entity, recordId) {
  if (props.onRecordClick) {
    props.onRecordClick({ entity, recordId })
  }
}

function getRecordName(record, entityName) {
  const entityMeta = metadataStore.getEntityMetadata(entityName)
  const isPerson = entityMeta?.person === true

  if (isPerson && record.first_name && record.last_name) {
    return `${record.first_name} ${record.last_name}`.trim()
  } else if (record.name) {
    return record.name
  }
  return record.id?.substring(0, 8) || 'Unnamed'
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

</script>

<style scoped>
.user-owned-records-view {
  min-height: 200px;
}

.accordion-button {
  font-weight: 500;
}

.accordion-body {
  max-height: 400px;
  overflow-y: auto;
}

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}
</style>

