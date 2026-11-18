<script setup lang="ts">
import type { InventoryRecord } from '~/shared/inventory'
import { draftRequest, type ReplenishmentRow } from '~/shared/replenishment'
import { errorMessage } from '~/utils/inventory'
const search = ref(''),
    eligibility = ref(''),
    filters = ref<Record<string, string>>({})
const api = useInventoryApi(),
    page = ref(1),
    selected = ref<Record<number, ReplenishmentRow>>({}),
    error = ref(''),
    busy = ref(false)
const {
    data,
    error: loadError,
    status,
    refresh,
} = await useAsyncData(
    'replenishment-review',
    () =>
        api.request<{ data: ReplenishmentRow[]; meta: { total: number } }>('replenishment', {
            query: { page: page.value, per_page: 25, ...filters.value },
        }),
    { watch: [page, filters] },
)
const selection = computed(() => Object.values(selected.value)),
    dialog = ref<HTMLDialogElement>(),
    reviewed = ref<ReplenishmentRow[]>([]),
    requestId = ref('')
const created = ref<InventoryRecord[]>([])
function applyFilters() {
    page.value = 1
    filters.value = {
        ...(search.value ? { search: search.value } : {}),
        ...(eligibility.value ? { eligible: eligibility.value } : {}),
    }
}
function toggle(row: ReplenishmentRow, checked: boolean) {
    if (checked && row.can_create_draft && selection.value.length < 100)
        selected.value = { ...selected.value, [row.id]: row }
    else {
        const next = { ...selected.value }
        delete next[row.id]
        selected.value = next
    }
}
function review() {
    reviewed.value = selection.value.map((row) => ({ ...row }))
    requestId.value = crypto.randomUUID()
    error.value = ''
    dialog.value?.showModal()
}
async function reload() {
    if (busy.value) return
    selected.value = {}
    error.value = ''
    await refresh()
}
async function createDrafts() {
    busy.value = true
    error.value = ''
    try {
        const result = await api.request<{ data: { purchase_orders: InventoryRecord[] } }>('replenishment/drafts', {
            method: 'POST',
            body: draftRequest(reviewed.value, requestId.value),
        })
        created.value = result.data.purchase_orders
        selected.value = {}
        dialog.value?.close()
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
function productLink(row: ReplenishmentRow) {
    return `/${row.parent_id ? 'variants' : 'products'}/${row.id}`
}
onBeforeUnmount(() => dialog.value?.close())
useHead({ title: 'Replenishment · Brickventory' })
</script>
<template>
    <div>
        <header class="page-header">
            <div>
                <p class="eyebrow">Purchasing</p>
                <h1>Replenishment</h1>
                <p>Review available stock and incoming purchases, then prepare supplier purchase orders.</p>
            </div>
            <div class="actions">
                <button :disabled="busy || status === 'pending'" @click="reload">Refresh suggestions</button
                ><button class="primary" :disabled="busy || !selection.length" @click="review">
                    Review {{ selection.length || '' }} selected
                </button>
            </div>
        </header>
        <p class="muted">
            Projected stock = on hand − reserved + outstanding purchase quantities. Prices remain in each supplier's
            currency. Draft creation does not receive stock or send emails.
        </p>
        <section v-if="created.length" class="panel detail-section" role="status">
            <h2>{{ created.length }} draft purchase {{ created.length === 1 ? 'order' : 'orders' }} created</h2>
            <div v-for="order in created" :key="order.id" class="history-row">
                <NuxtLink :to="`/purchase-orders/${order.id}`">{{ order.number }}</NuxtLink><span
                    >{{ order.total }} {{ order.details?.currency }}</span
                ><span class="badge">Draft</span>
            </div>
            <p>Review each draft before sending it to the supplier.</p>
        </section>
        <section class="panel">
            <form class="filters" @submit.prevent="applyFilters">
                <label class="search" for="replenishment-search"
                    >Search products or suppliers<input
                        id="replenishment-search"
                        v-model="search"
                        type="search"
                        maxlength="255"
                        placeholder="Name, SKU or supplier…" /></label
                ><label for="replenishment-eligibility"
                    >Readiness<select id="replenishment-eligibility" v-model="eligibility">
                        <option value="">All suggestions</option>
                        <option value="true">Ready to order</option>
                        <option value="false">Needs configuration</option>
                    </select></label
                ><button type="submit" :disabled="busy">Filter suggestions</button>
            </form>
            <p v-if="loadError" class="error" role="alert">
                {{ errorMessage(loadError) }} <button @click="reload">Retry</button>
            </p>
            <p v-if="status === 'pending'" class="loading" role="status">Calculating replenishment…</p>
            <div v-else-if="data?.data.length" class="table-viewport">
                <table>
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Product / supplier</th>
                            <th>On hand</th>
                            <th>Reserved</th>
                            <th>On order</th>
                            <th>Projected</th>
                            <th>Reorder point / target</th>
                            <th>Suggested</th>
                            <th>Unit cost</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in data.data" :key="row.id">
                            <td>
                                <input
                                    type="checkbox"
                                    :aria-label="`Select ${row.name}`"
                                    :checked="Boolean(selected[row.id])"
                                    :disabled="
                                        !row.can_create_draft || busy || (!selected[row.id] && selection.length >= 100)
                                    "
                                    @change="toggle(row, ($event.target as HTMLInputElement).checked)"
                                />
                            </td>
                            <td>
                                <NuxtLink :to="productLink(row)">{{ row.sku }} · {{ row.name }}</NuxtLink
                                ><small class="dashboard-subline">{{ row.supplier || 'No primary supplier' }}</small
                                ><small v-if="row.lead_time_days">{{ row.lead_time_days }} day lead time</small>
                            </td>
                            <td>{{ row.stock }}</td>
                            <td>{{ row.reserved_quantity }}</td>
                            <td>{{ row.on_order_quantity }}</td>
                            <td>{{ row.projected_quantity }}</td>
                            <td>{{ row.reorder_point }} / {{ row.max_stock }}</td>
                            <td>{{ row.suggested_quantity }}</td>
                            <td>{{ row.unit_cost }} {{ row.currency || '' }}</td>
                            <td>
                                <small v-if="row.blocked_reason">{{ row.blocked_reason }}</small><NuxtLink
                                    class="dashboard-subline"
                                    :to="`${productLink(row)}#suppliers`"
                                    >{{ row.has_variants ? 'Review variants' : 'Manage suppliers' }}</NuxtLink
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="!loadError" class="empty-state">
                <h2>No replenishment needed</h2>
                <p>Available stock and incoming purchases cover the configured reorder points.</p>
                <NuxtLink class="button" to="/products">View inventory</NuxtLink>
            </div>
            <footer v-if="data" class="pagination">
                <span>{{ data.meta.total }} suggestions · {{ selection.length }} selected (maximum 100)</span>
                <div class="actions">
                    <button :disabled="page === 1 || busy" @click="page--">Previous</button><span>Page {{ page }}</span
                    ><button :disabled="page * 25 >= data.meta.total || busy" @click="page++">Next</button>
                </div>
            </footer>
        </section>
        <dialog
            ref="dialog"
            class="action-dialog"
            aria-labelledby="replenishment-review-title"
            @cancel.prevent="!busy && dialog?.close()"
        >
            <form @submit.prevent="createDrafts">
                <h2 id="replenishment-review-title">Review draft purchase orders</h2>
                <p>
                    One draft is created per supplier and currency. Confirm quantities and unit costs before continuing.
                </p>
                <div class="table-viewport">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Unit cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in reviewed" :key="row.id">
                                <td>{{ row.sku }} · {{ row.name }}</td>
                                <td>{{ row.supplier }}</td>
                                <td>{{ row.suggested_quantity }}</td>
                                <td>{{ row.unit_cost }} {{ row.currency }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="error" class="error" role="alert">{{ error }}</p>
                <p class="muted">
                    If quantities or purchasing terms changed, you'll be asked to refresh and review again. Retrying
                    this request cannot create duplicate drafts.
                </p>
                <footer class="form-footer">
                    <button type="button" :disabled="busy" @click="dialog?.close()">Back</button
                    ><button class="primary" :disabled="busy">
                        {{ busy ? 'Creating drafts…' : 'Create draft purchase orders' }}
                    </button>
                </footer>
            </form>
        </dialog>
    </div>
</template>
