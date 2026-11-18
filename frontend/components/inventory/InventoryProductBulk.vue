<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ ids: number[] }>()
const emit = defineEmits<{ saved: [] }>()
const api = useInventoryApi()
const action = ref('price'),
    type = ref('percentage'),
    value = ref(''),
    category = ref<number | ''>(''),
    error = ref(''),
    busy = ref(false),
    confirm = ref(false)
async function submit() {
    busy.value = true
    error.value = ''
    try {
        const body: Record<string, any> = { ids: props.ids }
        if (action.value === 'price') Object.assign(body, { type: type.value, value: value.value })
        if (action.value === 'category') body.category_id = category.value
        if (action.value === 'export') {
            const blob = await api.request<Blob>('products/bulk/export', { method: 'POST', body, responseType: 'blob' })
            const url = URL.createObjectURL(blob),
                a = document.createElement('a')
            a.href = url
            a.download = 'selected-products.csv'
            a.click()
            URL.revokeObjectURL(url)
        } else {
            await api.request(`products/bulk/${action.value}`, { method: 'POST', body })
            emit('saved')
        }
        confirm.value = false
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section v-if="ids.length" class="panel detail-section">
        <h2>{{ ids.length }} products selected</h2>
        <form @submit.prevent="confirm = true">
            <div class="form-grid">
                <label
                    >Bulk operation<select v-model="action" @change="confirm = false">
                        <option value="price">Adjust prices</option>
                        <option value="category">Change category</option>
                        <option value="export">Export selected</option>
                        <option value="delete">Delete selected</option>
                    </select></label
                >
                <template v-if="action === 'price'"
                    ><label
                        >Adjustment type<select v-model="type">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select></label
                    ><label
                        >Adjustment value<input
                            v-model="value"
                            required
                            pattern="-?\d+(\.\d{1,2})?"
                            placeholder="10 or -5.00" /></label
                ></template>
                <div v-if="action === 'category'">
                    <label for="bulk-category">Category</label
                    ><InventorySelect id="bulk-category" v-model="category" resource="categories" required />
                </div>
            </div>
            <button :disabled="busy || ids.length > 500" type="submit">Review bulk operation</button>
        </form>
        <div v-if="confirm" class="detail-section" role="alert">
            <p>
                Apply {{ action }} to {{ ids.length }} selected products<span v-if="action === 'price'">
                    using {{ value }} {{ type }}</span
                >? All changes succeed together. Products with history cannot be deleted.
            </p>
            <button :disabled="busy" @click="submit">Confirm bulk {{ action }}</button
            ><button :disabled="busy" @click="confirm = false">Back</button>
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
</template>
