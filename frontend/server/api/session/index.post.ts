import { inventoryLoginFailure } from '../../../shared/inventoryLoginFailure'

export default defineEventHandler(async (event) => {
    checkInventoryOrigin(event)
    setResponseHeader(event, 'Cache-Control', 'no-store')
    const body = await readBody(event)
    if (typeof body?.token !== 'string' || body.token.length < 1 || body.token.length > 512 || /[\r\n]/.test(body.token)) {
        throw createError({ statusCode: 400, statusMessage: 'Enter a valid access token.' })
    }
    const session = await inventorySession(event)
    const config = useRuntimeConfig(event)
    try {
        await $fetch(`${config.apiBase.replace(/\/$/, '')}/api/v1/products`, {
            query: { per_page: 1 },
            headers: { Authorization: `Bearer ${body.token}` },
            timeout: 10000,
            retry: 0,
        })
    } catch (error: any) {
        throw createError(inventoryLoginFailure(error?.response?.status, error?.cause?.code || error?.cause?.cause?.code))
    }
    await session.update({ token: body.token })
    return { authenticated: true }
})
