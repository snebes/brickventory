<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
import { importIsActive, type ProductImport } from '~/shared/productImports'
const api = useInventoryApi(),
    busy = ref(false),
    error = ref(''),
    barcode = ref(''),
    file = ref<File>(),
    page = ref(1)
const confirming = ref<{ id: string; action: 'cancel' | 'resume' }>()
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData(
    'product-import-jobs',
    () =>
        api.request<{ data: ProductImport[]; meta: { total: number } }>('product-imports', {
            query: { page: page.value },
        }),
    { watch: [page] },
)
const active = computed(() => data.value?.data.some(importIsActive))
let timer: ReturnType<typeof setTimeout> | undefined
let mounted = false
async function poll() {
    if (!mounted) return
    if (active.value && document.visibilityState === 'visible') await refresh()
    if (mounted) timer = setTimeout(poll, 3000)
}
onMounted(() => {
    mounted = true
    timer = setTimeout(poll, 3000)
})
onBeforeUnmount(() => {
    mounted = false
    clearTimeout(timer)
})
async function upload() {
    if (!file.value) return
    busy.value = true
    error.value = ''
    try {
        if (file.value.size > 100 * 1024 * 1024) throw new Error('CSV files must be at most 100 MiB.')
        const body = new FormData()
        body.append('file', file.value)
        await api.request<{ data: ProductImport }>('product-imports', { method: 'POST', body })
        page.value = 1
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
async function action() {
    if (!confirming.value) return
    busy.value = true
    error.value = ''
    try {
        await api.request(`product-imports/${confirming.value.id}/${confirming.value.action}`, { method: 'POST' })
        confirming.value = undefined
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
async function lookup() {
    busy.value = true
    error.value = ''
    try {
        const response = await api.request(`barcode-lookup/${encodeURIComponent(barcode.value.trim())}`)
        await navigateTo(`/${response.data.parent_id ? 'variants' : 'products'}/${response.data.id}`)
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
useHead({ title: 'Inventory tools · Brickventory' })
</script>
<template>
    <div>
        <NuxtLink class="back-link" to="/products">← Back to inventory</NuxtLink>
        <header class="page-header">
            <div>
                <h1>Inventory tools</h1>
                <p>Import products in the background, export your catalog, or look up a barcode.</p>
            </div>
            <a class="button" href="/api/inventory/products/export">Download product CSV</a>
        </header>
        <div class="dashboard-grid">
            <form class="panel detail-section" @submit.prevent="upload">
                <h2>Import product CSV</h2>
                <p>
                    Up to 250,000 products / 100 MiB. Uploads are queued for background processing, so you can leave
                    this page while they run.
                </p>
                <p>
                    Existing products are updated by SKU. Each batch commits together. If a row fails, earlier completed
                    batches stay imported; download the error report for the failing row. Resume restarts at the last
                    completed batch.
                </p>
                <p>
                    Use columns <code>sku,name,description,price,cost,barcode,min_stock,is_active</code>. A new product
                    needs a name and SKU. Upload UTF-8 CSV with a header row.
                </p>
                <label
                    >CSV file<input
                        type="file"
                        accept=".csv,text/csv"
                        required
                        :disabled="busy"
                        @change="file = ($event.target as HTMLInputElement).files?.[0]"
                /></label>
                <footer class="form-footer">
                    <button class="primary" :disabled="busy || !file">
                        {{ busy ? 'Uploading…' : 'Queue product import' }}
                    </button>
                </footer>
            </form>
            <form class="panel detail-section" @submit.prevent="lookup">
                <h2>Find by barcode</h2>
                <p>Scan or enter a product or variant barcode.</p>
                <label>Barcode<input v-model="barcode" required autocomplete="off" /></label>
                <footer class="form-footer"><button class="primary" :disabled="busy">Find product</button></footer>
            </form>
        </div>
        <section class="panel detail-section">
            <header class="section-header">
                <h2>Product imports</h2>
                <button :disabled="busy" @click="refresh()">Refresh imports</button>
            </header>
            <p v-if="active">
                Processing continues in the background. Progress refreshes automatically. Queued imports start when a
                worker is available.
            </p>
            <p v-if="loadError" class="error" role="alert">
                {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
            </p>
            <article v-for="job in data?.data" :key="job.id" class="line-card">
                <h3>{{ job.filename }}</h3>
                <p>
                    <InventoryBadge :value="job.status" /> · {{ job.processed.toLocaleString() }} products imported ·
                    {{ job.created_at }}
                </p>
                <progress :value="job.progress" max="100" :aria-label="`Import progress for ${job.filename}`" /><span>
                    {{ job.progress }}% of file processed</span
                >
                <p v-if="job.error" role="alert" class="error">
                    {{ job.error.row ? `Row ${job.error.row}: ` : '' }}{{ job.error.message }}
                </p>
                <div class="actions">
                    <button
                        v-if="importIsActive(job)"
                        :disabled="busy"
                        @click="confirming = { id: job.id, action: 'cancel' }"
                    >
                        Cancel import</button
                    ><button
                        v-if="['failed', 'cancelled'].includes(job.status)"
                        :disabled="busy"
                        @click="confirming = { id: job.id, action: 'resume' }"
                    >
                        Resume import</button
                    ><a v-if="job.error" class="button" :href="`/api/inventory/product-imports/${job.id}/errors`"
                        >Download error report</a
                    >
                </div>
                <div v-if="confirming?.id === job.id" role="alert">
                    <p v-if="confirming.action === 'cancel'">
                        Cancel this import after its current batch? Completed batches remain imported.
                    </p>
                    <p v-else>
                        Resume this import from its last completed batch? Resolve the reported conflict before resuming;
                        upload a corrected file if the CSV needs changes.
                    </p>
                    <button :disabled="busy" @click="action">Confirm {{ confirming.action }}</button
                    ><button :disabled="busy" @click="confirming = undefined">Back</button>
                </div>
            </article>
            <p v-if="!data?.data.length && !loadError">No product imports yet.</p>
            <footer class="pagination">
                <button :disabled="page <= 1" @click="page--">Previous imports</button
                ><span>{{ data?.meta.total || 0 }} imports · Page {{ page }}</span
                ><button :disabled="page * 20 >= (data?.meta.total || 0)" @click="page++">Next imports</button>
            </footer>
        </section>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </div>
</template>
