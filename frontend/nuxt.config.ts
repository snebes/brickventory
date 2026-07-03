// https://nuxt.com/docs/api/configuration/nuxt-config
import { defineNuxtConfig } from "nuxt/config";

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: { enabled: true },

    modules: [
        '@nuxt/test-utils/module',
    ],

    // Development server configuration
    devServer: {
        port: 3000,
        host: '0.0.0.0'
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
    },

    vite: {
        optimizeDeps: {
            include: [
                '@vue/devtools-core',
                '@vue/devtools-kit',
            ]
        }
    }
})
