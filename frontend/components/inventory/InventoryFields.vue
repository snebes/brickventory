<script setup lang="ts">
import type { Field } from '~/shared/inventory'
import InventorySelect from './InventorySelect.vue'
import InventoryOptions from './InventoryOptions.vue'
import InventoryAttributes from './InventoryAttributes.vue'
defineProps<{ fields: Field[]; modelValue: Record<string, any>; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [Record<string, any>] }>()
function update(values: Record<string, any>, key: string, value: any) {
    emit('update:modelValue', { ...values, [key]: value })
}
</script>
<template>
    <div class="form-grid">
        <div
            v-for="field in fields"
            :key="field.key"
            class="field"
            :class="{
                wide: ['textarea', 'json', 'serials', 'options', 'attributes'].includes(field.type || ''),
                checkbox: field.type === 'checkbox',
            }"
        >
            <label :for="`field-${field.key}`">{{ field.label }}<span v-if="field.required"> *</span></label
            ><InventorySelect
                v-if="field.resource"
                :id="`field-${field.key}`"
                :resource="field.resource"
                :filters="
                    field.key === 'product_variant_id' ? { parent_id: modelValue.product_id || undefined } : undefined
                "
                :model-value="modelValue[field.key]"
                :required="field.required"
                :disabled="disabled"
                @update:model-value="update(modelValue, field.key, $event)"
            /><InventoryOptions
                v-else-if="field.type === 'options'"
                :model-value="modelValue[field.key]"
                :disabled="disabled"
                @update:model-value="update(modelValue, field.key, $event)"
            /><InventoryAttributes
                v-else-if="field.type === 'attributes'"
                :parent-id="modelValue.parent_id"
                :model-value="modelValue[field.key]"
                :disabled="disabled"
                @update:model-value="update(modelValue, field.key, $event)"
            /><select
                v-else-if="field.options"
                :id="`field-${field.key}`"
                :value="modelValue[field.key]"
                :disabled="disabled"
                @change="update(modelValue, field.key, ($event.target as HTMLSelectElement).value)"
            >
                <option value="">Select…</option>
                <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option></select
            ><textarea
                v-else-if="['textarea', 'json', 'serials'].includes(field.type || '')"
                :id="`field-${field.key}`"
                :value="modelValue[field.key]"
                :required="field.required"
                :disabled="disabled"
                rows="3"
                @input="update(modelValue, field.key, ($event.target as HTMLTextAreaElement).value)"
            /><input
                v-else-if="field.type === 'checkbox'"
                :id="`field-${field.key}`"
                type="checkbox"
                :checked="modelValue[field.key]"
                :disabled="disabled"
                @change="update(modelValue, field.key, ($event.target as HTMLInputElement).checked)"
            /><input
                v-else
                :id="`field-${field.key}`"
                :type="
                    field.type === 'money'
                        ? 'text'
                        : ['number', 'integer'].includes(field.type || '')
                          ? 'number'
                          : field.type || 'text'
                "
                :inputmode="field.type === 'money' ? 'decimal' : undefined"
                :min="field.type === 'number' ? 0 : undefined"
                :value="modelValue[field.key]"
                :required="field.required"
                :disabled="disabled"
                :maxlength="10000"
                @input="update(modelValue, field.key, ($event.target as HTMLInputElement).value)"
            />
        </div>
    </div>
</template>
