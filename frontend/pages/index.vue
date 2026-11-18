<script setup lang="ts">
// SPDX-License-Identifier: AGPL-3.0-only; adapted from Inventoros Dashboard.vue.
import { errorMessage, display } from '~/utils/inventory'
import {
    dashboardWidgets,
    money,
    reorderLink,
    type Dashboard,
    type DashboardProduct,
    type DashboardWidget,
} from '~/shared/dashboard'
import InventoryBadge from '~/components/inventory/InventoryBadge.vue'
import StockPosition from '~/components/dashboard/StockPosition.vue'
import StockReview from '~/components/dashboard/StockReview.vue'
const api = useInventoryApi()
const {
    data: response,
    error,
    status,
    refresh,
} = await useAsyncData('inventory-dashboard', () => api.request<{ data: Dashboard }>('dashboard'))
const data = computed(() => response.value?.data)
const preferences = useCookie<Partial<Record<DashboardWidget, boolean>>>('brickventory-dashboard-widgets', {
    default: () => ({}),
    sameSite: 'lax',
    maxAge: 31536000,
})
const draft = ref<Partial<Record<DashboardWidget, boolean>>>({})
const visible = (key: DashboardWidget) => preferences.value?.[key] !== false
const customize = ref<HTMLDialogElement>(),
    review = ref<DashboardProduct>()
function openCustomize() {
    draft.value = Object.fromEntries(Object.keys(dashboardWidgets).map((key) => [key, visible(key as DashboardWidget)]))
    customize.value?.showModal()
}
function savePreferences() {
    preferences.value = { ...draft.value }
    customize.value?.close()
}
const cards = computed(() =>
    data.value
        ? [
              {
                  key: 'products',
                  title: 'Total products',
                  value: data.value.stats.totalProducts.toLocaleString(),
                  context: `${data.value.stats.categories} categories · ${data.value.stats.locations} locations`,
                  to: '/products',
              },
              {
                  key: 'value',
                  title: 'Inventory value',
                  value: money(data.value.stats.totalValue, data.value.currency),
                  context: `At selling price · ${data.value.currency}`,
                  to: '/stock-balances',
              },
              {
                  key: 'pending',
                  title: 'Pending orders',
                  value: data.value.stats.pendingOrders.toLocaleString(),
                  context: `${data.value.stats.totalOrders} orders · all time`,
                  to: '/orders?status=pending',
              },
              {
                  key: 'revenue',
                  title: 'Revenue this month',
                  value: money(data.value.stats.revenueThisMonth, data.value.currency),
                  context: 'Month to date · excludes cancelled orders',
                  to: '/orders',
              },
          ]
        : [],
)
const categories = computed(() =>
    [...(data.value?.stockByCategory || [])].sort((a, b) => Number(b.value) - Number(a.value)),
)
const movementScale = computed(() =>
    Math.max(1, ...(data.value?.stockMovements || []).flatMap((day) => [day.inbound, day.outbound])),
)
function otherCurrencies(key: string) {
    return (data.value?.stats.byCurrency[key] || [])
        .filter((row) => row.currency !== data.value?.currency && Number(row.amount) !== 0)
        .map((row) => money(row.amount, row.currency))
        .join(' + ')
}
function categoryShare(value: string) {
    const total = Number(data.value?.stats.totalValue)
    return total > 0 ? Math.min(100, Math.max(0, (Number(value) / total) * 100)) : 0
}
useHead({ title: 'Dashboard · Brickventory' })
</script>
<template>
    <div class="inventory-dashboard">
        <header class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p>Your inventory, sales and purchasing at a glance.</p>
            </div>
            <div class="actions">
                <button :disabled="status === 'pending'" @click="refresh()">
                    {{ status === 'pending' ? 'Refreshing…' : 'Refresh' }}</button><button @click="openCustomize">
                    Customize</button
                ><NuxtLink class="button primary" to="/orders/new">+ New order</NuxtLink>
            </div>
        </header>
        <p v-if="error" class="error" role="alert">
            {{ errorMessage(error) }} <button @click="refresh()">Retry</button>
        </p>
        <p v-if="!data && status === 'pending'" role="status">Loading dashboard…</p>
        <template v-if="data"
            ><p class="dashboard-freshness">
                Updated
                {{
                    new Date(data.generatedAt).toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        timeZone: 'UTC',
                    })
                }}
                ·
                {{ data.currency }}
            </p>
            <section
                v-if="visible('stats_overview')"
                class="stat-grid dashboard-headlines"
                aria-label="Dashboard headline metrics"
            >
                <NuxtLink
                    v-for="card in cards"
                    :key="card.key"
                    :to="card.to"
                    class="panel stat-card"
                    :data-tone="card.key"
                    :data-testid="`dashboard-metric-${card.key}`"
                    ><span class="metric-symbol" aria-hidden="true"
                        ><svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <template v-if="card.key === 'products'">
                                <path d="m12 3 9 5-9 5-9-5 9-5Z M3 8v9l9 5 9-5V8 M12 13v9 M7.5 5.5l9 5" />
                            </template>
                            <template v-else-if="card.key === 'value'">
                                <rect x="3" y="5" width="18" height="15" rx="3" />
                                <path d="M3 9h18 M16 14h5 M7 5V3" />
                            </template>
                            <template v-else-if="card.key === 'pending'">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            </template>
                            <template v-else><path d="m3 17 6-6 4 4 8-10 M15 5h6v6" /></template></svg></span
                    ><small>{{ card.title }}</small
                    ><strong>{{ card.value }}</strong
                    ><span class="muted">{{ card.context }}</span
                    ><small v-if="card.key === 'revenue' && otherCurrencies('revenueThisMonth')"
                        >Also {{ otherCurrencies('revenueThisMonth') }}</small
                    ></NuxtLink
                >
            </section>
            <div v-if="visible('stock_movements') || visible('stock_by_category')" class="dashboard-grid dashboard-insights">
                <section v-if="visible('stock_movements')" class="panel dashboard-movements">
                    <header class="section-header">
                        <h2>Stock movements</h2>
                        <NuxtLink to="/stock-movements">View ledger →</NuxtLink>
                    </header>
                    <p>Last 7 days · units in and out</p>
                    <div
                        class="dashboard-movement-chart"
                        role="img"
                        aria-label="Inbound and outbound stock units for the last seven days"
                    >
                        <div
                            v-for="day in data.stockMovements"
                            :key="day.date"
                            class="dashboard-day"
                            :aria-label="`${day.date}: ${day.inbound} inbound, ${day.outbound} outbound`"
                        >
                            <div class="dashboard-day-bars">
                                <span
                                    class="dashboard-bar-in"
                                    :style="{ height: `${(day.inbound / movementScale) * 100}%` }"
                                    :title="`${day.inbound} inbound`"
                                /><span
                                    class="dashboard-bar-out"
                                    :style="{ height: `${(day.outbound / movementScale) * 100}%` }"
                                    :title="`${day.outbound} outbound`"
                                />
                            </div>
                            <small>{{ day.date.slice(5) }}</small>
                        </div>
                    </div>
                    <div class="dashboard-chart-legend"><span>● Inbound</span><span>● Outbound</span></div>
                    <details>
                        <summary>View movement data</summary>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Inbound</th>
                                    <th>Outbound</th>
                                    <th>Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="day in data.stockMovements" :key="day.date">
                                    <td>{{ day.date }}</td>
                                    <td>{{ day.inbound }}</td>
                                    <td>{{ day.outbound }}</td>
                                    <td>{{ day.total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </details>
                </section>
                <section v-if="visible('stock_by_category')" class="panel">
                    <header class="section-header">
                        <h2>Stock by category</h2>
                        <span class="muted">At selling price · {{ data.currency }}</span>
                    </header>
                    <div
                        v-for="category in categories"
                        :key="category.id ?? 'uncategorized'"
                        class="dashboard-category"
                    >
                        <div>
                            <NuxtLink :to="category.id ? `/products?category_id=${category.id}` : '/products'">{{
                                category.name
                            }}</NuxtLink><strong>{{ money(category.value, data.currency) }}</strong>
                        </div>
                        <div
                            class="dashboard-category-track"
                            role="img"
                            :aria-label="`${categoryShare(category.value).toFixed(1)} percent of inventory value`"
                        >
                            <span :style="{ width: `${categoryShare(category.value)}%` }" />
                        </div>
                        <small>{{ category.count }} products · {{ categoryShare(category.value).toFixed(1) }}%</small>
                    </div>
                    <p v-if="!categories.length" class="dashboard-empty">
                        Category values will appear when you add products.
                    </p>
                </section>
            </div>
            <section
                v-if="visible('low_stock_alerts')"
                class="panel dashboard-replenishment"
                aria-labelledby="replenishment-title"
            >
                <header class="section-header">
                    <div class="actions">
                        <h2 id="replenishment-title">Replenishment</h2>
                        <span v-if="data.stats.lowStockProducts" class="badge pending"
                            >{{ data.stats.lowStockProducts }} low stock</span
                        >
                    </div>
                    <NuxtLink to="/products?low_stock=true">View inventory →</NuxtLink>
                </header>
                <div v-if="!data.lowStockProducts.length" class="empty-state">
                    <h3>
                        {{
                            data.stats.totalProducts === 0
                                ? 'No products yet'
                                : 'All products are above their minimum stock level'
                        }}
                    </h3>
                    <NuxtLink v-if="data.stats.totalProducts === 0" class="button" to="/products/new"
                        >Add your first product</NuxtLink
                    >
                </div>
                <div v-else class="dashboard-stock-list">
                    <article v-for="product in data.lowStockProducts" :key="product.id" class="dashboard-stock-card">
                        <p class="eyebrow">{{ product.sku }}</p>
                        <NuxtLink :to="`/products/${product.id}`" class="dashboard-product-name">{{
                            product.name
                        }}</NuxtLink>
                        <p>{{ product.category || 'Uncategorized' }}</p>
                        <StockPosition :product="product" /><button
                            :aria-label="`Review stock: ${product.name}`"
                            @click="review = data!.reorderSuggestions.find((row) => row.id === product.id) || product"
                        >
                            Review stock →
                        </button>
                    </article>
                </div>
            </section>
            <section
                v-if="visible('revenue_chart')"
                class="dashboard-finances"
                aria-label="Financial and operational summary"
            >
                <article
                    v-for="metric in [
                        {
                            label: 'Outstanding receivables',
                            key: 'outstandingReceivables',
                            context: 'Unpaid balances on active orders',
                        },
                        {
                            label: 'Dead stock at cost',
                            key: 'deadStockValue',
                            context: `${data.stats.deadStockCount} products idle for 90 days`,
                        },
                    ]"
                    :key="metric.key"
                    class="panel detail-section"
                >
                    <h2>{{ metric.label }}</h2>
                    <strong>{{
                        money(data.stats[metric.key as 'outstandingReceivables' | 'deadStockValue'], data.currency)
                    }}</strong>
                    <p>{{ metric.context }}</p>
                    <small v-if="otherCurrencies(metric.key)">Also {{ otherCurrencies(metric.key) }}</small>
                </article>
                <NuxtLink class="panel detail-section" to="/purchase-orders"
                    ><h2>Open purchase orders</h2>
                    <strong>{{ data.stats.pendingPurchaseOrders }}</strong>
                    <p>Draft, sent or partially received</p></NuxtLink
                ><NuxtLink class="panel detail-section" to="/stock-transfers?status=shipped"
                    ><h2>Transfers in transit</h2>
                    <strong>{{ data.stats.inTransitTransfers }}</strong>
                    <p>Shipped, awaiting completion</p></NuxtLink
                >
            </section>
            <div class="dashboard-grid">
                <section v-if="visible('recent_orders')" class="panel">
                    <header class="section-header">
                        <h2>Recent orders</h2>
                        <NuxtLink to="/orders">All orders →</NuxtLink>
                    </header>
                    <div v-if="data.recentOrders.length" class="table-viewport">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order / customer</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Order date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in data.recentOrders" :key="order.id">
                                    <td>
                                        <NuxtLink :to="`/orders/${order.id}`">{{ order.number }}</NuxtLink><small
                                            class="dashboard-subline"
                                            >{{ order.customer }}</small
                                        >
                                    </td>
                                    <td><InventoryBadge :value="order.status" /></td>
                                    <td>{{ money(order.total, order.currency) }}</td>
                                    <td>{{ display(order.order_date, 'order_date') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="dashboard-empty">
                        No orders yet. <NuxtLink to="/orders/new">Create your first order</NuxtLink>
                    </p>
                    <header class="section-header dashboard-section-divider">
                        <h3>Recent purchase orders</h3>
                        <NuxtLink to="/purchase-orders">View all →</NuxtLink>
                    </header>
                    <div v-for="order in data.recentPurchaseOrders" :key="order.id" class="history-row">
                        <NuxtLink :to="`/purchase-orders/${order.id}`">{{ order.number }}</NuxtLink><span>{{
                            order.supplier
                        }}</span
                        ><InventoryBadge :value="order.status" /><span>{{ money(order.total, order.currency) }}</span>
                    </div>
                    <p v-if="!data.recentPurchaseOrders.length" class="dashboard-empty">No purchase orders yet.</p>
                </section>
                <section v-if="visible('reorder_suggestions')" class="panel">
                    <header class="section-header">
                        <h2>Reorder suggestions</h2>
                        <NuxtLink to="/replenishment">Review replenishment →</NuxtLink>
                    </header>
                    <article v-for="product in data.reorderSuggestions" :key="product.id" class="dashboard-reorder">
                        <div>
                            <NuxtLink :to="`/products/${product.id}`">{{ product.name }}</NuxtLink><small
                                class="dashboard-subline"
                                >{{ product.sku }} · {{ product.supplier || 'Choose a supplier' }}</small
                            ><small
                                >{{ product.suggested_quantity }} units suggested{{
                                    product.has_variants ? ' · select a variant' : ''
                                }}</small
                            >
                        </div>
                        <NuxtLink class="button" :to="reorderLink(product)">Prepare order</NuxtLink>
                    </article>
                    <p v-if="!data.reorderSuggestions.length" class="dashboard-empty">
                        Available stock and incoming purchases cover reorder points.
                    </p>
                </section>
                <section v-if="visible('recent_products')" class="panel">
                    <header class="section-header">
                        <h2>Recent products</h2>
                        <NuxtLink to="/products">All products →</NuxtLink>
                    </header>
                    <div v-for="product in data.recentProducts" :key="product.id" class="history-row">
                        <NuxtLink :to="`/products/${product.id}`">{{ product.name }}</NuxtLink><small>{{
                            product.sku
                        }}</small
                        ><span>{{ product.stock }} on hand</span>
                    </div>
                    <p v-if="!data.recentProducts.length" class="dashboard-empty">
                        Your recent products will appear here.
                    </p>
                </section>
                <section v-if="visible('top_products')" class="panel">
                    <header class="section-header">
                        <h2>Top products by value</h2>
                        <span class="muted">At selling price</span>
                    </header>
                    <div v-for="product in data.topProducts" :key="product.id" class="history-row">
                        <NuxtLink :to="`/products/${product.id}`">{{ product.name }}</NuxtLink><span
                            >{{ product.stock }} units</span
                        ><strong>{{ money(product.total_value, data.currency) }}</strong>
                    </div>
                    <p v-if="!data.topProducts.length" class="dashboard-empty">
                        Receive stock to see your highest-value products.
                    </p>
                </section>
                <section v-if="visible('recent_activity')" class="panel">
                    <header class="section-header">
                        <h2>Recent activity</h2>
                        <NuxtLink to="/stock-movements">View movements →</NuxtLink>
                    </header>
                    <ol class="dashboard-activity">
                        <li v-for="entry in data.recentActivity" :key="entry.id">
                            <NuxtLink :to="`/stock-movements/${entry.id}`"
                                >{{ display(entry.type) }} · {{ entry.quantity > 0 ? '+' : '' }}{{ entry.quantity }}
                                {{ entry.product }}</NuxtLink
                            ><small>{{ entry.location }} · {{ display(entry.created_at, 'created_at') }}</small>
                        </li>
                    </ol>
                    <p v-if="!data.recentActivity.length" class="dashboard-empty">Stock activity will appear here.</p>
                </section>
            </div>
            <footer class="dashboard-quick-actions" aria-label="Quick actions">
                <NuxtLink class="button" to="/products/new">+ Add product</NuxtLink
                ><NuxtLink class="button" to="/stock-adjustments/new">Adjust stock</NuxtLink
                ><NuxtLink class="button" to="/stock-transfers/new">Transfer stock</NuxtLink
                ><NuxtLink class="button" to="/inventory-import">Import / export</NuxtLink>
            </footer></template
        >
        <dialog ref="customize" class="action-dialog" aria-labelledby="customize-title">
            <form @submit.prevent="savePreferences">
                <h2 id="customize-title">Customize dashboard</h2>
                <p>Choose the widgets shown in this browser.</p>
                <div class="dashboard-widget-options">
                    <label v-for="(name, key) in dashboardWidgets" :key="key"
                        ><input v-model="draft[key]" type="checkbox" />{{ name }}</label
                    >
                </div>
                <footer class="form-footer">
                    <button type="button" @click="customize?.close()">Cancel</button
                    ><button class="primary">Save dashboard</button>
                </footer>
            </form>
        </dialog>
        <StockReview v-if="review" :product="review" @close="review = undefined" />
    </div>
</template>
