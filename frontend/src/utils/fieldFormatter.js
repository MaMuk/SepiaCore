/**
 * Formats field values for display in Grid.js tables
 * This mirrors the logic in FieldRenderer.vue for consistency
 */

export function formatFieldValue(value, fieldDef, relationship = null) {
  if (!fieldDef) {
    return value !== null && value !== undefined ? String(value) : '-'
  }

  const fieldType = fieldDef.type

  // Select field
  if (fieldType === 'select') {
    if (fieldDef.options && value) {
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
    if (Array.isArray(value) && value.length > 0) {
      return value.join(', ')
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
    const displayValue = value !== null && value !== undefined ? String(value) : '-'
    return displayValue === '-' ? '<span class="text-muted">-</span>' : displayValue
  }

  const fieldType = fieldDef.type

  // Select field
  if (fieldType === 'select') {
    if (fieldDef.options && value) {
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
    if (Array.isArray(value) && value.length > 0) {
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

