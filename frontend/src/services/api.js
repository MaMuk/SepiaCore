import axios from 'axios'
import { getApiBaseUrl, isHttpOnlyCookieMode } from '../config'
import { useAuthStore } from '../stores/auth'
import authService from './authService'

// Create axios instance with dynamic baseURL
// withCredentials is set conditionally based on httpOnly cookie mode
const api = axios.create({
  headers: {
    'Content-Type': 'application/json'
  },
  withCredentials: isHttpOnlyCookieMode() // Only send credentials when httpOnly cookies are enabled
})

// Set baseURL dynamically
function updateBaseURL() {
  const baseURL = getApiBaseUrl()
  if (baseURL) {
    api.defaults.baseURL = baseURL
  }
}

// Initialize baseURL
updateBaseURL()

// Request interceptor to add auth token and update baseURL
api.interceptors.request.use(
  (config) => {
    // Update baseURL on each request in case it changed
    updateBaseURL()
    
    const useHttpOnly = isHttpOnlyCookieMode()
    const authStore = useAuthStore()
    
    // Only add Authorization header if NOT using httpOnly cookies
    // (httpOnly cookies are sent automatically by browser via withCredentials)
    if (!useHttpOnly && authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    // If httpOnly cookies are enabled, the token is in the cookie
    // and will be sent automatically by the browser
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Use authService.logout() to handle backend logout and redirect
      // Router not provided, so it will use window.location fallback
      authService.logout()
    }
    return Promise.reject(error)
  }
)

export default api
export { updateBaseURL }

