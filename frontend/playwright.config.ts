import { defineConfig } from '@playwright/test'
export default defineConfig({
    testDir: './test/e2e',
    testMatch: '*.spec.ts',
    fullyParallel: false,
    workers: 1,
    use: { baseURL: 'http://localhost:4317', trace: 'retain-on-failure' },
    webServer: [
        { command: 'node test/e2e/mock-api.mjs', url: 'http://127.0.0.1:4318/__state', reuseExistingServer: false },
        {
            command: 'node .output/server/index.mjs',
            url: 'http://localhost:4317/login',
            env: {
                PORT: '4317',
                HOST: 'localhost',
                NUXT_API_BASE: 'http://127.0.0.1:4318',
                NUXT_SESSION_PASSWORD: 'test-only-session-secret-with-at-least-32-characters',
            },
            reuseExistingServer: false,
        },
    ],
})
