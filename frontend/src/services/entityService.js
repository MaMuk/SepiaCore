import api from './api'
import { getListLimit } from '../config'

export class EntityService {
  /**
   * Get list of entities
   */
  async getList(entityName, options = {}) {
    const {
      page = 1,
      limit = getListLimit(),
      search = null,
      sort = 'date_modified',
      order = 'DESC',
      filters = null
    } = options

    const params = new URLSearchParams({
      page: page.toString(),
      limit: limit.toString(),
      sort,
      order
    })

    if (search) {
      params.append('search', search)
    }
    if (filters) {
      params.append('filters', filters)
    }

    try {
      const response = await api.get(`/${entityName}?${params.toString()}`)
      return response.data
    } catch (error) {
      throw error
    }
  }

  /**
   * Get single entity record
   */
  async getRecord(entityName, id) {
    try {
      const response = await api.get(`/${entityName}/${id}`)
      return response.data
    } catch (error) {
      throw error
    }
  }

  /**
   * Get entity count
   */
  async getCount(entityName) {
    try {
      const response = await api.get(`/${entityName}/count`)
      return response.data
    } catch (error) {
      throw error
    }
  }

  /**
   * Filter entity records
   */
  async filter(entityName, payload = {}) {
    try {
      const response = await api.post(`/${entityName}/filter`, payload)
      return response.data
    } catch (error) {
      throw error
    }
  }
}

export default new EntityService()
