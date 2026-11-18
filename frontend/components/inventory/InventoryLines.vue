<script setup lang="ts">
import type { InventoryLine } from '~/shared/inventory'
import InventorySelect from './InventorySelect.vue'
import PurchaseOrderItems from './PurchaseOrderItems.vue'
const props = defineProps<{ modelValue: InventoryLine[]; resource: string; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [InventoryLine[]] }>()
const api = useInventoryApi()
const priced = computed(() => ['orders', 'purchase-orders'].includes(props.resource))
function change(index: number, key: string, value: any) {
    emit(
        'update:modelValue',
        props.modelValue.map((line, i) =>
            i === index ? { ...line, [key]: value, ...(key === 'product_id' ? { product_variant_id: '' } : {}) } : line,
        ),
    )
}
async function pick(index: number, key: string, value: number | '') {
    change(index, key, value)
    if (!value || !priced.value) return
    const previousPrice = props.modelValue[index]?.unit_price
    try {
        const row = (await api.get(key === 'product_variant_id' ? 'variants' : 'products', value)).data
        if (props.modelValue[index]?.[key] === value && props.modelValue[index]?.unit_price === previousPrice)
            change(index, 'unit_price', String(props.resource === 'purchase-orders' ? row.cost : row.price))
    } catch {
        /* The user can enter a price while the service is unavailable. */
    }
}
function remove(index: number) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}
function add() {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            product_id: '',
            quantity: 1,
            unit_price: '0.00',
            tax: '0.00',
            discount_type: 'fixed',
            discount_value: '0.00',
            serialText: '',
        },
    ])
}
</script>
<template>
    <section class="line-editor">
        <header class="section-header">
            <div>
                <h2>Line items</h2>
                <p>
                    {{
                        resource === 'stock-audits'
                            ? 'Enter absolute counted quantities, including zero.'
                            : 'Select products and quantities for this document.'
                    }}
                </p>
            </div>
            <button type="button" :disabled="disabled || modelValue.length >= 500" @click="add">+ Add line</button>
        </header>
        <p v-if="!modelValue.length" class="empty-state">No items yet. Add a line to get started.</p>
        <PurchaseOrderItems
            v-else-if="resource === 'purchase-orders'"
            :items="modelValue"
            :disabled="disabled"
            editable
            @pick="pick"
            @change="change"
            @remove="remove"
        />
        <template v-else>
            <fieldset v-for="(line, index) in modelValue" :key="index" class="line-card" :disabled="disabled">
                <legend>Item {{ index + 1 }}</legend>
                <div class="form-grid">
                    <div class="field">
                        <label :for="`product-${index}`">Product *</label
                        ><InventorySelect
                            :id="`product-${index}`"
                            resource="products"
                            :model-value="line.product_id"
                            required
                            @update:model-value="pick(index, 'product_id', $event)"
                        />
                    </div>
                    <div class="field">
                        <label :for="`variant-${index}`">Variant</label
                        ><InventorySelect
                            :id="`variant-${index}`"
                            resource="variants"
                            :model-value="line.product_variant_id"
                            :filters="{ parent_id: line.product_id || undefined }"
                            :disabled="!line.product_id"
                            @update:model-value="pick(index, 'product_variant_id', $event)"
                        />
                    </div>
                    <div class="field">
                        <label :for="`quantity-${index}`">Quantity *</label
                        ><input
                            :id="`quantity-${index}`"
                            :value="line.quantity"
                            type="number"
                            :min="resource === 'stock-audits' ? 0 : 1"
                            step="1"
                            max="1000000000"
                            required
                            @input="change(index, 'quantity', Number(($event.target as HTMLInputElement).value))"
                        />
                    </div>
                    <div v-if="priced" class="field">
                        <label :for="`price-${index}`"
                            >Unit price *</label
                        ><input
                            :id="`price-${index}`"
                            :value="line.unit_price"
                            inputmode="decimal"
                            pattern="\d+(\.\d{1,2})?"
                            required
                            @input="change(index, 'unit_price', ($event.target as HTMLInputElement).value)"
                        />
                    </div>
                    <template v-if="resource === 'orders'"
                        ><div class="field">
                            <label :for="`tax-${index}`">Line tax</label
                            ><input
                                :id="`tax-${index}`"
                                :value="line.tax"
                                inputmode="decimal"
                                @input="change(index, 'tax', ($event.target as HTMLInputElement).value)"
                            />
                        </div>
                        <div class="field">
                            <label :for="`discount-type-${index}`">Line discount</label
                            ><select
                                :id="`discount-type-${index}`"
                                :value="line.discount_type"
                                @change="change(index, 'discount_type', ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="fixed">Fixed amount</option>
                                <option value="percent">Percentage</option>
                            </select>
                        </div>
                        <div class="field">
                            <label :for="`discount-${index}`">Discount value</label
                            ><input
                                :id="`discount-${index}`"
                                :value="line.discount_value"
                                inputmode="decimal"
                                @input="change(index, 'discount_value', ($event.target as HTMLInputElement).value)"
                            /></div
                    ></template>
                </div>
                <details>
                    <summary>Batch, serial and expiry tracking</summary>
                    <div class="form-grid">
                        <div class="field">
                            <label :for="`batch-${index}`">Batch</label
                            ><input
                                :id="`batch-${index}`"
                                :value="line.batch"
                                maxlength="100"
                                @input="change(index, 'batch', ($event.target as HTMLInputElement).value)"
                            />
                        </div>
                        <div class="field">
                            <label :for="`expiry-${index}`">Expiry date</label
                            ><input
                                :id="`expiry-${index}`"
                                :value="line.expires_at"
                                type="date"
                                @input="change(index, 'expires_at', ($event.target as HTMLInputElement).value)"
                            />
                        </div>
                        <div class="field wide">
                            <label :for="`serials-${index}`">Serials (one per line)</label
                            ><textarea
                                :id="`serials-${index}`"
                                :value="line.serialText"
                                rows="2"
                                @input="change(index, 'serialText', ($event.target as HTMLTextAreaElement).value)"
                            />
                        </div>
                    </div>
                </details>
                <button
                    type="button"
                    class="danger subtle"
                    @click="remove(index)"
                >
                    Remove item {{ index + 1 }}
                </button>
            </fieldset>
        </template>
    </section>
</template>
