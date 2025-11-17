import { useAuthStore } from '../stores/auth'
import { getApiBaseUrl, isHttpOnlyCookieMode } from '../config'

export class AuthService {
  /**
   * Check if backend is reachable and installed
   */
  async checkBackendStatus() {
    const apiBaseUrl = getApiBaseUrl()
    if (!apiBaseUrl) {
      return {
        reachable: false,
        installed: false,
        error: 'API Base URL is not configured'
      }
    }

    try {
      const response = await fetch(`${apiBaseUrl}/ping`)
      const data = await response.json()
      return {
        reachable: true,
        installed: data.isInstalled || false
      }
    } catch (error) {
      return {
        reachable: false,
        installed: false,
        error: error.message
      }
    }
  }

  /**
   * Login user
   */
  async login(username, password) {
    const apiBaseUrl = getApiBaseUrl()
    if (!apiBaseUrl) {
      return {
        success: false,
        error: 'API Base URL is not configured. Please configure it in settings.'
      }
    }

    try {
      // Backend expects form data, not JSON
      const formData = new URLSearchParams()
      formData.append('username', username)
      formData.append('password', password)

      const useHttpOnly = isHttpOnlyCookieMode()
      
      const response = await fetch(`${apiBaseUrl}/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        credentials: useHttpOnly ? 'include' : 'same-origin', // Only include credentials when httpOnly cookies enabled
        body: formData
      })

      const data = await response.json()

      if (response.ok && data.token) {
        const authStore = useAuthStore()
        authStore.setAuth({
          token: data.token,
          isAdmin: data.isAdmin || false,
          username
        })
        return { success: true }
      }

      return { success: false, error: data.error || 'Invalid response from server' }
    } catch (error) {
      return { success: false, error: error.message || 'Login failed. Please try again.' }
    }
  }

  /**
   * Logout user
   * @param {Object} router - Optional router instance for redirect after logout
   */
  async logout(router = null) {
    const apiBaseUrl = getApiBaseUrl()
    const authStore = useAuthStore()
    const useHttpOnly = isHttpOnlyCookieMode()
    
    // Call backend logout endpoint
    // In httpOnly mode, token is in cookie (sent automatically)
    // In localStorage mode, send token in Authorization header
    if (apiBaseUrl) {
      try {
        const headers = {
          'Content-Type': 'application/json'
        }
        
        // Only add Authorization header if NOT using httpOnly cookies
        if (!useHttpOnly && authStore.token) {
          headers['Authorization'] = `Bearer ${authStore.token}`
        }
        
        await fetch(`${apiBaseUrl}/logout`, {
          method: 'POST',
          headers,
          credentials: useHttpOnly ? 'include' : 'same-origin' // Only include credentials when httpOnly cookies enabled
        })
      } catch (error) {
        // Ignore errors on logout - still clear local state
        console.error('Logout request failed:', error)
      }
    }
    
    // Always clear local auth state
    authStore.logout()
    
    // Redirect to root path to refresh interface and trigger router guards
    if (router) {
      router.push('/')
    } else {
      // Fallback: use window.location if router not provided
      window.location.href = '/'
    }
  }
}

export default new AuthService()

