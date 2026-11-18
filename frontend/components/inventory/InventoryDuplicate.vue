<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ productId: number }>()
const open = ref(false),
    sku = ref(''),
    error = ref(''),
    busy = ref(false)
const api = useInventoryApi()
async function duplicate() {
    busy.value = true
    error.value = ''
    try {
        const r = await api.request(`products/${props.productId}/duplicate`, {
            method: 'POST',
            body: { sku: sku.value },
        })
        await navigateTo(`/products/${r.data.id}`)
    } catch (e) {
        error.value = errorMessage(e)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section class="panel detail-section">
        <button @click="open = !open">Duplicate product</button>
        <form v-if="open" @submit.prevent="duplicate">
            <p>The copy starts with no stock, barcode, variants or supplier links.</p>
            <label>New SKU<input v-model="sku" required maxlength="100" /></label
            ><button :disabled="busy">Create product copy</button>
            <p v-if="error" class="error" role="alert">{{ error }}</p>
        </form>
    </section>
</template>
