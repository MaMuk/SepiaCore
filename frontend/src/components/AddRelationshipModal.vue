<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-link-45deg me-2"></i>
            Add {{ getEntityDisplayName(relatedEntity) }}
          </h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <!-- Tabs for Link Existing vs Create New -->
          <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                :class="{ active: activeTab === 'link' }"
                @click="activeTab = 'link'"
                type="button"
              >
                <i class="bi bi-link me-1"></i>
                Link Existing
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link"
                :class="{ active: activeTab === 'create' }"
                @click="activeTab = 'create'"
                type="button"
              >
                <i class="bi bi-plus-circle me-1"></i>
                Create New
              </button>
            </li>
          </ul>

          <!-- Link Existing Tab -->
          <div v-if="activeTab === 'link'" class="tab-content">
            <div class="mb-3">
              <label for="relationshipSearchInput" class="form-label">
                Search {{ getEntityDisplayName(relatedEntity) }}
              </label>
            </div>
            <RelationshipSearchSelect
              input-id="relationshipSearchInput"
              :related-entity="relatedEntity"
              :disabled="!!linking"
              :busy-id="linking"
              :reset-key="resetKey"
              @select="selectRecord"
            />
          </div>

          <!-- Create New Tab -->
          <div v-if="activeTab === 'create'" class="tab-content">
            <RecordDetailView
              :entity-name="relatedEntity"
              :initial-mode="'create'"
              @saved="handleRecordCreated"
              @cancel="close"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            @click="close"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useMetadataStore } from '../stores/metadata'
import { useToastStore } from '../stores/toast'
import api from '../services/api'
import RecordDetailView from './RecordDetailView.vue'
import RelationshipSearchSelect from './RelationshipSearchSelect.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  parentEntity: {
    type: String,
    required: true
  },
  parentId: {
    type: String,
    required: true
  },
  relatedEntity: {
    type: String,
    required: true
  },
  relType: {
    type: String,
    required: true,
    validator: (value) => ['one_to_many', 'many_to_many'].includes(value)
  },
  relName: {
    type: String,
    default: null
  },
  relField: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const metadataStore = useMetadataStore()
const toastStore = useToastStore()

const activeTab = ref('link')
const linking = ref(null)
const resetKey = ref(0)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Reset when modal opens
watch(isVisible, (newVal) => {
  if (newVal) {
    activeTab.value = 'link'
    linking.value = null
    resetKey.value += 1
  }
})

async function selectRecord(record) {
  linking.value = record.id

  try {
    let url
    if (props.relType === 'many_to_many') {
      url = `/relationship/many_to_many/${props.parentEntity}/${props.parentId}/${record.id}/${props.relatedEntity}/${props.relName}`
      await api.post(url)
    } else {
      url = `/relationship/one_to_many/${props.parentEntity}/${props.parentId}/${record.id}/${props.relatedEntity}/${props.relField}`
      await api.post(url)
    }

    toastStore.success('Relationship added successfully')
    emit('saved')
    close()
  } catch (err) {
    console.error('Error adding relationship:', err)
    toastStore.error(err.response?.data?.message || 'Failed to add relationship')
  } finally {
    linking.value = null
  }
}

function handleRecordCreated(data) {
  // After creating a new record, automatically link it
  if (data?.record?.id) {
    selectRecord({ id: data.record.id })
  }
}

function getEntityDisplayName(entityName) {
  if (!entityName) return ''
  return metadataStore.formatEntityName(entityName)
}

function close() {
  isVisible.value = false
  activeTab.value = 'link'
  linking.value = null
}

function handleBackdropClick() {
  close()
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

.nav-tabs {
  border-bottom: 1px solid #dee2e6;
}

.nav-tabs .nav-link {
  color: #495057;
  border: none;
  border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link:hover {
  border-color: #dee2e6;
  border-bottom-color: #dee2e6;
}

.nav-tabs .nav-link.active {
  color: #0d6efd;
  border-bottom-color: #0d6efd;
  background-color: transparent;
}

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
