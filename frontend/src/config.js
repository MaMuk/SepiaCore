// Configuration utility functions
// Priority: localStorage -> .env file -> no fallback (undefined)

const STORAGE_KEY_API_BASE_URL = 'api_base_url'
const STORAGE_KEY_LIST_LIMIT = 'list_limit'

/**
 * Get API_BASE_URL from localStorage first, then .env
 * Returns undefined if neither is set (no hardcoded fallback)
 */
export function getApiBaseUrl() {
  // First try localStorage
  const stored = localStorage.getItem(STORAGE_KEY_API_BASE_URL)
  if (stored) {
    return stored
  }
  // Then try .env
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL
  }
  // No fallback - return undefined
  return undefined
}

/**
 * Set API_BASE_URL in localStorage
 */
export function setApiBaseUrl(url) {
  if (url) {
    localStorage.setItem(STORAGE_KEY_API_BASE_URL, url)
  } else {
    localStorage.removeItem(STORAGE_KEY_API_BASE_URL)
  }
}

/**
 * Get LIST_LIMIT from localStorage first, then .env, default to 10
 */
export function getListLimit() {
  // First try localStorage
  const stored = localStorage.getItem(STORAGE_KEY_LIST_LIMIT)
  if (stored) {
    return parseInt(stored, 10)
  }
  // Then try .env
  if (import.meta.env.VITE_LIST_LIMIT) {
    return parseInt(import.meta.env.VITE_LIST_LIMIT, 10)
  }
  // Default to 10
  return 10
}

/**
 * Set LIST_LIMIT in localStorage
 */
export function setListLimit(limit) {
  if (limit) {
    localStorage.setItem(STORAGE_KEY_LIST_LIMIT, limit.toString())
  } else {
    localStorage.removeItem(STORAGE_KEY_LIST_LIMIT)
  }
}

/**
 * Get auth token from cookie (if httpOnly cookies are used)
 * Returns null if cookie not found or not accessible
 */
export function getTokenFromCookie() {
  // Try to read cookie (only works if not httpOnly, but we check anyway)
  const cookies = document.cookie.split(';')
  for (let cookie of cookies) {
    const [name, value] = cookie.trim().split('=')
    if (name === 'auth_token') {
      return decodeURIComponent(value)
    }
  }
  return null
}

/**
 * Check if httpOnly cookies are enabled
 * This is determined by whether we can read the cookie from JavaScript
 * If cookie exists but we can't read it, httpOnly is enabled
 */
export function isHttpOnlyCookieMode() {
  // Check if cookie exists but is not readable (httpOnly)
  // We'll determine this by checking if backend sent a Set-Cookie header
  // For now, we'll check if token exists in localStorage (dev mode) vs cookie
  const hasLocalStorageToken = !!localStorage.getItem('token')
  const hasCookieToken = getTokenFromCookie() !== null
  
  // If cookie exists but we can't read it via JS, httpOnly is enabled
  // We'll use a simpler approach: check if we should use cookies based on env
  return import.meta.env.VITE_USE_HTTPONLY_COOKIES === 'true'
}

// Export computed values for backward compatibility
export const API_BASE_URL = getApiBaseUrl()
export const LIST_LIMIT = getListLimit()

export default {
  API_BASE_URL,
  LIST_LIMIT,
  getApiBaseUrl,
  setApiBaseUrl,
  getListLimit,
  setListLimit
}

