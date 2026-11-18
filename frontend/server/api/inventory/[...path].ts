import { inventoryProxyPath } from '../../../shared/inventoryProxy'

export default defineEventHandler(async (event) => {
    checkInventoryOrigin(event)
    setResponseHeader(event, 'Cache-Control', 'no-store')
    const session = await inventorySession(event)
    if (!session.data.token) throw createError({ statusCode: 401, statusMessage: 'Sign in to continue.' })
    const path = inventoryProxyPath(getRouterParam(event, 'path') || '')
    if (!path) throw createError({ statusCode: 400, statusMessage: 'Invalid inventory path.' })
    const config = useRuntimeConfig(event)
    const query = getRequestURL(event).search
    return proxyRequest(event, `${config.apiBase.replace(/\/$/, '')}/api/v1/${path}${query}`, {
        streamRequest: true,
        headers: { Authorization: `Bearer ${session.data.token}`, cookie: '' },
        fetchOptions: { redirect: 'manual' },
        onResponse: (_event, response) => {
            if (response.status >= 300 && response.status < 400)
                throw createError({ statusCode: 502, statusMessage: 'Unexpected inventory service redirect.' })
        },
    })
})
