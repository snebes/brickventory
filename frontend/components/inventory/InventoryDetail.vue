<script setup lang="ts">
import { inventoryResources, type InventoryRecord } from '~/shared/inventory'
import { errorMessage, display, label, workflowActions, canEdit } from '~/utils/inventory'
import InventoryBadge from './InventoryBadge.vue'
import InventoryAction from './InventoryAction.vue'
import InventoryAttachments from './InventoryAttachments.vue'
import InventorySupplierCatalog from './InventorySupplierCatalog.vue'
const props = defineProps<{ resource: string; id: string }>()
const config = inventoryResources[props.resource]!,
    api = useInventoryApi()
const {
    data: record,
    error: loadError,
    refresh,
} = await useAsyncData(
    `detail-${props.resource}-${props.id}`,
    async () => (await api.get(props.resource, props.id)).data,
)
const action = ref(''),
    error = ref(''),
    deleting = ref(false),
    busy = ref(false)
const actions = computed(() => (record.value ? workflowActions(props.resource, record.value) : []))
const values = computed(() =>
    record.value
        ? Object.entries({ ...record.value, ...record.value.details }).filter(
              ([key, value]) =>
                  ![
                      'details',
                      'items',
                      'payments',
                      'shipments',
                      'receipts',
                      'metadata',
                      'count_history',
                      'count_reviews',
                      'count_required',
                      'cycle_schedule_id',
                      'warehouse_levels',
                  ].includes(key) &&
                  value !== null &&
                  value !== '',
          )
        : [],
)
const historyType = computed(() =>
    props.resource === 'customers'
        ? 'orders'
        : props.resource === 'suppliers'
          ? 'price-history'
          : props.resource === 'products'
            ? 'variants'
            : '',
)
const history = ref<InventoryRecord[]>([]),
    historyPage = ref(1),
    historyTotal = ref(0),
    historyError = ref('')
async function loadHistory() {
    if (!historyType.value) return
    historyError.value = ''
    try {
        const result = await api.request<{ data: InventoryRecord[]; meta: { total: number } }>(
            `${props.resource}/${props.id}/${historyType.value}`,
            { query: { page: historyPage.value, per_page: 10 } },
        )
        history.value = result.data
        historyTotal.value = result.meta.total
    } catch (cause) {
        historyError.value = errorMessage(cause)
    }
}
onMounted(loadHistory)
watch(historyPage, loadHistory)
useHead({ title: computed(() => `${record.value?.name || record.value?.number || config.singular} · Brickventory`) })
async function saved() {
    action.value = ''
    await refresh()
}
async function setDefaultWarehouse() {
    busy.value = true
    error.value = ''
    try {
        await api.request(`warehouses/${props.id}/set-default`, { method: 'POST' })
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
async function remove() {
    busy.value = true
    error.value = ''
    try {
        await api.remove(props.resource, Number(props.id))
        await navigateTo(`/${props.resource}`)
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <div>
        <NuxtLink class="back-link" :to="`/${resource}`">← Back to {{ config.title.toLowerCase() }}</NuxtLink>
        <div v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </div>
        <template v-if="record"
            ><header class="page-header">
                <div>
                    <p class="eyebrow">{{ config.singular }}</p>
                    <h1>
                        {{ record.name || record.number || `#${record.id}` }}
                        <InventoryBadge v-if="record.status" :value="record.status" />
                    </h1>
                    <p>{{ record.sku || config.description }}</p>
                </div>
                <div class="actions">
                    <NuxtLink v-if="canEdit(resource, record)" class="button" :to="`/${resource}/${id}/edit`"
                        >Edit</NuxtLink
                    ><button
                        v-for="value in actions"
                        :key="value"
                        :class="{
                            primary: !['cancel', 'reject', 'revert-shipment'].includes(value),
                            danger: ['cancel', 'reject'].includes(value),
                        }"
                        @click="action = value"
                    >
                        {{ label(value) }}</button
                    ><template v-if="['orders', 'purchase-orders'].includes(resource)"
                        ><a
                            class="button"
                            :href="`/api/inventory/${resource}/${id}/${resource === 'orders' ? 'invoice' : 'document'}`"
                            >Download PDF</a
                        ><button @click="action = resource === 'orders' ? 'email-invoice' : 'email'">
                            Email document
                        </button></template
                    >
                </div>
            </header>
            <div v-if="resource === 'orders'" class="panel detail-section actions">
                <button v-if="record.status !== 'cancelled'" @click="action = 'pay'">Record payment</button
                ><button v-if="Number(record.amount_paid) > 0" @click="action = 'refund'">Record refund</button
                ><button
                    v-if="['processing', 'shipped', 'delivered'].includes(record.status || '')"
                    @click="action = 'return'"
                >
                    Receive return</button
                ><NuxtLink class="button" to="/order-returns/new">Create return request</NuxtLink>
            </div>
            <InventoryDuplicate v-if="resource === 'products'" :product-id="record.id" />
            <InventoryWarehouseLevels v-if="['products', 'variants'].includes(resource)" :product-id="record.id" />
            <InventorySerials
                v-if="
                    ['products', 'variants'].includes(resource) &&
                    (record.details?.tracking === 'serial' || resource === 'variants')
                "
                :product-id="record.id" />
            <InventoryCountReview v-if="resource === 'stock-audits'" :record="record" @saved="refresh()" />
            <section v-if="resource === 'warehouses'" class="panel detail-section">
                <h2>Warehouse defaults</h2>
                <button :disabled="busy" @click="setDefaultWarehouse">Set as default warehouse</button>
            </section>
            <section v-if="resource === 'orders'" class="panel detail-section actions">
                <a class="button" :href="`/api/inventory/orders/${id}/pick-list`">Download pick list</a
                ><NuxtLink class="button" to="/shipments">View fulfillment</NuxtLink
                ><a
                    v-for="(shipment, index) in record.details?.shipments || []"
                    :key="index"
                    class="button"
                    :href="`/api/inventory/orders/${id}/shipments/${Number(index) + 1}/packing-slip`"
                    >Packing slip {{ Number(index) + 1 }}</a
                >
            </section>
            <section class="panel detail-section">
                <h2>Details</h2>
                <dl class="details-grid">
                    <div v-for="[key, value] in values" :key="key">
                        <dt>{{ label(key) }}</dt>
                        <dd>
                            <InventoryBadge
                                v-if="['status', 'payment_status', 'is_active', 'approval_status'].includes(key)"
                                :value="value"
                            /><span v-else>{{ display(value, key) }}</span>
                        </dd>
                    </div>
                </dl>
            </section>
            <section v-if="record.items?.length" class="panel detail-section">
                <h2>Line items</h2>
                <div class="table-viewport">
                    <table>
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Quantity</th>
                                <th>Unit price</th>
                                <th>Received</th>
                                <th>Shipped</th>
                                <th>Returned</th>
                                <th>Tracking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in record.items" :key="line.id">
                                <td>{{ line.id }}</td>
                                <td>
                                    <NuxtLink :to="`/products/${line.product_id}`">#{{ line.product_id }}</NuxtLink>
                                </td>
                                <td>
                                    <NuxtLink
                                        v-if="line.product_variant_id"
                                        :to="`/variants/${line.product_variant_id}`"
                                        >#{{ line.product_variant_id }}</NuxtLink
                                    ><span v-else>—</span>
                                </td>
                                <td>{{ line.quantity }}</td>
                                <td>{{ line.unit_price || '—' }}</td>
                                <td>{{ line.received_quantity || 0 }}</td>
                                <td>{{ line.shipped_quantity || 0 }}</td>
                                <td>{{ line.returned_quantity || 0 }}</td>
                                <td>{{ [line.batch, ...(line.serials || [])].filter(Boolean).join(', ') || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="record.total" class="totals">
                    <div
                        v-for="key in [
                            'subtotal',
                            'discount',
                            'tax',
                            'shipping',
                            'total',
                            'amount_paid',
                            'balance_due',
                        ].filter((key) => record![key] !== undefined)"
                        :key="key"
                    >
                        <span>{{ label(key) }}</span><strong
                            >{{ record[key] }} {{ record.details?.currency || '' }}</strong
                        >
                    </div>
                </div>
            </section>
            <section
                v-for="type in ['payments', 'shipments', 'receipts'].filter((type) => record!.details?.[type]?.length)"
                :key="type"
                class="panel detail-section"
            >
                <h2>{{ label(type) }}</h2>
                <ul class="history-list">
                    <li v-for="(entry, index) in record.details?.[type]" :key="index">
                        <span v-for="(value, key) in entry" :key="key"
                            ><strong>{{ label(String(key)) }}:</strong> {{ display(value, String(key)) }}
                        </span>
                    </li>
                </ul>
            </section>
            <section v-if="historyType" class="panel detail-section">
                <header class="section-header">
                    <h2>{{ label(historyType) }}</h2>
                    <NuxtLink v-if="resource === 'products'" class="button" to="/variants/new">+ New variant</NuxtLink>
                </header>
                <p v-if="historyError" class="error" role="alert">
                    {{ historyError }} <button @click="loadHistory">Retry</button>
                </p>
                <div v-for="row in history" :key="row.id || row.purchase_order_id" class="history-row">
                    <NuxtLink
                        :to="`/${resource === 'products' ? 'variants' : resource === 'suppliers' ? 'purchase-orders' : 'orders'}/${row.id || row.purchase_order_id}`"
                        >{{ row.name || row.number || `#${row.id || row.purchase_order_id}` }}</NuxtLink><InventoryBadge
                        v-if="row.status"
                        :value="row.status"
                    /><span>{{ row.price || row.total || row.currency || '' }}</span>
                    <ul v-if="resource === 'suppliers'">
                        <li v-for="(line, index) in row.items" :key="index">
                            Product #{{ line.product_id }} · {{ line.unit_cost }} {{ row.currency }} ·
                            {{ line.received_quantity }} received
                        </li>
                    </ul>
                </div>
                <p v-if="!history.length && !historyError" class="muted">No history yet.</p>
                <div v-if="historyTotal > 10" class="pagination">
                    <button :disabled="historyPage <= 1" @click="historyPage--">Previous</button
                    ><span>Page {{ historyPage }}</span
                    ><button :disabled="historyPage * 10 >= historyTotal" @click="historyPage++">Next</button>
                </div>
            </section>
            <InventorySupplierCatalog
                v-if="['products', 'variants'].includes(resource)"
                :product-id="record.id"
                :default-cost="record.cost" />
            <InventorySupplierCatalog v-if="resource === 'suppliers'" :supplier-id="record.id" />
            <InventoryAttachments v-if="['products', 'variants'].includes(resource)" :product-id="record.id" />
            <section v-if="!config.readonly && resource !== 'stock-adjustments'" class="delete-section">
                <button v-if="!deleting" class="subtle danger" @click="deleting = true">
                    Delete {{ config.singular.toLowerCase() }}
                </button>
                <div v-else class="error">
                    <p>
                        Delete this {{ config.singular.toLowerCase() }}? Records with stock or financial history are
                        protected. Deleting an open order cancels its allocation.
                    </p>
                    <button class="danger" :disabled="busy" @click="remove">
                        {{ busy ? 'Deleting…' : 'Delete record' }}
                    </button>
                    <button :disabled="busy" @click="deleting = false">Keep record</button>
                </div>
                <p v-if="error" class="error" role="alert">{{ error }}</p>
            </section>
            <InventoryAction
                v-if="action"
                :resource="resource"
                :record="record"
                :action="action"
                @close="action = ''"
                @saved="saved"
        /></template>
    </div>
</template>
