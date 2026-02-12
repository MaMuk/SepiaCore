<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Entity Capabilities</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <h6 class="text-muted">Action Console</h6>
            <div class="form-check">
              <input
                id="cap-action-console-active"
                v-model="actionConsoleActive"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-action-console-active">
                Available in Action Console
              </label>
            </div>
            <div class="form-check mt-2">
              <input
                id="cap-action-console-admin"
                v-model="actionConsoleRequiresAdmin"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-action-console-admin">
                Requires admin
              </label>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="text-muted">Filter Suggestions</h6>
            <div class="form-check">
              <input
                id="cap-filter-suggestions-active"
                v-model="listFilterSuggestionsActive"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-filter-suggestions-active">
                Show entity in filter suggestions
              </label>
            </div>
            <div class="form-check mt-2">
              <input
                id="cap-filter-suggestions-admin"
                v-model="listFilterSuggestionsRequiresAdmin"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-filter-suggestions-admin">
                Requires admin
              </label>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="text-muted">Quick Form</h6>
            <div class="form-check">
              <input
                id="cap-quick-form-active"
                v-model="quickFormActive"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-quick-form-active">
                Allow quick form creation
              </label>
            </div>
            <div class="form-check mt-2">
              <input
                id="cap-quick-form-admin"
                v-model="quickFormRequiresAdmin"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-quick-form-admin">
                Requires admin
              </label>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="text-muted">List Widget</h6>
            <div class="form-check">
              <input
                id="cap-list-widget-active"
                v-model="listWidgetActive"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-list-widget-active">
                Allow list widget
              </label>
            </div>
            <div class="form-check mt-2">
              <input
                id="cap-list-widget-admin"
                v-model="listWidgetRequiresAdmin"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-list-widget-admin">
                Requires admin
              </label>
            </div>
          </div>

          <div class="mb-3">
            <h6 class="text-muted">Graph Widget</h6>
            <div class="form-check">
              <input
                id="cap-graph-widget-active"
                v-model="graphWidgetActive"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-graph-widget-active">
                Allow graph reporting
              </label>
            </div>
            <div class="form-check mt-2">
              <input
                id="cap-graph-widget-admin"
                v-model="graphWidgetRequiresAdmin"
                class="form-check-input"
                type="checkbox"
              />
              <label class="form-check-label" for="cap-graph-widget-admin">
                Requires admin
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close" :disabled="saving">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" @click="save" :disabled="saving">
            <span
              v-if="saving"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Save Capabilities
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useToastStore } from '../stores/toast'
import api from '../services/api'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  entity: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toastStore = useToastStore()
const saving = ref(false)

const actionConsoleActive = ref(true)
const actionConsoleRequiresAdmin = ref(false)
const listFilterSuggestionsActive = ref(true)
const listFilterSuggestionsRequiresAdmin = ref(false)
const quickFormActive = ref(true)
const quickFormRequiresAdmin = ref(false)
const listWidgetActive = ref(true)
const listWidgetRequiresAdmin = ref(false)
const graphWidgetActive = ref(true)
const graphWidgetRequiresAdmin = ref(false)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

watch(isVisible, (next) => {
  if (next) {
    loadCapabilities()
  }
})

watch(
  () => props.entity,
  () => {
    if (isVisible.value) {
      loadCapabilities()
    }
  }
)

function toBoolean(value) {
  return value === true || value === 'true' || value === 1 || value === '1'
}

function normalizeCapability(entry, defaults = { active: true, requires_admin: false }) {
  if (entry === undefined || entry === null) {
    return { ...defaults }
  }
  if (typeof entry === 'boolean' || typeof entry === 'string') {
    return { active: toBoolean(entry), requires_admin: defaults.requires_admin }
  }
  if (typeof entry === 'object') {
    return {
      active: entry.active !== undefined ? toBoolean(entry.active) : defaults.active,
      requires_admin: entry.requires_admin !== undefined ? toBoolean(entry.requires_admin) : defaults.requires_admin
    }
  }
  return { ...defaults }
}

function loadCapabilities() {
  const capabilities = props.entity?.capabilities || {}
  const actionConsole = normalizeCapability(capabilities['action-console'])
  const listFilter = normalizeCapability(capabilities['list-filter-suggestions'])
  const quickForm = normalizeCapability(capabilities['quick-form'])
  const listWidget = normalizeCapability(capabilities['list-widget'])
  const graphWidget = normalizeCapability(capabilities['graph-widget'])

  actionConsoleActive.value = actionConsole.active
  actionConsoleRequiresAdmin.value = actionConsole.requires_admin
  listFilterSuggestionsActive.value = listFilter.active
  listFilterSuggestionsRequiresAdmin.value = listFilter.requires_admin
  quickFormActive.value = quickForm.active
  quickFormRequiresAdmin.value = quickForm.requires_admin
  listWidgetActive.value = listWidget.active
  listWidgetRequiresAdmin.value = listWidget.requires_admin
  graphWidgetActive.value = graphWidget.active
  graphWidgetRequiresAdmin.value = graphWidget.requires_admin
}

function buildCapabilitiesPayload() {
  const base = { ...(props.entity?.capabilities || {}) }
  base['action-console'] = {
    active: actionConsoleActive.value,
    requires_admin: actionConsoleRequiresAdmin.value
  }
  base['list-filter-suggestions'] = {
    active: listFilterSuggestionsActive.value,
    requires_admin: listFilterSuggestionsRequiresAdmin.value
  }
  base['quick-form'] = {
    active: quickFormActive.value,
    requires_admin: quickFormRequiresAdmin.value
  }
  base['list-widget'] = {
    active: listWidgetActive.value,
    requires_admin: listWidgetRequiresAdmin.value
  }
  base['graph-widget'] = {
    active: graphWidgetActive.value,
    requires_admin: graphWidgetRequiresAdmin.value
  }
  return base
}

async function save() {
  if (!props.entity?.name) return
  saving.value = true
  try {
    await api.post('/modulebuilder/updateCapabilities', {
      entity: props.entity.name,
      capabilities: buildCapabilitiesPayload()
    })
    toastStore.success('Capabilities updated successfully!')
    emit('saved')
    close()
  } catch (error) {
    toastStore.error(error.response?.data?.message || 'Failed to update capabilities')
  } finally {
    saving.value = false
  }
}

function close() {
  isVisible.value = false
}

function handleBackdropClick() {
  if (saving.value) return
  close()
}
</script>
