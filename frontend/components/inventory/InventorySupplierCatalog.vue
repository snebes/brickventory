<script setup lang="ts">
import type { SupplierLink } from '~/shared/replenishment'
import { errorMessage } from '~/utils/inventory'
import InventorySelect from './InventorySelect.vue'
const props = defineProps<{ productId?: number; supplierId?: number; defaultCost?: string }>()
const api = useInventoryApi(),
    page = ref(1),
    error = ref(''),
    busy = ref(false),
    showForm = ref(false),
    editing = ref<number>(),
    removing = ref<number>()
const path = computed(() =>
    props.productId ? `products/${props.productId}/suppliers` : `suppliers/${props.supplierId}/products`,
)
const {
    data,
    error: loadError,
    refresh,
    status,
} = await useAsyncData(
    `catalog-${path.value}`,
    () =>
        api.request<{ data: SupplierLink[]; meta: { total: number } }>(path.value, {
            query: { page: page.value, per_page: 20 },
        }),
    { watch: [page] },
)
const form = ref({
    supplier_id: '' as number | '',
    supplier_sku: '',
    cost_price: props.defaultCost || '0.00',
    currency: '',
    minimum_order_quantity: 1,
    lead_time_days: 0,
    is_primary: true,
})
function edit(row?: SupplierLink) {
    editing.value = row?.id
    error.value = ''
    showForm.value = true
    form.value = {
        supplier_id: row?.supplier_id || '',
        supplier_sku: row?.supplier_sku || '',
        cost_price: row?.cost_price || props.defaultCost || '0.00',
        currency: row?.currency || '',
        minimum_order_quantity: row?.minimum_order_quantity || 1,
        lead_time_days: row?.lead_time_days || 0,
        is_primary: row?.is_primary ?? !data.value?.data.some((link) => link.is_primary),
    }
}
async function save() {
    busy.value = true
    error.value = ''
    try {
        const body = {
            ...form.value,
            supplier_id: Number(form.value.supplier_id),
            minimum_order_quantity: Number(form.value.minimum_order_quantity),
            lead_time_days: Number(form.value.lead_time_days),
            ...(form.value.currency ? {} : { currency: undefined }),
        }
        await api.request(editing.value ? `${path.value}/${editing.value}` : path.value, {
            method: editing.value ? 'PATCH' : 'POST',
            body,
        })
        showForm.value = false
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
async function remove(id: number) {
    busy.value = true
    error.value = ''
    try {
        await api.request(`${path.value}/${id}`, { method: 'DELETE' })
        removing.value = undefined
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section id="suppliers" class="panel detail-section">
        <header class="section-header">
            <div>
                <h2>{{ productId ? 'Supplier catalog' : 'Supplied products' }}</h2>
                <p>
                    {{
                        productId
                            ? 'Choose a primary supplier and maintain purchasing terms for this SKU.'
                            : 'Products and variants linked to this supplier.'
                    }}
                </p>
            </div>
            <button v-if="productId" :disabled="busy" @click="edit()">+ Link supplier</button>
        </header>
        <p v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <form v-if="showForm" class="catalog-form" @submit.prevent="save">
            <h3>{{ editing ? 'Edit supplier terms' : 'Link supplier' }}</h3>
            <fieldset class="form-grid" :disabled="busy">
                <div class="field">
                    <label for="catalog-supplier">Supplier *</label
                    ><InventorySelect
                        id="catalog-supplier"
                        v-model="form.supplier_id"
                        resource="suppliers"
                        required
                        :disabled="Boolean(editing)"
                    />
                </div>
                <label for="catalog-sku"
                    >Supplier SKU<input id="catalog-sku" v-model="form.supplier_sku" maxlength="255" /></label
                ><label for="catalog-cost"
                    >Unit cost *<input
                        id="catalog-cost"
                        v-model="form.cost_price"
                        required
                        inputmode="decimal"
                        pattern="\d+(\.\d{1,2})?" /></label
                ><label for="catalog-currency"
                    >Currency<input
                        id="catalog-currency"
                        v-model="form.currency"
                        pattern="[A-Z]{3}"
                        maxlength="3"
                        placeholder="Supplier / workspace default" /></label
                ><label for="catalog-minimum"
                    >Minimum order quantity *<input
                        id="catalog-minimum"
                        v-model.number="form.minimum_order_quantity"
                        type="number"
                        min="1"
                        max="1000000000"
                        step="1"
                        required /></label
                ><label for="catalog-lead"
                    >Lead time (days)<input
                        id="catalog-lead"
                        v-model.number="form.lead_time_days"
                        type="number"
                        min="0"
                        max="3650"
                        step="1"
                        required /></label
                ><label class="inline-check"><input v-model="form.is_primary" type="checkbox" />Primary supplier</label>
            </fieldset>
            <footer class="form-footer">
                <button type="button" :disabled="busy" @click="showForm = false">Cancel</button
                ><button class="primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save supplier terms' }}</button>
            </footer>
        </form>
        <p v-if="status === 'pending'" role="status">Loading supplier catalog…</p>
        <div v-if="data?.data.length" class="table-viewport">
            <table>
                <thead>
                    <tr>
                        <th>{{ productId ? 'Supplier' : 'Product' }}</th>
                        <th>Supplier SKU</th>
                        <th>Unit cost</th>
                        <th>Minimum</th>
                        <th>Lead time</th>
                        <th>Primary</th>
                        <th v-if="productId">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data.data" :key="row.id">
                        <td>
                            <NuxtLink
                                :to="
                                    productId
                                        ? `/suppliers/${row.supplier_id}`
                                        : `/${row.parent_id ? 'variants' : 'products'}/${row.product_id}`
                                "
                                >{{
                                    productId ? row.supplier_name : `${row.product_sku} · ${row.product_name}`
                                }}</NuxtLink
                            ><small v-if="!row.supplier_active" class="error">Supplier inactive</small>
                        </td>
                        <td>{{ row.supplier_sku || '—' }}</td>
                        <td>{{ row.cost_price }} {{ row.currency }}</td>
                        <td>{{ row.minimum_order_quantity }}</td>
                        <td>{{ row.lead_time_days }} days</td>
                        <td>{{ row.is_primary ? 'Primary' : '—' }}</td>
                        <td v-if="productId">
                            <div class="actions">
                                <button
                                    :aria-label="`Edit terms for ${row.supplier_name}`"
                                    :disabled="busy"
                                    @click="edit(row)"
                                >
                                    Edit</button
                                ><button
                                    v-if="removing !== row.id"
                                    class="subtle danger"
                                    :disabled="busy"
                                    @click="removing = row.id"
                                >
                                    Unlink</button
                                ><template v-else
                                    ><span>Unlink supplier?</span
                                    ><button class="danger" :disabled="busy" @click="remove(row.id)">
                                        Confirm unlink</button
                                    ><button @click="removing = undefined">Keep</button></template
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else-if="!loadError && !showForm" class="muted">No supplier catalog links yet.</p>
        <footer v-if="data && data.meta.total > 20" class="pagination">
            <button :disabled="page === 1 || busy" @click="page--">Previous</button
            ><span>Page {{ page }} · {{ data.meta.total }} links</span
            ><button :disabled="page * 20 >= data.meta.total || busy" @click="page++">Next</button>
        </footer>
    </section>
</template>
