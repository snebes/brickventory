import { createServer } from 'node:http'
const token = 'frontend-test-token'
let records, requests, supplierLinks, reorderBatches, serialRegistry, warehouseLevels
function reset() {
    const now = '2026-11-16T12:00:00+00:00'
    records = Object.fromEntries(
        [
            'products',
            'variants',
            'customers',
            'suppliers',
            'locations',
            'warehouses',
            'categories',
            'orders',
            'purchase-orders',
            'stock-transfers',
            'stock-adjustments',
            'stock-audits',
            'order-returns',
            'stock-balances',
            'stock-movements',
            'trace-balances',
        ].map((key) => [key, []]),
    )
    records.products = [
        {
            id: 1,
            sku: 'BRICK-RED',
            name: 'Red brick',
            price: '1.25',
            cost: '0.50',
            stock: 10,
            min_stock: 3,
            is_active: true,
            details: { options: { color: ['blue'] } },
            created_at: now,
        },
    ]
    records.variants = [
        {
            id: 2,
            parent_id: 1,
            sku: 'BRICK-BLUE',
            name: 'Blue brick',
            stock: 5,
            price: '1.25',
            details: { attributes: { color: 'blue' } },
            is_active: true,
        },
    ]
    records.customers = [
        { id: 1, name: 'Acme customer', code: 'ACME', email: 'customer@example.com', is_active: true, details: {} },
    ]
    records.suppliers = [
        { id: 1, name: 'Brick supply', code: 'SUP', email: 'supplier@example.com', is_active: true, details: {} },
    ]
    records.locations = [
        { id: 1, name: 'Main bin', code: 'A', details: {} },
        { id: 2, name: 'Receiving bin', code: 'B', details: {} },
    ]
    records.categories = [{ id: 1, name: 'Bricks', details: {} }]
    records.warehouses = [{ id: 1, name: 'Main warehouse', details: {} }]
    requests = []
    serialRegistry = []
    warehouseLevels = {}
    records['cycle-counts'] = []
    records['product-imports'] = []
    supplierLinks = []
    reorderBatches = {}
}
reset()
createServer(async (req, res) => {
    const url = new URL(req.url, 'http://127.0.0.1:4318')
    const json = (status, body) => {
        res.writeHead(status, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify(body))
    }
    if (url.pathname === '/__reset') {
        reset()
        return json(200, {})
    }
    if (url.pathname === '/__state') return json(200, { records, requests })
    if (req.headers.authorization === 'Bearer backend-error')
        return json(500, { message: 'Private database diagnostic' })
    if (req.headers.authorization !== `Bearer ${token}`) return json(401, { message: 'Invalid API token.' })
    const chunks = []
    for await (const chunk of req) chunks.push(chunk)
    let body = {}
    const raw = Buffer.concat(chunks)
    if (raw.length && req.headers['content-type']?.includes('application/json')) body = JSON.parse(raw)
    requests.push({
        method: req.method,
        path: url.pathname,
        query: Object.fromEntries(url.searchParams),
        body,
        authorization: req.headers.authorization,
        cookie: req.headers.cookie,
    })
    const [resource, id, action] = url.pathname.replace('/api/v1/', '').split('/')
    if (resource === 'product-imports') {
        if (req.method === 'POST' && !id) {
            const job = {
                id: '019b0000-0000-7000-8000-000000000001',
                filename: 'bulk-products.csv',
                status: 'queued',
                processed: 0,
                progress: 0,
                bytes_processed: 0,
                bytes_total: raw.length,
                error: null,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            }
            records[resource].unshift(job)
            return json(202, { data: job })
        }
        if (req.method === 'POST' && action) {
            const row = records[resource].find((r) => r.id === id)
            row.status = action === 'cancel' ? 'cancelled' : 'queued'
            return json(200, { data: row })
        }
        if (req.method === 'GET')
            return json(200, { data: records[resource], meta: { total: records[resource].length } })
    }
    if (resource === 'products' && action === 'duplicate') {
        const source = records.products.find((r) => r.id === Number(id))
        const copy = {
            ...source,
            id: Math.max(...records.products.map((r) => r.id)) + 1,
            sku: body.sku,
            name: source.name + ' (copy)',
            stock: 0,
        }
        records.products.push(copy)
        return json(201, { data: copy })
    }
    if (resource === 'products' && id === 'bulk') {
        if (action === 'price')
            for (const p of records.products.filter((p) => body.ids.includes(p.id)))
                p.price = Math.max(
                    0,
                    Number(p.price) +
                        (body.type === 'fixed' ? Number(body.value) : (Number(p.price) * Number(body.value)) / 100),
                ).toFixed(2)
        if (action === 'category')
            for (const p of records.products.filter((p) => body.ids.includes(p.id))) p.category_id = body.category_id
        if (action === 'delete') records.products = records.products.filter((p) => !body.ids.includes(p.id))
        return json(200, { data: { affected: body.ids.length } })
    }
    if (resource === 'products' && action === 'warehouse-levels') {
        if (req.method === 'PUT')
            for (const level of body.levels) {
                const { warehouse_id, ...values } = level
                warehouseLevels[`${id}-${warehouse_id}`] = values
            }
        return json(200, {
            data: records.warehouses.map((w) => ({
                warehouse_id: w.id,
                warehouse: w.name,
                stock: 10,
                reserved_quantity: 0,
                available_quantity: 10,
                levels: warehouseLevels[`${id}-${w.id}`] || {},
                is_default: w.details.is_default || false,
            })),
        })
    }
    if (resource === 'warehouses' && action === 'set-default') {
        for (const w of records.warehouses) w.details.is_default = w.id === Number(id)
        return json(200, { data: { warehouse_id: Number(id) } })
    }
    if (resource === 'products' && action === 'serials') {
        const number = decodeURIComponent(url.pathname.split('/')[6] || '')
        if (url.pathname.endsWith('/history')) return json(200, { data: [] })
        if (req.method === 'POST') {
            const row = {
                id: serialRegistry.length + 1,
                product_id: Number(id),
                ...body,
                status: body.location_id ? 'available' : 'registered',
                locations: [],
            }
            serialRegistry.push(row)
            return json(201, { data: row })
        }
        if (req.method === 'PATCH') {
            const row = serialRegistry.find((r) => r.product_id === Number(id) && r.serial_number === number)
            Object.assign(row, body)
            return json(200, { data: row })
        }
        const data = serialRegistry.filter((r) => r.product_id === Number(id))
        return json(200, { data, meta: { total: data.length } })
    }
    if (resource === 'cycle-counts' && req.method === 'POST' && !id) {
        const row = {
            id: records[resource].length + 1,
            name: body.name,
            is_active: body.is_active,
            next_run_at: body.next_run_at || new Date().toISOString(),
            last_run_at: null,
            details: body,
        }
        records[resource].push(row)
        return json(201, { data: row })
    }
    if (resource === 'cycle-counts' && action === 'run') {
        const audit = {
            id: records['stock-audits'].length + 1,
            number: 'COUNT-1',
            status: 'draft',
            items: [{ id: 1, product_id: 1, quantity: 0, expected_quantity: 10 }],
            details: { location_id: 1, count_required: true },
        }
        records['stock-audits'].push(audit)
        return json(200, { data: { created: [{ id: audit.id, number: audit.number }] } })
    }
    if (resource === 'stock-audits' && action === 'items') {
        const parts = url.pathname.split('/'),
            lineId = parts[6],
            operation = parts[7],
            audit = records[resource].find((r) => r.id === Number(id)),
            line = audit.items.find((l) => l.id === Number(lineId))
        if (operation === 'count') {
            line.quantity = body.quantity
            audit.details.count_history ||= {}
            ;(audit.details.count_history[lineId] ||= []).push({ ...body, created_at: new Date().toISOString() })
            audit.details.count_reviews ||= {}
            delete audit.details.count_reviews[lineId]
        }
        if (operation === 'review') {
            audit.details.count_reviews ||= {}
            audit.details.count_reviews[lineId] = 'reviewed'
        }
        return json(200, { data: audit })
    }
    if (resource === 'shipments') {
        const data = records.orders.flatMap((order) =>
            (order.details.shipments || []).map((row, index) => ({
                ...row,
                id: `${order.id}-${index + 1}`,
                order_id: order.id,
                order_number: order.number,
                customer: order.customer_name || '',
            })),
        )
        return json(200, { data, meta: { total: data.length } })
    }
    if (resource === 'orders' && action === 'shipments' && req.method === 'PATCH') {
        const shipmentId = Number(url.pathname.split('/')[6])
        const row = records.orders.find((r) => r.id === Number(id)).details.shipments[shipmentId - 1]
        Object.assign(row, body)
        return json(200, { data: row })
    }
    if (resource === 'orders' && (action === 'pick-list' || url.pathname.endsWith('/packing-slip'))) {
        res.writeHead(200, { 'Content-Type': 'application/pdf' })
        return res.end('%PDF-1.4\nfixture')
    }
    if (resource === 'barcode-lookup') return json(200, { data: records.products[0] })
    if ((resource === 'products' && action === 'suppliers') || (resource === 'suppliers' && action === 'products')) {
        const linkId = Number(url.pathname.split('/')[6])
        if (req.method === 'GET') {
            const rows = supplierLinks.filter((link) =>
                resource === 'products' ? link.product_id === Number(id) : link.supplier_id === Number(id),
            )
            return json(200, { data: rows, meta: { total: rows.length, current_page: 1, per_page: 20 } })
        }
        const product = [...records.products, ...records.variants].find((row) => row.id === Number(id))
        if (req.method === 'DELETE') {
            supplierLinks = supplierLinks.filter((link) => link.id !== linkId)
            res.writeHead(204)
            return res.end()
        }
        const link =
            req.method === 'PATCH'
                ? supplierLinks.find((row) => row.id === linkId && row.product_id === Number(id))
                : {
                      id: supplierLinks.length + 1,
                      product_id: Number(id),
                      product_name: product.name,
                      product_sku: product.sku,
                      parent_id: product.parent_id || null,
                      is_primary: !supplierLinks.some((row) => row.product_id === Number(id)),
                      supplier_active: true,
                  }
        if (!link) return json(404, { message: 'Supplier link not found.' })
        Object.assign(link, body, { currency: body.currency || 'USD' })
        link.supplier_name = records.suppliers.find((row) => row.id === link.supplier_id)?.name
        if (link.is_primary)
            for (const other of supplierLinks)
                if (other !== link && other.product_id === link.product_id) other.is_primary = false
        if (req.method === 'POST') supplierLinks.push(link)
        return json(req.method === 'POST' ? 201 : 200, { data: link })
    }
    if (resource === 'replenishment') {
        const primary = supplierLinks.find((link) => link.product_id === 1 && link.is_primary)
        if (req.method === 'GET')
            return json(200, {
                data: records['purchase-orders'].length
                    ? []
                    : [
                          {
                              id: 1,
                              product_id: 1,
                              product_variant_id: null,
                              parent_id: null,
                              name: 'Red brick',
                              sku: 'BRICK-RED',
                              stock: 2,
                              min_stock: 5,
                              reorder_point: 5,
                              max_stock: 5,
                              reserved_quantity: 0,
                              available_quantity: 2,
                              on_order_quantity: 0,
                              projected_quantity: 2,
                              suggested_quantity: 3,
                              supplier_id: primary?.supplier_id || null,
                              supplier: primary?.supplier_name || null,
                              supplier_sku: primary?.supplier_sku || '',
                              unit_cost: primary?.cost_price || '0.50',
                              currency: primary?.currency || 'USD',
                              lead_time_days: 0,
                              has_variants: false,
                              can_create_draft: Boolean(primary),
                              blocked_reason: primary ? null : 'Choose a primary supplier.',
                              fingerprint: 'a'.repeat(64),
                          },
                      ],
                meta: { total: records['purchase-orders'].length ? 0 : 1, current_page: 1, per_page: 25 },
            })
        if (req.method === 'POST' && id === 'drafts') {
            if (reorderBatches[body.request_id]) return json(201, { data: reorderBatches[body.request_id] })
            if (!primary || body.expected?.[1] !== 'a'.repeat(64))
                return json(409, { message: 'Refresh and review again.' })
            const order = {
                id: 1,
                number: 'PO-REORDER-1',
                status: 'draft',
                supplier_id: primary.supplier_id,
                total: '1.50',
                details: { currency: primary.currency },
                items: [{ id: 1, product_id: 1, quantity: 3, unit_price: primary.cost_price, received_quantity: 0 }],
            }
            records['purchase-orders'].push(order)
            reorderBatches[body.request_id] = { purchase_orders: [order] }
            return json(201, { data: reorderBatches[body.request_id] })
        }
    }
    if (resource === 'dashboard') {
        const date = new Date().toISOString().slice(0, 10)
        const product = {
            ...records.products[0],
            stock: 2,
            min_stock: 5,
            total_value: '2.50',
            cost_value: '1.00',
            category: 'Bricks',
            has_variants: false,
        }
        return json(200, {
            data: {
                generatedAt: new Date().toISOString(),
                currency: 'USD',
                stats: {
                    totalProducts: 1,
                    lowStockProducts: 1,
                    categories: 1,
                    locations: 2,
                    totalOrders: records.orders.length,
                    pendingOrders: records.orders.filter((row) => row.status === 'pending').length,
                    pendingPurchaseOrders: 0,
                    inTransitTransfers: 0,
                    deadStockCount: 0,
                    totalValue: '2.50',
                    revenueThisMonth: '12.50',
                    outstandingReceivables: '10.00',
                    deadStockValue: '0.00',
                    byCurrency: {
                        revenueThisMonth: [
                            { currency: 'USD', amount: '12.50' },
                            { currency: 'EUR', amount: '5.00' },
                        ],
                    },
                },
                recentProducts: [product],
                lowStockProducts: [product],
                topProducts: [product],
                reorderSuggestions: [
                    {
                        ...product,
                        suggested_quantity: 3,
                        supplier_id: 1,
                        supplier: 'Brick supply',
                        unit_cost: '0.50',
                        currency: 'USD',
                    },
                ],
                recentOrders: [],
                recentPurchaseOrders: [],
                stockByCategory: [{ id: 1, name: 'Bricks', count: 1, value: '2.50' }],
                stockMovements: [{ date, inbound: 3, outbound: 1, total: 2 }],
                recentActivity: [],
            },
        })
    }
    if (!records[resource]) return json(404, { message: 'Unknown resource.' })
    if (id === 'export') {
        res.writeHead(200, { 'Content-Type': 'text/csv', 'Content-Disposition': 'attachment; filename="products.csv"' })
        return res.end('sku,name\nBRICK-RED,Red brick\n')
    }
    if (id === 'import-csv') return json(200, { data: records.products })
    if (id && action === 'attachments') return json(200, { data: [] })
    if (id && ['orders', 'price-history', 'variants'].includes(action))
        return json(200, {
            data: action === 'variants' ? records.variants : [],
            meta: { total: action === 'variants' ? 1 : 0, current_page: 1, per_page: 10 },
        })
    if (id && ['invoice', 'document'].includes(action)) {
        res.writeHead(200, { 'Content-Type': 'application/pdf' })
        return res.end('%PDF-1.4\nfixture')
    }
    if (req.method === 'GET') {
        if (id)
            return records[resource].some((row) => row.id === Number(id))
                ? json(200, { data: records[resource].find((row) => row.id === Number(id)) })
                : json(404, { message: 'Record not found.' })
        let data = records[resource]
        if (url.searchParams.get('search')) {
            const search = url.searchParams.get('search').toLowerCase()
            data = data.filter((row) =>
                `${row.name || ''} ${row.sku || ''} ${row.number || ''}`.toLowerCase().includes(search),
            )
        }
        if (url.searchParams.get('status')) data = data.filter((row) => row.status === url.searchParams.get('status'))
        if (url.searchParams.get('parent_id'))
            data = data.filter((row) => row.parent_id === Number(url.searchParams.get('parent_id')))
        if (url.searchParams.get('low_stock')) data = data.filter((row) => row.stock <= row.min_stock)
        const page = Number(url.searchParams.get('page') || 1),
            limit = Number(url.searchParams.get('per_page') || 25)
        return json(200, {
            data: data.slice((page - 1) * limit, page * limit),
            meta: { total: data.length, current_page: page, per_page: limit },
        })
    }
    if (req.method === 'POST' && !id) {
        const doc = ['orders', 'purchase-orders', 'stock-transfers', 'stock-audits', 'order-returns'].includes(resource)
        const nextId = records[resource].length ? Math.max(...records[resource].map((row) => row.id)) + 1 : 1
        const items = (body.items || []).map((line, index) => ({
            ...line,
            id: index + 1,
            unit_price: line.unit_cost || line.unit_price || '1.25',
            received_quantity: 0,
            shipped_quantity: 0,
        }))
        const record = {
            ...body,
            id: nextId,
            ...(doc
                ? {
                      number: `${resource.toUpperCase()}-${nextId}`,
                      status: resource === 'orders' ? 'pending' : resource === 'order-returns' ? 'requested' : 'draft',
                      total: '2.50',
                      items,
                      details: { ...body, payments: [] },
                  }
                : { details: {} }),
            created_at: '2026-11-16T12:00:00+00:00',
        }
        records[resource].push(record)
        return json(201, { data: record })
    }
    const record = records[resource].find((row) => row.id === Number(id))
    if (!record) return json(404, { message: 'Record not found.' })
    if (req.method === 'PATCH') {
        Object.assign(record, body)
        if (record.details) Object.assign(record.details, body)
        return json(200, { data: record })
    }
    if (req.method === 'DELETE') {
        records[resource] = records[resource].filter((row) => row.id !== Number(id))
        res.writeHead(204)
        return res.end()
    }
    if (req.method === 'POST' && action) {
        if (['email', 'email-invoice'].includes(action)) return json(202, { message: 'Document queued for delivery.' })
        if (action === 'receive' && resource === 'purchase-orders') {
            if (
                body.items.some(
                    (line) => 'product_id' in line || !line.receiving_location_id || !line.quantity_to_receive,
                )
            )
                return json(422, { message: 'Invalid receipt contract.' })
            for (const line of body.items)
                record.items.find((item) => item.id === line.id).received_quantity += line.quantity_to_receive
            record.status = record.items.some((line) => line.received_quantity < line.quantity) ? 'partial' : 'received'
        } else if (action === 'ship' && resource === 'orders') {
            ;(record.details.shipments ||= []).push({
                items: body.items,
                carrier: body.carrier || '',
                tracking_number: body.tracking_number || '',
                created_at: new Date().toISOString(),
            })
            for (const line of body.items)
                record.items.find((item) => item.id === line.id).shipped_quantity += line.quantity
            record.status = record.items.some((line) => line.shipped_quantity < line.quantity)
                ? 'processing'
                : 'shipped'
        } else if (action === 'pay') {
            record.amount_paid = body.amount
            record.payment_status = 'paid'
            record.details.payments.push(body)
        } else
            record.status =
                {
                    process: 'processing',
                    cancel: 'cancelled',
                    send: 'sent',
                    prepare: 'prepared',
                    ship: 'shipped',
                    complete: 'completed',
                    deliver: 'delivered',
                    approve: 'approved',
                    receive: 'received',
                }[action] || record.status
        return json(200, { data: record })
    }
    json(404, { message: 'Route not found.' })
}).listen(4318, '127.0.0.1')
