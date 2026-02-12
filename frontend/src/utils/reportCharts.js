import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const COLOR_PALETTE = [
  '#1b9aaa',
  '#ef476f',
  '#ffd166',
  '#06d6a0',
  '#118ab2',
  '#073b4c',
  '#8e44ad',
  '#f4a261',
  '#2a9d8f',
  '#e76f51'
]

function buildColors(count, alpha = '0.7') {
  const colors = []
  for (let i = 0; i < count; i += 1) {
    const base = COLOR_PALETTE[i % COLOR_PALETTE.length]
    colors.push(withAlpha(base, alpha))
  }
  return colors
}

function withAlpha(hex, alpha) {
  const normalized = hex.replace('#', '')
  const bigint = parseInt(normalized, 16)
  const r = (bigint >> 16) & 255
  const g = (bigint >> 8) & 255
  const b = bigint & 255
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

export function buildChartConfig(result, definition) {
  const labels = result?.labels || []
  const series = result?.series || []
  const chartType = definition?.chartType || 'bar'
  const isFunnel = chartType === 'funnel'
  const resolvedType = isFunnel ? 'bar' : chartType

  const datasets = series.map((item, index) => {
    const label = item?.label || 'Series'
    const data = item?.data || []
    const baseColor = COLOR_PALETTE[index % COLOR_PALETTE.length]

    if (resolvedType === 'line') {
      return {
        label,
        data,
        borderColor: baseColor,
        backgroundColor: withAlpha(baseColor, '0.15'),
        tension: 0.3,
        fill: false,
        pointRadius: 3
      }
    }

    const colors = buildColors(labels.length)
    return {
      label,
      data,
      backgroundColor: colors,
      borderColor: colors,
      borderWidth: 1
    }
  })

  const showLegend = chartType === 'pie' || chartType === 'line' || series.length > 1

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: showLegend
      },
      title: {
        display: !!definition?.title,
        text: definition?.title || ''
      }
    }
  }

  if (resolvedType === 'bar' && isFunnel) {
    options.indexAxis = 'y'
    options.plugins.legend.display = false
  }

  return {
    type: resolvedType,
    data: {
      labels,
      datasets
    },
    options
  }
}

export function createChart(canvas, result, definition) {
  if (!canvas) return null
  const config = buildChartConfig(result, definition)
  return new Chart(canvas, config)
}

export function updateChart(chart, result, definition) {
  if (!chart) return
  const config = buildChartConfig(result, definition)
  chart.config.type = config.type
  chart.data = config.data
  chart.options = config.options
  chart.update()
}
