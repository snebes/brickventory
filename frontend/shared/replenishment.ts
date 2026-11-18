export interface SupplierLink {
    id: number
    product_id: number
    product_name: string
    product_sku: string
    parent_id: number | null
    supplier_id: number
    supplier_name: string
    supplier_active: boolean
    supplier_sku: string
    cost_price: string
    currency: string
    minimum_order_quantity: number
    lead_time_days: number
    is_primary: boolean
}
export interface ReplenishmentRow {
    id: number
    product_id: number
    product_variant_id: number | null
    parent_id: number | null
    name: string
    sku: string
    stock: number
    min_stock: number
    reorder_point: number
    max_stock: number
    reserved_quantity: number
    available_quantity: number
    on_order_quantity: number
    projected_quantity: number
    suggested_quantity: number
    supplier_id: number | null
    supplier: string | null
    supplier_sku: string
    unit_cost: string
    currency: string | null
    lead_time_days: number
    has_variants: boolean
    can_create_draft: boolean
    blocked_reason: string | null
    fingerprint: string
}
export function draftRequest(rows: ReplenishmentRow[], requestId: string) {
    if (!rows.length || rows.length > 100 || rows.some((row) => !row.can_create_draft))
        throw new Error('Select up to 100 eligible products.')
    return {
        request_id: requestId,
        product_ids: rows.map((row) => row.id),
        expected: Object.fromEntries(rows.map((row) => [row.id, row.fingerprint])),
    }
}
