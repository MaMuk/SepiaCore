import api from './api'

export async function run(definition) {
  const response = await api.post('/system/reports/run', { definition })
  return response.data
}

export async function options() {
  const response = await api.get('/system/reports/options')
  return response.data
}

export async function validate(definition) {
  const response = await api.post('/system/reports/validate', { definition })
  return response.data
}

export default {
  run,
  options,
  validate
}
