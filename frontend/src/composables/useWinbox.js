import { ref, nextTick, watch } from 'vue'
import WinBox from 'winbox/src/js/winbox.js'
import { createApp } from 'vue'
import RecordDetailView from '../components/RecordDetailView.vue'
import UserOwnedRecordsView from '../components/UserOwnedRecordsView.vue'
import { useMetadataStore } from '../stores/metadata'
import { useSidebar } from './useSidebar'
import { getIconPath } from '../utils/iconUtils'

let winboxInstances = ref([])

export function useWinbox() {
  const metadataStore = useMetadataStore()
  const { minimizedContainerRef, addMinimizedInstance, removeMinimizedInstance } = useSidebar()

  function openRecordWindow(entityName, recordId = null, mode = 'detail', recordName = null) {
    // Create unique ID for this window
    const windowId = `${entityName}-${recordId || 'create'}-${mode}`
    
    // Check for existing window by ID
    const existing = winboxInstances.value.find(w => w.id === windowId)
    if (existing) {
      // If window is minimized, restore it
      if (existing.min) {
        existing.restore()
      }
      // Focus the existing window
      existing.focus()
      return existing
    }

    const entityDisplayName = metadataStore.formatEntityName(entityName)
    
    // Get icon from metadata, default to 'bi-box'
    const entityMeta = metadataStore.getEntityMetadata(entityName)
    const iconName = entityMeta?.icon || 'bi-box'
    const iconPath = getIconPath(iconName)
    
    let title
    if (mode === 'create') {
      title = `Create ${entityDisplayName}`
    } else if (mode === 'edit') {
      title = recordName ? `Edit ${recordName}` : `Edit ${entityDisplayName}`
    } else {
      // detail mode
      title = recordName || `${entityDisplayName} - ${recordId?.substring(0, 8) || 'Detail'}`
    }

    // Create container for Vue app
    const container = document.createElement('div')
    container.style.width = '100%'
    container.style.height = '100%'
    container.style.overflow = 'auto'
    container.style.padding = '1rem'

    // Create reactive refs for props that might change
    const recordIdRef = ref(recordId)
    const modeRef = ref(mode)
    const recordNameRef = ref(recordName)
    let app = null
    let x = 90;
    let y = 58 + 90;
    if (winboxInstances.value.length > 0) {
        const last = winboxInstances.value[winboxInstances.value.length - 1];
        x = Math.min((last.x || 90) + 30, window.innerWidth - (last.width || 400));
        y = Math.min((last.y || 180) + 30, window.innerHeight - (last.height || 300));
    }
    // Create WinBox window first
    const winbox = new WinBox(title, {
      id: windowId,
      width: 800,
      height: 600,
      top: 68,
      x,
      y,
      icon: iconPath,
      class: ['no-full'],
      mount: container,
      oncreate: function() {
        // Add Bootstrap classes to newly created windows
        const wbWindow = this.body.parentNode
        const wbHeader = wbWindow.querySelector('.wb-header')
        wbWindow.classList.add('rounded-2')
        if (wbHeader) {
          wbHeader.classList.add('rounded-top-2')
          wbHeader.classList.remove('border-bottom')
        }
      },
      onminimize: function() {
        const wbWindow = this.body.parentNode
        // Add instance first to make sidebar visible
        addMinimizedInstance(winbox)
        
        // Wait for container to be available, then append
        nextTick(() => {
          const minimizedContainer = minimizedContainerRef.value
          if (minimizedContainer) {
            minimizedContainer.appendChild(wbWindow)
            wbWindow.style.position = 'static'
            const wbHeader = wbWindow.querySelector('.wb-header')
            wbWindow.classList.remove('rounded-2')
            if (wbHeader) {
              wbHeader.classList.remove('rounded-top-2')
              wbHeader.classList.add('border-bottom')
              wbHeader.style.backgroundColor = 'rgb(92, 146, 193)'
              const wbMax = wbHeader.querySelector('.wb-max')
              if (wbMax) {
                wbMax.style.display = 'none'
              }
            }
          }
        })
      },
      onrestore: function() {
        const minimizedContainer = minimizedContainerRef.value
        if (minimizedContainer) {
          const wbWindow = this.body.parentNode
          // Check if window is in the minimized container
          if (minimizedContainer.contains(wbWindow)) {
            // Remove from minimized container and append to body
            minimizedContainer.removeChild(wbWindow)
            document.body.appendChild(wbWindow)
            
            // Restore control display
            const control = wbWindow.querySelector('.wb-control')
            if (control) {
              control.style.display = 'flex'
            }
            
            // Restore window styling
            wbWindow.style.position = ''
            const wbHeader = wbWindow.querySelector('.wb-header')
            wbWindow.classList.add('rounded-2')
            if (wbHeader) {
              wbHeader.classList.add('rounded-top-2')
              wbHeader.classList.remove('border-bottom')
              wbHeader.style.backgroundColor = ''
              const wbMax = wbHeader.querySelector('.wb-max')
              if (wbMax) {
                wbMax.style.display = 'block'
              }
            }
            removeMinimizedInstance(winbox)
          }
        }
      },
      onclose: function() {
        // Clean up Vue app
        if (app) {
          app.unmount()
        }
        // Remove from minimized instances if it was minimized
        removeMinimizedInstance(winbox)
        // Remove from instances using the window ID
        const idx = winboxInstances.value.findIndex(w => w.id === windowId)
        if (idx !== -1) {
          winboxInstances.value.splice(idx, 1)
        }
        return false // Allow close
      }
    })

    // Watch mode changes to update title
    watch(modeRef, (newMode) => {
      if (newMode === 'edit' && recordNameRef.value) {
        winbox.setTitle(`Edit ${recordNameRef.value}`)
      } else if (newMode === 'detail' && recordNameRef.value) {
        winbox.setTitle(recordNameRef.value)
      }
    })

    // Use UserOwnedRecordsView for Users entity, RecordDetailView for others
    const Component = entityName === 'users' ? UserOwnedRecordsView : RecordDetailView
    let componentProps
    
    if (entityName === 'users') {
      componentProps = {
        userId: recordIdRef.value,
        onRecordClick: (data) => {
          // Open clicked record in a new window
          if (data.entity && data.recordId) {
            openRecordWindow(data.entity, data.recordId, 'detail')
          }
        }
      }
    } else {
      componentProps = {
        entityName,
        recordId: recordIdRef.value,
        initialMode: modeRef.value,
        onSaved: (data) => {
        // Update recordId if it was a create
        if (modeRef.value === 'create' && data.record?.id) {
          recordIdRef.value = data.record.id
        }
        
        // Update title with record name if available
        const savedRecord = data.record
        if (savedRecord) {
          const entityMeta = metadataStore.getEntityMetadata(entityName)
          const isPerson = entityMeta?.person === true
          let savedRecordName = null
          
          if (isPerson && savedRecord.first_name && savedRecord.last_name) {
            savedRecordName = `${savedRecord.first_name} ${savedRecord.last_name}`.trim()
          } else if (savedRecord.name) {
            savedRecordName = savedRecord.name
          }
          
          if (savedRecordName) {
            recordNameRef.value = savedRecordName
            winbox.setTitle(savedRecordName)
          } else {
            winbox.setTitle(`${entityDisplayName} - ${recordIdRef.value?.substring(0, 8) || 'Detail'}`)
          }
        } else {
          winbox.setTitle(`${entityDisplayName} - ${recordIdRef.value?.substring(0, 8) || 'Detail'}`)
        }
        modeRef.value = 'detail'
      },
      'onUpdate:recordId': (newId) => {
        recordIdRef.value = newId
      },
      'onUpdate:mode': (newMode) => {
        modeRef.value = newMode
      },
      onRecordLoaded: (data) => {
        if (data.recordName && !recordNameRef.value) {
          recordNameRef.value = data.recordName
          if (modeRef.value === 'detail') {
            winbox.setTitle(data.recordName)
          } else if (modeRef.value === 'edit') {
            winbox.setTitle(`Edit ${data.recordName}`)
          }
        }
      },
      onCancel: () => {
        winbox.close()
      },
      onDeleted: () => {
        winbox.close()
      },
      onRecordClick: (data) => {
        // Open clicked record in a new window
        if (data.entity && data.recordId) {
          openRecordWindow(data.entity, data.recordId, 'detail')
        }
      }
    }
    }

    // Create Vue app after winbox is created
    app = createApp(Component, componentProps)

    // Mount Vue app
    app.mount(container)

    // Store winbox instance directly in array (like old approach)
    winboxInstances.value.push(winbox)

    return winbox
  }

  function closeAll() {
    winboxInstances.value.forEach(winbox => {
      if (winbox && !winbox.closed) {
        winbox.close()
      }
    })
    winboxInstances.value = []
  }

  return {
    openRecordWindow,
    closeAll,
    instances: winboxInstances
  }
}

