<script setup lang="ts">
import type { InventoryRecord } from '~/shared/inventory'
import { errorMessage, label } from '~/utils/inventory'
import InventorySelect from './InventorySelect.vue'
const props = defineProps<{ resource: string; record: InventoryRecord; action: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const api = useInventoryApi(),
    dialog = ref<HTMLDialogElement>(),
    busy = ref(false),
    error = ref('')
const values = reactive({
    amount: '',
    reference: '',
    method: '',
    reason: '',
    to: '',
    cc: '',
    message: '',
    tracking_number: '',
    carrier: '',
})
const entries = ref(
    (props.record.items || []).map((line) => ({
        ...line,
        selectedQuantity: props.action === 'ship' ? Math.max(0, line.quantity - (line.shipped_quantity || 0)) : 0,
        location: '',
        serialText: '',
    })),
)
const isReceipt = computed(() => props.action === 'receive' && props.resource === 'purchase-orders')
const isReturn = computed(() => props.action === 'return')
const isShipment = computed(() => props.action === 'ship' && props.resource === 'orders')
const isMail = computed(() => ['send', 'email', 'email-invoice'].includes(props.action))
onMounted(() => dialog.value?.showModal())
async function submit() {
    busy.value = true
    error.value = ''
    try {
        let body: Record<string, any> = {}
        if (['pay', 'refund'].includes(props.action))
            body = {
                amount: values.amount,
                ...(values.reference ? { reference: values.reference } : {}),
                ...(values.method ? { method: values.method } : {}),
            }
        if (props.action === 'reject' && values.reason) body.reason = values.reason
        if (isMail.value)
            body = {
                ...(values.to ? { to: values.to } : {}),
                ...(values.cc
                    ? {
                          cc: values.cc
                              .split(',')
                              .map((v) => v.trim())
                              .filter(Boolean),
                      }
                    : {}),
                ...(values.message ? { message: values.message } : {}),
            }
        if (isReceipt.value || isReturn.value || isShipment.value) {
            body.items = entries.value
                .filter((line) => line.selectedQuantity > 0)
                .map((line) => {
                    const result: Record<string, any> = {
                        id: line.id,
                        [isReceipt.value ? 'quantity_to_receive' : 'quantity']: Number(line.selectedQuantity),
                    }
                    if (isReceipt.value || isReturn.value) {
                        if (!line.location) throw new Error('Choose a receiving location for every selected line.')
                        result.receiving_location_id = Number(line.location)
                    }
                    if (line.batch) result.batch = line.batch
                    if (line.serialText)
                        result.serials = line.serialText
                            .split(/\r?\n/)
                            .map((s) => s.trim())
                            .filter(Boolean)
                    if (line.expires_at && isReceipt.value) result.expires_at = line.expires_at
                    if (isShipment.value) delete result.batch
                    return result
                })
            if (!body.items.length) throw new Error('Enter a quantity on at least one line.')
        }
        if (isShipment.value || (props.action === 'ship' && props.resource === 'stock-transfers')) {
            if (values.tracking_number) body.tracking_number = values.tracking_number
            if (values.carrier) body.carrier = values.carrier
        }
        await api.action(props.resource, props.record.id, props.action, body)
        emit('saved')
    } catch (cause) {
        error.value = errorMessage(cause)
    } finally {
        busy.value = false
    }
}
function maximum(line: any) {
    return isReceipt.value
        ? line.quantity - (line.received_quantity || 0)
        : isShipment.value
          ? line.quantity - (line.shipped_quantity || 0)
          : (line.shipped_quantity ??
                (['shipped', 'delivered'].includes(props.record.status || '') ? line.quantity : 0)) -
            (line.returned_quantity || 0)
}
</script>
<template>
    <dialog ref="dialog" class="action-dialog" aria-labelledby="action-title" @cancel.prevent="!busy && emit('close')">
        <form @submit.prevent="submit">
            <header class="section-header">
                <h2 id="action-title">{{ label(action) }} {{ record.number || `#${record.id}` }}</h2>
                <button type="button" aria-label="Close dialog" :disabled="busy" @click="emit('close')">×</button>
            </header>
            <p v-if="!isReceipt && !isReturn && !isShipment && !isMail && !['pay', 'refund'].includes(action)">
                Confirm this action for {{ record.number || `record #${record.id}` }}.
            </p>
            <div v-if="['pay', 'refund'].includes(action)" class="form-grid">
                <label
                    >Amount<input
                        v-model="values.amount"
                        inputmode="decimal"
                        pattern="\d+(\.\d{1,2})?"
                        required /></label
                ><label>Reference<input v-model="values.reference" /></label
                ><label>Method<input v-model="values.method" placeholder="Bank transfer, cash…" /></label>
            </div>
            <label v-if="action === 'reject'">Reason<textarea v-model="values.reason" /></label>
            <div v-if="isMail" class="form-grid">
                <label>To (leave blank for contact on file)<input v-model="values.to" type="email" /></label
                ><label>CC (comma-separated)<input v-model="values.cc" /></label
                ><label class="wide">Message<textarea v-model="values.message" maxlength="4000" /></label>
            </div>
            <div v-if="action === 'ship'" class="form-grid">
                <label>Tracking number<input v-model="values.tracking_number" /></label
                ><label>Carrier<input v-model="values.carrier" /></label>
            </div>
            <template v-if="isReceipt || isReturn || isShipment"
                ><p>Enter quantities for selected lines. Leave other quantities at zero.</p>
                <fieldset v-for="line in entries" :key="line.id" class="line-card" :disabled="busy">
                    <legend>Line {{ line.id }} · Product #{{ line.product_id }}</legend>
                    <div class="form-grid">
                        <label
                            >Quantity (up to {{ maximum(line) }})<input
                                v-model.number="line.selectedQuantity"
                                type="number"
                                min="0"
                                :max="maximum(line)"
                                step="1"
                                required
                        /></label>
                        <div v-if="!isShipment" class="field">
                            <label :for="`receive-${line.id}`">Receiving location</label
                            ><InventorySelect
                                :id="`receive-${line.id}`"
                                v-model="line.location"
                                resource="locations"
                                :required="line.selectedQuantity > 0"
                            />
                        </div>
                        <label v-if="isReceipt">Batch<input v-model="line.batch" /></label
                        ><label v-if="isReceipt">Expiry date<input v-model="line.expires_at" type="date" /></label
                        ><label class="wide"
                            >Serials (one per line)<textarea
                                v-model="line.serialText"
                                :placeholder="isShipment ? 'Leave blank to use the remaining allocated serials' : ''"
                            />
                        </label>
                    </div></fieldset
            ></template>
            <p v-if="error" class="error" role="alert">{{ error }}</p>
            <footer class="form-footer">
                <button type="button" :disabled="busy" @click="emit('close')">Back</button
                ><button class="primary" :disabled="busy">
                    {{ busy ? 'Saving…' : `Confirm ${label(action).toLowerCase()}` }}
                </button>
            </footer>
        </form>
    </dialog>
</template>
