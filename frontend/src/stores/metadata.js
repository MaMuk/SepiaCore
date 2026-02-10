import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'

export const useMetadataStore = defineStore('metadata', () => {
  const metadata = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const navigationEntities = computed(() => {
    if (!metadata.value?.navigation_entities) return []
    
    // Handle both array and object formats (PHP arrays with numeric keys can become objects in JSON)
    const entities = Array.isArray(metadata.value.navigation_entities)
      ? metadata.value.navigation_entities
      : Object.values(metadata.value.navigation_entities)
    
    return entities.map((entityName, index) => {
      const entityData = metadata.value.entities[entityName] || {}
      return {
        name: entityName,
        displayName: formatEntityName(entityName),
        index: index + 1,
        ...entityData
      }
    })
  })

  const entities = computed(() => {
    return metadata.value?.entities || {}
  })

  const protectedEntities = computed(() => {
    const list = metadata.value?.protected_entities
    if (!list) return []

    const entitiesList = Array.isArray(list) ? list : Object.values(list)
    return entitiesList
      .filter(Boolean)
      .map((name) => name.toString().toLowerCase())
  })

  function formatEntityName(name) {
    // Convert snake_case or camelCase to Title Case
    return name
      .split(/[-_]/)
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ')
  }

  async function fetchMetadata() {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/metadata', {
        params: { _ts: Date.now() }
      })
      metadata.value = response.data
      return response.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  function setMetadata(nextMetadata) {
    metadata.value = nextMetadata
  }

  function getEntityMetadata(entityName) {
    return metadata.value?.entities[entityName] || null
  }

  function getEntityListLayout(entityName) {
    const entityMeta = getEntityMetadata(entityName)
    if (!entityMeta?.module_views?.list) return []
    
    const listView = entityMeta.module_views.list
    return listView.layout || []
  }

  return {
    metadata,
    navigationEntities,
    entities,
    protectedEntities,
    loading,
    error,
    fetchMetadata,
    setMetadata,
    getEntityMetadata,
    getEntityListLayout,
    formatEntityName
  }
})
