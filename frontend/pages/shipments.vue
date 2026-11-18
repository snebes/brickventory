<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const api = useInventoryApi(),
    page = ref(1),
    search = ref(''),
    applied = ref(''),
    error = ref(''),
    busy = ref(false),
    editing = ref(''),
    tracking = ref(''),
    carrier = ref('')
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData(
    'fulfillment-shipments',
    () =>
        api.request<{ data: any[]; meta: { total: number } }>('shipments', {
            query: { page: page.value, per_page: 25, search: applied.value },
        }),
    { watch: [page, applied] },
)
function edit(row: any) {
    editing.value = row.id
    tracking.value = row.tracking_number
    carrier.value = row.carrier
}
function apply() {
    page.value = 1
    applied.value = search.value
}
async function save(row: any) {
    busy.value = true
    error.value = ''
    try {
        const shipmentId = String(row.id).split('-')[1]
        await api.request(`orders/${row.order_id}/shipments/${shipmentId}`, {
            method: 'PATCH',
            body: { carrier: carrier.value, tracking_number: tracking.value },
        })
        editing.value = ''
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
useHead({ title: 'Fulfillment · Brickventory' })
</script>
<template>
    <div>
        <header class="page-header">
            <div>
                <p class="eyebrow">Orders</p>
                <h1>Fulfillment</h1>
                <p>Review manual and partial shipments, update tracking and print packing slips.</p>
            </div>
            <NuxtLink class="button" to="/orders">Open orders</NuxtLink>
        </header>
        <form class="filters" @submit.prevent="apply">
            <label>Search shipments<input v-model="search" placeholder="Order, customer, carrier or tracking…" /></label
            ><button>Find shipments</button>
        </form>
        <p v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </p>
        <section v-for="row in data?.data" :key="row.id" class="panel detail-section">
            <h2>
                <NuxtLink :to="`/orders/${row.order_id}`">{{ row.order_number }}</NuxtLink> · Shipment
                {{ String(row.id).split('-')[1] }}
            </h2>
            <p>{{ row.customer }} · {{ row.created_at }} · {{ row.carrier }} {{ row.tracking_number }}</p>
            <p v-for="line in row.items" :key="line.id">
                Order line {{ line.id }} · {{ line.quantity }} units
                <span v-if="line.serials?.length">· {{ line.serials.join(', ') }}</span>
            </p>
            <div class="actions">
                <a class="button" :href="`/api/inventory/orders/${row.order_id}/pick-list`">Download pick list</a
                ><a
                    class="button"
                    :href="`/api/inventory/orders/${row.order_id}/shipments/${String(row.id).split('-')[1]}/packing-slip`"
                    >Download packing slip</a
                ><button @click="edit(row)">Update tracking</button>
            </div>
            <form v-if="editing === row.id" @submit.prevent="save(row)">
                <div class="form-grid">
                    <label>Carrier<input v-model="carrier" maxlength="255" /></label
                    ><label>Tracking number<input v-model="tracking" maxlength="255" /></label>
                </div>
                <button :disabled="busy">Save shipment tracking</button
                ><button type="button" @click="editing = ''">Back</button>
            </form>
        </section>
        <div v-if="!data?.data.length" class="empty-state">
            <h2>No shipments found</h2>
            <p>Ship selected quantities from a processing order to create a shipment.</p>
        </div>
        <footer class="pagination">
            <button :disabled="page === 1" @click="page--">Previous shipments</button
            ><span>{{ data?.meta.total || 0 }} shipments · Page {{ page }}</span
            ><button :disabled="page * 25 >= (data?.meta.total || 0)" @click="page++">Next shipments</button>
        </footer>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </div>
</template>
