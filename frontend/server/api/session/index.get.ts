export default defineEventHandler(async (event) => {
    setResponseHeader(event, 'Cache-Control', 'no-store')
    const session = await inventorySession(event)
    return { authenticated: Boolean(session.data.token) }
})
