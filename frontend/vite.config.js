import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import fs from 'fs'
import path from 'path'

// Check if HTTPS certificates exist
const certDir = path.resolve(__dirname, '../certs')
const certFile = path.join(certDir, 'frontend-cert.pem')
const keyFile = path.join(certDir, 'frontend-key.pem')
const useHttps = fs.existsSync(certFile) && fs.existsSync(keyFile)

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    port: 3001,
    host: '0.0.0.0', // listen on all addresses
    https: useHttps ? {
      cert: fs.readFileSync(certFile),
      key: fs.readFileSync(keyFile)
    } : false,

    proxy: {
      '/api': {
        target: process.env.VITE_API_BASE_URL || 'http://localhost:8001',
        changeOrigin: true,
        secure: false, // Allow self-signed certificates
        rewrite: (path) => path.replace(/^\/api/, '')
      }
    }
  }
})

