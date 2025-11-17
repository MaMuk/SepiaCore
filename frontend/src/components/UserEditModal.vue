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
          <h5 class="modal-title">{{ isEdit ? 'Edit User' : 'Create User' }}</h5>
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
                :disabled="isEdit"
                required
                :class="{ 'is-invalid': errors.name }"
              />
              <div v-if="errors.name" class="invalid-feedback">
                {{ errors.name }}
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">
                {{ isEdit ? 'New Password' : 'Password' }}
                <span v-if="isEdit" class="text-muted">(leave blank to keep current)</span>
              </label>
              <input
                type="password"
                class="form-control"
                id="password"
                v-model="form.password"
                :required="!isEdit"
                :class="{ 'is-invalid': errors.password }"
              />
              <div v-if="errors.password" class="invalid-feedback">
                {{ errors.password }}
              </div>
            </div>

            <div v-if="authStore.isAdmin" class="mb-3">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="isAdmin"
                  v-model="form.isadmin"
                  :disabled="isEditingSelf && form.isadmin"
                />
                <label class="form-check-label" for="isAdmin">
                  Administrator
                </label>
              </div>
              <div v-if="isEditingSelf && form.isadmin" class="form-text text-warning">
                You cannot remove your own administrator privileges.
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
            {{ isEdit ? 'Update' : 'Create' }}
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
  user: {
    type: Object,
    default: null
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toastStore = useToastStore()
const authStore = useAuthStore()

const form = ref({
  name: '',
  password: '',
  isadmin: false
})

const errors = ref({})
const error = ref('')
const saving = ref(false)
const currentUserId = ref(null)

// Check if editing own account
const isEditingSelf = computed(() => {
  if (!props.isEdit || !props.user || !currentUserId.value) return false
  return props.user.id === currentUserId.value
})

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

watch(isVisible, async (newVal) => {
  if (newVal) {
    resetForm()
    // Fetch current user profile to get ID for comparison
    if (props.isEdit) {
      try {
        const profileResponse = await api.get('/users/profile/me')
        currentUserId.value = profileResponse.data.id
      } catch (err) {
        console.error('Failed to fetch current user profile:', err)
      }
    }
    
    if (props.isEdit && props.user) {
      // Convert isadmin to boolean (handles string "1"/"0", integer 1/0, or boolean)
      const isAdmin = props.user.isadmin === true || 
                     props.user.isadmin === 1 || 
                     props.user.isadmin === '1' || 
                     props.user.isadmin === 'true'
      
      form.value = {
        name: props.user.name || '',
        password: '',
        isadmin: Boolean(isAdmin)
      }
    }
  } else {
    currentUserId.value = null
  }
})

function resetForm() {
  form.value = {
    name: '',
    password: '',
    isadmin: false
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

async function handleSubmit() {
  errors.value = {}
  error.value = ''

  if (!props.isEdit && !form.value.password) {
    errors.value.password = 'Password is required'
    return
  }

  saving.value = true

  try {
    const payload = {
      name: form.value.name,
      isadmin: form.value.isadmin
    }

    if (form.value.password) {
      payload.password = form.value.password
    }

    let response
    if (props.isEdit) {
      response = await api.put(`/users/${props.user.id}`, payload)
    } else {
      response = await api.post('/users', payload)
    }

    toastStore.success(props.isEdit ? 'User updated successfully' : 'User created successfully')
    emit('saved', response.data)
    close()
  } catch (err) {
    const errorMessage = err.response?.data?.error || 'Failed to save user'
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

