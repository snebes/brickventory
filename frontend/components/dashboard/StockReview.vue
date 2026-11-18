<script setup lang="ts">
import { reorderLink, stockLevel, type DashboardProduct } from '~/shared/dashboard'
import StockPosition from './StockPosition.vue'
const props = defineProps<{ product: DashboardProduct }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
onMounted(() => dialog.value?.showModal())
onBeforeUnmount(() => dialog.value?.close())
</script>
<template>
    <dialog ref="dialog" class="action-dialog" aria-labelledby="stock-review-title" @cancel.prevent="emit('close')">
        <header class="section-header">
            <div>
                <p class="eyebrow">Stock review · {{ product.sku }}</p>
                <h2 id="stock-review-title">{{ product.name }}</h2>
            </div>
            <button aria-label="Close stock review" @click="emit('close')">×</button>
        </header>
        <StockPosition :product="product" />
        <p class="dashboard-review-copy">
            {{ stockLevel(product).shortfall }} units below the minimum stock level.<span v-if="product.supplier">
                Last ordered from {{ product.supplier }}.</span
            ><span v-else> Choose a supplier when preparing your purchase order.</span>
        </p>
        <p v-if="product.has_variants">
            This product has variants. Select the variant to replenish before saving the purchase order.
        </p>
        <p v-if="product.on_order_quantity">{{ product.on_order_quantity }} units are already on order.</p>
        <p v-if="product.reserved_quantity">{{ product.reserved_quantity }} units are reserved for transfers.</p>
        <NuxtLink class="button" to="/replenishment" @click="emit('close')">Review replenishment</NuxtLink>
        <footer class="form-footer">
            <NuxtLink class="button" :to="`/products/${product.id}`" @click="emit('close')">View product</NuxtLink
            ><NuxtLink class="button primary" :to="reorderLink(product)" @click="emit('close')"
                >Prepare purchase order</NuxtLink
            >
        </footer>
    </dialog>
</template>
