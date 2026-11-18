import { test, expect, type Page } from '@playwright/test'
const backend = 'http://127.0.0.1:4318'
async function login(page: Page) {
    await page.goto('/login')
    await page.getByLabel('Workspace access token').fill('frontend-test-token')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
}
async function choose(page: Page, id: string, value = '1') {
    await expect(page.locator(`#${id} option[value="${value}"]`)).toBeAttached()
    await page.locator(`#${id}`).selectOption(value)
}
test.beforeEach(async ({ request }) => {
    await request.get(`${backend}/__reset`)
})

test('requires sign-in, protects the proxy, and never exposes the shared token', async ({ page, request }) => {
    expect((await request.get('/api/inventory/products')).status()).toBe(401)
    await page.goto('/products')
    await expect(page).toHaveURL(/\/login$/)
    await page.getByLabel('Workspace access token').fill('wrong')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText('not accepted')
    await page.getByLabel('Workspace access token').fill('backend-error')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText('server error')
    await expect(page.getByRole('alert')).not.toContainText('Private database diagnostic')
    await login(page)
    const cookies = await page.context().cookies()
    const session = cookies.find((cookie) => cookie.name === 'brickventory-session')!
    expect(session.httpOnly).toBe(true)
    expect(session.sameSite).toBe('Strict')
    expect(session.value).not.toContain('frontend-test-token')
    await page.screenshot({ path: '/tmp/brickventory-overview.png', fullPage: true, animations: 'disabled' })
    expect(await page.evaluate(() => JSON.stringify({ ...localStorage }))).not.toContain('frontend-test-token')
    const response = await page.request.get('/api/inventory/products')
    expect(response.status()).toBe(200)
    const crossSite = await page.request.post('/api/inventory/products', {
        headers: { Origin: 'https://attacker.invalid' },
        data: { name: 'Bad', sku: 'BAD' },
    })
    expect(crossSite.status()).toBe(403)
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.requests.every((entry: any) => !entry.cookie)).toBe(true)
    await page.getByRole('button', { name: 'Sign out', exact: true }).click()
    await expect(page).toHaveURL(/\/login$/)
    expect((await page.request.get('/api/inventory/products')).status()).toBe(401)
})

test('creates and edits a product using the Symfony payload', async ({ page, request }) => {
    await login(page)
    await page.goto('/products/new')
    await page.getByLabel('Name *', { exact: true }).fill('New brick')
    await page.getByLabel('SKU *', { exact: true }).fill('NEW-1')
    await page.getByLabel('Selling price').fill('0.10')
    await page.getByRole('button', { name: 'Save product', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'New brick', exact: true })).toBeVisible()
    await page.getByRole('link', { name: 'Edit', exact: true }).click()
    await page.getByLabel('Name *', { exact: true }).fill('Updated brick')
    await page.getByRole('button', { name: 'Save product', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Updated brick', exact: true })).toBeVisible()
    const state = await (await request.get(`${backend}/__state`)).json()
    const create = state.requests.find((entry: any) => entry.method === 'POST' && entry.path === '/api/v1/products')
    expect(create.body.price).toBe('0.10')
    expect(create.body).not.toHaveProperty('id')
    expect(create.body).not.toHaveProperty('stock')
})

test('creates an order, processes it, partially ships, and records payment', async ({ page, request }) => {
    await login(page)
    await page.goto('/orders/new')
    await page.getByLabel('Customer name', { exact: true }).fill('Walk-in buyer')
    await choose(page, 'field-location_id')
    await page.getByRole('button', { name: '+ Add line', exact: true }).click()
    await choose(page, 'product-0')
    await page.getByLabel('Quantity *', { exact: true }).fill('3')
    await page.getByLabel('Unit price *', { exact: true }).fill('1.25')
    await page.getByRole('button', { name: 'Save order', exact: true }).click()
    await expect(page.getByRole('heading', { name: /ORDERS-1/ })).toBeVisible()
    await page.getByRole('button', { name: 'Process', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm process', exact: true }).click()
    await page.getByRole('button', { name: 'Ship', exact: true }).click()
    await page.getByLabel('Quantity (up to 3)').fill('1')
    await page.getByRole('button', { name: 'Confirm ship', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await page.getByRole('button', { name: 'Record payment', exact: true }).click()
    await page.getByLabel('Amount', { exact: true }).fill('2.50')
    await page.getByRole('button', { name: 'Confirm pay', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records.orders[0].items[0].shipped_quantity).toBe(1)
    expect(state.requests.find((entry: any) => entry.path.endsWith('/ship')).body).toEqual({
        items: [{ id: 1, quantity: 1 }],
    })
})

test('sends a purchase order and receives a partial delivery by document line ID', async ({ page, request }) => {
    await login(page)
    await page.goto('/purchase-orders/new')
    await choose(page, 'field-supplier_id')
    await page.getByRole('button', { name: '+ Add line', exact: true }).click()
    await choose(page, 'product-0')
    await page.getByLabel('Quantity *', { exact: true }).fill('5')
    await page.getByLabel('Unit cost *', { exact: true }).fill('0.75')
    await page.getByRole('button', { name: 'Save purchase order', exact: true }).click()
    await page.getByRole('button', { name: 'Send', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm send', exact: true }).click()
    await page.getByRole('button', { name: 'Receive', exact: true }).click()
    await page.getByLabel('Quantity (up to 5)').fill('2')
    await choose(page, 'receive-1', '2')
    await page.getByRole('button', { name: 'Confirm receive', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records['purchase-orders'][0].status).toBe('partial')
    expect(state.requests.find((entry: any) => entry.path.endsWith('/receive')).body).toEqual({
        items: [{ id: 1, quantity_to_receive: 2, receiving_location_id: 2 }],
    })
})

test('supports mobile navigation without horizontal page overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await login(page)
    await page.getByRole('button', { name: 'Toggle navigation' }).click()
    await page.getByRole('link', { name: 'Stock transfers', exact: true }).click()
    await expect(page.getByRole('heading', { name: /Stock transfers/ })).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.screenshot({ path: '/tmp/brickventory-mobile.png', fullPage: true, animations: 'disabled' })
})

test('creates contacts and completes a stock transfer', async ({ page, request }) => {
    await login(page)
    for (const [resource, name] of [
        ['customers', 'New customer'],
        ['suppliers', 'New supplier'],
    ]) {
        await page.goto(`/${resource}/new`)
        await page.getByLabel('Name *', { exact: true }).fill(name!)
        await page.getByLabel('Email', { exact: true }).fill(`${resource}@example.com`)
        await page
            .getByRole('button', { name: `Save ${resource === 'customers' ? 'customer' : 'supplier'}`, exact: true })
            .click()
        await expect(page.getByRole('heading', { name, exact: true })).toBeVisible()
    }
    await page.goto('/stock-transfers/new')
    await choose(page, 'field-from_location_id')
    await choose(page, 'field-to_location_id', '2')
    await page.getByRole('button', { name: '+ Add line', exact: true }).click()
    await choose(page, 'product-0')
    await page.getByLabel('Quantity *', { exact: true }).fill('2')
    await page.getByRole('button', { name: 'Save stock transfer', exact: true }).click()
    for (const action of ['Prepare', 'Ship', 'Complete']) {
        await page.getByRole('button', { name: action, exact: true }).click()
        await page.getByRole('button', { name: `Confirm ${action.toLowerCase()}`, exact: true }).click()
        await expect(page.getByRole('dialog')).toHaveCount(0)
    }
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records['stock-transfers'][0].status).toBe('completed')
    expect(
        state.requests.find((entry: any) => entry.method === 'POST' && entry.path === '/api/v1/stock-transfers').body
            .items,
    ).toEqual([{ product_id: 1, quantity: 2 }])
})

test('creates a zero physical count and a customer return request', async ({ page, request }) => {
    await login(page)
    await page.goto('/stock-audits/new')
    await choose(page, 'field-location_id')
    await page.getByRole('button', { name: '+ Add line', exact: true }).click()
    await choose(page, 'product-0')
    await page.getByLabel('Quantity *', { exact: true }).fill('0')
    await page.getByRole('button', { name: 'Save stock count', exact: true }).click()
    await page.getByRole('button', { name: 'Complete', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm complete', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await page.request.post('/api/inventory/orders', {
        data: { customer_name: 'Buyer', location_id: 1, items: [{ product_id: 1, quantity: 3, unit_price: '1.25' }] },
    })
    await page.request.post('/api/inventory/orders/1/process', { data: {} })
    await page.request.post('/api/inventory/orders/1/ship', { data: { items: [{ id: 1, quantity: 3 }] } })
    await page.goto('/order-returns/new')
    await choose(page, 'field-order_id')
    await page.getByLabel('Return quantity', { exact: true }).fill('1')
    await choose(page, 'return-location-0', '2')
    await page.getByRole('button', { name: 'Save return request', exact: true }).click()
    await expect(page.getByRole('heading', { name: /ORDER-RETURNS-1/ })).toBeVisible()
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(
        state.requests.find((entry: any) => entry.method === 'POST' && entry.path === '/api/v1/order-returns').body
            .items,
    ).toEqual([{ id: 1, quantity: 1, receiving_location_id: 2 }])
})

test('selects named product options when creating a variant', async ({ page, request }) => {
    await login(page)
    await page.goto('/variants/new')
    await choose(page, 'field-parent_id')
    await page.getByLabel('Name *', { exact: true }).fill('Blue variant')
    await page.getByLabel('SKU *', { exact: true }).fill('NEW-BLUE')
    await page.getByLabel('color *', { exact: true }).selectOption('blue')
    await page.getByRole('button', { name: 'Save variant', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Blue variant', exact: true })).toBeVisible()
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(
        state.requests.find((entry: any) => entry.method === 'POST' && entry.path === '/api/v1/variants').body,
    ).toMatchObject({ parent_id: 1, attributes: { color: 'blue' } })
})

test('dashboard metrics, stock review, reorder drafts and browser preferences', async ({ page, request }) => {
    await login(page)
    await expect(page.getByTestId('dashboard-metric-products')).toContainText('1')
    await expect(page.getByTestId('dashboard-metric-value')).toContainText('$2.50')
    await expect(page.getByTestId('dashboard-metric-revenue')).toContainText('€5.00')
    await expect(page.getByRole('heading', { name: 'Replenishment', exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Review stock: Red brick' }).click()
    await expect(page.getByRole('dialog')).toContainText('3 units below')
    await page.getByRole('link', { name: 'Prepare purchase order', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'New purchase order', exact: true })).toBeVisible()
    await expect(page.locator('#field-supplier_id')).toHaveValue('1')
    await expect(page.locator('#product-0')).toHaveValue('1')
    await expect(page.locator('#quantity-0')).toHaveValue('3')
    await expect(page.locator('#price-0')).toHaveValue('0.50')
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records['purchase-orders']).toHaveLength(0)
    await page.goto('/')
    await page.getByRole('button', { name: 'Customize', exact: true }).click()
    await page.getByRole('dialog').getByLabel('Recent products', { exact: true }).uncheck()
    await page.getByRole('button', { name: 'Save dashboard', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Recent products', exact: true })).toHaveCount(0)
    await page.reload()
    await expect(page.getByRole('heading', { name: 'Recent products', exact: true })).toHaveCount(0)
    await page.screenshot({ path: '/tmp/brickventory-dashboard.png', fullPage: true, animations: 'disabled' })
    await page.setViewportSize({ width: 390, height: 844 })
    await expect(page.getByRole('heading', { name: 'Replenishment', exact: true })).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.screenshot({ path: '/tmp/brickventory-dashboard-mobile.png', fullPage: true, animations: 'disabled' })
})

test('links a primary supplier and reviews replenishment before creating a draft', async ({ page, request }) => {
    await login(page)
    await page.goto('/products/1')
    await page.getByRole('button', { name: '+ Link supplier', exact: true }).click()
    await choose(page, 'catalog-supplier')
    await page.locator('#catalog-cost').fill('0.50')
    await page.locator('#catalog-currency').fill('USD')
    await page.getByRole('button', { name: 'Save supplier terms', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Link supplier', exact: true })).toHaveCount(0)
    await expect(page.locator('#suppliers')).toContainText('Brick supply')
    await page.goto('/replenishment')
    await page.getByLabel('Search products or suppliers', { exact: true }).fill('Red')
    await page.getByRole('combobox', { name: 'Readiness', exact: true }).selectOption('true')
    await page.getByRole('button', { name: 'Filter suggestions', exact: true }).click()
    await page.getByRole('checkbox', { name: 'Select Red brick', exact: true }).check()
    await page.getByRole('button', { name: 'Review 1 selected', exact: true }).click()
    await expect(page.getByRole('dialog')).toContainText('0.50 USD')
    let state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records['purchase-orders']).toHaveLength(0)
    await page.getByRole('button', { name: 'Create draft purchase orders', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: '1 draft purchase order created', exact: true })).toBeVisible()
    state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records['purchase-orders']).toHaveLength(1)
    const draftCall = state.requests.find((entry: any) => entry.path === '/api/v1/replenishment/drafts')
    expect(
        state.requests.some(
            (entry: any) =>
                entry.path === '/api/v1/replenishment' &&
                entry.query.eligible === 'true' &&
                entry.query.search === 'Red',
        ),
    ).toBe(true)
    expect(draftCall.body.expected).toEqual({ 1: 'a'.repeat(64) })
    expect(draftCall.body.request_id).toMatch(/^[a-f0-9-]{36}$/)
    await expect(page.getByRole('heading', { name: 'No replenishment needed', exact: true })).toBeVisible()
})

test('bulk adjusts prices, duplicates a product and configures warehouse levels', async ({ page, request }) => {
    await login(page)
    await page.goto('/products')
    await page.getByRole('checkbox', { name: 'Select Red brick', exact: true }).check()
    await page.getByLabel('Adjustment value', { exact: true }).fill('10')
    await page.getByRole('button', { name: 'Review bulk operation', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm bulk price', exact: true }).click()
    await expect(page.getByText('1.38', { exact: true })).toBeVisible()
    await page.goto('/products/1')
    await page.getByRole('button', { name: 'Duplicate product', exact: true }).click()
    await page.getByLabel('New SKU', { exact: true }).fill('COPY-RED')
    await page.getByRole('button', { name: 'Create product copy', exact: true }).click()
    await expect(page).toHaveURL(/\/products\/2$/)
    await page.getByLabel('min stock', { exact: true }).fill('5')
    await page.getByLabel('max stock', { exact: true }).fill('20')
    await page.getByRole('button', { name: 'Save Main warehouse levels', exact: true }).click()
    await page.getByRole('combobox', { name: 'Active warehouse', exact: true }).selectOption('1')
    await page.goto('/stock-balances')
    await expect
        .poll(async () => {
            const state = await (await request.get(`${backend}/__state`)).json()
            return state.requests.some((r: any) => r.path === '/api/v1/stock-balances' && r.query.warehouse_id === '1')
        })
        .toBe(true)
})

test('schedules, records and reviews a physical stock count', async ({ page }) => {
    await login(page)
    await page.goto('/cycle-counts')
    await page.getByRole('button', { name: 'New count schedule', exact: true }).click()
    await page.getByLabel('Schedule name', { exact: true }).fill('Weekly bins')
    await page.getByRole('button', { name: 'Save schedule', exact: true }).click()
    await page.getByRole('button', { name: 'Run count now', exact: true }).click()
    await page.getByRole('status').getByRole('link', { name: 'COUNT-1', exact: true }).click()
    await page.getByLabel('Physical quantity', { exact: true }).fill('8')
    await page.getByRole('button', { name: 'Record count for line 1', exact: true }).click()
    await page.getByRole('button', { name: 'Review line 1', exact: true }).click()
    await expect(page.getByText('Expected 10 · Counted 8 · Reviewed', { exact: true })).toBeVisible()
    await page.locator('summary').filter({ hasText: 'Count history' }).click()
    await expect(page.getByText(/8 units/)).toBeVisible()
})

test('registers and reserves serial stock', async ({ page, request }) => {
    await login(page)
    await request.patch(`${backend}/api/v1/products/1`, {
        headers: { Authorization: 'Bearer frontend-test-token' },
        data: { tracking: 'serial' },
    })
    await page.goto('/products/1')
    await page.getByRole('button', { name: 'Register serial', exact: true }).click()
    await page.getByLabel('Serial number', { exact: true }).fill('SER-100')
    await choose(page, 'serial-location')
    await page.getByRole('button', { name: 'Save serial registration', exact: true }).click()
    await page.getByRole('combobox', { name: 'Availability', exact: true }).selectOption('reserved')
    await page.getByRole('button', { name: 'Save SER-100', exact: true }).click()
    await expect
        .poll(async () => {
            const state = await (await request.get(`${backend}/__state`)).json()
            return state.requests.some(
                (r: any) => r.method === 'PATCH' && r.path.endsWith('/serials/SER-100') && r.body.status === 'reserved',
            )
        })
        .toBe(true)
})

test('fulfillment shows shipment documents and updates tracking', async ({ page, request }) => {
    await login(page)
    const headers = { Authorization: 'Bearer frontend-test-token' }
    const order = await (
        await request.post(`${backend}/api/v1/orders`, {
            headers,
            data: { customer_name: 'Buyer', location_id: 1, items: [{ product_id: 1, quantity: 2 }] },
        })
    ).json()
    await request.post(`${backend}/api/v1/orders/${order.data.id}/ship`, {
        headers,
        data: { items: [{ id: 1, quantity: 1 }], carrier: 'Manual' },
    })
    await page.goto('/shipments')
    await expect(page.getByRole('link', { name: 'Download packing slip', exact: true })).toHaveAttribute(
        'href',
        /shipments\/1\/packing-slip$/,
    )
    await page.getByRole('button', { name: 'Update tracking', exact: true }).click()
    await page.getByLabel('Tracking number', { exact: true }).fill('TRACK-100')
    await page.getByRole('button', { name: 'Save shipment tracking', exact: true }).click()
    await expect(page.getByText(/Manual TRACK-100/)).toBeVisible()
})

test('queues product CSVs and can leave, cancel and resume the background import', async ({ page, request }) => {
    await login(page)
    await page.goto('/inventory-import')
    await page
        .getByLabel('CSV file', { exact: true })
        .setInputFiles({
            name: 'bulk-products.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from('sku,name\nQUEUED,Queued product\n'),
        })
    await page.getByRole('button', { name: 'Queue product import', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'bulk-products.csv', exact: true })).toBeVisible()
    await expect(page.getByText('queued', { exact: true })).toBeVisible()
    const state = await (await request.get(`${backend}/__state`)).json()
    expect(state.records.products).toHaveLength(1)
    expect(state.requests.some((r: any) => r.method === 'POST' && r.path === '/api/v1/product-imports')).toBe(true)
    await page.goto('/products')
    await page.goto('/inventory-import')
    await page.getByRole('button', { name: 'Cancel import', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm cancel', exact: true }).click()
    await expect(page.getByText('cancelled', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Resume import', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm resume', exact: true }).click()
    await expect(page.getByText('queued', { exact: true })).toBeVisible()
})
