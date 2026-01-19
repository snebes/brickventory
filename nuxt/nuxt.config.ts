// https://nuxt.com/docs/api/configuration/nuxt-config
const apiBase = process.env.NUXT_API_BASE || 'http://localhost:8000'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  
  // Development server configuration
  devServer: {
    port: 3000,
    host: '0.0.0.0'
  },
  
  // Runtime config for API endpoint
  runtimeConfig: {
    // Server-side API base URL (used by SSR and Nitro proxy)
    apiBase,
    public: {
      // Client-side API base URL (empty = use same origin with /api prefix)
      apiBase: ''
    }
  },
  
  // Nitro configuration for API proxy
  nitro: {
    routeRules: {
      '/api/**': {
        proxy: `${apiBase}/api/**`
      }
    }
  },
  
  // App configuration
  app: {
    head: {
      title: 'Brickventory - Order Management',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Brickventory order management system' }
      ]
    }
  }
})
