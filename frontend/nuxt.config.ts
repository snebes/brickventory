// https://nuxt.com/docs/api/configuration/nuxt-config
import { defineNuxtConfig } from "nuxt/config";

export default defineNuxtConfig({
    srcDir: '.',
    css: ['~/assets/css/inventory.css'],
    runtimeConfig: { apiBase: 'http://localhost:8000', sessionPassword: '' },
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
            title: 'Brickventory',
            link: [
                { rel: 'icon', type: 'image/png', href: '/branding/brickventory-logo.png' },
                { rel: 'icon', type: 'image/svg+xml', sizes: 'any', href: '/favicon.svg' },
            ],
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
