import { ref, nextTick, watch } from 'vue'
import WinBox from 'winbox/src/js/winbox.js'
import { createApp } from 'vue'
import RecordDetailView from '../components/RecordDetailView.vue'
import UserOwnedRecordsView from '../components/UserOwnedRecordsView.vue'
import { useMetadataStore } from '../stores/metadata'
import { useWindowPane } from './useWindowPane'
import { useViewport } from './useViewport'
import { getIconPath } from '../utils/iconUtils'

let winboxInstances = ref([])
let windowPaneWatcherInitialized = false
/* Trigger Hack für Vue-Reaktivität: WinBox-State und DOM-Klassen entstehen ausserhalb von Vue,
   damit die Sidebar nicht mit veralteten Daten läuft. */
const sidebarPulse = ref(0)

export function useWinbox() {
  const metadataStore = useMetadataStore()
  const { paneContainerRef, isExpanded: isWindowPaneExpanded } = useWindowPane()
  const { isMobile } = useViewport()

  function getWinboxWindowElement(winboxInstance) {
    return winboxInstance?.body?.parentNode || null
  }

  function applyNormalWindowStyles(wbWindow) {
    if (!wbWindow) return
    wbWindow.style.position = ''
    wbWindow.style.right = ''
    wbWindow.style.bottom = ''
    wbWindow.classList.remove('windowpane-attached')
    wbWindow.classList.remove('window-mobile-attached')
    wbWindow.classList.remove('no-move')
    wbWindow.classList.remove('no-resize')
    wbWindow.classList.remove('no-max')
    wbWindow.classList.add('rounded-2')
    const wbHeader = wbWindow.querySelector('.wb-header')
    if (wbHeader) {
      wbHeader.classList.add('rounded-top-2')
      wbHeader.classList.remove('border-bottom')
      wbHeader.style.backgroundColor = ''
      const wbMax = wbHeader.querySelector('.wb-max')
      if (wbMax) {
        wbMax.style.display = 'block'
      }
      const wbMin = wbHeader.querySelector('.wb-min')
      if (wbMin) {
        wbMin.style.display = 'block'
      }
    }
    const control = wbWindow.querySelector('.wb-control')
    if (control) {
      control.style.display = 'flex'
    }
  }

  function applyMobileWindowStyles(wbWindow) {
    if (!wbWindow) return
    wbWindow.style.position = ''
    wbWindow.style.right = ''
    wbWindow.style.bottom = ''
    wbWindow.style.width = '100%'
    wbWindow.style.height = '100%'
    wbWindow.classList.remove('windowpane-attached')
    wbWindow.classList.add('window-mobile-attached')
    wbWindow.classList.add('no-move')
    wbWindow.classList.add('no-resize')
    wbWindow.classList.add('no-max')
    wbWindow.classList.add('rounded-2')
    const wbHeader = wbWindow.querySelector('.wb-header')
    if (wbHeader) {
      wbHeader.classList.add('rounded-top-2')
      wbHeader.classList.remove('border-bottom')
      wbHeader.style.backgroundColor = ''
      const wbMin = wbHeader.querySelector('.wb-min')
      if (wbMin) {
        wbMin.style.display = 'none'
      }
      const wbMax = wbHeader.querySelector('.wb-max')
      if (wbMax) {
        wbMax.style.display = 'none'
      }
    }
    const control = wbWindow.querySelector('.wb-control')
    if (control) {
      control.style.display = 'flex'
    }
    const wbMax = wbHeader.querySelector('.wb-max')
    if (wbMax) {
      wbMax.style.display = 'none'
    }
  }

  function applyPaneWindowStyles(wbWindow) {
    if (!wbWindow) return
    wbWindow.style.position = ''
    wbWindow.style.top = '0'
    wbWindow.style.left = '0'
    wbWindow.style.right = '0'
    wbWindow.style.bottom = '0'
    wbWindow.style.width = '100%'
    wbWindow.style.height = '100%'
    wbWindow.classList.add('windowpane-attached')
    wbWindow.classList.remove('window-mobile-attached')
    wbWindow.classList.add('no-move')
    wbWindow.classList.add('no-resize')
    wbWindow.classList.add('no-max')
    wbWindow.classList.add('rounded-2')
    const wbHeader = wbWindow.querySelector('.wb-header')
    if (wbHeader) {
      wbHeader.classList.remove('rounded-top-2')
      wbHeader.classList.remove('border-bottom')
      wbHeader.style.backgroundColor = ''
      const wbMax = wbHeader.querySelector('.wb-max')
      if (wbMax) {
        wbMax.style.display = 'none'
      }
      const wbMin = wbHeader.querySelector('.wb-min')
      if (wbMin) {
        wbMin.style.display = 'block'
      }
    }
    const control = wbWindow.querySelector('.wb-control')
    if (control) {
      control.style.display = 'flex'
    }
  }

  function maximizeWinbox(winboxInstance) {
    if (typeof winboxInstance?.maximize === 'function') {
      winboxInstance.maximize()
    } else if (typeof winboxInstance?.max === 'function') {
      winboxInstance.max()
    }
  }

  function minimizeWinbox(winboxInstance) {
    if (typeof winboxInstance?.minimize === 'function') {
      winboxInstance.minimize()
    } else if (typeof winboxInstance?.min === 'function') {
      winboxInstance.min()
    }
  }

  function showWinbox(winboxInstance) {
    if (typeof winboxInstance?.show === 'function') {
      winboxInstance.show()
    }
  }

  function hideWinbox(winboxInstance) {
    if (typeof winboxInstance?.hide === 'function') {
      winboxInstance.hide()
    }
  }


  function bumpSidebarPulse() {
    sidebarPulse.value += 1
  }

  function setWinboxTitle(winboxInstance, title) {
    if (typeof winboxInstance?.setTitle === 'function') {
      winboxInstance.setTitle(title)
      bumpSidebarPulse()
    }
  }

  function attachToPane(winboxInstance) {
    const paneContainer = paneContainerRef.value
    const wbWindow = getWinboxWindowElement(winboxInstance)
    if (!paneContainer || !wbWindow) return false
    if (wbWindow.parentNode !== paneContainer) {
      paneContainer.appendChild(wbWindow)
    }
    applyPaneWindowStyles(wbWindow)
    return true
  }

  function attachToBody(winboxInstance, forceRestore = false) {
    const wbWindow = getWinboxWindowElement(winboxInstance)
    if (!wbWindow) return
    if (wbWindow.parentNode !== document.body) {
      document.body.appendChild(wbWindow)
    }
    if (typeof winboxInstance?.restore === 'function') {
      winboxInstance.restore()
    }
    if (isMobile.value) {
      applyMobileWindowStyles(wbWindow)
    } else {
      applyNormalWindowStyles(wbWindow)
    }
  }

  function ensureFloatingPosition(winboxInstance) {
    const wbWindow = getWinboxWindowElement(winboxInstance)
    if (!wbWindow) return
    const style = wbWindow.style
    const hasPosition =
      style.left || style.top || style.width || style.height
    if (hasPosition) return
    const width = winboxInstance?.width || 800
    const height = winboxInstance?.height || 600
    const x = typeof winboxInstance?.x === 'number' ? winboxInstance.x : 90
    const y = typeof winboxInstance?.y === 'number' ? winboxInstance.y : 148
    if (typeof winboxInstance?.resize === 'function') {
      winboxInstance.resize(width, height)
    }
    if (typeof winboxInstance?.move === 'function') {
      winboxInstance.move(x, y)
    }
  }

  function detachFromPane(winboxInstance) {
    const paneContainer = paneContainerRef.value
    const wbWindow = getWinboxWindowElement(winboxInstance)
    if (!paneContainer || !wbWindow) return
    if (wbWindow.parentNode === paneContainer) {
      attachToBody(winboxInstance)
      ensureFloatingPosition(winboxInstance)
    }
  }

  function minimizeAllExcept(exceptId) {
    winboxInstances.value.forEach(winboxInstance => {
      if (!winboxInstance || winboxInstance.closed) return
      if (winboxInstance.id === exceptId) return
      if (winboxInstance.min) return
      minimizeWinbox(winboxInstance)
    })
  }

  function attachToPaneWithRetry(winboxInstance) {
    if (attachToPane(winboxInstance)) {
      maximizeWinbox(winboxInstance)
      applyPaneWindowStyles(getWinboxWindowElement(winboxInstance))
      return
    }
    nextTick(() => {
      if (attachToPane(winboxInstance)) {
        maximizeWinbox(winboxInstance)
        applyPaneWindowStyles(getWinboxWindowElement(winboxInstance))
      }
    })
  }

  function ensureWindowPaneAttachment(winboxInstance) {
    if (!isWindowPaneExpanded.value) {
      attachToBody(winboxInstance)
      if (isMobile.value) {
        maximizeWinbox(winboxInstance)
      }
      return
    }
    minimizeAllExcept(winboxInstance.id)
    attachToPaneWithRetry(winboxInstance)
  }

  if (!windowPaneWatcherInitialized) {
    windowPaneWatcherInitialized = true
    watch(isWindowPaneExpanded, (expanded) => {
      if (expanded) {
        const candidates = winboxInstances.value.filter(winboxInstance => {
          return winboxInstance && !winboxInstance.closed && !winboxInstance.min
        })
        if (candidates.length === 0) return
        const active = candidates[candidates.length - 1]
        minimizeAllExcept(active.id)
        attachToPaneWithRetry(active)
      } else {
        winboxInstances.value.forEach(winboxInstance => {
          if (!winboxInstance || winboxInstance.closed) return
          detachFromPane(winboxInstance)
        })
      }
    })
    watch(isMobile, (mobile) => {
      if (!mobile) return
      winboxInstances.value.forEach(winboxInstance => {
        if (!winboxInstance || winboxInstance.closed) return
        attachToBody(winboxInstance)
        maximizeWinbox(winboxInstance)
      })
    })
    watch(isMobile, (mobile) => {
      if (mobile) return
      winboxInstances.value.forEach(winboxInstance => {
        if (!winboxInstance || winboxInstance.closed) return
        if (isWindowPaneExpanded.value) {
          ensureWindowPaneAttachment(winboxInstance)
          return
        }
        attachToBody(winboxInstance)
        ensureFloatingPosition(winboxInstance)
      })
    })
  }

  function openRecordWindow(entityName, recordId = null, mode = 'detail', recordName = null) {
    // Create unique ID for this window
    const windowId = `${entityName}-${recordId || 'create'}-${mode}`
    
    // Check for existing window by ID
    const existing = winboxInstances.value.find(w => w.id === windowId)
    if (existing) {
      if (isWindowPaneExpanded.value) {
        minimizeAllExcept(existing.id)
        if (existing.min) {
          existing.restore()
        } else {
          attachToPaneWithRetry(existing)
        }
      } else if (existing.min) {
        existing.restore()
      }
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
        hideWinbox(winbox)
        bumpSidebarPulse()
      },
      onrestore: function() {
        showWinbox(winbox)
        if (isWindowPaneExpanded.value) {
          minimizeAllExcept(winbox.id)
          attachToPaneWithRetry(winbox)
        } else {
          attachToBody(winbox)
          ensureFloatingPosition(winbox)
        }
        bumpSidebarPulse()
      },
      onfocus: function() {
        this.__isFocused = true
        bumpSidebarPulse()
      },
      onblur: function() {
        this.__isFocused = false
        bumpSidebarPulse()
      },
      onclose: function() {
        // Clean up Vue app
        if (app) {
          app.unmount()
        }
        // Remove from instances using the window ID
        const idx = winboxInstances.value.findIndex(w => w.id === windowId)
        if (idx !== -1) {
          winboxInstances.value.splice(idx, 1)
        }
        bumpSidebarPulse()
        return false // Allow close
      }
    })
    winbox.__sidebarIcon = iconPath

    // Watch mode changes to update title
    watch(modeRef, (newMode) => {
      if (newMode === 'edit' && recordNameRef.value) {
        setWinboxTitle(winbox, `Edit ${recordNameRef.value}`)
      } else if (newMode === 'detail' && recordNameRef.value) {
        setWinboxTitle(winbox, recordNameRef.value)
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
            setWinboxTitle(winbox, savedRecordName)
          } else {
            setWinboxTitle(winbox, `${entityDisplayName} - ${recordIdRef.value?.substring(0, 8) || 'Detail'}`)
          }
        } else {
          setWinboxTitle(winbox, `${entityDisplayName} - ${recordIdRef.value?.substring(0, 8) || 'Detail'}`)
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
            setWinboxTitle(winbox, data.recordName)
          } else if (modeRef.value === 'edit') {
            setWinboxTitle(winbox, `Edit ${data.recordName}`)
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
    bumpSidebarPulse()

    if (isMobile.value) {
      attachToBody(winbox)
      maximizeWinbox(winbox)
    } else if (isWindowPaneExpanded.value) {
      ensureWindowPaneAttachment(winbox)
    }

    return winbox
  }

  function closeAll() {
    winboxInstances.value.forEach(winbox => {
      if (winbox && !winbox.closed) {
        winbox.close()
      }
    })
    winboxInstances.value = []
    bumpSidebarPulse()
  }

  return {
    openRecordWindow,
    closeAll,
    instances: winboxInstances,
    sidebarPulse
  }
}
