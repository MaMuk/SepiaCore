import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { isHttpOnlyCookieMode } from '../config'

export const useAuthStore = defineStore('auth', () => {
  // In httpOnly cookie mode, we can't read the token from JS
  // So we only store it in localStorage for dev mode
  const useHttpOnly = isHttpOnlyCookieMode()
  
  const token = ref(useHttpOnly ? null : (localStorage.getItem('token') || null))
  const isAdmin = ref(localStorage.getItem('isAdmin') === 'true')
  const username = ref(localStorage.getItem('username') || null)

  // In httpOnly mode, we assume authenticated if we have username/isAdmin
  // (token is in cookie, not accessible to JS)
  const isAuthenticated = computed(() => {
    if (useHttpOnly) {
      // If httpOnly cookies are used, check if we have user info
      // The actual token is in the httpOnly cookie
      return !!username.value
    }
    return !!token.value
  })

  function setAuth({ token: newToken, isAdmin: adminStatus, username: user }) {
    isAdmin.value = !!adminStatus // Ensure boolean
    username.value = user

    // Only store token in localStorage if NOT using httpOnly cookies
    // (httpOnly cookies are set by backend and not accessible to JS)
    if (!useHttpOnly && newToken) {
      token.value = newToken
      localStorage.setItem('token', newToken)
    } else {
      // In httpOnly mode, token is in cookie, we just track user info
      token.value = null
    }
    
    localStorage.setItem('isAdmin', adminStatus ? 'true' : 'false') // Store as string
    localStorage.setItem('username', user)
  }

  function logout() {
    token.value = null
    isAdmin.value = false
    username.value = null

    // Clear localStorage (cookie will be cleared by backend on logout)
    localStorage.removeItem('token')
    localStorage.removeItem('isAdmin')
    localStorage.removeItem('username')
  }

  return {
    token,
    isAdmin,
    username,
    isAuthenticated,
    setAuth,
    logout
  }
})

