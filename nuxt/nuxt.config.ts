// https://nuxt.com/docs/api/configuration/nuxt-config
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
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000'
    }
  },
  
  // Nitro configuration for API proxy
  nitro: {
    devProxy: {
      '/api': {
        target: 'http://localhost:8000/api',
        changeOrigin: true,
        prependPath: false
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
