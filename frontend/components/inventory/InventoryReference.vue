<script setup lang="ts">
const props = defineProps<{ resource: string; id: number | string }>()
const api = useInventoryApi()
const cache = useState<Record<string, { label: string; resource: string }>>('inventory-labels', () => ({}))
const key = computed(() => `${props.resource}/${props.id}`)
const item = computed(() => cache.value[key.value])
async function load() {
    if (item.value || !props.id) return
    let target = props.resource
    try {
        let response
        try {
            response = await api.get(target, props.id)
        } catch (error: any) {
            if (target !== 'products' || error?.statusCode !== 404) throw error
            target = 'variants'
            response = await api.get(target, props.id)
        }
        const row = response.data
        cache.value[key.value] = {
            label: [row.sku || row.code, row.name || row.number || `#${row.id}`].filter(Boolean).join(' · '),
            resource: target,
        }
    } catch {
        /* The numeric reference remains usable if its label cannot be loaded. */
    }
}
onMounted(load)
watch(key, load)
</script>
<template>
    <NuxtLink :to="`/${item?.resource || resource}/${id}`">{{ item?.label || `#${id}` }}</NuxtLink>
</template>
