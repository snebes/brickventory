<script setup lang="ts">
import type { InventoryRecord } from '~/shared/inventory'
import { errorMessage } from '~/utils/inventory'
import InventorySelect from './InventorySelect.vue'
const props = defineProps<{ orderId?: number | string; modelValue: any[]; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [any[]] }>()
const api = useInventoryApi(),
    order = ref<InventoryRecord>(),
    error = ref(''),
    loading = ref(false)
let version = 0
watch(
    () => props.orderId,
    async (id) => {
        const current = ++version
        order.value = undefined
        emit('update:modelValue', [])
        error.value = ''
        if (!id) return
        loading.value = true
        try {
            const result = await api.get('orders', id)
            if (current === version) {
                order.value = result.data
                emit(
                    'update:modelValue',
                    (result.data.items || []).map((line) => ({
                        id: line.id,
                        quantity: 0,
                        receiving_location_id: '',
                        batch: line.batch || '',
                        serialText: '',
                    })),
                )
            }
        } catch (cause) {
            if (current === version) error.value = errorMessage(cause)
        } finally {
            if (current === version) loading.value = false
        }
    },
    { immediate: true },
)
function change(index: number, key: string, value: any) {
    emit(
        'update:modelValue',
        props.modelValue.map((line, i) => (i === index ? { ...line, [key]: value } : line)),
    )
}
</script>
<template>
    <section>
        <h2>Items to return</h2>
        <p>Choose an order, then enter quantities for the items being returned.</p>
        <p v-if="loading" role="status">Loading order lines…</p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <fieldset v-for="(line, index) in modelValue" :key="line.id" class="line-card" :disabled="disabled">
            <legend>Order line {{ line.id }} · Product #{{ order?.items?.[index]?.product_id }}</legend>
            <div class="form-grid">
                <div class="field">
                    <label :for="`return-qty-${index}`">Return quantity</label
                    ><input
                        :id="`return-qty-${index}`"
                        :value="line.quantity"
                        type="number"
                        min="0"
                        :max="
                            (order?.items?.[index]?.shipped_quantity ??
                                (['shipped', 'delivered'].includes(order?.status || '')
                                    ? order?.items?.[index]?.quantity || 0
                                    : 0)) - (order?.items?.[index]?.returned_quantity || 0)
                        "
                        @input="change(index, 'quantity', Number(($event.target as HTMLInputElement).value))"
                    />
                </div>
                <div class="field">
                    <label :for="`return-location-${index}`">Receive into</label
                    ><InventorySelect
                        :id="`return-location-${index}`"
                        resource="locations"
                        :model-value="line.receiving_location_id"
                        :required="line.quantity > 0"
                        @update:model-value="change(index, 'receiving_location_id', $event)"
                    />
                </div>
                <div class="field">
                    <label :for="`return-serials-${index}`">Serials (one per line)</label
                    ><textarea
                        :id="`return-serials-${index}`"
                        :value="line.serialText"
                        @input="change(index, 'serialText', ($event.target as HTMLTextAreaElement).value)"
                    />
                </div>
            </div>
        </fieldset>
    </section>
</template>
