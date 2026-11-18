<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
const props = defineProps<{ parentId?: number | string; modelValue?: string; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()
const api = useInventoryApi(),
    options = ref<Record<string, string[]>>({}),
    error = ref(''),
    loading = ref(false)
const values = ref<Record<string, string>>(JSON.parse(props.modelValue || '{}'))
let generation = 0
watch(
    () => props.parentId,
    async (id, previous) => {
        const version = ++generation
        options.value = {}
        error.value = ''
        if (previous && previous !== id) values.value = {}
        if (!id) return
        loading.value = true
        try {
            const product = (await api.get('products', id)).data
            if (version === generation) {
                options.value = product.details?.options || {}
                emit('update:modelValue', JSON.stringify(values.value))
            }
        } catch (cause) {
            if (version === generation) error.value = errorMessage(cause)
        } finally {
            if (version === generation) loading.value = false
        }
    },
    { immediate: true },
)
</script>
<template>
    <div>
        <p v-if="!parentId" class="muted">Choose a parent product to see its options.</p>
        <p v-if="loading" role="status">Loading product options…</p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <div class="form-grid">
            <div v-for="(choices, key) in options" :key="key" class="field">
                <label :for="`attribute-${key}`">{{ key }} *</label>
                <select
                    :id="`attribute-${key}`"
                    v-model="values[key]"
                    :disabled="disabled"
                    required
                    @change="emit('update:modelValue', JSON.stringify(values))"
                >
                    <option value="">Choose {{ key }}</option>
                    <option v-for="choice in choices" :key="choice" :value="choice">{{ choice }}</option>
                </select>
            </div>
        </div>
    </div>
</template>
