<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const api = useInventoryApi(),
    error = ref(''),
    busy = ref(false),
    editing = ref<number | null>(null),
    open = ref(false),
    created = ref<any[]>([])
const form = reactive({
    name: '',
    frequency: 'weekly',
    scope_type: 'all',
    scope_id: '' as number | '',
    products_per_run: 50,
    is_active: true,
    next_run_at: '',
})
const deleting = ref<number | null>(null)
async function remove(id: number) {
    busy.value = true
    error.value = ''
    try {
        await api.request(`cycle-counts/${id}`, { method: 'DELETE' })
        deleting.value = null
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
const scopeResource = computed(
    () => ({ warehouse: 'warehouses', location: 'locations', category: 'categories' })[form.scope_type],
)
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData('cycle-schedules', () => api.request<{ data: any[] }>('cycle-counts'))
function edit(row?: any) {
    editing.value = row?.id || null
    open.value = true
    Object.assign(form, {
        name: row?.name || '',
        frequency: row?.details.frequency || 'weekly',
        scope_type: row?.details.scope_type || 'all',
        scope_id: row?.details.scope_id || '',
        products_per_run: row?.details.products_per_run || 50,
        is_active: row?.is_active ?? true,
        next_run_at: row?.next_run_at?.slice(0, 16) || '',
    })
}
async function save() {
    busy.value = true
    error.value = ''
    try {
        await api.request(`cycle-counts${editing.value ? `/${editing.value}` : ''}`, {
            method: editing.value ? 'PATCH' : 'POST',
            body: {
                name: form.name,
                frequency: form.frequency,
                scope_type: form.scope_type,
                ...(form.scope_type !== 'all' ? { scope_id: Number(form.scope_id) } : {}),
                products_per_run: Number(form.products_per_run),
                is_active: form.is_active,
                ...(form.next_run_at ? { next_run_at: `${form.next_run_at}:00Z` } : {}),
            },
        })
        open.value = false
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
async function run(id: number) {
    busy.value = true
    error.value = ''
    created.value = []
    try {
        const result = await api.request<{ data: { created: any[]; message?: string } }>(`cycle-counts/${id}/run`, {
            method: 'POST',
        })
        created.value = result.data.created
        if (result.data.message) error.value = result.data.message
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
async function toggle(row: any) {
    busy.value = true
    error.value = ''
    try {
        await api.request(`cycle-counts/${row.id}`, { method: 'PATCH', body: { is_active: !row.is_active } })
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
useHead({ title: 'Cycle counts · Brickventory' })
</script>
<template>
    <div>
        <header class="page-header">
            <div>
                <p class="eyebrow">Inventory</p>
                <h1>Cycle counts</h1>
                <p>Schedule recurring physical counts by location, warehouse or category.</p>
            </div>
            <button class="primary" @click="edit()">New count schedule</button>
        </header>
        <p>
            Due runs select the least recently counted stock positions and create drafts. Count and review every line
            before completing them. Schedule times are UTC.
        </p>
        <p v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </p>
        <section v-if="open" class="panel detail-section">
            <h2>{{ editing ? 'Edit schedule' : 'New schedule' }}</h2>
            <form @submit.prevent="save">
                <div class="form-grid">
                    <label>Schedule name<input v-model="form.name" required maxlength="255" /></label
                    ><label
                        >Frequency<select v-model="form.frequency">
                            <option>daily</option>
                            <option>weekly</option>
                            <option>monthly</option>
                        </select></label
                    ><label
                        >Count scope<select v-model="form.scope_type" @change="form.scope_id = ''">
                            <option>all</option>
                            <option>location</option>
                            <option>warehouse</option>
                            <option>category</option>
                        </select></label
                    >
                    <div v-if="scopeResource">
                        <label for="count-scope">Scope selection</label
                        ><InventorySelect id="count-scope" v-model="form.scope_id" :resource="scopeResource" required />
                    </div>
                    <label
                        >Items per run<input
                            v-model.number="form.products_per_run"
                            type="number"
                            min="1"
                            max="500"
                            required /></label
                    ><label
                        >Next run (UTC; blank runs now)<input v-model="form.next_run_at" type="datetime-local" /></label
                    ><label><input v-model="form.is_active" type="checkbox" /> Active</label>
                </div>
                <button :disabled="busy">Save schedule</button
                ><button type="button" @click="open = false">Cancel</button>
            </form>
        </section>
        <section v-if="created.length" class="panel detail-section" role="status">
            <h2>{{ created.length }} draft counts created</h2>
            <p v-for="audit in created" :key="audit.id">
                <NuxtLink :to="`/stock-audits/${audit.id}`">{{ audit.number }}</NuxtLink>
            </p>
        </section>
        <section v-for="row in data?.data" :key="row.id" class="panel detail-section">
            <h2>{{ row.name }}</h2>
            <p>
                {{ row.details.frequency }} · {{ row.details.scope_type }} {{ row.details.scope_id || '' }} ·
                {{ row.details.products_per_run }} positions · {{ row.is_active ? 'Active' : 'Paused' }}
            </p>
            <p>Next run: {{ row.next_run_at }} · Last run: {{ row.last_run_at || 'Never' }}</p>
            <div class="actions">
                <button :disabled="busy" @click="edit(row)">Edit {{ row.name }}</button
                ><button :disabled="busy" @click="run(row.id)">Run count now</button
                ><button :disabled="busy" @click="toggle(row)">
                    {{ row.is_active ? 'Pause' : 'Resume' }} schedule
                </button>
            </div>
            <button :disabled="busy" @click="deleting = row.id">Delete schedule</button>
            <div v-if="deleting === row.id" role="alert">
                <p>Delete this schedule? Existing stock counts stay available.</p>
                <button :disabled="busy" @click="remove(row.id)">Confirm delete schedule</button
                ><button @click="deleting = null">Keep schedule</button>
            </div>
            <p v-for="audit in row.details.last_audits" :key="audit.id">
                <NuxtLink :to="`/stock-audits/${audit.id}`">{{ audit.number }}</NuxtLink>
            </p>
        </section>
        <p v-if="!data?.data.length">No cycle-count schedules yet.</p>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
    </div>
</template>
