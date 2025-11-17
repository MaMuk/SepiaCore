<template>
  <div
    class="icon-selector-overlay"
    v-if="isVisible"
    @click.self="close"
  >
    <div class="icon-selector-card">
      <div class="icon-selector-header">
        <h5 class="mb-0">Select Icon</h5>
        <button
          type="button"
          class="btn-close"
          @click="close"
          aria-label="Close"
        ></button>
      </div>
      <div class="icon-selector-body">
        <div class="mb-3">
          <input
            type="text"
            class="form-control"
            placeholder="Search icons by name or tag (e.g., person, contact, user)..."
            v-model="searchQuery"
          />
        </div>
        <div class="icon-grid">
          <div
            v-for="icon in filteredIcons"
            :key="icon.name"
            class="icon-item"
            :class="{ 'icon-selected': selectedIcon === icon.name }"
            @click="selectIcon(icon.name)"
            :title="icon.name"
          >
            <img
              :src="getIconPath(icon.name)"
              :alt="icon.name"
              class="icon-image"
            />
            <div class="icon-name">{{ icon.displayName }}</div>
          </div>
        </div>
        <div v-if="filteredIcons.length === 0" class="text-center text-muted p-4">
          No icons found matching "{{ searchQuery }}"
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { getIconPath } from '../utils/iconUtils'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  selectedIcon: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'select'])

// Icon definitions with tags for search
// Each icon has at least 3 tags for flexible searching
const iconDefinitions = [
  {
    name: 'bi-person-vcard-fill',
    displayName: 'Person',
    tags: ['person', 'contact', 'user', 'people', 'individual', 'customer', 'client']
  },
  {
    name: 'bi-building-fill',
    displayName: 'Building',
    tags: ['company', 'organization', 'business', 'office', 'corporate', 'enterprise', 'firm']
  },
  {
    name: 'bi-briefcase-fill',
    displayName: 'Briefcase',
    tags: ['job', 'work', 'career', 'business', 'professional', 'employment', 'position']
  },
  {
    name: 'bi-calendar-event-fill',
    displayName: 'Calendar',
    tags: ['event', 'appointment', 'schedule', 'meeting', 'date', 'time', 'planning']
  },
  {
    name: 'bi-file-earmark-text-fill',
    displayName: 'Document',
    tags: ['document', 'file', 'text', 'paper', 'record', 'report', 'form']
  },
  {
    name: 'bi-envelope-fill',
    displayName: 'Email',
    tags: ['email', 'message', 'mail', 'communication', 'contact', 'inbox', 'letter']
  },
  {
    name: 'bi-telephone-fill',
    displayName: 'Phone',
    tags: ['phone', 'call', 'contact', 'communication', 'telephone', 'mobile', 'telecom']
  },
  {
    name: 'bi-cart-fill',
    displayName: 'Shopping Cart',
    tags: ['shopping', 'order', 'purchase', 'ecommerce', 'cart', 'buy', 'retail']
  },
  {
    name: 'bi-box-seam-fill',
    displayName: 'Package',
    tags: ['product', 'item', 'package', 'inventory', 'box', 'goods', 'stock']
  },
  {
    name: 'bi-clipboard-data-fill',
    displayName: 'Data',
    tags: ['data', 'report', 'analytics', 'statistics', 'metrics', 'information', 'stats']
  },
  {
    name: 'bi-gear-fill',
    displayName: 'Settings',
    tags: ['settings', 'configuration', 'setup', 'preferences', 'options', 'config', 'admin']
  },
  {
    name: 'bi-tag-fill',
    displayName: 'Tag',
    tags: ['tag', 'label', 'category', 'classification', 'keyword', 'marker', 'badge']
  },
  {
    name: 'bi-folder-fill',
    displayName: 'Folder',
    tags: ['folder', 'directory', 'category', 'organization', 'collection', 'archive', 'group']
  },
  {
    name: 'bi-credit-card-fill',
    displayName: 'Payment',
    tags: ['payment', 'transaction', 'finance', 'money', 'card', 'billing', 'invoice']
  },
  {
    name: 'bi-truck',
    displayName: 'Shipping',
    tags: ['shipping', 'delivery', 'logistics', 'transport', 'freight', 'dispatch', 'carrier']
  },
  {
    name: 'bi-graph-up-arrow',
    displayName: 'Analytics',
    tags: ['analytics', 'chart', 'growth', 'performance', 'trend', 'graph', 'metrics']
  },
  {
    name: 'bi-journal-text',
    displayName: 'Journal',
    tags: ['notes', 'journal', 'log', 'documentation', 'diary', 'entry', 'record']
  },
  {
    name: 'bi-clipboard-check-fill',
    displayName: 'Task',
    tags: ['task', 'todo', 'checklist', 'completion', 'done', 'check', 'assignment']
  },
  {
    name: 'bi-star-fill',
    displayName: 'Star',
    tags: ['favorite', 'rating', 'review', 'quality', 'star', 'important', 'featured']
  },
  {
    name: 'bi-heart-fill',
    displayName: 'Heart',
    tags: ['favorite', 'like', 'preference', 'wishlist', 'love', 'bookmark', 'save']
  },
  {
    name: 'bi-megaphone-fill',
    displayName: 'Announcement',
    tags: ['announcement', 'notification', 'marketing', 'campaign', 'broadcast', 'promotion', 'alert']
  },
  {
    name: 'bi-gift-fill',
    displayName: 'Gift',
    tags: ['reward', 'promotion', 'bonus', 'incentive', 'gift', 'prize', 'benefit']
  },
  {
    name: 'bi-book-fill',
    displayName: 'Book',
    tags: ['knowledge', 'article', 'content', 'library', 'book', 'reading', 'education']
  },
  {
    name: 'bi-camera-fill',
    displayName: 'Camera',
    tags: ['image', 'photo', 'media', 'gallery', 'picture', 'camera', 'visual']
  },
  {
    name: 'bi-music-note-beamed',
    displayName: 'Media',
    tags: ['media', 'audio', 'entertainment', 'content', 'music', 'sound', 'playlist']
  }
]

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const searchQuery = ref('')
const selectedIcon = ref(props.selectedIcon || '')

const filteredIcons = computed(() => {
  if (!searchQuery.value.trim()) {
    return iconDefinitions
  }

  const query = searchQuery.value.toLowerCase().trim()
  return iconDefinitions.filter(icon => {
    // Search in icon name
    if (icon.name.toLowerCase().includes(query)) {
      return true
    }
    // Search in display name
    if (icon.displayName.toLowerCase().includes(query)) {
      return true
    }
    // Search in tags
    return icon.tags.some(tag => tag.toLowerCase().includes(query))
  })
})

function selectIcon(iconName) {
  selectedIcon.value = iconName
  emit('select', iconName)
  close()
}

function close() {
  isVisible.value = false
  searchQuery.value = ''
}

watch(() => props.selectedIcon, (newVal) => {
  selectedIcon.value = newVal || ''
})
</script>

<style scoped>
.icon-selector-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1060;
  padding: 1rem;
}

.icon-selector-card {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.icon-selector-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.icon-selector-body {
  padding: 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.icon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 1rem;
}

.icon-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1rem;
  border: 2px solid #dee2e6;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s ease;
  background: white;
}

.icon-item:hover {
  border-color: #0d6efd;
  background-color: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.icon-item.icon-selected {
  border-color: #0d6efd;
  background-color: #e7f1ff;
}

.icon-image {
  width: 48px;
  height: 48px;
  object-fit: contain;
  margin-bottom: 0.5rem;
}

.icon-name {
  font-size: 0.75rem;
  text-align: center;
  color: #6c757d;
  word-break: break-word;
}

.icon-item.icon-selected .icon-name {
  color: #0d6efd;
  font-weight: 600;
}
</style>

