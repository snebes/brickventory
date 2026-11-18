<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ productId: number }>()
const api = useInventoryApi(),
    page = ref(1),
    search = ref(''),
    filter = ref(''),
    applied = ref({ search: '', status: '' }),
    error = ref(''),
    busy = ref(false),
    open = ref(false)
const number = ref(''),
    notes = ref(''),
    location = ref<number | ''>(''),
    history = ref<any[]>([]),
    historyNumber = ref('')
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData(
    `serials-${props.productId}`,
    () =>
        api.request<{ data: any[]; meta: { total: number } }>(`products/${props.productId}/serials`, {
            query: { page: page.value, per_page: 20, ...applied.value },
        }),
    { watch: [page, applied] },
)
async function register() {
    busy.value = true
    error.value = ''
    try {
        await api.request(`products/${props.productId}/serials`, {
            method: 'POST',
            body: {
                serial_number: number.value,
                notes: notes.value,
                ...(location.value ? { location_id: Number(location.value) } : {}),
            },
        })
        open.value = false
        number.value = ''
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
async function save(row: any) {
    busy.value = true
    error.value = ''
    try {
        await api.request(`products/${props.productId}/serials/${encodeURIComponent(row.serial_number)}`, {
            method: 'PATCH',
            body: { status: row.status, notes: row.notes },
        })
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
        await refresh()
    } finally {
        busy.value = false
    }
}
async function showHistory(row: any) {
    error.value = ''
    try {
        history.value = (
            await api.request<{ data: any[] }>(
                `products/${props.productId}/serials/${encodeURIComponent(row.serial_number)}/history`,
            )
        ).data
        historyNumber.value = row.serial_number
    } catch (e) {
        error.value = errorMessage(e)
    }
}
function apply() {
    page.value = 1
    applied.value = { search: search.value, status: filter.value }
}
</script>
<template>
    <section class="panel detail-section">
        <header class="section-header">
            <h2>Serial management</h2>
            <button @click="open = !open">Register serial</button>
        </header>
        <p>
            Reserve or mark stocked units damaged to prevent allocation. Sales and returns update serial status through
            their order workflows.
        </p>
        <form v-if="open" @submit.prevent="register">
            <div class="form-grid">
                <label>Serial number<input v-model="number" required maxlength="100" /></label
                ><label>Serial notes<input v-model="notes" maxlength="4000" /></label>
                <div>
                    <label for="serial-location">Receive into location (optional)</label
                    ><InventorySelect id="serial-location" v-model="location" resource="locations" />
                </div>
            </div>
            <p>
                Choosing a location records one unit of opening stock. Leaving it blank registers the serial without
                adding stock.
            </p>
            <button :disabled="busy">Save serial registration</button>
        </form>
        <form class="filters" @submit.prevent="apply">
            <label>Search serials<input v-model="search" /></label
            ><label
                >Serial status<select v-model="filter">
                    <option value="">All statuses</option>
                    <option
                        v-for="s in [
                            'registered',
                            'available',
                            'reserved',
                            'damaged',
                            'sold',
                            'in_transit',
                            'unavailable',
                        ]"
                        :key="s"
                    >
                        {{ s }}
                    </option>
                </select></label
            ><button>Filter serials</button>
        </form>
        <p v-if="loadError" class="error">{{ errorMessage(loadError) }} <button @click="refresh()">Retry</button></p>
        <div v-for="row in data?.data" :key="row.serial_number" class="line-card">
            <h3>{{ row.serial_number }}</h3>
            <p v-for="l in row.locations" :key="l.location_id">{{ l.location }} · {{ l.batch }} · {{ l.expires_at }}</p>
            <form @submit.prevent="save(row)">
                <div class="form-grid">
                    <label
                        >Availability<select v-model="row.status">
                            <option
                                v-for="s in Array.from(new Set([row.status, 'available', 'reserved', 'damaged']))"
                                :key="s"
                            >
                                {{ s }}
                            </option>
                        </select></label
                    ><label>Notes<input v-model="row.notes" maxlength="4000" /></label>
                </div>
                <button :disabled="busy">Save {{ row.serial_number }}</button
                ><button type="button" @click="showHistory(row)">History for {{ row.serial_number }}</button>
            </form>
        </div>
        <p v-if="!data?.data.length">No serials match.</p>
        <footer class="pagination">
            <button :disabled="page === 1" @click="page--">Previous serials</button
            ><span>{{ data?.meta.total || 0 }} serials · Page {{ page }}</span
            ><button :disabled="page * 20 >= (data?.meta.total || 0)" @click="page++">Next serials</button>
        </footer>
        <div v-if="historyNumber">
            <h3>Movement history: {{ historyNumber }}</h3>
            <p v-for="m in history" :key="m.id">
                {{ m.created_at }} · {{ m.type }} · {{ m.quantity }} · {{ m.reference }} · Location #{{ m.location_id }}
            </p>
            <p v-if="!history.length">No stock movements recorded.</p>
        </div>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
    </section>
</template>
