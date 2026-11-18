<script setup lang="ts">
// SPDX-License-Identifier: AGPL-3.0-only; adapted from Inventoros StockPosition.vue.
import { stockLevel, type DashboardProduct } from '~/shared/dashboard'
const props = defineProps<{ product: DashboardProduct }>()
const level = computed(() => stockLevel(props.product))
</script>
<template>
    <div class="dashboard-stock-position">
        <div class="dashboard-stock-values">
            <span
                ><strong>{{ level.stock.toLocaleString() }}</strong
                >On hand</span
            ><span
                ><strong>{{ level.minimum.toLocaleString() }}</strong
                >Minimum</span
            >
        </div>
        <div class="dashboard-stock-ruler" role="img" :aria-label="`${level.stock} on hand; minimum ${level.minimum}`">
            <svg viewBox="0 0 240 20" preserveAspectRatio="none" aria-hidden="true">
                <path class="dashboard-ruler-gap" d="M0 10H240" />
                <path
                    v-if="level.percent > 0"
                    class="dashboard-ruler-fill"
                    :d="`M0 10H${(240 * level.percent) / 100}`"
                />
                <path class="dashboard-ruler-end" d="M240 2V18" /></svg
            ><span class="dashboard-ruler-head" :style="{ left: `${level.percent}%` }" />
        </div>
    </div>
</template>
