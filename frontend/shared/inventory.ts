// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros scoped page contracts. See docs/upstream/Inventoros-NOTICE.
export interface InventoryRecord {
    id: number
    name?: string
    sku?: string
    number?: string
    status?: string
    details?: Record<string, any>
    items?: InventoryLine[]
    [key: string]: any
}
export interface InventoryLine {
    id?: number
    product_id: number | ''
    product_variant_id?: number | ''
    quantity: number
    unit_price?: string
    unit_cost?: string
    tax?: string
    discount_type?: string
    discount_value?: string
    batch?: string
    serials?: string[]
    expires_at?: string
    [key: string]: any
}
export interface PageResult {
    data: InventoryRecord[]
    meta: { total: number; current_page: number; per_page: number }
}
export interface Field {
    key: string
    label: string
    type?: string
    required?: boolean
    resource?: string
    options?: string[]
    detail?: boolean
}
export interface Resource {
    title: string
    singular: string
    description: string
    columns: string[]
    fields: Field[]
    document?: boolean
    readonly?: boolean
    statuses?: string[]
}
const field = (key: string, label: string, type = 'text', extra: Partial<Field> = {}): Field => ({
    key,
    label,
    type,
    ...extra,
})
const name = field('name', 'Name', 'text', { required: true })
const notes = field('notes', 'Notes', 'textarea', { detail: true })
const active = field('is_active', 'Active', 'checkbox')
const contact = [
    name,
    field('code', 'Code'),
    field('email', 'Email', 'email'),
    field('phone', 'Phone', 'text', { detail: true }),
    field('company_name', 'Company', 'text', { detail: true }),
    field('contact_name', 'Contact person', 'text', { detail: true }),
    field('currency', 'Currency', 'text', { detail: true }),
    field('payment_terms', 'Payment terms', 'text', { detail: true }),
    notes,
    active,
]
const pricing = [
    field('currency', 'Currency'),
    field('order_date', 'Order date', 'date'),
    field('tax', 'Tax', 'money'),
    field('shipping', 'Shipping', 'money'),
    field('approval_required', 'Require approval', 'checkbox'),
    field('notes', 'Notes', 'textarea'),
]
export const inventoryResources: Record<string, Resource> = {
    'cycle-counts': {
        title: 'Cycle counts',
        singular: 'Cycle count schedule',
        description: 'Recurring physical counts',
        columns: [],
        fields: [],
        readonly: true,
    },
    shipments: {
        title: 'Fulfillment',
        singular: 'Shipment',
        description: 'Shipment management',
        columns: [],
        fields: [],
        readonly: true,
    },
    replenishment: {
        title: 'Replenishment',
        singular: 'Replenishment suggestion',
        description: 'Review projected stock and prepare supplier purchase orders.',
        columns: [],
        fields: [],
        readonly: true,
    },
    products: {
        title: 'Inventory',
        singular: 'Product',
        description: 'Manage products, variants and stock across your locations.',
        columns: ['sku', 'name', 'stock', 'price', 'is_active'],
        fields: [
            name,
            field('sku', 'SKU', 'text', { required: true }),
            field('barcode', 'Barcode'),
            field('category_id', 'Category', 'select', { resource: 'categories' }),
            field('price', 'Selling price', 'money'),
            field('cost', 'Cost', 'money'),
            field('min_stock', 'Minimum stock', 'number'),
            field('reorder_point', 'Reorder point', 'number', { detail: true }),
            field('max_stock', 'Target stock', 'number', { detail: true }),
            field('reorder_quantity', 'Fixed reorder quantity (0 uses shortfall)', 'number', { detail: true }),
            field('tracking', 'Stock tracking', 'select', { detail: true, options: ['none', 'batch', 'serial'] }),
            field('unit', 'Unit', 'text', { detail: true }),
            field('description', 'Description', 'textarea'),
            field('options', 'Product options', 'options', { detail: true }),
            active,
        ],
    },
    variants: {
        title: 'Variants',
        singular: 'Variant',
        description: 'Manage independently stocked product options.',
        columns: ['sku', 'name', 'parent_id', 'stock', 'price'],
        fields: [
            field('parent_id', 'Parent product', 'select', { required: true, resource: 'products' }),
            name,
            field('sku', 'SKU', 'text', { required: true }),
            field('barcode', 'Barcode'),
            field('attributes', 'Variant options', 'attributes', { detail: true, required: true }),
            field('min_stock', 'Minimum stock', 'number'),
            field('reorder_point', 'Reorder point', 'number', { detail: true }),
            field('max_stock', 'Target stock', 'number', { detail: true }),
            field('reorder_quantity', 'Fixed reorder quantity (0 uses shortfall)', 'number', { detail: true }),
            field('price', 'Price', 'money'),
            field('cost', 'Cost', 'money'),
            active,
        ],
    },
    customers: {
        title: 'Customers',
        singular: 'Customer',
        description: 'Keep customer details and order history together.',
        columns: ['name', 'code', 'email', 'is_active'],
        fields: [
            ...contact,
            ...['billing', 'shipping'].flatMap((prefix) =>
                ['address', 'city', 'state', 'zip_code', 'country'].map((part) =>
                    field(`${prefix}_${part}`, `${prefix} ${part.replaceAll('_', ' ')}`, 'text', { detail: true }),
                ),
            ),
            field('tax_id', 'Tax ID', 'text', { detail: true }),
            field('credit_limit', 'Credit limit', 'money', { detail: true }),
        ],
    },
    suppliers: {
        title: 'Suppliers',
        singular: 'Supplier',
        description: 'Manage supplier contacts, terms and purchase history.',
        columns: ['name', 'code', 'email', 'is_active'],
        fields: [
            ...contact.filter((f) => f.key !== 'company_name'),
            ...['address', 'city', 'state', 'zip_code', 'country', 'website'].map((key) =>
                field(key, key.replaceAll('_', ' '), 'text', { detail: true }),
            ),
            field('rating', 'Rating (1–5)', 'number', { detail: true }),
        ],
    },
    orders: {
        title: 'Orders',
        singular: 'Order',
        description: 'From stock allocation to shipment, payment and returns.',
        document: true,
        columns: ['number', 'status', 'customer_id', 'total', 'payment_status', 'created_at'],
        statuses: ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
        fields: [
            field('customer_id', 'Customer', 'select', { resource: 'customers' }),
            field('customer_name', 'Customer name'),
            field('customer_email', 'Customer email', 'email'),
            field('customer_address', 'Customer address', 'textarea'),
            field('location_id', 'Ship from', 'select', { resource: 'locations', required: true }),
            ...pricing,
            field('discount_type', 'Order discount', 'select', { options: ['fixed', 'percent'] }),
            field('discount_value', 'Discount value', 'money'),
        ],
    },
    'purchase-orders': {
        title: 'Purchase orders',
        singular: 'Purchase order',
        description: 'Order from suppliers and receive stock into the right bin.',
        document: true,
        columns: ['number', 'status', 'supplier_id', 'total', 'created_at'],
        statuses: ['draft', 'sent', 'partial', 'received', 'cancelled'],
        fields: [
            field('supplier_id', 'Supplier', 'select', { required: true, resource: 'suppliers' }),
            ...pricing,
            field('expected_date', 'Expected date', 'date'),
        ],
    },
    'stock-transfers': {
        title: 'Stock transfers',
        singular: 'Stock transfer',
        description: 'Reserve and move stock between locations.',
        document: true,
        columns: ['number', 'status', 'total', 'created_at'],
        statuses: ['draft', 'prepared', 'shipped', 'completed', 'cancelled'],
        fields: [
            field('from_location_id', 'From location', 'select', { required: true, resource: 'locations' }),
            field('to_location_id', 'To location', 'select', { required: true, resource: 'locations' }),
            field('approval_required', 'Require approval', 'checkbox'),
            field('notes', 'Notes', 'textarea'),
        ],
    },
    locations: {
        title: 'Locations',
        singular: 'Location',
        description: 'Organize warehouses down to individual bins.',
        columns: ['code', 'name', 'warehouse_id', 'parent_id'],
        fields: [
            name,
            field('code', 'Code', 'text', { required: true }),
            field('warehouse_id', 'Warehouse', 'select', { resource: 'warehouses' }),
            field('parent_id', 'Parent location', 'select', { resource: 'locations' }),
            ...['aisle', 'shelf', 'bin'].map((key) => field(key, key, 'text', { detail: true })),
        ],
    },
    warehouses: {
        title: 'Warehouses',
        singular: 'Warehouse',
        description: 'Manage storage sites and their contact details.',
        columns: ['name', 'created_at'],
        fields: [
            name,
            ...['address', 'city', 'state', 'zip_code', 'country', 'phone', 'email'].map((key) =>
                field(key, key.replaceAll('_', ' '), 'text', { detail: true }),
            ),
            notes,
        ],
    },
    categories: {
        title: 'Categories',
        singular: 'Category',
        description: 'Group your inventory into a useful hierarchy.',
        columns: ['name', 'parent_id'],
        fields: [
            name,
            field('parent_id', 'Parent category', 'select', { resource: 'categories' }),
            field('description', 'Description', 'textarea', { detail: true }),
        ],
    },
    'stock-adjustments': {
        title: 'Stock adjustments',
        singular: 'Stock adjustment',
        description: 'Record opening stock, damage and manual corrections.',
        columns: ['product_id', 'location_id', 'quantity', 'type', 'created_at'],
        fields: [
            field('product_id', 'Product', 'select', { required: true, resource: 'products' }),
            field('product_variant_id', 'Variant', 'select', { resource: 'variants' }),
            field('location_id', 'Location', 'select', { required: true, resource: 'locations' }),
            field('quantity', 'Change in quantity', 'integer', { required: true }),
            field('type', 'Reason type', 'select', { options: ['manual', 'count', 'damage', 'return'] }),
            field('reason', 'Reason', 'textarea'),
            field('batch', 'Batch'),
            field('serials', 'Serials (one per line)', 'serials'),
            field('expires_at', 'Expiry', 'date'),
        ],
    },
    'stock-audits': {
        title: 'Stock counts',
        singular: 'Stock count',
        description: 'Compare physical counts with stock records and reconcile differences.',
        document: true,
        columns: ['number', 'status', 'created_at'],
        statuses: ['draft', 'completed', 'cancelled'],
        fields: [
            field('location_id', 'Location', 'select', { required: true, resource: 'locations' }),
            field('notes', 'Notes', 'textarea'),
        ],
    },
    'order-returns': {
        title: 'Returns',
        singular: 'Return request',
        description: 'Approve and receive customer returns.',
        document: true,
        columns: ['number', 'status', 'total', 'created_at'],
        statuses: ['requested', 'approved', 'received', 'cancelled'],
        fields: [
            field('order_id', 'Order', 'select', { required: true, resource: 'orders' }),
            field('reason', 'Reason', 'textarea'),
            field('notes', 'Notes', 'textarea'),
        ],
    },
    'stock-balances': {
        title: 'Stock balances',
        singular: 'Stock balance',
        description: 'See on-hand, reserved and available quantities by location.',
        readonly: true,
        columns: ['product_id', 'location_id', 'quantity', 'reserved_quantity', 'available_quantity'],
        fields: [],
    },
    'stock-movements': {
        title: 'Stock movements',
        singular: 'Stock movement',
        description: 'An immutable history of every stock change.',
        readonly: true,
        columns: [
            'product_id',
            'location_id',
            'quantity',
            'quantity_before',
            'quantity_after',
            'type',
            'reference',
            'created_at',
        ],
        fields: [],
    },
    'trace-balances': {
        title: 'Batches & serials',
        singular: 'Trace balance',
        description: 'Trace individual units, batches and expiry dates.',
        readonly: true,
        columns: ['product_id', 'location_id', 'batch', 'serial', 'quantity', 'expires_at'],
        fields: [],
    },
}
export const navigation = [
    'products',
    'orders',
    'shipments',
    'customers',
    'purchase-orders',
    'suppliers',
    'stock-transfers',
    'replenishment',
    'stock-balances',
    'stock-movements',
    'stock-adjustments',
    'stock-audits',
    'cycle-counts',
    'order-returns',
    'variants',
    'categories',
    'warehouses',
    'locations',
    'trace-balances',
]
