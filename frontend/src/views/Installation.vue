<template>
  <div class="installation-container d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="container" style="max-width: 800px;">
      <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
          <h3 class="mb-0">
            <i class="bi bi-gear-fill me-2"></i>
            Installation
          </h3>
        </div>
        <div class="card-body p-4">
          <!-- Step 1: Requirement Check -->
          <div v-if="currentStep === 'requirements'" class="requirements-step">
            <h4 class="mb-4">
              <i class="bi bi-check-circle me-2"></i>
              System Requirements Check
            </h4>
            
            <div v-if="checkingRequirements" class="text-center py-4">
              <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Checking requirements...</span>
              </div>
              <p class="text-muted">Checking system requirements...</p>
            </div>

            <div v-else-if="requirementsChecked">
              <div v-if="requirements.success" class="alert alert-success" role="alert">
                <h5 class="alert-heading">
                  <i class="bi bi-check-circle-fill me-2"></i>
                  All Requirements Met
                </h5>
                <p class="mb-0">Your system meets all the requirements for installation.</p>
              </div>
              <div v-else class="alert alert-warning" role="alert">
                <h5 class="alert-heading">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  Some Requirements Not Met
                </h5>
                <p class="mb-0">Please fix the issues below before continuing with installation.</p>
              </div>

              <div class="mt-4">
                <h5 class="mb-3">Requirement Details</h5>
                <div class="list-group">
                  <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <strong>PHP Version</strong>
                        <div class="small text-muted">
                          {{ requirements.requirements?.php?.version || 'N/A' }} 
                          (Required: {{ requirements.requirements?.php?.required || '8.3+' }})
                        </div>
                      </div>
                      <i 
                        class="bi bi-check-circle-fill text-success" 
                        v-if="requirements.requirements?.php?.meetsRequirement"
                      ></i>
                      <i 
                        class="bi bi-x-circle-fill text-danger" 
                        v-else
                      ></i>
                    </div>
                  </div>
                  <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <strong>PHP Extensions</strong>
                        <div class="small text-muted">
                          <span v-if="requirements.requirements?.extensions?.pdo?.installed">PDO ✓</span>
                          <span v-else class="text-danger">PDO ✗</span>
                          <span v-if="requirements.requirements?.extensions?.json?.installed">, JSON ✓</span>
                          <span v-else class="text-danger">, JSON ✗</span>
                          <span v-if="requirements.requirements?.extensions?.mbstring?.installed">, MBString ✓</span>
                          <span v-else class="text-danger">, MBString ✗</span>
                          <span v-if="requirements.requirements?.extensions?.curl?.installed">, cURL ✓</span>
                          <span v-else class="text-danger">, cURL ✗</span>
                        </div>
                      </div>
                      <i 
                        class="bi bi-check-circle-fill text-success" 
                        v-if="requirements.requirements?.extensions?.pdo?.installed && 
                              requirements.requirements?.extensions?.json?.installed && 
                              requirements.requirements?.extensions?.mbstring?.installed && 
                              requirements.requirements?.extensions?.curl?.installed"
                      ></i>
                      <i 
                        class="bi bi-x-circle-fill text-danger" 
                        v-else
                      ></i>
                    </div>
                  </div>
                  <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <strong>File Permissions - Config Directory</strong>
                        <div class="small text-muted">Is Config directory writable?</div>
                      </div>
                      <i 
                        class="bi bi-check-circle-fill text-success" 
                        v-if="requirements.requirements?.permissions?.configDir?.writable"
                      ></i>
                      <i 
                        class="bi bi-x-circle-fill text-danger" 
                        v-else
                      ></i>
                    </div>
                  </div>
                  <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <strong>File Permissions - Entity Directory</strong>
                        <div class="small text-muted">
                          Is Entity directory writable? 
                          <span class="text-muted">(optional)</span>
                        </div>
                      </div>
                      <i 
                        class="bi bi-check-circle-fill text-success" 
                        v-if="requirements.requirements?.permissions?.entityDir?.writable"
                      ></i>
                      <i 
                        class="bi bi-exclamation-triangle-fill text-warning" 
                        v-else-if="requirements.requirements?.permissions?.entityDir !== undefined"
                      ></i>
                      <i 
                        class="bi bi-question-circle-fill text-muted" 
                        v-else
                      ></i>
                    </div>
                    <div 
                      v-if="requirements.requirements?.permissions?.entityDir?.writable === false" 
                      class="small text-warning mt-1"
                    >
                      <i class="bi bi-exclamation-triangle me-1"></i>
                      Warning: Entity directory is not writable. You may not be able to create custom entities.
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-4 d-flex justify-content-end">
                <button 
                  class="btn btn-primary" 
                  @click="currentStep = 'form'"
                  :disabled="!requirements.success"
                >
                  Continue to Installation
                  <i class="bi bi-arrow-right ms-2"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Step 2: Installation Form -->
          <div v-if="currentStep === 'form'" class="form-step">
            <h4 class="mb-4">
              <i class="bi bi-database me-2"></i>
              Installation Configuration
            </h4>

            <form @submit.prevent="handleInstall">
              <!-- Database Type -->
              <div class="mb-3">
                <label for="dbType" class="form-label">Database Type <span class="text-danger">*</span></label>
                <select
                  class="form-select"
                  id="dbType"
                  v-model="formData.dbType"
                  required
                  :disabled="installing"
                >
                  <option value="sqlite">SQLite</option>
                  <option value="mysql">MySQL</option>
                  <option value="pgsql">PostgreSQL</option>
                  <option value="mssql">SQL Server</option>
                </select>
                <div class="form-text">Select the database type you want to use</div>
              </div>

              <!-- Database Name -->
              <div class="mb-3">
                <label for="dbName" class="form-label">Database Name <span class="text-danger">*</span></label>
                <input
                  type="text"
                  :class="['form-control', getFieldClass('dbName')]"
                  id="dbName"
                  v-model="formData.dbName"
                  @blur="handleFieldBlur('dbName')"
                  :disabled="installing"
                  placeholder="Enter database name"
                />
                <div class="invalid-feedback" v-if="touchedFields.dbName && validationErrors.dbName">
                  {{ validationErrors.dbName }}
                </div>
                <div class="form-text" v-if="formData.dbType === 'sqlite'">
                  SQLite database file name (without .db extension)
                </div>
                <div class="form-text" v-else>
                  Name of the database to create
                </div>
              </div>

              <!-- Database Connection Fields (only for non-SQLite) -->
              <template v-if="formData.dbType !== 'sqlite'">
                <div class="mb-3">
                  <label for="dbHost" class="form-label">Database Host <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    :class="['form-control', getFieldClass('dbHost')]"
                    id="dbHost"
                    v-model="formData.dbHost"
                    @blur="handleFieldBlur('dbHost')"
                    :disabled="installing"
                    placeholder="127.0.0.1"
                  />
                  <div class="invalid-feedback" v-if="touchedFields.dbHost && validationErrors.dbHost">
                    {{ validationErrors.dbHost }}
                  </div>
                </div>

                <div class="mb-3">
                  <label for="dbPort" class="form-label">Database Port <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    :class="['form-control', getFieldClass('dbPort')]"
                    id="dbPort"
                    v-model="formData.dbPort"
                    @blur="handleFieldBlur('dbPort')"
                    :disabled="installing"
                    :placeholder="getDefaultPort()"
                  />
                  <div class="invalid-feedback" v-if="touchedFields.dbPort && validationErrors.dbPort">
                    {{ validationErrors.dbPort }}
                  </div>
                </div>

                <div class="mb-3">
                  <label for="dbUser" class="form-label">Database User <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    :class="['form-control', getFieldClass('dbUser')]"
                    id="dbUser"
                    v-model="formData.dbUser"
                    @blur="handleFieldBlur('dbUser')"
                    :disabled="installing"
                    placeholder="Enter database user"
                  />
                  <div class="invalid-feedback" v-if="touchedFields.dbUser && validationErrors.dbUser">
                    {{ validationErrors.dbUser }}
                  </div>
                </div>

                <div class="mb-3">
                  <label for="dbPass" class="form-label">Database Password</label>
                  <input
                    type="password"
                    class="form-control"
                    id="dbPass"
                    v-model="formData.dbPass"
                    :disabled="installing"
                    placeholder="Enter database password (optional)"
                  />
                </div>
              </template>

              <hr class="my-4">

              <!-- Admin User -->
              <h5 class="mb-3">Administrator Account</h5>
              
              <div class="mb-3">
                <label for="username" class="form-label">Admin Username <span class="text-danger">*</span></label>
                <input
                  type="text"
                  :class="['form-control', getFieldClass('username')]"
                  id="username"
                  v-model="formData.username"
                  @blur="handleFieldBlur('username')"
                  :disabled="installing"
                  placeholder="Enter admin username"
                />
                <div class="invalid-feedback" v-if="touchedFields.username && validationErrors.username">
                  {{ validationErrors.username }}
                </div>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">Admin Password <span class="text-danger">*</span></label>
                <input
                  type="password"
                  :class="['form-control', getFieldClass('password')]"
                  id="password"
                  v-model="formData.password"
                  @blur="handleFieldBlur('password')"
                  :disabled="installing"
                  placeholder="Enter admin password"
                />
                <div class="invalid-feedback" v-if="touchedFields.password && validationErrors.password">
                  {{ validationErrors.password }}
                </div>
              </div>

              <div class="mb-4">
                <label for="instancename" class="form-label">Instance Name <span class="text-danger">*</span></label>
                <input
                  type="text"
                  :class="['form-control', getFieldClass('instancename')]"
                  id="instancename"
                  v-model="formData.instancename"
                  @blur="handleInstanceNameBlur"
                  @input="sanitizeInstanceNameInput"
                  :disabled="installing"
                  placeholder="Enter instance name"
                  maxlength="100"
                />
                <div class="invalid-feedback" v-if="touchedFields.instancename && validationErrors.instancename">
                  {{ validationErrors.instancename }}
                </div>
                <div class="form-text">A name to identify this installation (max 100 characters, alphanumeric and basic punctuation only)</div>
              </div>

              <hr class="my-4">

              <!-- Environment Configuration -->
              <h5 class="mb-3">Environment Configuration</h5>
              
              <div class="mb-3">
                <label for="environment" class="form-label">Environment <span class="text-danger">*</span></label>
                <select
                  class="form-select"
                  id="environment"
                  v-model="formData.environment"
                  required
                  :disabled="installing"
                >
                  <option value="dev">Development</option>
                  <option value="prod">Production</option>
                </select>
                <div class="form-text">
                  <strong>Development:</strong> Allows all origins for CORS (flexible for testing)<br>
                  <strong>Production:</strong> Uses whitelisted origins only (more secure)
                </div>
              </div>

              <!-- Allowed Origins (only shown in production) -->
              <div v-if="formData.environment === 'prod'" class="mb-4">
                <label for="allowedOrigins" class="form-label">
                  Allowed Origins
                  <span class="text-muted">(Production only)</span>
                </label>
                <textarea
                  class="form-control"
                  id="allowedOrigins"
                  v-model="allowedOriginsText"
                  @input="updateAllowedOrigins"
                  :disabled="installing"
                  rows="3"
                  placeholder="https://example.com&#10;https://app.example.com&#10;https://localhost:3001"
                ></textarea>
                <div class="form-text">
                  Enter one origin per line (e.g., <code>https://example.com</code>). 
                  These are the allowed frontend origins for CORS when using httpOnly cookies in production.
                  Leave empty to allow all origins (not recommended for production).
                </div>
                <div v-if="allowedOriginsList.length > 0" class="mt-2">
                  <small class="text-muted">Allowed origins:</small>
                  <ul class="list-unstyled ms-3 mb-0">
                    <li v-for="(origin, index) in allowedOriginsList" :key="index" class="small">
                      <code>{{ origin }}</code>
                    </li>
                  </ul>
                </div>
              </div>

              <!-- Error Message -->
              <div v-if="errorMessage" class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ errorMessage }}
              </div>

              <!-- Form Actions -->
              <div class="d-flex justify-content-between">
                <button
                  type="button"
                  class="btn btn-secondary"
                  @click="currentStep = 'requirements'"
                  :disabled="installing"
                >
                  <i class="bi bi-arrow-left me-2"></i>
                  Back
                </button>
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="installing"
                  @click="handleInstall"
                >
                  <span v-if="installing" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                  <span v-else>
                    <i class="bi bi-play-fill me-2"></i>
                    Install
                  </span>
                </button>
              </div>
            </form>
          </div>

          <!-- Step 3: Installation Progress -->
          <div v-if="currentStep === 'installing'" class="installing-step text-center py-5">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
              <span class="visually-hidden">Installing...</span>
            </div>
            <h4>Installing...</h4>
            <p class="text-muted">Please wait while we set up your system.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import installService from '../services/installService'
import authService from '../services/authService'

const router = useRouter()
const toastStore = useToastStore()

const currentStep = ref('requirements')
const checkingRequirements = ref(false)
const requirementsChecked = ref(false)
const requirements = ref({})
const installing = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const touchedFields = ref({})

// Initialize form data using setupInstallForm
const formData = ref(installService.setupInstallForm())

// Allowed origins handling (convert between array and textarea)
const allowedOriginsText = ref('')
const allowedOriginsList = ref([])

function updateAllowedOrigins() {
  // Split by newlines and filter empty lines
  const origins = allowedOriginsText.value
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)
  
  formData.value.allowedOrigins = origins
  allowedOriginsList.value = origins
}

// Watch formData.allowedOrigins to sync with textarea
watch(() => formData.value.allowedOrigins, (newOrigins) => {
  if (Array.isArray(newOrigins)) {
    allowedOriginsText.value = newOrigins.join('\n')
    allowedOriginsList.value = newOrigins
  }
}, { immediate: true })

// Sanitize instance name to prevent XSS
function sanitizeInstanceName(value) {
  if (typeof value !== 'string') return ''
  
  // Remove HTML tags
  const div = document.createElement('div')
  div.textContent = value
  let sanitized = div.textContent || div.innerText || ''
  
  // Remove control characters and null bytes
  sanitized = sanitized.replace(/[\x00-\x1F\x7F]/g, '')
  
  // Limit length
  sanitized = sanitized.substring(0, 100)
  
  // Remove potentially dangerous characters, allow alphanumeric, spaces, hyphens, underscores, and common punctuation
  sanitized = sanitized.replace(/[^a-zA-Z0-9\s\-_.,!?()]/g, '')
  
  return sanitized.trim()
}

// Validation functions
function validateField(fieldName, value) {
  const trimmed = typeof value === 'string' ? value.trim() : value
  
  switch (fieldName) {
    case 'dbName':
      return trimmed ? null : 'Database name is required'
    case 'username':
      return trimmed ? null : 'Admin username is required'
    case 'password':
      return trimmed ? null : 'Admin password is required'
    case 'instancename':
      if (!trimmed) {
        return 'Instance name is required'
      }
      // Check for potentially dangerous content
      const sanitized = sanitizeInstanceName(trimmed)
      if (sanitized !== trimmed) {
        return 'Instance name contains invalid characters. Only letters, numbers, spaces, hyphens, underscores, and basic punctuation are allowed.'
      }
      if (trimmed.length > 100) {
        return 'Instance name must be 100 characters or less'
      }
      return null
    case 'environment':
      const envValue = trimmed ? trimmed.toLowerCase() : 'dev'
      if (!['dev', 'prod'].includes(envValue)) {
        return 'Environment must be either "dev" or "prod"'
      }
      // Ensure formData has the correct value
      if (formData.value.environment !== envValue) {
        formData.value.environment = envValue
      }
      return null
    case 'dbHost':
      return formData.value.dbType !== 'sqlite' && !trimmed ? 'Database host is required' : null
    case 'dbPort':
      return formData.value.dbType !== 'sqlite' && !trimmed ? 'Database port is required' : null
    case 'dbUser':
      return formData.value.dbType !== 'sqlite' && !trimmed ? 'Database user is required' : null
    default:
      return null
  }
}

function validateAllFields() {
  const errors = {}
  
  // Always required fields
  errors.dbName = validateField('dbName', formData.value.dbName)
  errors.username = validateField('username', formData.value.username)
  errors.password = validateField('password', formData.value.password)
  errors.instancename = validateField('instancename', formData.value.instancename)
  errors.environment = validateField('environment', formData.value.environment)
  
  // Non-SQLite specific fields
  if (formData.value.dbType !== 'sqlite') {
    errors.dbHost = validateField('dbHost', formData.value.dbHost)
    errors.dbPort = validateField('dbPort', formData.value.dbPort)
    errors.dbUser = validateField('dbUser', formData.value.dbUser)
  }
  
  validationErrors.value = errors
  
  // Mark all fields as touched
  Object.keys(errors).forEach(key => {
    if (errors[key] !== null) {
      touchedFields.value[key] = true
    }
  })
  
  return Object.values(errors).every(error => error === null)
}

function handleFieldBlur(fieldName) {
  touchedFields.value[fieldName] = true
  validationErrors.value[fieldName] = validateField(fieldName, formData.value[fieldName])
}

function handleInstanceNameBlur() {
  // Sanitize on blur
  formData.value.instancename = sanitizeInstanceName(formData.value.instancename)
  handleFieldBlur('instancename')
}

function sanitizeInstanceNameInput(event) {
  // Sanitize as user types
  const sanitized = sanitizeInstanceName(event.target.value)
  if (sanitized !== event.target.value) {
    formData.value.instancename = sanitized
  }
}

function getFieldClass(fieldName) {
  if (touchedFields.value[fieldName] && validationErrors.value[fieldName]) {
    return 'is-invalid'
  }
  return ''
}

// Get default port based on database type
function getDefaultPort() {
  switch (formData.value.dbType) {
    case 'mysql':
      return '3306'
    case 'pgsql':
      return '5432'
    case 'mssql':
      return '1433'
    default:
      return ''
  }
}

// Watch database type to set default port and clear SQLite-specific fields
watch(() => formData.value.dbType, (newType, oldType) => {
  // Clear validation errors when switching database types
  if (newType !== oldType) {
    validationErrors.value = {}
    touchedFields.value = {}
  }
  
  if (newType !== 'sqlite') {
    // Set default port if empty or undefined
    if (!formData.value.dbPort || (typeof formData.value.dbPort === 'string' && formData.value.dbPort.trim() === '')) {
      formData.value.dbPort = getDefaultPort()
    }
    // Ensure other fields are initialized (but don't overwrite if they exist)
    if (formData.value.dbHost === undefined || formData.value.dbHost === null) {
      formData.value.dbHost = ''
    }
    if (formData.value.dbUser === undefined || formData.value.dbUser === null) {
      formData.value.dbUser = ''
    }
  } else {
    // When switching to SQLite, clear non-SQLite fields
    formData.value.dbHost = ''
    formData.value.dbPort = ''
    formData.value.dbUser = ''
    formData.value.dbPass = ''
    // Clear validation errors for non-SQLite fields
    delete validationErrors.value.dbHost
    delete validationErrors.value.dbPort
    delete validationErrors.value.dbUser
  }
})

// Check if already installed on mount
onMounted(async () => {
  // Check if backend is already installed
  try {
    const status = await authService.checkBackendStatus()
    if (status.reachable && status.installed) {
      // Backend is already installed, redirect to home
      router.push('/')
      return
    }
  } catch (error) {
    // If check fails, continue with installation process
    console.error('Failed to check installation status:', error)
  }
  
  // If not installed, proceed with requirements check
  await checkRequirements()
})

async function checkRequirements() {
  checkingRequirements.value = true
  errorMessage.value = ''
  
  try {
    const result = await installService.checkRequirements()
    requirements.value = result
    requirementsChecked.value = true
    
    if (!result.success) {
      errorMessage.value = 'Some requirements are not met. Please check your system configuration.'
      toastStore.warning('Some requirements are not met')
    }
  } catch (error) {
    errorMessage.value = `Failed to check requirements: ${error.message}`
    toastStore.error('Failed to check requirements')
    requirementsChecked.value = true
    requirements.value = { success: false, requirements: {} }
  } finally {
    checkingRequirements.value = false
  }
}

async function handleInstall() {
  // Validate all fields when install button is clicked
  if (!validateAllFields()) {
    toastStore.warning('Please fill in all required fields')
    // Scroll to first error
    const firstErrorField = Object.keys(validationErrors.value).find(key => validationErrors.value[key])
    if (firstErrorField) {
      const element = document.getElementById(firstErrorField)
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' })
        element.focus()
      }
    }
    return
  }

  installing.value = true
  errorMessage.value = ''
  currentStep.value = 'installing'

  try {
    const result = await installService.executeInstall(formData.value)

    if (result.success) {
      toastStore.success(result.message || 'Installation completed successfully!')
      // Redirect to login after successful installation
      setTimeout(() => {
        router.push('/login')
      }, 2000)
    } else {
      errorMessage.value = result.error || 'Installation failed'
      currentStep.value = 'form'
      toastStore.error(result.error || 'Installation failed')
    }
  } catch (error) {
    errorMessage.value = error.message || 'An unexpected error occurred during installation'
    currentStep.value = 'form'
    toastStore.error('Installation failed')
  } finally {
    installing.value = false
  }
}
</script>

<style scoped>
.installation-container {
  background-color: #f8f9fa;
}

.card {
  border: none;
  border-radius: 1rem;
}

.card-header {
  border-radius: 1rem 1rem 0 0 !important;
}

.form-control:focus,
.form-select:focus {
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

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}
</style>

