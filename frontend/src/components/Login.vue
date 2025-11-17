<template>
  <div class="login-container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg" style="width: 100%; max-width: 400px; position: relative;">
      <button
        class="btn btn-link position-absolute top-0 end-0 m-2"
        style="z-index: 10; padding: 0.25rem 0.5rem;"
        @click="openApiUrlModal"
        title="API Settings"
      >
        <i class="bi bi-gear-fill" style="font-size: 1.2rem;"></i>
      </button>
      <div class="card-body p-5">
        <!-- Backend Status Alert -->
        <div v-if="backendStatusChecked && !backendReachable && !backendAlertDismissed" class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
          <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Backend Unreachable</strong>
          <p class="mb-0 mt-2 small">{{ backendStatusMessage }}</p>
          <p class="mb-0 mt-2 small">Click the <i class="bi bi-gear-fill"></i> button to configure the API URL.</p>
          <button type="button" class="btn-close" @click="dismissBackendAlert" aria-label="Close"></button>
        </div>

        <div class="text-center mb-4">
          <h2 class="card-title mb-2">Welcome</h2>
          <p class="text-muted">Please sign in to continue</p>
        </div>

        <form @submit.prevent="handleLogin">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input
              type="text"
              class="form-control"
              id="username"
              v-model="username"
              :disabled="loading"
              required
              autocomplete="username"
              placeholder="Enter your username"
            />
          </div>

          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input
              type="password"
              class="form-control"
              id="password"
              v-model="password"
              :disabled="loading"
              required
              autocomplete="current-password"
              placeholder="Enter your password"
            />
          </div>

          <button
            type="submit"
            class="btn btn-primary w-100"
            :disabled="loading || !username || !password"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            <span v-else>Sign In</span>
          </button>
        </form>
      </div>
    </div>

    <!-- API Base URL Modal -->
    <ApiBaseUrlModal
      v-model="showApiUrlModal"
      @saved="handleApiUrlSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import authService from '../services/authService'
import ApiBaseUrlModal from './ApiBaseUrlModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const toastStore = useToastStore()
window.toastStore = toastStore;

const username = ref('')
const password = ref('')
const loading = ref(false)
const showApiUrlModal = ref(false)
const backendStatusChecked = ref(false)
const backendReachable = ref(true)
const backendStatusMessage = ref('')
const backendAlertDismissed = ref(false)

onMounted(async () => {
  // Check if backend status was already checked (e.g., from BackendCheck redirect)
  const backendChecked = sessionStorage.getItem('backendChecked')
  const backendStatusStr = sessionStorage.getItem('backendStatus')
  
  if (backendChecked === 'true' && backendStatusStr) {
    // Use the status from BackendCheck to avoid duplicate ping
    try {
      const status = JSON.parse(backendStatusStr)
      backendStatusChecked.value = true
      backendReachable.value = status.reachable
      
      // If backend is reachable but not installed, redirect to installation
      if (status.reachable && !status.installed) {
        // Clear sessionStorage before redirect
        sessionStorage.removeItem('backendStatus')
        sessionStorage.removeItem('backendChecked')
        router.push('/install')
        return
      }
      
      if (!status.reachable) {
        backendStatusMessage.value = status.error 
          ? `Backend server is not reachable: ${status.error}`
          : 'Backend server is not reachable. Please check your connection and API URL configuration.'
        toastStore.warning('Backend server is not reachable')
      }
      
      // Clear sessionStorage after using it
      sessionStorage.removeItem('backendStatus')
      sessionStorage.removeItem('backendChecked')
    } catch (error) {
      // If parsing fails, fall back to checking
      sessionStorage.removeItem('backendStatus')
      sessionStorage.removeItem('backendChecked')
      await checkBackendStatus()
    }
  } else {
    // No previous check, perform the check now
    await checkBackendStatus()
  }
})

async function checkBackendStatus() {
  try {
    const status = await authService.checkBackendStatus()
    backendStatusChecked.value = true
    backendReachable.value = status.reachable
    
    // If backend is reachable but not installed, redirect to installation
    if (status.reachable && !status.installed) {
      router.push('/install')
      return
    }
    
    if (!status.reachable) {
      backendStatusMessage.value = status.error 
        ? `Backend server is not reachable: ${status.error}`
        : 'Backend server is not reachable. Please check your connection and API URL configuration.'
      toastStore.warning('Backend server is not reachable')
    }
  } catch (error) {
    backendStatusChecked.value = true
    backendReachable.value = false
    backendStatusMessage.value = `Failed to check backend status: ${error.message}`
    toastStore.error('Failed to check backend status')
  }
}

function dismissBackendAlert() {
  backendAlertDismissed.value = true
}

async function handleLogin() {
  if (!username.value || !password.value) {
    toastStore.warning('Please enter both username and password')
    return
  }

  loading.value = true

  try {
    const result = await authService.login(username.value, password.value)

    if (result.success) {
      toastStore.success('Login successful!')
      router.push('/app')
    } else {
      toastStore.error(result.error || 'Login failed. Please try again.')
    }
  } catch (error) {
    toastStore.error('An unexpected error occurred. Please try again.')
  } finally {
    loading.value = false
  }
}

function openApiUrlModal() {
  showApiUrlModal.value = true
}

function handleApiUrlSaved() {
  // Modal will reload the page, so no need to update here
}
</script>

<style scoped>
.login-container {
  background: linear-gradient(135deg,rgba(102, 126, 234, 0.14) 0%,rgb(255, 255, 255) 100%);
}

.card {
  border: none;
  border-radius: 1rem;
}

.form-control:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
  background-color: #667eea;
  border-color: #667eea;
}

.btn-primary:hover {
  background-color: #5568d3;
  border-color: #5568d3;
}
</style>

