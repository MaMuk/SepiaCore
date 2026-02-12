<template>
  <div class="graph-widget h-100 d-flex flex-column">
    <div v-if="editable" class="d-flex align-items-center justify-content-between mb-2">
      <small class="text-muted">Graph configuration</small>
      <button class="btn btn-sm btn-outline-primary" type="button" @click="openBuilder">
        Configure
      </button>
    </div>

    <div v-if="!hasDefinition" class="graph-empty text-muted small flex-grow-1 d-flex align-items-center justify-content-center">
      {{ editable ? 'Configure this widget to render a chart.' : 'Chart not configured.' }}
    </div>

    <div v-else class="graph-body flex-grow-1 position-relative">
      <div v-if="loading" class="graph-status text-muted small">Loading chart...</div>
      <div v-else-if="error" class="alert alert-danger py-1 mb-2" role="alert">
        {{ error }}
      </div>
      <canvas ref="canvasRef" class="graph-canvas"></canvas>
    </div>

    <ChartBuilderModal
      v-model="showBuilder"
      :definition="reportDefinition"
      @apply="handleApply"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import reportService from '../../services/reportService'
import ChartBuilderModal from '../ChartBuilderModal.vue'
import { createChart, updateChart } from '../../utils/reportCharts'

const props = defineProps({
  widget: { type: Object, required: true },
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['update-widget'])

const canvasRef = ref(null)
const chartInstance = ref(null)
const loading = ref(false)
const error = ref('')
const showBuilder = ref(false)
const lastRunId = ref(0)
const retryCount = ref(0)
const retryTimer = ref(null)

const reportDefinition = computed(() => props.widget?.config?.reportDefinition || null)
const hasDefinition = computed(() => {
  const def = reportDefinition.value
  if (!def) return false
  if (!def.chartType || !def.entity) return false
  if (!def.metric?.type) return false
  if (def.metric?.type !== 'count' && !def.metric?.field) return false
  if (def.chartType === 'funnel' && (!Array.isArray(def.funnelStages) || def.funnelStages.length === 0)) {
    return false
  }
  return true
})

watch(reportDefinition, () => {
  error.value = ''
  retryCount.value = 0
  runReport()
}, { deep: true })

onMounted(() => {
  runReport()
})

onBeforeUnmount(() => {
  if (chartInstance.value) {
    chartInstance.value.destroy()
    chartInstance.value = null
  }
  if (retryTimer.value) {
    clearTimeout(retryTimer.value)
    retryTimer.value = null
  }
})

function openBuilder() {
  showBuilder.value = true
}

function handleApply(definition) {
  emit('update-widget', {
    id: props.widget.id,
    config: {
      reportDefinition: definition
    }
  })
}

async function runReport() {
  if (!hasDefinition.value) {
    destroyChart()
    error.value = ''
    return
  }
  const runId = ++lastRunId.value
  loading.value = true
  error.value = ''

  try {
    const result = await reportService.run(reportDefinition.value)
    if (runId !== lastRunId.value) return
    await nextTick()
    renderChart(result)
    retryCount.value = 0
  } catch (err) {
    if (runId !== lastRunId.value) return
    const detail = err?.response?.data?.error
    const errors = err?.response?.data?.errors
    destroyChart()
    if (retryCount.value < 1) {
      error.value = ''
      scheduleRetry()
    } else {
      error.value = detail || errors?.[0] || 'Failed to load chart'
    }
  } finally {
    if (runId === lastRunId.value) {
      loading.value = false
    }
  }
}

function renderChart(result) {
  if (!canvasRef.value) return
  if (!chartInstance.value) {
    chartInstance.value = createChart(canvasRef.value, result, reportDefinition.value)
  } else {
    updateChart(chartInstance.value, result, reportDefinition.value)
  }
}

function destroyChart() {
  if (chartInstance.value) {
    chartInstance.value.destroy()
    chartInstance.value = null
  }
}

function scheduleRetry() {
  if (retryCount.value >= 1) return
  if (!hasDefinition.value) return
  if (retryTimer.value) clearTimeout(retryTimer.value)
  retryCount.value += 1
  retryTimer.value = setTimeout(() => {
    runReport()
  }, 500)
}
</script>

<style scoped>
.graph-widget {
  min-height: 120px;
  flex: 1 1 auto;
}

.graph-body {
  flex: 1 1 auto;
  min-height: 0;
}

.graph-canvas {
  display: block;
  width: 100%;
  height: 100%;
}

.graph-status {
  position: absolute;
  top: 0.5rem;
  right: 0.75rem;
}

.graph-empty {
  padding: 1rem;
}
</style>
