<script setup lang="ts">
import type { InventoryRecord } from '~/shared/inventory'
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{
    resource: string
    modelValue?: number | string
    required?: boolean
    id: string
    filters?: Record<string, any>
    disabled?: boolean
}>()
const emit = defineEmits<{ 'update:modelValue': [number | ''] }>()
const api = useInventoryApi()
const search = ref(''),
    page = ref(1),
    total = ref(0),
    loading = ref(false),
    error = ref('')
const rows = ref<InventoryRecord[]>([]),
    selected = ref<InventoryRecord>()
let generation = 0
async function load() {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
        const result = await api.list(props.resource, {
            search: search.value || undefined,
            page: page.value,
            per_page: 20,
            ...props.filters,
        })
        if (current === generation) {
            rows.value = result.data
            total.value = result.meta.total
        }
        if (props.modelValue && !rows.value.some((row) => row.id === Number(props.modelValue)))
            selected.value = (await api.get(props.resource, Number(props.modelValue))).data
    } catch (cause) {
        if (current === generation) error.value = errorMessage(cause)
    } finally {
        if (current === generation) loading.value = false
    }
}
watch(() => [props.resource, props.filters, page.value], load, { deep: true })
watch(
    () => props.modelValue,
    (value) => {
        if (value) selected.value = rows.value.find((row) => row.id === Number(value)) || selected.value
        else selected.value = undefined
    },
)
onMounted(load)
function searchOptions() {
    page.value = 1
    load()
}
</script>
<template>
    <div class="lookup">
        <div class="lookup-search">
            <input
                v-model="search"
                :aria-label="`Search ${resource}`"
                type="search"
                placeholder="Search…"
                :disabled="disabled"
                @keydown.enter.prevent="searchOptions"
            /><button type="button" :disabled="loading || disabled" @click="searchOptions">Find</button>
        </div>
        <select
            :id="id"
            :value="modelValue ?? ''"
            :required="required"
            :disabled="disabled"
            @change="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value
                        ? Number(($event.target as HTMLSelectElement).value)
                        : '',
                )
            "
        >
            <option value="">Select {{ resource.replaceAll('-', ' ') }}</option>
            <option v-if="selected && !rows.some((row) => row.id === selected?.id)" :value="selected.id">
                {{ selected.sku || selected.number || '' }} {{ selected.name || `#${selected.id}` }}
            </option>
            <option v-for="row in rows" :key="row.id" :value="row.id">
                {{ row.sku || row.number || row.code || '' }} {{ row.name || `#${row.id}` }}
            </option>
        </select>
        <div v-if="total > 20" class="lookup-pages">
            <button type="button" :disabled="page === 1 || loading" @click="page--">Previous</button
            ><small>Page {{ page }} of {{ Math.ceil(total / 20) }}</small
            ><button type="button" :disabled="page * 20 >= total || loading" @click="page++">Next</button>
        </div>
        <small v-if="loading" role="status">Loading choices…</small
        ><small v-if="error" class="error" role="alert"
            >{{ error }} <button type="button" @click="load">Retry</button></small
        >
    </div>
</template>
