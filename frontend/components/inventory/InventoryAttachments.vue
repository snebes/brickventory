<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ productId: number }>()
const api = useInventoryApi(),
    error = ref(''),
    busy = ref(false)
const {
    data,
    error: loadError,
    refresh,
} = await useAsyncData(`attachments-${props.productId}`, () =>
    api.request<{ data: any[] }>(`products/${props.productId}/attachments`),
)
async function upload(event: Event) {
    const input = event.target as HTMLInputElement,
        file = input.files?.[0]
    if (!file) return
    error.value = ''
    if (file.size > 5 * 1024 * 1024) {
        error.value = 'Choose a file smaller than 5 MB.'
        input.value = ''
        return
    }
    busy.value = true
    try {
        const body = new FormData()
        body.append('file', file)
        await api.request(`products/${props.productId}/attachments`, { method: 'POST', body })
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
        input.value = ''
    }
}
const deleting = ref<number>()
async function remove(id: number) {
    busy.value = true
    error.value = ''
    try {
        await api.request(`products/${props.productId}/attachments/${id}`, { method: 'DELETE' })
        deleting.value = undefined
        await refresh()
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <section class="panel detail-section">
        <h2>Images & documents</h2>
        <p>JPEG, PNG, WebP or PDF · up to 5 MB each</p>
        <label class="file-upload"
            >Add attachment<input
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                :disabled="busy"
                @change="upload"
        /></label>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <p v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <button @click="refresh()">Retry</button>
        </p>
        <p v-if="busy" role="status">Saving attachment…</p>
        <ul class="attachment-list">
            <li v-for="file in data?.data || []" :key="file.id">
                <a :href="`/api/inventory/products/${productId}/attachments/${file.id}`">{{ file.name }}</a><button
                    v-if="deleting !== file.id"
                    class="subtle danger"
                    :disabled="busy"
                    @click="deleting = file.id"
                >
                    Remove</button
                ><span v-else
                    >Remove this attachment?
                    <button class="danger" :disabled="busy" @click="remove(file.id)">Remove</button>
                    <button @click="deleting = undefined">Keep</button></span
                >
            </li>
        </ul>
        <p v-if="!data?.data.length && !loadError" class="muted">No attachments yet.</p>
    </section>
</template>
