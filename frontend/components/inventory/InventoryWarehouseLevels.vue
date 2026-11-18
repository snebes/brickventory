<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ productId: number }>()
const api = useInventoryApi(),
    error = ref(''),
    busy = ref(false)
const keys = ['min_stock', 'max_stock', 'reorder_point', 'reorder_quantity']
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData(
    `warehouse-levels-${props.productId}`,
    async () => (await api.request<{ data: any[] }>(`products/${props.productId}/warehouse-levels`)).data,
)
async function save(row: any) {
    error.value = ''
    busy.value = true
    try {
        const level: Record<string, any> = { warehouse_id: row.warehouse_id }
        for (const key of keys)
            level[key] = row.levels[key] === '' || row.levels[key] == null ? null : Number(row.levels[key])
        await api.request(`products/${props.productId}/warehouse-levels`, { method: 'PUT', body: { levels: [level] } })
        await refresh()
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section class="panel detail-section">
        <h2>Warehouse stock levels</h2>
        <p>
            Blank thresholds inherit product defaults. Clear all fields to remove the warehouse override. Capacity is a
            warning, not a receiving limit.
        </p>
        <p v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </p>
        <div v-for="row in data" :key="row.warehouse_id" class="line-card">
            <h3>{{ row.warehouse }} {{ row.is_default ? '(default)' : '' }}</h3>
            <p>
                {{ row.stock }} on hand · {{ row.reserved_quantity }} reserved · {{ row.available_quantity }} available
                <strong v-if="row.low_stock">· Low stock</strong
                ><strong v-if="row.over_capacity">· Over capacity</strong>
            </p>
            <form @submit.prevent="save(row)">
                <div class="form-grid">
                    <label v-for="key in keys" :key="key"
                        >{{ key.replaceAll('_', ' ')
                        }}<input v-model="row.levels[key]" type="number" min="0" max="1000000000"
                    /></label>
                </div>
                <button :disabled="busy">Save {{ row.warehouse }} levels</button>
            </form>
        </div>
        <p v-if="!data?.length">Create a warehouse to configure stock thresholds.</p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
</template>
