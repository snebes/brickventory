import { createError, getHeader, getMethod, getRequestURL, useSession, type H3Event } from 'h3'

export function checkInventoryOrigin(event: H3Event) {
    if (['GET', 'HEAD', 'OPTIONS'].includes(getMethod(event))) return
    const origin = getHeader(event, 'origin')
    if ((origin && origin !== getRequestURL(event).origin) || getHeader(event, 'sec-fetch-site') === 'cross-site') {
        throw createError({ statusCode: 403, statusMessage: 'Cross-site requests are not allowed.' })
    }
}

export function inventorySession(event: H3Event) {
    const config = useRuntimeConfig(event)
    if (config.sessionPassword.length < 32)
        throw createError({ statusCode: 503, statusMessage: 'Sign-in is not configured. Set NUXT_SESSION_PASSWORD.' })
    return useSession<{ token: string }>(event, {
        name: 'brickventory-session',
        password: config.sessionPassword,
        maxAge: 60 * 60 * 8,
        sessionHeader: false,
        cookie: { httpOnly: true, sameSite: 'strict', secure: !import.meta.dev, path: '/' },
    })
}
