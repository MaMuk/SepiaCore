<template>
  <div class="subpanel" v-if="subpanelDef">
    <div class="subpanel-header d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">
        <i class="bi bi-list-ul me-2"></i>
        {{ getEntityDisplayName(subpanelDef.entity) }}
        <span class="badge bg-secondary ms-2">{{ records.length }}</span>
      </h6>
      <button
        type="button"
        class="btn btn-sm btn-primary"
        @click="openAddModal"
        :title="`Add ${getEntityDisplayName(subpanelDef.entity)}`"
      >
        <i class="bi bi-plus-circle me-1"></i>
        Add
      </button>
    </div>

    <div v-if="loading" class="text-center p-3">
      <div class="spinner-border spinner-border-sm" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <div v-else-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </div>

    <div v-else-if="records.length === 0" class="text-center text-muted p-3">
      <i class="bi bi-inbox" style="font-size: 2rem;"></i>
      <p class="mt-2 mb-0">No related records</p>
    </div>

    <div v-else class="table-responsive">
      <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th v-for="fieldName in subpanelFields" :key="fieldName" style="white-space: nowrap;">
              {{ formatFieldName(fieldName) }}
            </th>
            <th style="width: 80px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td v-for="fieldName in subpanelFields" :key="fieldName">
              <FieldRenderer
                :field-name="fieldName"
                :field-def="fieldDefinitions[fieldName]"
                :value="record[fieldName]"
                :relationship="relationship[record.id]?.[fieldName]"
                :mode="'detail'"
                :form-id="`subpanel-${subpanelKey}-${record.id}`"
                @relationship-click="handleRelationshipClick"
              />
            </td>
            <td>
              <div class="btn-group btn-group-sm" role="group">
                <button
                  type="button"
                  class="btn btn-outline-primary"
                  @click="viewRecord(record.id)"
                  :title="`View ${getEntityDisplayName(subpanelDef.entity)}`"
                >
                  <i class="bi bi-eye"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-outline-danger"
                  @click="handleRemove(record)"
                  :disabled="removing === record.id"
                  :title="`Remove relationship`"
                >
                  <span
                    v-if="removing === record.id"
                    class="spinner-border spinner-border-sm"
                    role="status"
                    aria-hidden="true"
                  ></span>
                  <i v-else class="bi bi-x-circle"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Relationship Modal -->
    <AddRelationshipModal
      v-model="showAddModal"
      :parent-entity="parentEntity"
      :parent-id="parentId"
      :related-entity="subpanelDef.entity"
      :rel-type="subpanelDef.rel_type"
      :rel-name="subpanelDef.rel_name"
      :rel-field="subpanelDef.rel_field"
      @saved="handleRelationshipAdded"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import api from '../services/api'
import FieldRenderer from './FieldRenderer.vue'
import AddRelationshipModal from './AddRelationshipModal.vue'

const props = defineProps({
  parentEntity: {
    type: String,
    required: true
  },
  parentId: {
    type: String,
    required: true
  },
  subpanelDef: {
    type: Object,
    required: true
  },
  subpanelKey: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['recordClick'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const loading = ref(false)
const error = ref(null)
const records = ref([])
const fieldDefinitions = ref({})
const subpanelFields = ref([])
const relationship = ref({})
const removing = ref(null)
const showAddModal = ref(false)

onMounted(() => {
  loadSubpanelData()
})

watch(() => props.parentId, () => {
  if (props.parentId) {
    loadSubpanelData()
  }
})

async function loadSubpanelData() {
  if (!props.parentId || !props.subpanelDef) return

  loading.value = true
  error.value = null

  try {
    let url
    if (props.subpanelDef.rel_type === 'many_to_many') {
      url = `/subpanel/many_to_many/${props.parentEntity}/${props.parentId}/${props.subpanelDef.entity}/${props.subpanelDef.rel_name}`
    } else {
      url = `/subpanel/one_to_many/${props.parentEntity}/${props.parentId}/${props.subpanelDef.entity}/${props.subpanelDef.rel_field}`
    }

    const response = await api.get(url)
    records.value = response.data.records || []
    fieldDefinitions.value = response.data.field_definitions || {}
    subpanelFields.value = response.data.subpanel_fields || []
    relationship.value = response.data.relationship || {}
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load subpanel data'
    toastStore.error('Failed to load subpanel data')
  } finally {
    loading.value = false
  }
}

function getEntityDisplayName(entityName) {
  if (!entityName) return ''
  return metadataStore.formatEntityName(entityName)
}

function formatFieldName(fieldName) {
  return fieldName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function viewRecord(recordId) {
  emit('recordClick', {
    entity: props.subpanelDef.entity,
    recordId: recordId
  })
}

function handleRelationshipClick({ entity, recordId }) {
  emit('recordClick', { entity, recordId })
}

async function handleRemove(record) {
  const recordName = record.name || `${record.first_name || ''} ${record.last_name || ''}`.trim() || record.id
  const message = `Are you sure you want to remove "${recordName}" from this relationship?`

  if (!confirm(message)) {
    return
  }

  removing.value = record.id

  try {
    let url
    if (props.subpanelDef.rel_type === 'many_to_many') {
      url = `/relationship/many_to_many/${props.parentEntity}/${props.parentId}/${record.id}/${props.subpanelDef.entity}/${props.subpanelDef.rel_name}`
      await api.delete(url)
    } else {
      url = `/relationship/one_to_many/${props.parentEntity}/${props.parentId}/${record.id}/${props.subpanelDef.entity}/${props.subpanelDef.rel_field}`
      await api.delete(url)
    }

    toastStore.success('Relationship removed successfully')
    await loadSubpanelData()
  } catch (err) {
    console.error('Error removing relationship:', err)
    toastStore.error(err.response?.data?.message || 'Failed to remove relationship')
  } finally {
    removing.value = null
  }
}

function openAddModal() {
  showAddModal.value = true
}

function handleRelationshipAdded() {
  showAddModal.value = false
  loadSubpanelData()
}
</script>

<style scoped>
.subpanel {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #dee2e6;
}

.subpanel .table-responsive {
  margin-right: -1rem;
  margin-left: -1rem;
}

.subpanel-header {
  padding-bottom: 0.5rem;
}

.table {
  margin-bottom: 0;
}

.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
}
</style>

