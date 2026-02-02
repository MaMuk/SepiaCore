import api from './api'

export async function getDefaultDashboard() {
  const response = await api.get('/dashboard')
  return response.data
}

export async function getDashboardById(id) {
  const response = await api.get(`/dashboard/${id}`)
  return response.data
}

export async function saveDashboardWidgets(id, widgets) {
  const response = await api.put(`/dashboard/${id}`, { widgets })
  return response.data
}

export async function createDashboard(name) {
  const response = await api.post('/dashboard', { name })
  return response.data
}

export async function setDefaultDashboard(id) {
  const response = await api.put(`/dashboard/${id}/default`)
  return response.data
}

export async function deleteDashboard(id) {
  const response = await api.delete(`/dashboard/${id}`)
  return response.data
}

export default {
  getDefaultDashboard,
  getDashboardById,
  saveDashboardWidgets,
  createDashboard,
  setDefaultDashboard,
  deleteDashboard
}
