export default defineEventHandler(async (event) => {
    checkInventoryOrigin(event)
    await (await inventorySession(event)).clear()
    return { authenticated: false }
})
