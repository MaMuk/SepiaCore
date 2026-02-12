/**
 * Formats field values for display in Grid.js tables
 * This mirrors the logic in FieldRenderer.vue for consistency
 */

export function formatFieldValue(value, fieldDef, relationship = null) {
  if (!fieldDef) {
    if (Array.isArray(value)) {
      return value.length ? value.join(', ') : 'No items'
    }
    return value !== null && value !== undefined ? String(value) : '-'
  }

  const fieldType = fieldDef.type

  // Collections (array values) should render as badges/text even if type metadata is missing.
  if (Array.isArray(value) && fieldType !== 'relationship') {
    return value.length ? value.join(', ') : 'No items'
  }

  // Select-like fields: use options mapping whenever provided.
  if (fieldDef.options && value !== null && value !== undefined && value !== '' && fieldType !== 'relationship') {
    return fieldDef.options[value] || value
  }

  // Select field
  if (fieldType === 'select') {
    if (fieldDef.options && value !== null && value !== undefined && value !== '') {
      return fieldDef.options[value] || value
    }
    return '-'
  }

  // Relationship field
  if (fieldType === 'relationship') {
    if (relationship?.name) {
      // For Grid.js, we'll return the name as text
      // The link will be handled by the formatter with click handler
      return relationship.name
    }
    return '-'
  }

  // File field
  if (fieldType === 'file') {
    return value ? 'File' : '-'
  }

  // Datetime field
  if (fieldType === 'datetime') {
    if (!value) return '-'
    const date = new Date(value)
    return date.toLocaleString()
  }

  // Date field
  if (fieldType === 'date') {
    if (!value) return '-'
    const date = new Date(value)
    return date.toLocaleDateString()
  }

  // Collection field
  if (fieldType === 'collection') {
    const normalized = normalizeCollectionValue(value)
    if (Array.isArray(normalized) && normalized.length > 0) {
      return normalized.join(', ')
    }
    return 'No items'
  }

  // Boolean/Checkbox field
  if (fieldType === 'boolean' || fieldType === 'checkbox') {
    return value ? 'Yes' : 'No'
  }

  // Default: return value as string
  return value !== null && value !== undefined ? String(value) : '-'
}

/**
 * Formats field value as HTML for Grid.js formatters
 * This allows for clickable links and styled content
 */
export function formatFieldValueHTML(value, fieldDef, relationship = null, onClickHandler = null) {
  if (!fieldDef) {
    if (Array.isArray(value)) {
      if (!value.length) return '<span class="text-muted">No items</span>'
      const badges = value.map(item => {
        const escapedItem = String(item)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;')
        return `<span class="badge bg-secondary me-1">${escapedItem}</span>`
      }).join('')
      return `<div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">${badges}</div>`
    }
    const displayValue = value !== null && value !== undefined ? String(value) : '-'
    return displayValue === '-' ? '<span class="text-muted">-</span>' : displayValue
  }

  const fieldType = fieldDef.type

  if (Array.isArray(value) && fieldType !== 'relationship') {
    const normalized = normalizeCollectionValue(value)
    if (Array.isArray(normalized) && normalized.length > 0) {
      const badges = normalized.map(item => {
        const escapedItem = String(item)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;')
        return `<span class="badge bg-secondary me-1">${escapedItem}</span>`
      }).join('')
      return `<div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">${badges}</div>`
    }
    return '<span class="text-muted">No items</span>'
  }

  if (fieldDef.options && value !== null && value !== undefined && value !== '' && fieldType !== 'relationship') {
    return fieldDef.options[value] || value
  }

  // Select field
  if (fieldType === 'select') {
    if (fieldDef.options && value !== null && value !== undefined && value !== '') {
      return fieldDef.options[value] || value
    }
    return '<span class="text-muted">-</span>'
  }

  // Relationship field
  if (fieldType === 'relationship') {
    if (relationship?.name) {
      // Create clickable link for all relationships including Users - escape HTML in name
      const escapedName = relationship.name
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
      const onClick = onClickHandler 
        ? `onclick="event.stopPropagation(); window.handleRelationshipClick('${relationship.entity}', '${relationship.id}')"`
        : ''
      return `<a href="#" ${onClick} class="text-decoration-none link-primary">${escapedName}</a>`
    }
    // If we have a relationship ID but no name, show the ID (fallback)
    if (relationship?.id) {
      return `<span class="text-muted">${relationship.id}</span>`
    }
    return '<span class="text-muted">-</span>'
  }

  // File field
  if (fieldType === 'file') {
    return value ? '<span class="text-muted">File</span>' : '<span class="text-muted">-</span>'
  }

  // Datetime field
  if (fieldType === 'datetime') {
    if (!value) return '<span class="text-muted">-</span>'
    const date = new Date(value)
    return date.toLocaleString()
  }

  // Date field
  if (fieldType === 'date') {
    if (!value) return '<span class="text-muted">-</span>'
    const date = new Date(value)
    return date.toLocaleDateString()
  }

  // Collection field
  if (fieldType === 'collection') {
    const normalized = normalizeCollectionValue(value)
    if (Array.isArray(normalized) && normalized.length > 0) {
      const badges = normalized.map(item => {
        const escapedItem = String(item)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;')
        return `<span class="badge bg-secondary me-1">${escapedItem}</span>`
      }).join('')
      return `<div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">${badges}</div>`
    }
    return '<span class="text-muted">No items</span>'
  }

  // Boolean/Checkbox field
  if (fieldType === 'boolean' || fieldType === 'checkbox') {
    const className = value ? 'text-success' : 'text-muted'
    const text = value ? 'Yes' : 'No'
    return `<span class="${className}">${text}</span>`
  }

  // Default: return value as string
  const displayValue = value !== null && value !== undefined ? String(value) : '-'
  return displayValue === '-' ? '<span class="text-muted">-</span>' : displayValue
}

function normalizeCollectionValue(value) {
  if (Array.isArray(value)) return value
  if (value === null || value === undefined || value === '') return []
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return []
    if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
      try {
        const parsed = JSON.parse(trimmed)
        return Array.isArray(parsed) ? parsed : [parsed]
      } catch (error) {
        return trimmed.split(',').map(item => item.trim()).filter(Boolean)
      }
    }
    return trimmed.split(',').map(item => item.trim()).filter(Boolean)
  }
  return [value]
}
