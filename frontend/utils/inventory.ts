import type { Field, InventoryRecord } from '~/shared/inventory'

export function errorMessage(error: any): string {
    return (
        error?.data?.message ||
        error?.data?.data?.message ||
        error?.data?.statusMessage ||
        error?.statusMessage ||
        error?.message ||
        'The request failed. Please try again.'
    )
}
export function label(key: string): string {
    return key
        .replaceAll('_', ' ')
        .replace(/\bid\b/g, 'ID')
        .replace(/^./, (value) => value.toUpperCase())
}
export function display(value: any, key = ''): string {
    if (value === undefined || value === null || value === '') return '—'
    if (typeof value === 'boolean') return value ? 'Active' : 'Inactive'
    if (key.endsWith('_at') || key.endsWith('_date')) {
        const date = new Date(value)
        if (!Number.isNaN(date.getTime()))
            return date.toLocaleDateString('en-US', { dateStyle: 'medium', timeZone: 'UTC' })
    }
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value).replaceAll('_', ' ')
}
export function formValues(fields: Field[], record?: InventoryRecord): Record<string, any> {
    return Object.fromEntries(
        fields.map((field) => {
            let value =
                (field.detail ? record?.details?.[field.key] : record?.[field.key]) ?? record?.details?.[field.key]
            if (value == null)
                value =
                    field.type === 'checkbox'
                        ? field.key === 'is_active'
                        : field.type === 'money'
                          ? '0.00'
                          : field.key === 'tracking'
                            ? 'none'
                            : ''
            if (['json', 'options', 'attributes'].includes(field.type || ''))
                value = value ? JSON.stringify(value, null, 2) : ''
            if (field.type === 'serials') value = Array.isArray(value) ? value.join('\n') : value
            return [field.key, value]
        }),
    )
}
export function formPayload(fields: Field[], values: Record<string, any>): Record<string, any> {
    const result: Record<string, any> = {}
    for (const field of fields) {
        const value = values[field.key]
        if (value === '' || value == null) {
            if (field.required) throw new Error(`${field.label} is required.`)
            continue
        }
        if (['json', 'options', 'attributes'].includes(field.type || '')) {
            try {
                result[field.key] = JSON.parse(value)
            } catch {
                throw new Error(`${field.label} must contain valid JSON.`)
            }
        } else if (field.type === 'serials')
            result[field.key] = String(value)
                .split(/\r?\n/)
                .map((v) => v.trim())
                .filter(Boolean)
        else if (['number', 'integer'].includes(field.type || '') || field.resource) {
            const number = Number(value)
            if (!Number.isSafeInteger(number)) throw new Error(`${field.label} must be a whole number.`)
            result[field.key] = number
        } else if (field.type === 'money') {
            if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(String(value)))
                throw new Error(`${field.label} needs a nonnegative amount with at most two decimal places.`)
            result[field.key] = String(value)
        } else result[field.key] = value
    }
    return result
}
export function canEdit(resource: string, record: InventoryRecord): boolean {
    if (['orders'].includes(resource)) return ['pending', 'processing'].includes(record.status || '')
    if (['purchase-orders', 'stock-transfers'].includes(resource)) return record.status === 'draft'
    return ![
        'stock-audits',
        'order-returns',
        'stock-adjustments',
        'stock-balances',
        'stock-movements',
        'trace-balances',
    ].includes(resource)
}
export function workflowActions(resource: string, record: InventoryRecord): string[] {
    const state = record.status || ''
    const approval = record.details?.approval_status
    if (approval === 'pending') return ['approve', 'reject', 'cancel']
    if (resource === 'orders' && state === 'processing' && record.items?.some((line) => line.shipped_quantity > 0))
        return ['ship']
    if (resource === 'orders')
        return (
            (
                {
                    pending: ['process', 'cancel', 'submit'],
                    processing: ['ship', 'cancel', 'submit'],
                    shipped: ['deliver', 'revert-shipment'],
                    delivered: [],
                    cancelled: [],
                } as Record<string, string[]>
            )[state] || []
        )
    if (resource === 'purchase-orders')
        return (
            (
                {
                    draft: ['send', 'cancel', 'submit'],
                    sent: ['receive', 'cancel'],
                    partial: ['receive', 'cancel'],
                } as Record<string, string[]>
            )[state] || []
        )
    if (resource === 'stock-transfers')
        return (
            (
                {
                    draft: ['prepare', 'ship', 'complete', 'cancel', 'submit'],
                    prepared: ['ship', 'complete', 'cancel'],
                    shipped: ['complete'],
                } as Record<string, string[]>
            )[state] || []
        )
    if (resource === 'stock-audits') return state === 'draft' ? ['complete', 'cancel'] : []
    if (resource === 'order-returns')
        return (
            ({ requested: ['approve', 'cancel'], approved: ['receive', 'cancel'] } as Record<string, string[]>)[
                state
            ] || []
        )
    return []
}

export function linePayload(resource: string, lines: any[]): any[] {
    if (!lines.length) throw new Error('Add at least one line item.')
    return lines.map((line) => {
        if (!Number.isInteger(Number(line.product_id)) || Number(line.product_id) < 1)
            throw new Error('Choose a product on every line.')
        if (!Number.isInteger(Number(line.quantity)) || Number(line.quantity) < (resource === 'stock-audits' ? 0 : 1))
            throw new Error('Enter a valid whole-number quantity on every line.')
        const result: Record<string, any> = { product_id: Number(line.product_id), quantity: Number(line.quantity) }
        if (line.product_variant_id) result.product_variant_id = Number(line.product_variant_id)
        for (const key of ['batch', 'expires_at']) if (line[key]) result[key] = line[key]
        const serials = String(line.serialText ?? '')
            .split(/\r?\n/)
            .map((value) => value.trim())
            .filter(Boolean)
        if (serials.length) result.serials = serials
        if (['orders', 'purchase-orders'].includes(resource)) {
            const price = String(line.unit_price ?? '')
            if (!/^\d+(?:\.\d{1,2})?$/.test(price))
                throw new Error('Enter unit prices with at most two decimal places.')
            result[resource === 'orders' ? 'unit_price' : 'unit_cost'] = price
        }
        if (resource === 'orders')
            for (const key of ['tax', 'discount_type', 'discount_value'])
                if (line[key] !== '' && line[key] != null) result[key] = String(line[key])
        if (resource === 'purchase-orders' && line.supplier_sku) result.supplier_sku = line.supplier_sku
        return result
    })
}

export function referenceResource(resource: string, key: string): string | undefined {
    return (
        {
            product_id: 'products',
            product_variant_id: 'variants',
            customer_id: 'customers',
            supplier_id: 'suppliers',
            warehouse_id: 'warehouses',
            category_id: 'categories',
            location_id: 'locations',
            from_location_id: 'locations',
            to_location_id: 'locations',
            order_id: 'orders',
            parent_id: resource === 'variants' ? 'products' : resource,
        } as Record<string, string>
    )[key]
}
