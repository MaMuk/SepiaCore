<template>
  <div
    class="modal fade"
    :class="{ show: isVisible, 'd-block': isVisible }"
    tabindex="-1"
    :aria-hidden="!isVisible"
    @click.self="handleBackdropClick"
  >
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Profile</h5>
          <button
            type="button"
            class="btn-close"
            @click="close"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSubmit">
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input
                type="text"
                class="form-control"
                id="username"
                v-model="form.name"
                disabled
              />
              <div class="form-text">Username cannot be changed</div>
            </div>

            <div class="mb-3">
              <label for="currentPassword" class="form-label">Current Password</label>
              <input
                type="password"
                class="form-control"
                id="currentPassword"
                v-model="form.currentPassword"
                :class="{ 'is-invalid': errors.currentPassword }"
              />
              <div v-if="errors.currentPassword" class="invalid-feedback">
                {{ errors.currentPassword }}
              </div>
              <div class="form-text" v-if="authStore.isAdmin">
                As an admin, you can skip this field when editing other users
              </div>
            </div>

            <div class="mb-3">
              <label for="newPassword" class="form-label">New Password</label>
              <input
                type="password"
                class="form-control"
                id="newPassword"
                v-model="form.newPassword"
                :class="{ 'is-invalid': errors.newPassword }"
              />
              <div v-if="errors.newPassword" class="invalid-feedback">
                {{ errors.newPassword }}
              </div>
            </div>

            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Confirm New Password</label>
              <input
                type="password"
                class="form-control"
                id="confirmPassword"
                v-model="form.confirmPassword"
                :class="{ 'is-invalid': errors.confirmPassword }"
              />
              <div v-if="errors.confirmPassword" class="invalid-feedback">
                {{ errors.confirmPassword }}
              </div>
            </div>

            <div v-if="error" class="alert alert-danger" role="alert">
              {{ error }}
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="close">
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary"
            @click="handleSubmit"
            :disabled="saving"
          >
            <span
              v-if="saving"
              class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"
            ></span>
            Update Password
          </button>
        </div>
      </div>
    </div>
  </div>
  <div v-if="isVisible" class="modal-backdrop fade show" @click="handleBackdropClick"></div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  userId: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toastStore = useToastStore()
const authStore = useAuthStore()

const form = ref({
  name: '',
  currentPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const errors = ref({})
const error = ref('')
const saving = ref(false)
const userProfile = ref(null)

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

watch(isVisible, async (newVal) => {
  if (newVal) {
    await loadProfile()
    resetForm()
  }
})

async function loadProfile() {
  try {
    const userId = props.userId || 'me'
    const response = await api.get(`/users/${userId === 'me' ? 'profile/me' : userId}`)
    userProfile.value = response.data
    form.value.name = response.data.name || ''
  } catch (err) {
    error.value = 'Failed to load profile'
    toastStore.error('Failed to load profile')
  }
}

function resetForm() {
  form.value = {
    name: userProfile.value?.name || '',
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  }
  errors.value = {}
  error.value = ''
}

function close() {
  isVisible.value = false
  resetForm()
}

function handleBackdropClick() {
  close()
}

function validateForm() {
  errors.value = {}
  let isValid = true

  // If admin editing another user, skip current password check
  const isEditingOtherUser = props.userId && props.userId !== 'me' && authStore.isAdmin

  if (!isEditingOtherUser && !form.value.currentPassword) {
    errors.value.currentPassword = 'Current password is required'
    isValid = false
  }

  if (!form.value.newPassword) {
    errors.value.newPassword = 'New password is required'
    isValid = false
  } else if (form.value.newPassword.length < 6) {
    errors.value.newPassword = 'Password must be at least 6 characters'
    isValid = false
  }

  if (form.value.newPassword !== form.value.confirmPassword) {
    errors.value.confirmPassword = 'Passwords do not match'
    isValid = false
  }

  return isValid
}

async function handleSubmit() {
  if (!validateForm()) {
    return
  }

  saving.value = true
  error.value = ''

  try {
    const userId = props.userId || userProfile.value?.id
    const payload = {
      password: form.value.newPassword
    }

    await api.put(`/users/${userId}`, payload)

    toastStore.success('Password updated successfully')
    emit('saved')
    close()
  } catch (err) {
    const errorMessage = err.response?.data?.error || 'Failed to update password'
    error.value = errorMessage
    toastStore.error(errorMessage)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal {
  background-color: rgba(0, 0, 0, 0.5);
}
</style>

