<template>
  <div class="settings-container p-4">
    <div class="container-fluid">
      <h2 class="mb-4">Settings</h2>
      
      <div class="row g-4">
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">
                <i class="bi bi-link-45deg me-2"></i>
                API Base URL
              </h5>
              <p class="card-text text-muted">
                Configure the base URL for the API endpoint.
              </p>
              <div class="mb-3">
                <small class="text-muted">
                  Current: <code>{{ currentApiUrl || 'Not set' }}</code>
                </small>
              </div>
              <button
                class="btn btn-primary"
                @click="openApiUrlModal"
              >
                Configure
              </button>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">
                <i class="bi bi-list-ul me-2"></i>
                List Limit
              </h5>
              <p class="card-text text-muted">
                Set the number of records displayed per page in list views.
              </p>
              <div class="mb-3">
                <small class="text-muted">
                  Current: <code>{{ currentListLimit }}</code>
                </small>
              </div>
              <button
                class="btn btn-primary"
                @click="openListLimitModal"
              >
                Configure
              </button>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">
                <i class="bi bi-person-gear me-2"></i>
                Edit Profile
              </h5>
              <p class="card-text text-muted">
                Update your password and profile settings.
              </p>
              <button
                class="btn btn-primary"
                @click="openEditProfileModal"
              >
                Edit Profile
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Entity Studio Section (Admin Only) -->
      <div v-if="authStore.isAdmin" class="mt-5">
        <hr class="my-4" />
        <div class="row g-4">
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">
                  <i class="bi bi-puzzle me-2"></i>
                  Entity Studio
                </h5>
                <p class="card-text text-muted">
                  Manage entities, fields, relationships, and layouts.
                </p>
                <button
                  class="btn btn-primary"
                  @click="navigateToEntityStudio"
                >
                  Open Entity Studio
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">
                  <i class="bi bi-plug me-2"></i>
                  Endpoints
                </h5>
                <p class="card-text text-muted">
                  Manage API endpoints configuration.
                </p>
                <button
                  class="btn btn-primary"
                  @click="navigateToEndpoints"
                >
                  Open Endpoints
                </button>
              </div>
            </div>
          </div>

          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <h5 class="card-title">
                  <i class="bi bi-people me-2"></i>
                  User Management
                </h5>
                <p class="card-text text-muted">
                  Create, modify, and delete user accounts.
                </p>
                <button
                  class="btn btn-primary"
                  @click="navigateToUserManagement"
                >
                  Open User Management
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <ApiBaseUrlModal
      v-model="showApiUrlModal"
      @saved="handleApiUrlSaved"
    />
    <ListLimitModal
      v-model="showListLimitModal"
      @saved="handleListLimitSaved"
    />
    <EditProfileModal
      v-model="showEditProfileModal"
      @saved="handleProfileSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { getApiBaseUrl, getListLimit } from '../config'
import ApiBaseUrlModal from '../components/ApiBaseUrlModal.vue'
import ListLimitModal from '../components/ListLimitModal.vue'
import EditProfileModal from '../components/EditProfileModal.vue'

const router = useRouter()
const authStore = useAuthStore()

const showApiUrlModal = ref(false)
const showListLimitModal = ref(false)
const showEditProfileModal = ref(false)

const currentApiUrl = computed(() => getApiBaseUrl())
const currentListLimit = computed(() => getListLimit())

function openApiUrlModal() {
  showApiUrlModal.value = true
}

function openListLimitModal() {
  showListLimitModal.value = true
}

function navigateToEntityStudio() {
  router.push('/app/entity-studio')
}

function navigateToEndpoints() {
  router.push('/app/entity/endpoints')
}

function navigateToUserManagement() {
  router.push('/app/user-management')
}

function openEditProfileModal() {
  showEditProfileModal.value = true
}

function handleApiUrlSaved() {
  // Modal will reload the page, so no need to update here
}

function handleListLimitSaved() {
  // Update is handled by the modal
}

function handleProfileSaved() {
  // Profile update handled by modal
}
</script>

<style scoped>
.settings-container {
  max-width: 1200px;
  margin: 0 auto;
}

.card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
}

.card-title {
  color: #333;
  font-weight: 600;
}

code {
  background-color: #f8f9fa;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}
</style>

