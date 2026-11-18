export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path === '/login') return
    try {
        const session = await useRequestFetch()<{ authenticated: boolean }>('/api/session')
        if (!session.authenticated) return navigateTo('/login')
    } catch {
        return navigateTo('/login')
    }
})
