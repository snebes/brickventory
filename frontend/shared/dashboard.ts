import type { InventoryRecord } from './inventory'
export interface CurrencyAmount {
    currency: string
    amount: string
}
export interface DashboardProduct extends InventoryRecord {
    name: string
    sku: string
    stock: number
    min_stock: number
    total_value: string
    cost_value: string
    category: string | null
    has_variants: boolean
    on_order_quantity?: number
    reserved_quantity?: number
    can_create_draft?: boolean
    blocked_reason?: string | null
    product_id?: number
    product_variant_id?: number | null
    parent_id?: number | null
    suggested_quantity?: number
    supplier_id?: number | null
    supplier?: string | null
    unit_cost?: string
    currency?: string
}
export interface DashboardDocument {
    id: number
    number: string
    status: string
    total: string
    currency: string
    created_at: string
    order_date: string
    customer?: string
    supplier?: string
}
export interface Dashboard {
    generatedAt: string
    currency: string
    stats: {
        totalProducts: number
        lowStockProducts: number
        categories: number
        locations: number
        totalOrders: number
        pendingOrders: number
        pendingPurchaseOrders: number
        inTransitTransfers: number
        deadStockCount: number
        totalValue: string
        revenueThisMonth: string
        outstandingReceivables: string
        deadStockValue: string
        byCurrency: Record<string, CurrencyAmount[]>
    }
    recentProducts: DashboardProduct[]
    lowStockProducts: DashboardProduct[]
    topProducts: DashboardProduct[]
    reorderSuggestions: DashboardProduct[]
    recentOrders: DashboardDocument[]
    recentPurchaseOrders: DashboardDocument[]
    stockByCategory: { id: number | null; name: string; count: number; value: string }[]
    stockMovements: { date: string; inbound: number; outbound: number; total: number }[]
    recentActivity: {
        id: number
        type: string
        quantity: number
        reference: string
        created_at: string
        product: string
        sku: string
        location: string
    }[]
}
export const dashboardWidgets = {
    stats_overview: 'Headline metrics',
    revenue_chart: 'Financial summary',
    low_stock_alerts: 'Replenishment',
    reorder_suggestions: 'Reorder suggestions',
    recent_orders: 'Recent orders',
    recent_products: 'Recent products',
    stock_by_category: 'Stock by category',
    stock_movements: 'Stock movements',
    top_products: 'Top products',
    recent_activity: 'Recent activity',
}
export type DashboardWidget = keyof typeof dashboardWidgets
export function stockLevel(product: Pick<DashboardProduct, 'stock' | 'min_stock'>) {
    const minimum = Math.max(0, product.min_stock)
    return {
        stock: product.stock,
        minimum,
        shortfall: Math.max(0, minimum - product.stock),
        percent: minimum > 0 ? Math.min(100, Math.max(0, (product.stock / minimum) * 100)) : 0,
    }
}
export function money(value: string, currency: string) {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(Number(value))
    } catch {
        return `${value} ${currency}`
    }
}
export function reorderLink(product: DashboardProduct): string {
    const query = new URLSearchParams({
        product_id: String(product.product_id || product.id),
        quantity: String(
            Math.min(1000000000, product.suggested_quantity || Math.max(1, product.min_stock - product.stock)),
        ),
    })
    if (product.product_variant_id) query.set('product_variant_id', String(product.product_variant_id))
    if (product.has_variants) query.set('variant_required', '1')
    if (product.supplier_id) query.set('supplier_id', String(product.supplier_id))
    if (product.currency) query.set('currency', product.currency)
    if (product.unit_cost) query.set('unit_cost', product.unit_cost)
    return `/purchase-orders/new?${query}`
}
