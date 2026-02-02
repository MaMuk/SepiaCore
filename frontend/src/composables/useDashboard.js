import { ref } from 'vue'
import dashboardService from '../services/dashboardService'

export function useDashboard() {
  const dashboards = ref([])
  const defaultDashboard = ref(null)
  const activeDashboard = ref(null)
  const widgets = ref([])
  const loading = ref(false)
  const error = ref(null)

  function applyResponse(data) {
    dashboards.value = data?.dashboards || []
    defaultDashboard.value = data?.defaultDashboard || null
    activeDashboard.value = data?.activeDashboard || data?.defaultDashboard || null
    widgets.value = data?.widgets || []
  }

  async function loadDefault() {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.getDefaultDashboard()
      applyResponse(data)
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to load dashboard'
    } finally {
      loading.value = false
    }
  }

  async function selectDashboard(id) {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.getDashboardById(id)
      applyResponse(data)
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to load dashboard'
    } finally {
      loading.value = false
    }
  }

  async function saveWidgets(id, updatedWidgets) {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.saveDashboardWidgets(id, updatedWidgets)
      widgets.value = data?.widgets || updatedWidgets
      return data
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to save dashboard'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createDashboard(name) {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.createDashboard(name)
      applyResponse(data)
      return data
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to create dashboard'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function setDefault(id) {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.setDefaultDashboard(id)
      applyResponse(data)
      return data
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to set default'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function removeDashboard(id) {
    loading.value = true
    error.value = null
    try {
      const data = await dashboardService.deleteDashboard(id)
      applyResponse(data)
      return data
    } catch (err) {
      error.value = err?.response?.data?.error || 'Failed to delete dashboard'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    dashboards,
    defaultDashboard,
    activeDashboard,
    widgets,
    loading,
    error,
    loadDefault,
    selectDashboard,
    saveWidgets,
    createDashboard,
    setDefault,
    removeDashboard
  }
}
