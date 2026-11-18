<script setup lang="ts">
import { inventoryResources, type InventoryRecord, type InventoryLine } from '~/shared/inventory'
import { formValues, formPayload, linePayload, errorMessage, canEdit } from '~/utils/inventory'
import InventoryFields from './InventoryFields.vue'
import InventoryLines from './InventoryLines.vue'
import InventoryReturnLines from './InventoryReturnLines.vue'
const props = defineProps<{ resource: string; id?: string }>()
const config = inventoryResources[props.resource]!
const api = useInventoryApi()
const { data: record, error: loadError } = await useAsyncData(
    `edit-${props.resource}-${props.id || 'new'}`,
    async () => (props.id ? (await api.get(props.resource, props.id)).data : null),
)
if (record.value && !canEdit(props.resource, record.value))
    throw createError({ statusCode: 409, statusMessage: 'This record can no longer be edited.' })
const fields = computed(() =>
    config.fields.filter(
        (field) =>
            !props.id ||
            (props.resource === 'orders'
                ? !['location_id', 'approval_required'].includes(field.key)
                : props.resource === 'purchase-orders'
                  ? field.key !== 'approval_required'
                  : props.resource === 'stock-transfers'
                    ? field.key === 'notes'
                    : true),
    ),
)
const values = ref(formValues(fields.value, record.value || undefined))
const lines = ref<InventoryLine[]>(
    (record.value?.items || []).map((line) => ({ ...line, serialText: (line.serials || []).join('\n') })),
)
// A dashboard suggestion opens a reviewable draft; it never creates stock or a PO automatically.
const route = useRoute()
if (!props.id && props.resource === 'purchase-orders') {
    const productId = Number(route.query.product_id)
    const quantity = Number(route.query.quantity)
    const supplierId = Number(route.query.supplier_id)
    if (
        Number.isSafeInteger(productId) &&
        productId > 0 &&
        Number.isSafeInteger(quantity) &&
        quantity > 0 &&
        quantity <= 1000000000
    ) {
        const unitCost =
            typeof route.query.unit_cost === 'string' && /^\d{1,10}(?:\.\d{1,2})?$/.test(route.query.unit_cost)
                ? route.query.unit_cost
                : '0.00'
        const variantId = Number(route.query.product_variant_id)
        lines.value = [
            {
                product_id: productId,
                ...(Number.isSafeInteger(variantId) && variantId > 0 ? { product_variant_id: variantId } : {}),
                quantity,
                unit_price: unitCost,
                serialText: '',
            },
        ]
        if (Number.isSafeInteger(supplierId) && supplierId > 0) values.value.supplier_id = supplierId
        if (typeof route.query.currency === 'string' && /^[A-Z]{3}$/.test(route.query.currency))
            values.value.currency = route.query.currency
    }
}
const busy = ref(false),
    error = ref('')
useHead({ title: `${props.id ? 'Edit' : 'New'} ${config.singular.toLowerCase()} · Brickventory` })
async function save() {
    busy.value = true
    error.value = ''
    try {
        const body = formPayload(fields.value, values.value)
        if (
            !props.id &&
            props.resource === 'purchase-orders' &&
            route.query.variant_required === '1' &&
            lines.value.some((line) => line.product_id === Number(route.query.product_id) && !line.product_variant_id)
        )
            throw new Error('Choose a variant for the product being replenished before saving.')
        if (config.document)
            body.items =
                props.resource === 'order-returns'
                    ? lines.value
                          .filter((line) => line.quantity > 0)
                          .map((line) => {
                              if (!line.receiving_location_id)
                                  throw new Error('Choose a receiving location for each return.')
                              return {
                                  id: line.id,
                                  quantity: Number(line.quantity),
                                  receiving_location_id: Number(line.receiving_location_id),
                                  ...(line.batch ? { batch: line.batch } : {}),
                                  ...(line.serialText
                                      ? {
                                            serials: line.serialText
                                                .split(/\r?\n/)
                                                .map((s: string) => s.trim())
                                                .filter(Boolean),
                                        }
                                      : {}),
                              }
                          })
                    : linePayload(props.resource, lines.value)
        if (props.resource === 'orders' && !body.customer_id && !body.customer_name)
            throw new Error('Choose a customer or enter a customer name.')
        const result = props.id
            ? await api.update(props.resource, Number(props.id), body)
            : await api.create(props.resource, body)
        await navigateTo(`/${props.resource}/${result.data.id}`)
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <div>
        <NuxtLink :to="`/${resource}`" class="back-link">← Back to {{ config.title.toLowerCase() }}</NuxtLink>
        <header class="page-header">
            <div>
                <p class="eyebrow">{{ config.title }}</p>
                <h1>{{ id ? 'Edit' : 'New' }} {{ config.singular.toLowerCase() }}</h1>
                <p>{{ config.description }}</p>
            </div>
        </header>
        <div v-if="loadError" class="error" role="alert">
            {{ errorMessage(loadError) }} <NuxtLink :to="`/${resource}`">Return to list</NuxtLink>
        </div>
        <form v-else class="panel editor" @submit.prevent="save">
            <InventoryFields v-model="values" :fields="fields" :disabled="busy" /><InventoryReturnLines
                v-if="resource === 'order-returns'"
                v-model="lines"
                :order-id="values.order_id"
                :disabled="busy"
            /><InventoryLines v-else-if="config.document" v-model="lines" :resource="resource" :disabled="busy" />
            <p v-if="error" class="error" role="alert">{{ error }}</p>
            <footer class="form-footer">
                <NuxtLink class="button" :to="id ? `/${resource}/${id}` : `/${resource}`">Cancel</NuxtLink
                ><button class="primary" :disabled="busy">
                    {{ busy ? 'Saving…' : `Save ${config.singular.toLowerCase()}` }}
                </button>
            </footer>
        </form>
    </div>
</template>
