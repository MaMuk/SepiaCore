<template>
  <div class="dashboard">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Dashboard</h2>
      <span v-if="loading" class="text-muted small">Loading...</span>
    </div>

    <DashboardToolbar
      :dashboards="dashboards"
      :active-dashboard="activeDashboard"
      :is-edit-mode="isEditMode"
      :is-loading="loading"
      :is-default="isDefaultDashboard"
      @select="handleSelect"
      @toggle-edit="handleToggleEdit"
      @cancel-edit="handleCancelEdit"
      @toggle-default="handleToggleDefault"
      @create="handleCreate"
      @delete="handleDelete"
    />

    <div v-if="isEditMode" class="d-flex align-items-center gap-2 flex-wrap mb-3 dashboard-edit-controls">
      <select
        class="form-select form-select-sm dashboard-widget-select"
        v-model="selectedWidgetType"
      >
        <option v-for="type in widgetTypes" :key="type" :value="type">
          {{ type }}
        </option>
      </select>
      <button class="btn btn-sm btn-outline-success" type="button" @click="handleAddWidget">
        Add Widget
      </button>
    </div>

    <div v-if="showCreateForm" class="card mb-3">
      <div class="card-body">
        <div class="d-flex flex-column gap-2">
          <label class="form-label mb-0">Dashboard name</label>
          <input
            v-model="newDashboardName"
            type="text"
            class="form-control"
            placeholder="Enter dashboard name"
          />
          <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" @click="submitCreate">
              Create
            </button>
            <button class="btn btn-outline-secondary btn-sm" @click="cancelCreate">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger" role="alert">
      {{ error }}
    </div>

    <div v-if="!loading && widgets.length === 0" class="alert alert-light border">
      This dashboard has no widgets yet.
    </div>

    <DashboardGrid
      v-else
      ref="gridRef"
      :key="gridKey"
      :widgets="widgets"
      :editable="isEditMode"
      @remove-widget="handleRemoveWidget"
      @update-title="handleUpdateTitle"
      @update-widget="handleUpdateWidget"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useDashboard } from '../composables/useDashboard'
import { getListLimit } from '../config'
import DashboardToolbar from '../components/dashboard/DashboardToolbar.vue'
import DashboardGrid from '../components/dashboard/DashboardGrid.vue'

const gridRef = ref(null)
const isEditMode = ref(false)
const showCreateForm = ref(false)
const newDashboardName = ref('')
const selectedWidgetType = ref('list')
const editSnapshot = ref([])

const widgetTypes = ['list', 'graph', 'quickForm', 'action-console']

const {
  dashboards,
  activeDashboard,
  defaultDashboard,
  widgets,
  loading,
  error,
  loadDefault,
  selectDashboard,
  saveWidgets,
  createDashboard,
  setDefault,
  removeDashboard
} = useDashboard()

const gridKey = computed(() => activeDashboard.value?.id || 'default-dashboard')
const isDefaultDashboard = computed(() => {
  if (!activeDashboard.value || !defaultDashboard.value) return false
  return activeDashboard.value.id === defaultDashboard.value.id
})

onMounted(async () => {
  await loadDefault()
})

async function handleSelect(id) {
  if (activeDashboard.value?.id === id) return
  isEditMode.value = false
  await selectDashboard(id)
}

async function handleToggleEdit() {
  if (!activeDashboard.value) return

  if (!isEditMode.value) {
    editSnapshot.value = JSON.parse(JSON.stringify(widgets.value))
    isEditMode.value = true
    return
  }

  const layout = gridRef.value?.saveLayout?.() || []
  const mergedWidgets = mergeWidgetLayout(widgets.value, layout)

  await saveWidgets(activeDashboard.value.id, mergedWidgets)
  widgets.value = mergedWidgets
  isEditMode.value = false
}

function handleCancelEdit() {
  widgets.value = JSON.parse(JSON.stringify(editSnapshot.value))
  isEditMode.value = false
}

function handleCreate() {
  showCreateForm.value = true
  newDashboardName.value = ''
}

function mergeWidgetLayout(currentWidgets, layout) {
  const layoutById = new Map(
    layout.map((node) => [String(node.id), node])
  )

  return currentWidgets.map((widget) => {
    const node = layoutById.get(String(widget.id))
    if (!node) return widget
    return {
      ...widget,
      x: node.x,
      y: node.y,
      w: node.w,
      h: node.h
    }
  })
}

function handleAddWidget() {
  if (!isEditMode.value) return
  const type = selectedWidgetType.value || 'list'
  const newWidget = {
    id: generateId(),
    type,
    title: defaultTitleForType(type),
    x: null,
    y: null,
    w: 4,
    h: 2
  }

  if (type === 'list') {
    newWidget.config = {
      entity: '',
      filterId: null,
      filterLabel: '',
      limit: getListLimit(),
      columns: []
    }
  }
  widgets.value = [...widgets.value, newWidget]
}

function defaultTitleForType(type) {
  switch (type) {
    case 'graph':
      return 'New Graph'
    case 'quickForm':
      return 'New Quick Form'
    case 'action-console':
      return 'New Action Console'
    case 'list':
    default:
      return 'New List'
  }
}

function generateId() {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID()
  }
  return `widget-${Math.random().toString(36).slice(2, 10)}`
}

async function submitCreate() {
  const name = newDashboardName.value.trim()
  const response = await createDashboard(name)
  showCreateForm.value = false
  newDashboardName.value = ''
  if (response?.activeDashboard?.id) {
    isEditMode.value = false
  }
}

function cancelCreate() {
  showCreateForm.value = false
  newDashboardName.value = ''
}

async function handleToggleDefault() {
  if (!activeDashboard.value) return
  await setDefault(activeDashboard.value.id)
}

async function handleDelete() {
  if (!activeDashboard.value) return
  if (!window.confirm('Delete this dashboard?')) return
  const deletingId = activeDashboard.value.id
  await removeDashboard(deletingId)
  isEditMode.value = false
}

function handleRemoveWidget(id) {
  widgets.value = widgets.value.filter((widget) => String(widget.id) !== String(id))
}

function handleUpdateTitle({ id, title }) {
  widgets.value = widgets.value.map((widget) => {
    if (String(widget.id) !== String(id)) return widget
    return { ...widget, title }
  })
}

function handleUpdateWidget({ id, config }) {
  if (!config) return
  widgets.value = widgets.value.map((widget) => {
    if (String(widget.id) !== String(id)) return widget
    return {
      ...widget,
      config: {
        ...(widget.config || {}),
        ...config
      }
    }
  })
}
</script>

<style scoped>
.dashboard {
  padding: 1rem;
}

.dashboard-widget-select {
  width: 180px;
}

.dashboard-edit-controls .btn,
.dashboard-edit-controls .dashboard-widget-select {
  white-space: nowrap;
}
</style>
