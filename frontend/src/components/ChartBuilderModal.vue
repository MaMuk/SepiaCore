<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="close"
  >
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Chart Builder</h5>
          <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
        </div>
        <div class="modal-body">
          <div v-if="optionsLoading" class="text-muted small">Loading report options...</div>
          <div v-else-if="optionsError" class="alert alert-danger py-1" role="alert">
            {{ optionsError }}
          </div>

          <div v-else class="chart-builder">
            <div class="builder-section">
              <label class="form-label small text-muted">Chart Type</label>
              <div class="btn-group btn-group-sm flex-wrap" role="group">
                <button
                  v-for="type in chartTypeOptions"
                  :key="type.value"
                  type="button"
                  class="btn"
                  :class="localDefinition.chartType === type.value ? 'btn-primary' : 'btn-outline-primary'"
                  @click="setChartType(type.value)"
                >
                  {{ type.label }}
                </button>
              </div>
            </div>

            <div v-if="localDefinition.chartType" class="builder-section">
              <label class="form-label small text-muted">Entity</label>
              <select v-model="localDefinition.entity" class="form-select form-select-sm">
                <option value="">Select entity</option>
                <option v-for="entity in entityOptions" :key="entity.value" :value="entity.value">
                  {{ entity.label }}
                </option>
              </select>
            </div>

            <div v-if="localDefinition.entity" class="builder-section">
              <label class="form-label small text-muted">Metric</label>
              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                  <select v-model="localDefinition.metric.type" class="form-select form-select-sm">
                    <option v-for="metric in metricOptions" :key="metric.value" :value="metric.value">
                      {{ metric.label }}
                    </option>
                  </select>
                </div>
                <div class="col-12 col-md-8" v-if="metricFieldRequired">
                  <select v-model="localDefinition.metric.field" class="form-select form-select-sm">
                    <option value="">Select field</option>
                    <option v-for="field in metricFieldOptions" :key="field.value" :value="field.value">
                      {{ field.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div v-if="localDefinition.entity && !isFunnel" class="builder-section">
              <label class="form-label small text-muted">Grouping</label>
              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                  <select v-model="localDefinition.groupBy.field" class="form-select form-select-sm">
                    <option value="">No grouping</option>
                    <option v-for="field in groupByFieldOptions" :key="field.value" :value="field.value">
                      {{ field.label }}
                    </option>
                  </select>
                </div>
                <div class="col-12 col-md-6" v-if="bucketAllowed">
                  <select v-model="localDefinition.groupBy.bucket" class="form-select form-select-sm">
                    <option v-for="bucket in bucketOptions" :key="bucket.value" :value="bucket.value">
                      {{ bucket.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div v-if="localDefinition.entity" class="builder-section">
              <label class="form-label small text-muted">
                {{ isFunnel ? 'Global Filters' : 'Filters' }}
              </label>
              <EntityFilters
                :entity-name="localDefinition.entity"
                :entity-display-name="selectedEntityLabel"
                :fields="entityFields"
                :initial-filter-id="initialFilterId"
                :initial-filters="initialFilters"
                @filter-change="handleFilterChange"
              />
            </div>

            <div v-if="localDefinition.entity && !isFunnel" class="builder-section">
              <label class="form-label small text-muted">Order & Limit</label>
              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                  <select v-model="localDefinition.order.by" class="form-select form-select-sm">
                    <option value="value">By value</option>
                    <option value="label">By label</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <select v-model="localDefinition.order.dir" class="form-select form-select-sm">
                    <option value="desc">Descending</option>
                    <option value="asc">Ascending</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <input
                    v-model.number="localDefinition.limit"
                    type="number"
                    min="1"
                    class="form-control form-control-sm"
                    placeholder="Limit"
                  />
                </div>
              </div>
            </div>

            <div v-if="localDefinition.entity && isFunnel" class="builder-section">
              <label class="form-label small text-muted">Funnel Stages</label>
              <div v-if="!localDefinition.funnelStages.length" class="text-muted small mb-2">
                Add at least one stage.
              </div>
              <div v-for="(stage, index) in localDefinition.funnelStages" :key="stage.id" class="funnel-stage card card-body mb-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <strong>Stage {{ index + 1 }}</strong>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="removeStage(index)">Remove</button>
                </div>
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-5">
                    <label class="form-label small text-muted">Label</label>
                    <input v-model="stage.label" type="text" class="form-control form-control-sm" placeholder="Stage label" />
                  </div>
                  <div class="col-12 col-md-7">
                    <label class="form-label small text-muted">Filters</label>
                    <EntityFilters
                      :entity-name="localDefinition.entity"
                      :entity-display-name="selectedEntityLabel"
                      :fields="entityFields"
                      :initial-filter-id="stage.initialFilterId"
                      :initial-filters="stage.initialFilters"
                      @filter-change="payload => handleStageFilterChange(index, payload)"
                    />
                  </div>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-primary" type="button" @click="addStage">Add Stage</button>
            </div>

            <div class="builder-section">
              <label class="form-label small text-muted">Chart Title</label>
              <input v-model="localDefinition.title" type="text" class="form-control form-control-sm" placeholder="Optional title" />
            </div>

            <div class="builder-section">
              <div class="d-flex align-items-center gap-2">
                <button
                  class="btn btn-sm btn-outline-secondary"
                  type="button"
                  :disabled="!canPreview || previewLoading"
                  @click="runPreview"
                >
                  Preview
                </button>
                <span v-if="previewLoading" class="text-muted small">Loading preview...</span>
                <span v-else-if="previewError" class="text-danger small">{{ previewError }}</span>
              </div>
              <div class="preview-panel mt-2" v-if="previewVisible">
                <canvas ref="previewCanvas" class="preview-canvas"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!canApply"
            @click="applyDefinition"
          >
            Apply
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="close"></div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import reportService from '../services/reportService'
import EntityFilters from './EntityFilters.vue'
import { createChart, updateChart } from '../utils/reportCharts'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  definition: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'apply'])

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const optionsLoading = ref(false)
const optionsError = ref('')
const optionsData = ref({ entities: [] })

const localDefinition = ref(buildDefaultDefinition())
const initialFilterId = ref(null)
const initialFilters = ref(null)
const isHydrating = ref(false)

const previewCanvas = ref(null)
const previewChart = ref(null)
const previewLoading = ref(false)
const previewError = ref('')
const previewVisible = ref(false)

const chartTypeOptions = [
  { value: 'pie', label: 'Pie' },
  { value: 'bar', label: 'Bar' },
  { value: 'line', label: 'Time Series' },
  { value: 'funnel', label: 'Funnel' }
]

const metricOptions = [
  { value: 'count', label: 'Count' },
  { value: 'sum', label: 'Sum' },
  { value: 'avg', label: 'Average' },
  { value: 'min', label: 'Minimum' },
  { value: 'max', label: 'Maximum' }
]

const bucketOptions = [
  { value: 'none', label: 'No bucket' },
  { value: 'day', label: 'Day' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
  { value: 'quarter', label: 'Quarter' },
  { value: 'year', label: 'Year' }
]

const numericTypes = ['integer', 'number', 'float', 'decimal', 'currency']
const groupableTypes = ['string', 'integer', 'select', 'relationship', 'date', 'datetime', 'boolean', 'uuid', 'text', 'textarea']

const entityOptions = computed(() => {
  return (optionsData.value.entities || []).map(entity => ({
    value: entity.name,
    label: entity.label
  }))
})

const entityFields = computed(() => {
  if (!localDefinition.value.entity) return []
  const entity = optionsData.value.entities.find(item => item.name === localDefinition.value.entity)
  return entity?.fields || []
})

const selectedEntityLabel = computed(() => {
  const entity = optionsData.value.entities.find(item => item.name === localDefinition.value.entity)
  return entity?.label || ''
})

const metricFieldOptions = computed(() => {
  return entityFields.value
    .filter(field => numericTypes.includes((field.type || '').toLowerCase()))
    .map(field => ({ value: field.name, label: field.label }))
})

const groupByFieldOptions = computed(() => {
  return entityFields.value
    .filter(field => groupableTypes.includes((field.type || '').toLowerCase()))
    .map(field => ({ value: field.name, label: field.label, type: field.type }))
})

const metricFieldRequired = computed(() => localDefinition.value.metric.type !== 'count')

const isFunnel = computed(() => localDefinition.value.chartType === 'funnel')

const selectedGroupField = computed(() => {
  return entityFields.value.find(field => field.name === localDefinition.value.groupBy.field) || null
})

const bucketAllowed = computed(() => {
  const type = (selectedGroupField.value?.type || '').toLowerCase()
  return type === 'date' || type === 'datetime'
})

const canPreview = computed(() => {
  if (!localDefinition.value.chartType || !localDefinition.value.entity) return false
  if (!localDefinition.value.metric.type) return false
  if (metricFieldRequired.value && !localDefinition.value.metric.field) return false
  if (isFunnel.value && (!localDefinition.value.funnelStages || localDefinition.value.funnelStages.length === 0)) return false
  return true
})

const canApply = computed(() => canPreview.value)

watch(isVisible, async (next) => {
  if (next) {
    await ensureOptions()
    hydrateDefinition()
  } else {
    destroyPreview()
  }
})

watch(() => localDefinition.value.entity, () => {
  if (isHydrating.value) return
  if (!localDefinition.value.entity) return
  localDefinition.value.metric.field = null
  localDefinition.value.groupBy.field = null
  localDefinition.value.groupBy.bucket = 'none'
  localDefinition.value.filters = null
  localDefinition.value.filterId = null
  localDefinition.value.funnelStages = []
  initialFilterId.value = null
  initialFilters.value = null
  previewVisible.value = false
})

watch(() => localDefinition.value.metric.type, (next) => {
  if (next === 'count') {
    localDefinition.value.metric.field = null
  }
})

watch(bucketAllowed, (allowed) => {
  if (!allowed) {
    localDefinition.value.groupBy.bucket = 'none'
  }
})

function buildDefaultDefinition() {
  return {
    chartType: '',
    entity: '',
    filterId: null,
    filters: null,
    metric: {
      type: 'count',
      field: null
    },
    groupBy: {
      field: null,
      bucket: 'none'
    },
    order: {
      by: 'value',
      dir: 'desc'
    },
    limit: null,
    title: '',
    funnelStages: []
  }
}

async function ensureOptions() {
  if (optionsData.value.entities.length) return
  optionsLoading.value = true
  optionsError.value = ''
  try {
    const response = await reportService.options()
    optionsData.value = response || { entities: [] }
  } catch (err) {
    optionsError.value = err?.response?.data?.error || 'Failed to load report options'
  } finally {
    optionsLoading.value = false
  }
}

function hydrateDefinition() {
  isHydrating.value = true
  const incoming = props.definition ? JSON.parse(JSON.stringify(props.definition)) : {}
  const defaults = buildDefaultDefinition()
  const merged = {
    ...defaults,
    ...incoming,
    metric: { ...defaults.metric, ...(incoming.metric || {}) },
    groupBy: { ...defaults.groupBy, ...(incoming.groupBy || {}) },
    order: { ...defaults.order, ...(incoming.order || {}) }
  }

  merged.chartType = merged.chartType || ''
  merged.entity = merged.entity || ''
  merged.metric.type = merged.metric.type || 'count'
  merged.metric.field = merged.metric.field || null
  merged.groupBy.field = merged.groupBy.field || null
  merged.groupBy.bucket = merged.groupBy.bucket || 'none'
  merged.order.by = merged.order.by || 'value'
  merged.order.dir = merged.order.dir || 'desc'
  merged.limit = merged.limit || null
  merged.title = merged.title || ''

  merged.funnelStages = normalizeStages(merged.funnelStages || [])

  localDefinition.value = merged
  initialFilterId.value = merged.filterId || null
  initialFilters.value = merged.filters || null
  previewVisible.value = false
  isHydrating.value = false
}

function normalizeStages(stages) {
  if (!Array.isArray(stages)) return []
  return stages.map((stage, index) => ({
    id: `stage-${Date.now()}-${index}-${Math.random().toString(36).slice(2, 6)}`,
    label: stage?.label || '',
    filterId: stage?.filterId || stage?.filter_id || null,
    filters: stage?.filters || null,
    initialFilterId: stage?.filterId || stage?.filter_id || null,
    initialFilters: stage?.filters || null
  }))
}

function setChartType(type) {
  localDefinition.value.chartType = type
  if (type === 'funnel') {
    localDefinition.value.groupBy.field = null
    localDefinition.value.groupBy.bucket = 'none'
  }
}

function handleFilterChange(payload) {
  if (!payload) {
    localDefinition.value.filterId = null
    localDefinition.value.filters = null
    return
  }

  if (payload.mode === 'stored') {
    localDefinition.value.filterId = payload.payload?.filter_id || null
    localDefinition.value.filters = null
  } else if (payload.mode === 'adhoc') {
    localDefinition.value.filterId = null
    localDefinition.value.filters = payload.payload || null
  }
}

function addStage() {
  localDefinition.value.funnelStages.push({
    id: `stage-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
    label: '',
    filterId: null,
    filters: null,
    initialFilterId: null,
    initialFilters: null
  })
}

function removeStage(index) {
  localDefinition.value.funnelStages.splice(index, 1)
}

function handleStageFilterChange(index, payload) {
  const stage = localDefinition.value.funnelStages[index]
  if (!stage) return

  if (!payload) {
    stage.filterId = null
    stage.filters = null
    return
  }

  if (payload.mode === 'stored') {
    stage.filterId = payload.payload?.filter_id || null
    stage.filters = null
  } else if (payload.mode === 'adhoc') {
    stage.filterId = null
    stage.filters = payload.payload || null
  }
}

function buildDefinitionForSave() {
  const def = {
    chartType: localDefinition.value.chartType,
    entity: localDefinition.value.entity,
    filterId: localDefinition.value.filterId || null,
    filters: localDefinition.value.filters || null,
    metric: {
      type: localDefinition.value.metric.type,
      field: localDefinition.value.metric.type === 'count' ? null : localDefinition.value.metric.field
    },
    groupBy: {
      field: localDefinition.value.groupBy.field || null,
      bucket: localDefinition.value.groupBy.bucket || 'none'
    },
    order: {
      by: localDefinition.value.order.by,
      dir: localDefinition.value.order.dir
    },
    limit: localDefinition.value.limit ? Number(localDefinition.value.limit) : null,
    title: localDefinition.value.title?.trim() || null
  }

  if (localDefinition.value.chartType === 'funnel') {
    def.funnelStages = (localDefinition.value.funnelStages || []).map((stage, index) => ({
      label: stage.label?.trim() || `Stage ${index + 1}`,
      filterId: stage.filterId || null,
      filters: stage.filters || null
    }))
  }

  return def
}

async function runPreview() {
  if (!canPreview.value) return
  previewLoading.value = true
  previewError.value = ''

  try {
    const result = await reportService.run(buildDefinitionForSave())
    previewVisible.value = true
    await nextTick()
    if (!previewChart.value) {
      previewChart.value = createChart(previewCanvas.value, result, buildDefinitionForSave())
    } else {
      updateChart(previewChart.value, result, buildDefinitionForSave())
    }
  } catch (err) {
    previewError.value = err?.response?.data?.error || 'Failed to load preview'
  } finally {
    previewLoading.value = false
  }
}

function applyDefinition() {
  emit('apply', buildDefinitionForSave())
  close()
}

function destroyPreview() {
  previewVisible.value = false
  if (previewChart.value) {
    previewChart.value.destroy()
    previewChart.value = null
  }
}

function close() {
  isVisible.value = false
}

onBeforeUnmount(() => {
  destroyPreview()
})
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}

.chart-builder {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.builder-section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.preview-panel {
  border: 1px solid #e5e5e5;
  border-radius: 0.5rem;
  padding: 0.75rem;
  min-height: 220px;
}

.preview-canvas {
  width: 100%;
  height: 200px;
}

.funnel-stage {
  background: #f8f9fa;
}
</style>
