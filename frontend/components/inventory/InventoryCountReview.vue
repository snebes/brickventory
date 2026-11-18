<script setup lang="ts">
import type { InventoryRecord } from '~/shared/inventory'
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ record: InventoryRecord }>(),
    emit = defineEmits<{ saved: [] }>()
const api = useInventoryApi(),
    error = ref(''),
    busy = ref(false)
const quantities = reactive<Record<number, number>>({}),
    serials = reactive<Record<number, string>>({}),
    notes = reactive<Record<number, string>>({})
for (const line of props.record.items || []) {
    quantities[line.id!] = line.quantity
    serials[line.id!] = (line.serials || []).join('\n')
}
async function perform(id: number, review = false) {
    busy.value = true
    error.value = ''
    try {
        const values = (serials[id] || '')
            .split(/\r?\n/)
            .map((s) => s.trim())
            .filter(Boolean)
        await api.request(`stock-audits/${props.record.id}/items/${id}/${review ? 'review' : 'count'}`, {
            method: 'POST',
            body: review
                ? {}
                : {
                      quantity: Number(quantities[id]),
                      ...(values.length ? { serials: values } : {}),
                      notes: notes[id] || '',
                  },
        })
        emit('saved')
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section class="panel detail-section">
        <h2>Record and review counts</h2>
        <p>Recording a new count clears its review. Review every line before completing a count entered here.</p>
        <div v-for="line in record.items" :key="line.id" class="line-card">
            <h3>Line {{ line.id }} · Product #{{ line.product_variant_id || line.product_id }}</h3>
            <p>
                Expected {{ line.expected_quantity }} · Counted {{ line.quantity }} ·
                {{ record.details?.count_reviews?.[String(line.id)] ? 'Reviewed' : 'Awaiting review' }}
            </p>
            <form v-if="record.status === 'draft'" @submit.prevent="perform(line.id!)">
                <div class="form-grid">
                    <label
                        >Physical quantity<input
                            v-model.number="quantities[line.id!]"
                            required
                            type="number"
                            min="0"
                            max="1000000000" /></label
                    ><label>Counted serials (one per line)<textarea v-model="serials[line.id!]" /></label
                    ><label>Count note<input v-model="notes[line.id!]" maxlength="4000" /></label>
                </div>
                <button :disabled="busy">Record count for line {{ line.id }}</button
                ><button
                    type="button"
                    :disabled="busy || !record.details?.count_history?.[String(line.id)]?.length"
                    @click="perform(line.id!, true)"
                >
                    Review line {{ line.id }}
                </button>
            </form>
            <details v-if="record.details?.count_history?.[String(line.id)]?.length">
                <summary>Count history</summary>
                <p v-for="(entry, index) in record.details.count_history[String(line.id)]" :key="index">
                    {{ entry.created_at }} · {{ entry.quantity }} units · {{ entry.notes }}
                    {{ entry.serials?.join(', ') }}
                </p>
            </details>
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
</template>
