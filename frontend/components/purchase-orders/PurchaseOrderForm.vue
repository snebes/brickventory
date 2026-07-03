<template>
  <div class="card">
    <h3>{{ formOrder.id ? 'Edit' : 'Create' }} Purchase Order</h3>

    <form @submit.prevent="save">
      <div class="form-group">
        <label>Vendor *</label>
        <select v-model="formOrder.vendorId" required :disabled="isVendorLocked" class="vendor-select">
          <option value="">-- Select a Vendor --</option>
          <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
            {{ vendor.vendorCode }} - {{ vendor.vendorName }}
          </option>
        </select>
        <p v-if="isVendorLocked" class="help-text">
          Vendor cannot be changed after Purchase Order approval.
        </p>
      </div>

      <div v-if="selectedVendor" class="vendor-details">
        <small>
          <strong>Vendor Details:</strong>
          {{ selectedVendor.vendorName }}
          <span v-if="selectedVendor.defaultPaymentTerms"> | Terms: {{ selectedVendor.defaultPaymentTerms }}</span>
          <span v-if="selectedVendor.defaultCurrency"> | Currency: {{ selectedVendor.defaultCurrency }}</span>
        </small>
      </div>

      <div class="form-group">
        <label>Receiving Location *</label>
        <location-selector
          v-model="formOrder.locationId"
          :required="true"
          :disabled="isLocationLocked"
          filterType="receiving"
          placeholder="Select a receiving location"
        />
        <p v-if="isLocationLocked" class="help-text">
          Location cannot be changed after items have been received.
        </p>
      </div>

      <div v-if="selectedLocation" class="location-details">
        <small>
          <strong>Location Details:</strong>
          {{ selectedLocation.locationCode }} - {{ selectedLocation.locationName }}
          <span v-if="selectedLocation.locationType"> | Type: {{ selectedLocation.locationType }}</span>
        </small>
      </div>

      <div class="form-group">
        <label>Order Number (optional)</label>
        <input v-model="formOrder.orderNumber" type="text" placeholder="Auto-generated if left empty" />
      </div>

      <div class="form-group">
        <label>Order Date *</label>
        <input v-model="formOrder.orderDate" type="date" required />
      </div>

      <div class="form-group">
        <label>Status *</label>
        <select v-model="formOrder.status" required>
          <option value="Pending Approval">Pending Approval</option>
          <option value="Pending Receipt">Pending Receipt</option>
          <option value="Partially Received">Partially Received</option>
          <option value="Fully Received">Fully Received</option>
          <option value="Closed">Closed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="form-group">
        <label>Reference</label>
        <input v-model="formOrder.reference" type="text" placeholder="Vendor reference, PO number, etc." />
      </div>

      <div class="form-group">
        <label>Expected Receipt Date</label>
        <input v-model="formOrder.expectedReceiptDate" type="date" />
      </div>

      <div class="form-group">
        <label>Notes</label>
        <textarea v-model="formOrder.notes" placeholder="Additional notes..."></textarea>
      </div>

      <div class="line-items">
        <h4>Line Items</h4>
        <div class="table-wrapper">
          <table class="line-items-table">
            <thead>
              <tr>
                <th class="item-col">Item *</th>
                <th class="quantity-col">Quantity *</th>
                <th class="rate-col">Rate *</th>
                <th class="amount-col">Amount</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, index) in formOrder.lines" :key="index">
                <td class="item-col">
                  <ItemComboBox
                    v-model="line.itemId"
                    required
                    placeholder="Search for an item..."
                  />
                </td>
                <td class="quantity-col">
                  <input v-model.number="line.quantityOrdered" type="number" min="1" required class="quantity-input" />
                </td>
                <td class="rate-col">
                  <div class="currency-input">
                    <span class="currency-symbol">$</span>
                    <input v-model.number="line.rate" type="number" step="0.01" min="0" required />
                  </div>
                </td>
                <td class="amount-col">
                  {{ formatCurrency((line.quantityOrdered || 0) * (line.rate || 0)) }}
                </td>
                <td class="actions-col">
                  <button type="button" class="btn btn-danger btn-small" @click="removeLine(index)">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="formOrder.lines.length === 0" class="no-items">
          No items added yet. Click "Add Line" to add items.
        </div>
        <div class="line-items-footer">
          <button type="button" class="btn btn-secondary" @click="addLine">Add Line</button>
          <div v-if="formOrder.lines.length > 0" class="total-amount">
            <strong>Total: {{ formatCurrency(calculateTotal()) }}</strong>
          </div>
        </div>
      </div>

      <div class="actions" style="margin-top: 20px;">
        <button type="submit" class="btn btn-success" :disabled="!formOrder.vendorId || !formOrder.locationId">Save</button>
        <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { parseApiDateForInput } from '~/utils/dateUtils'
import LocationSelector from "~/components/locations/LocationSelector.vue";

const props = defineProps<{
  order?: any
}>()

const emit = defineEmits(['save', 'cancel'])
const api = useApi()

const vendors = ref<any[]>([])

const formOrder = ref({
  id: null,
  vendorId: null as number | null,
  locationId: null as number | null,
  orderNumber: '',
  orderDate: parseApiDateForInput(null),
  status: 'Pending Approval',
  reference: '',
  expectedReceiptDate: '',
  notes: '',
  lines: []
})

// Computed property to check if vendor field should be locked
const isVendorLocked = computed(() => {
  if (!formOrder.value.id) return false
  const lockedStatuses = ['Pending Receipt', 'Partially Received', 'Fully Received', 'Closed']
  return lockedStatuses.includes(formOrder.value.status)
})

// Computed property to check if location field should be locked
const isLocationLocked = computed(() => {
  if (!formOrder.value.id) return false
  const lockedStatuses = ['Partially Received', 'Fully Received']
  return lockedStatuses.includes(formOrder.value.status)
})

// Computed property to get selected vendor details
const selectedVendor = computed(() => {
  if (!formOrder.value.vendorId) return null
  return vendors.value.find(v => v.id === formOrder.value.vendorId)
})

// Computed property to get selected location details
const selectedLocation = ref<any>(null)

// Watch locationId and fetch details when it changes
watch(() => formOrder.value.locationId, async (newLocationId) => {
  if (newLocationId) {
    try {
      selectedLocation.value = await api.getLocation(newLocationId)
    } catch (error) {
      console.error('Failed to load location details:', error)
      selectedLocation.value = null
    }
  } else {
    selectedLocation.value = null
  }
}, { immediate: true })

const loadVendors = async () => {
  try {
    const response = await api.getActiveVendors()
    vendors.value = response?.vendors || response || []
  } catch (error) {
    console.error('Failed to load vendors:', error)
    vendors.value = []
  }
}

const resetForm = () => {
  formOrder.value = {
    id: null,
    vendorId: null,
    locationId: null,
    orderNumber: '',
    orderDate: parseApiDateForInput(null),
    status: 'Pending Approval',
    reference: '',
    expectedReceiptDate: '',
    notes: '',
    lines: []
  }
}

watch(() => props.order, (newOrder) => {
  if (newOrder) {
    formOrder.value = {
      ...newOrder,
      vendorId: newOrder.vendor?.id || newOrder.vendorId || null,
      locationId: newOrder.location?.id || newOrder.locationId || null,
      orderDate: parseApiDateForInput(newOrder.orderDate),
      expectedReceiptDate: newOrder.expectedReceiptDate ? parseApiDateForInput(newOrder.expectedReceiptDate) : '',
      status: newOrder.status || 'Pending Approval',
      lines: (newOrder.lines || []).map(line => ({
        ...line,
        itemId: line.item?.id || line.itemId
      }))
    }
  } else {
    resetForm()
  }
}, { immediate: true })

const addLine = () => {
  formOrder.value.lines.push({
    itemId: '',
    quantityOrdered: 1,
    rate: 0
  })
}

const removeLine = (index: number) => {
  formOrder.value.lines.splice(index, 1)
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0)
}

const calculateTotal = (): number => {
  return formOrder.value.lines.reduce((sum, line) => {
    const qty = line.quantityOrdered || 0
    const rate = line.rate || 0
    return sum + (qty * rate)
  }, 0)
}

const save = () => {
  if (!formOrder.value.vendorId) {
    alert('Vendor is required. Please select a vendor before saving the Purchase Order.')
    return
  }
  if (!formOrder.value.locationId) {
    alert('Receiving location is required. Please select a location before saving the Purchase Order.')
    return
  }
  emit('save', formOrder.value)
}

onMounted(() => {
  loadVendors()
})
</script>

<style scoped>
.vendor-select {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.vendor-details {
  margin-top: 5px;
  padding: 8px;
  background-color: #f8f9fa;
  border-radius: 4px;
  font-size: 0.9em;
}

.location-details {
  margin-top: 5px;
  padding: 8px;
  background-color: #e8f5e9;
  border-radius: 4px;
  font-size: 0.9em;
}

.help-text {
  margin-top: 5px;
  font-size: 0.85em;
  color: #666;
}

.line-items {
  margin-top: 20px;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 15px;
  background-color: #fafafa;
}

.line-items h4 {
  margin-bottom: 15px;
  color: #2c3e50;
}

.table-wrapper {
  overflow-x: auto;
  margin-bottom: 15px;
}

.line-items-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 4px;
  overflow: hidden;
}

.line-items-table th {
  background: #2c3e50;
  color: white;
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.line-items-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: middle;
}

.line-items-table tbody tr:hover {
  background-color: #f5f8fa;
}

.line-items-table tbody tr:last-child td {
  border-bottom: none;
}

.item-col {
  width: 40%;
  min-width: 200px;
}

.quantity-col {
  width: 15%;
  min-width: 100px;
}

.rate-col {
  width: 18%;
  min-width: 120px;
}

.amount-col {
  width: 15%;
  min-width: 100px;
  text-align: right;
  font-weight: 500;
  color: #2c3e50;
}

.actions-col {
  width: 12%;
  min-width: 80px;
  text-align: center;
}

.quantity-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  text-align: center;
}

.currency-input {
  display: flex;
  align-items: center;
  border: 1px solid #ddd;
  border-radius: 4px;
  background: white;
  overflow: hidden;
}

.currency-input .currency-symbol {
  padding: 8px 10px;
  background: #f0f0f0;
  color: #666;
  font-weight: 500;
  border-right: 1px solid #ddd;
}

.currency-input input {
  flex: 1;
  padding: 8px;
  border: none;
  outline: none;
  font-size: 14px;
  width: 100%;
}

.currency-input input:focus {
  box-shadow: inset 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.no-items {
  text-align: center;
  padding: 20px;
  color: #95a5a6;
  font-style: italic;
}

.line-items-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #ddd;
}

.total-amount {
  font-size: 16px;
  color: #2c3e50;
}
</style>
