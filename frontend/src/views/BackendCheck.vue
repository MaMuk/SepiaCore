<template>
  <div class="backend-check d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center" v-if="!checkComplete">
      <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
      </div>
      <h4>Checking backend connection...</h4>
      <p class="text-muted">Please wait while we verify the server is available.</p>
    </div>
    <div class="text-center" v-else-if="backendError">
      <div class="alert alert-warning" role="alert" style="max-width: 500px; margin: 0 auto;">
        <h5 class="alert-heading">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Backend Connection Issue
        </h5>
        <p class="mb-3">{{ backendErrorMessage }}</p>
        <p class="mb-0">
          <small>You can configure the API URL using the settings button (⚙️) on the login page.</small>
        </p>
        <hr>
        <button class="btn btn-primary" @click="redirectToLogin">
          Continue to Login
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import authService from '../services/authService'

const router = useRouter()
const authStore = useAuthStore()
const toastStore = useToastStore()

const checkComplete = ref(false)
const backendError = ref(false)
const backendErrorMessage = ref('')

onMounted(async () => {
  try {
    const status = await authService.checkBackendStatus()

    if (!status.reachable) {
      // Show error message and let user proceed to login
      backendError.value = true
      backendErrorMessage.value = status.error 
        ? `Backend server is not reachable: ${status.error}`
        : 'Backend server is not reachable. Please check your connection and API URL configuration.'
      checkComplete.value = true
      toastStore.error('Backend server is not reachable')
      return
    }

    if (!status.installed) {
      checkComplete.value = true
      router.push('/install')
      return
    }

    // Backend is reachable and installed
    checkComplete.value = true
    if (authStore.isAuthenticated) {
      router.push('/app')
    } else {
      // Store backend status in sessionStorage to avoid duplicate ping in Login
      sessionStorage.setItem('backendStatus', JSON.stringify(status))
      sessionStorage.setItem('backendChecked', 'true')
      router.push('/login')
    }
  } catch (error) {
    // Show error message and let user proceed to login
    backendError.value = true
    backendErrorMessage.value = `Failed to check backend status: ${error.message}`
    checkComplete.value = true
    toastStore.error('Failed to check backend status')
  }
})

function redirectToLogin() {
  // Mark that backend check was done (even if unreachable)
  sessionStorage.setItem('backendChecked', 'true')
  router.push('/login')
}
</script>

<style scoped>
.backend-check {
  background-color: #f8f9fa;
}
</style>

