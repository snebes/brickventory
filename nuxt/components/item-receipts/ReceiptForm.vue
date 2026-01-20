<template>
  <div class="card">
    <h3>Receive Items from Purchase Order</h3>
    
    <form @submit.prevent="save">
      <div class="form-group">
        <label>Purchase Order *</label>
        <div v-if="purchaseOrder" class="po-info">
          <strong>{{ purchaseOrder.orderNumber }}</strong>
          <span v-if="purchaseOrder.reference"> - {{ purchaseOrder.reference }}</span>
        </div>
        <p v-else class="error">Purchase order not loaded</p>
      </div>

      <div class="form-group">
        <label>Receiving Location *</label>
        <div v-if="purchaseOrder && purchaseOrder.location" class="location-info">
          <p class="help-text">
            This Purchase Order is set to receive at: <strong>{{ purchaseOrder.location.locationCode }} - {{ purchaseOrder.location.locationName }}</strong>
          </p>
        </div>
        <LocationSelector
          v-model="formReceipt.locationId"
          :required="true"
          filterType="receiving"
          placeholder="Select a receiving location"
        />
        <p class="help-text">
          You can override the default location if needed. Items will be received at the selected location.
        </p>
      </div>
      
      <div class="form-group">
        <label>Receipt Date *</label>
        <input v-model="formReceipt.receiptDate" type="date" required />
      </div>
      
      <div class="form-group">
        <label>Notes</label>
        <textarea v-model="formReceipt.notes" placeholder="Additional notes about this receipt..."></textarea>
      </div>
      
      <div class="line-items">
        <h4>Items to Receive</h4>
        <table v-if="purchaseOrder && purchaseOrder.lines && purchaseOrder.lines.length > 0">
          <thead>
            <tr>
              <th>Item</th>
              <th>Ordered</th>
              <th>Already Received</th>
              <th>Remaining</th>
              <th>Receive Now</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, index) in purchaseOrder.lines" :key="line.id">
              <td>{{ line.item.itemName }}</td>
              <td>{{ line.quantityOrdered }}</td>
              <td>{{ line.quantityReceived }}</td>
              <td>{{ line.quantityOrdered - line.quantityReceived }}</td>
              <td>
                <input 
                  v-model.number="receiveQuantities[line.id]" 
                  type="number" 
                  :max="line.quantityOrdered - line.quantityReceived"
                  min="0"
                  :disabled="line.quantityOrdered - line.quantityReceived <= 0"
                  style="width: 100px;"
                />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="error">No purchase order lines available</p>
      </div>
      
      <div class="actions" style="margin-top: 20px;">
        <button type="submit" class="btn btn-success" :disabled="!hasItemsToReceive">Receive Items</button>
        <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  purchaseOrder: any
}>()

const emit = defineEmits(['save', 'cancel'])

const formReceipt = ref({
  receiptDate: new Date().toISOString().split('T')[0],
  locationId: null as number | null,
  notes: '',
  status: 'received'
})

const receiveQuantities = ref<Record<number, number>>({})

// Initialize receive quantities to 0 for all lines and set default location
watch(() => props.purchaseOrder, (po) => {
  if (po && po.lines) {
    const quantities: Record<number, number> = {}
    po.lines.forEach((line: any) => {
      quantities[line.id] = 0
    })
    receiveQuantities.value = quantities
    
    // Default location to PO's location
    if (po.location && po.location.id) {
      formReceipt.value.locationId = po.location.id
    }
  }
}, { immediate: true })

const hasItemsToReceive = computed(() => {
  return Object.values(receiveQuantities.value).some(qty => qty > 0)
})

const save = () => {
  if (!props.purchaseOrder) {
    alert('Purchase order not loaded')
    return
  }

  // Build receipt lines from quantities
  const lines = []
  for (const [lineId, quantity] of Object.entries(receiveQuantities.value)) {
    if (quantity > 0) {
      lines.push({
        purchaseOrderLineId: parseInt(lineId),
        quantityReceived: quantity
      })
    }
  }

  if (lines.length === 0) {
    alert('Please enter at least one quantity to receive')
    return
  }

  const receiptData = {
    purchaseOrderId: props.purchaseOrder.id,
    locationId: formReceipt.value.locationId,
    receiptDate: formReceipt.value.receiptDate,
    notes: formReceipt.value.notes,
    status: formReceipt.value.status,
    lines: lines
  }

  emit('save', receiptData)
}
</script>

<style scoped>
.po-info {
  padding: 10px;
  background: #ecf0f1;
  border-radius: 4px;
  margin-top: 5px;
}

.location-info {
  padding: 10px;
  background: #e8f5e9;
  border-radius: 4px;
  margin-bottom: 10px;
}

.help-text {
  margin-top: 5px;
  font-size: 0.85em;
  color: #666;
}

.error {
  color: #e74c3c;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}

th, td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

th {
  background: #34495e;
  color: white;
}

tbody tr:hover {
  background: #f8f9fa;
}

input[type="number"] {
  padding: 5px;
  border: 1px solid #bdc3c7;
  border-radius: 4px;
}

input[type="number"]:disabled {
  background: #ecf0f1;
  cursor: not-allowed;
}
</style>
