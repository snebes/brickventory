<script setup lang="ts">
import { inventoryResources } from '~/shared/inventory'
import { display, label, errorMessage, referenceResource } from '~/utils/inventory'
import InventoryBadge from './InventoryBadge.vue'
import InventoryReference from './InventoryReference.vue'
const props = defineProps<{ resource: string }>()
const selected = ref<number[]>([])
const warehouse = useCookie<string>('inventory-warehouse', { sameSite: 'lax' })
const warehouseScoped = computed(() =>
    ['products', 'variants', 'locations', 'stock-balances', 'stock-movements', 'trace-balances'].includes(
        props.resource,
    ),
)
watch(warehouse, () => {
    if (warehouseScoped.value) apply()
})
const config = inventoryResources[props.resource]!,
    api = useInventoryApi()
const route = useRoute()
const page = ref(Math.max(1, Number(route.query.page) || 1)),
    search = ref(String(route.query.search || '')),
    status = ref(String(route.query.status || '')),
    active = ref(String(route.query.is_active || '')),
    lowStock = ref(route.query.low_stock === 'true')
const sort = ref(String(route.query.sort_by || 'id')),
    direction = ref(String(route.query.sort_dir || 'desc')),
    dateFrom = ref(String(route.query.date_from || '')),
    dateTo = ref(String(route.query.date_to || ''))
const categoryId = ref(typeof route.query.category_id === 'string' ? route.query.category_id : '')
const query = ref<Record<string, any>>({
    warehouse_id: warehouseScoped.value && warehouse.value && warehouse.value !== 'all' ? warehouse.value : undefined,
    category_id: props.resource === 'products' ? categoryId.value || undefined : undefined,
    page: page.value,
    per_page: 25,
    search: search.value || undefined,
    status: status.value || undefined,
    is_active: active.value || undefined,
    low_stock: lowStock.value || undefined,
    sort_by: sort.value,
    sort_dir: direction.value,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
})
const {
    data,
    error,
    status: loading,
    refresh,
} = await useAsyncData(`list-${props.resource}`, () => api.list(props.resource, query.value), { watch: [query] })
useHead({ title: `${config.title} · Brickventory` })
async function apply(nextPage = 1) {
    page.value = nextPage
    query.value = {
        warehouse_id:
            warehouseScoped.value && warehouse.value && warehouse.value !== 'all' ? warehouse.value : undefined,
        category_id: props.resource === 'products' ? categoryId.value || undefined : undefined,
        page: nextPage,
        per_page: 25,
        search: search.value || undefined,
        status: status.value || undefined,
        is_active: active.value || undefined,
        low_stock: lowStock.value || undefined,
        sort_by: sort.value,
        sort_dir: direction.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }
    await navigateTo(
        {
            path: route.path,
            query: Object.fromEntries(
                Object.entries(query.value)
                    .filter(([, v]) => v !== undefined && v !== '')
                    .map(([k, v]) => [k, String(v)]),
            ),
        },
        { replace: true },
    )
}
async function bulkSaved() {
    selected.value = []
    await refresh()
}
function clear() {
    categoryId.value = ''
    search.value = ''
    status.value = ''
    active.value = ''
    lowStock.value = false
    sort.value = 'id'
    direction.value = 'desc'
    dateFrom.value = ''
    dateTo.value = ''
    apply()
}
</script>
<template>
    <div>
        <header class="page-header">
            <div>
                <h1>
                    {{ config.title }} <span v-if="data" class="record-count">{{ data.meta.total }}</span>
                </h1>
                <p>{{ config.description }}</p>
            </div>
            <div class="actions">
                <NuxtLink v-if="resource === 'products'" class="button" to="/inventory-import">Import / export</NuxtLink
                ><NuxtLink v-if="!config.readonly" class="button primary" :to="`/${resource}/new`"
                    >+ New {{ config.singular.toLowerCase() }}</NuxtLink
                >
            </div>
        </header>
        <InventoryProductBulk v-if="resource === 'products'" :ids="selected" @saved="bulkSaved" />
        <section class="panel">
            <form class="filters" @submit.prevent="apply()">
                <div class="search">
                    <label class="sr-only" for="search">Search {{ config.title }}</label
                    ><input
                        id="search"
                        v-model="search"
                        type="search"
                        :placeholder="`Search ${config.title.toLowerCase()}…`"
                    />
                </div>
                <label v-if="config.statuses"
                    >Status<select v-model="status">
                        <option value="">All statuses</option>
                        <option v-for="value in config.statuses" :key="value" :value="value">{{ value }}</option>
                    </select></label
                ><label v-if="['products', 'variants', 'customers', 'suppliers'].includes(resource)"
                    >Visibility<select v-model="active">
                        <option value="">All records</option>
                        <option value="true">Active</option>
                        <option value="false">Inactive</option>
                    </select></label
                ><label v-if="resource === 'products'" class="inline-check"
                    ><input v-model="lowStock" type="checkbox" />Low stock</label
                ><label
                    >Sort by<select v-model="sort">
                        <option value="id">Newest record</option>
                        <option value="created_at">Created date</option>
                        <option v-if="config.fields.some((field) => field.key === 'name')" value="name">Name</option>
                        <option v-if="['products', 'variants'].includes(resource)" value="sku">SKU</option>
                    </select></label
                ><label
                    >Direction<select v-model="direction">
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select></label
                >
                <details class="date-filter">
                    <summary>Date range</summary>
                    <div class="form-grid">
                        <label>From<input v-model="dateFrom" type="date" /></label
                        ><label>To<input v-model="dateTo" type="date" :min="dateFrom || undefined" /></label>
                    </div>
                </details>
                <button type="submit">Search</button><button type="button" class="subtle" @click="clear">Clear</button>
            </form>
            <div v-if="error" class="error" role="alert">
                {{ errorMessage(error) }} <button @click="refresh()">Retry</button>
            </div>
            <p v-if="loading === 'pending'" class="loading" role="status">Loading {{ config.title.toLowerCase() }}…</p>
            <div v-else-if="data?.data.length" class="table-viewport">
                <table>
                    <thead>
                        <tr>
                            <th v-if="resource === 'products'">Select</th>
                            <th v-for="column in config.columns" :key="column" scope="col">{{ label(column) }}</th>
                            <th scope="col"><span class="sr-only">View record</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="record in data.data" :key="record.id">
                            <td v-if="resource === 'products'">
                                <input
                                    v-model="selected"
                                    type="checkbox"
                                    :value="record.id"
                                    :aria-label="`Select ${record.name}`"
                                />
                            </td>
                            <td v-for="(column, index) in config.columns" :key="column">
                                <NuxtLink v-if="index === 0" class="record-link" :to="`/${resource}/${record.id}`">{{
                                    display(record[column], column)
                                }}</NuxtLink><InventoryReference
                                    v-else-if="referenceResource(resource, column) && record[column]"
                                    :resource="referenceResource(resource, column)!"
                                    :id="record[column]"
                                /><InventoryBadge
                                    v-else-if="['status', 'payment_status', 'is_active'].includes(column)"
                                    :value="record[column]"
                                /><span v-else>{{ display(record[column], column) }}</span>
                            </td>
                            <td>
                                <NuxtLink
                                    :to="`/${resource}/${record.id}`"
                                    :aria-label="`View ${record.name || record.number || record.id}`"
                                    >View →</NuxtLink
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="!error" class="empty-state">
                <div class="empty-icon" aria-hidden="true">◈</div>
                <h2>{{ search || status ? 'No matching records' : `No ${config.title.toLowerCase()} yet` }}</h2>
                <p>
                    {{
                        search || status
                            ? 'Try a different search or clear your filters.'
                            : config.readonly
                              ? 'Stock activity will appear here as you receive or move inventory.'
                              : `Create your first ${config.singular.toLowerCase()} to get started.`
                    }}
                </p>
                <button v-if="search || status" @click="clear">Clear filters</button
                ><NuxtLink v-else-if="!config.readonly" class="button primary" :to="`/${resource}/new`"
                    >+ New {{ config.singular.toLowerCase() }}</NuxtLink
                >
            </div>
            <footer v-if="data" class="pagination">
                <span
                    >{{ data.meta.total }} records · Page {{ page }} of
                    {{ Math.max(1, Math.ceil(data.meta.total / 25)) }}</span
                >
                <div class="actions">
                    <button :disabled="page <= 1 || loading === 'pending'" @click="apply(page - 1)">← Previous</button
                    ><button :disabled="page * 25 >= data.meta.total || loading === 'pending'" @click="apply(page + 1)">
                        Next →
                    </button>
                </div>
            </footer>
        </section>
    </div>
</template>
