<script setup lang="ts">
import type { InventoryLine, InventoryRecord } from '~/shared/inventory'
import { display } from '~/utils/inventory'
import InventorySelect from './InventorySelect.vue'

const props = defineProps<{ items: InventoryLine[]; editable?: boolean; disabled?: boolean }>()
const emit = defineEmits<{
    pick: [index: number, field: 'product_id' | 'product_variant_id', value: number | '']
    change: [index: number, field: string, value: string | number]
    remove: [index: number]
}>()

const api = useInventoryApi()
const itemsByKey = reactive<Record<string, InventoryRecord>>({})
const pending = new Set<string>()
function resourceFor(line: InventoryLine) {
    return line.product_variant_id ? 'variants' : 'products'
}
function idFor(line: InventoryLine) {
    return line.product_variant_id || line.product_id
}
function keyFor(line: InventoryLine) {
    return `${resourceFor(line)}/${idFor(line)}`
}
async function loadItem(line: InventoryLine) {
    const id = idFor(line)
    const key = keyFor(line)
    if (!id || itemsByKey[key] || pending.has(key)) return
    pending.add(key)
    try {
        itemsByKey[key] = (await api.get(resourceFor(line), id)).data
    } catch {
        // Keep the item link usable if catalog data is unavailable.
    } finally {
        pending.delete(key)
    }
}
function loadItems() {
    for (const line of props.items) void loadItem(line)
}
onMounted(loadItems)
watch(() => props.items.map(keyFor).join(','), loadItems)
const trackingFields = [
    { key: 'supplier_sku', label: 'Supplier SKU', type: 'text', maxLength: 255 },
    { key: 'batch', label: 'Batch', type: 'text', maxLength: 100 },
    { key: 'expires_at', label: 'Expiry date', type: 'date' },
    { key: 'serialText', label: 'Serials (one per line)', type: 'textarea' },
]
</script>

<template>
    <div class="table-viewport purchase-order-items" tabindex="0" role="region" aria-label="Purchase order line items">
        <table>
            <thead>
                <tr>
                    <th scope="col">SKU</th>
                    <th scope="col">Product</th>
                    <th v-if="editable" scope="col">Variant</th>
                    <th scope="col">Quantity</th>
                    <th v-if="!editable" scope="col">Received</th>
                    <th scope="col">Unit cost</th>
                    <th v-for="field in trackingFields" :key="field.key" scope="col">
                        {{ field.key === 'serialText' ? 'Serials' : field.label }}
                    </th>
                    <th v-if="editable" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in items" :key="line.id ? `saved-${line.id}` : `new-${index}`">
                    <th scope="row" class="purchase-item-sku">
                        <NuxtLink
                            v-if="idFor(line)"
                            :to="`/${keyFor(line)}`"
                            :title="itemsByKey[keyFor(line)]?.sku"
                        >
                            {{ itemsByKey[keyFor(line)]?.sku || `#${idFor(line)}` }}
                        </NuxtLink>
                        <span v-else>—</span>
                    </th>
                    <td v-if="editable" class="purchase-item-picker">
                        <label class="sr-only" :for="`product-${index}`">Product *</label>
                        <InventorySelect
                            :id="`product-${index}`"
                            resource="products"
                            :model-value="line.product_id"
                            :disabled="disabled"
                            required
                            @update:model-value="emit('pick', index, 'product_id', $event)"
                        />
                    </td>
                    <td v-else>{{ itemsByKey[keyFor(line)]?.name || '—' }}</td>
                    <td v-if="editable" class="purchase-item-picker">
                        <label class="sr-only" :for="`variant-${index}`">Variant</label>
                        <InventorySelect
                            :id="`variant-${index}`"
                            resource="variants"
                            :model-value="line.product_variant_id"
                            :filters="{ parent_id: line.product_id || undefined }"
                            :disabled="disabled || !line.product_id"
                            @update:model-value="emit('pick', index, 'product_variant_id', $event)"
                        />
                    </td>
                    <td class="purchase-item-number">
                        <input
                            v-if="editable"
                            :id="`quantity-${index}`"
                            aria-label="Quantity *"
                            :value="line.quantity"
                            :disabled="disabled"
                            type="number"
                            min="1"
                            max="1000000000"
                            step="1"
                            required
                            @input="emit('change', index, 'quantity', Number(($event.target as HTMLInputElement).value))"
                        />
                        <template v-else>{{ line.quantity }}</template>
                    </td>
                    <td v-if="!editable" class="purchase-item-number">{{ line.received_quantity ?? 0 }}</td>
                    <td class="purchase-item-number">
                        <input
                            v-if="editable"
                            :id="`price-${index}`"
                            aria-label="Unit cost *"
                            :value="line.unit_price ?? line.unit_cost"
                            :disabled="disabled"
                            inputmode="decimal"
                            pattern="\d+(\.\d{1,2})?"
                            required
                            @input="emit('change', index, 'unit_price', ($event.target as HTMLInputElement).value)"
                        />
                        <template v-else>{{ line.unit_price ?? line.unit_cost ?? '—' }}</template>
                    </td>
                    <td v-for="field in trackingFields" :key="field.key" class="purchase-item-tracking">
                        <template v-if="editable">
                            <textarea
                                v-if="field.type === 'textarea'"
                                :aria-label="field.label"
                                :value="line[field.key]"
                                :disabled="disabled"
                                rows="1"
                                @input="emit('change', index, field.key, ($event.target as HTMLTextAreaElement).value)"
                            />
                            <input
                                v-else
                                :aria-label="field.label"
                                :type="field.type"
                                :maxlength="field.maxLength"
                                :value="line[field.key]"
                                :disabled="disabled"
                                @input="emit('change', index, field.key, ($event.target as HTMLInputElement).value)"
                            />
                        </template>
                        <template v-else>
                            {{
                                field.key === 'serialText'
                                    ? line.serials?.join(', ') || '—'
                                    : display(line[field.key], field.key)
                            }}
                        </template>
                    </td>
                    <td v-if="editable">
                        <button type="button" class="danger subtle" :disabled="disabled" @click="emit('remove', index)">
                            Remove item {{ index + 1 }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
