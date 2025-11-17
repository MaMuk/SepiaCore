<template>
  <div id="app">
    <ToastContainer />
    <router-view v-if="metadataLoaded" />
    <div v-else class="d-flex align-items-center justify-content-center min-vh-100">
      <div class="text-center">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p>Loading application...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import ToastContainer from './components/ToastContainer.vue'
import { useMetadataStore } from './stores/metadata'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import authService from './services/authService'

const router = useRouter()
const metadataStore = useMetadataStore()
const authStore = useAuthStore()
const toastStore = useToastStore()
const metadataLoaded = ref(false)

onMounted(async () => {
  // Only load metadata if user is authenticated
  if (authStore.isAuthenticated) {
    try {
      await metadataStore.fetchMetadata()
      // Update document title from metadata
      if (metadataStore.metadata?.appTitle) {
        // Sanitize title before setting (defense in depth)
        const sanitized = metadataStore.metadata.appTitle.replace(/<[^>]*>/g, '').substring(0, 100)
        document.title = sanitized
      }
      metadataLoaded.value = true
    } catch (error) {
      toastStore.error('Failed to load metadata')
      metadataLoaded.value = true // Still show app, but with error
    }
  } else {
    metadataLoaded.value = true
  }
})

// Watch for metadata changes to update title
watch(() => metadataStore.metadata?.appTitle, (newTitle) => {
  if (newTitle) {
    // Sanitize title before setting (defense in depth)
    const sanitized = newTitle.replace(/<[^>]*>/g, '').substring(0, 100)
    document.title = sanitized
  }
})
</script>

<style>
#app {
  min-height: 100vh;
}
</style>

