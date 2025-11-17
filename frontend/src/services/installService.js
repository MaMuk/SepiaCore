import api from './api'
import { getApiBaseUrl } from '../config'

export class InstallService {
  /**
   * Check installation requirements
   */
  async checkRequirements() {
    const apiBaseUrl = getApiBaseUrl()
    if (!apiBaseUrl) {
      return {
        success: false,
        error: 'API Base URL is not configured',
        requirements: {}
      }
    }

    try {
      const response = await api.get('/install/requirements')
      return {
        success: response.data.success || false,
        requirements: response.data.requirements || {}
      }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Failed to check requirements',
        requirements: {}
      }
    }
  }

  /**
   * Setup installation form - returns form structure
   * This is a helper method to structure the form data
   */
  setupInstallForm() {
    return {
      dbType: 'sqlite',
      dbName: '',
      dbHost: '',
      dbPort: '',
      dbUser: '',
      dbPass: '',
      username: '',
      password: '',
      instancename: '',
      environment: 'dev',
      allowedOrigins: []
    }
  }

  /**
   * Execute installation
   */
  async executeInstall(inputs) {
    const apiBaseUrl = getApiBaseUrl()
    if (!apiBaseUrl) {
      return {
        success: false,
        error: 'API Base URL is not configured'
      }
    }

    try {
      // Prepare data based on database type
      const data = {
        dbType: inputs.dbType,
        dbName: inputs.dbName,
        instancename: inputs.instancename,
        username: inputs.username,
        password: inputs.password,
        environment: inputs.environment || 'dev',
        allowedOrigins: inputs.allowedOrigins || []
      }

      // Add database connection fields only if not SQLite
      if (inputs.dbType !== 'sqlite') {
        data.dbHost = inputs.dbHost
        data.dbPort = inputs.dbPort
        data.dbUser = inputs.dbUser
        if (inputs.dbPass) {
          data.dbPass = inputs.dbPass
        }
      }
      
      // Filter out empty allowed origins
      if (Array.isArray(data.allowedOrigins)) {
        data.allowedOrigins = data.allowedOrigins.filter(origin => origin && origin.trim())
      }

      const response = await api.post('/install', data)

      if (response.data.success) {
        return {
          success: true,
          message: response.data.message || 'Installation complete'
        }
      }

      return {
        success: false,
        error: response.data.error || 'Installation failed'
      }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error || error.message || 'Installation failed. Please try again.'
      }
    }
  }
}

export default new InstallService()

