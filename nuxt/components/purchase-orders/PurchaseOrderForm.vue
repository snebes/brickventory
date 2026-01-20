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
        <div v-for="(line, index) in formOrder.lines" :key="index" class="line-item">
          <div class="form-group">
            <label>Item *</label>
            <ItemComboBox 
              v-model="line.itemId" 
              required 
              placeholder="Search for an item..."
            />
          </div>
          
          <div class="form-group">
            <label>Quantity *</label>
            <input v-model.number="line.quantityOrdered" type="number" min="1" required />
          </div>
          
          <div class="form-group">
            <label>Rate *</label>
            <input v-model.number="line.rate" type="number" step="0.01" min="0" required />
          </div>
          
          <button type="button" class="btn btn-danger" @click="removeLine(index)">Remove</button>
        </div>
        
        <button type="button" class="btn btn-secondary" @click="addLine">Add Line</button>
      </div>
      
      <div class="actions" style="margin-top: 20px;">
        <button type="submit" class="btn btn-success" :disabled="!formOrder.vendorId">Save</button>
        <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { parseApiDateForInput } from '~/utils/dateUtils'

const props = defineProps<{
  order?: any
}>()

const emit = defineEmits(['save', 'cancel'])
const api = useApi()

const vendors = ref<any[]>([])

const formOrder = ref({
  id: null,
  vendorId: null as number | null,
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

// Computed property to get selected vendor details
const selectedVendor = computed(() => {
  if (!formOrder.value.vendorId) return null
  return vendors.value.find(v => v.id === formOrder.value.vendorId)
})

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

const save = () => {
  if (!formOrder.value.vendorId) {
    alert('Vendor is required. Please select a vendor before saving the Purchase Order.')
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

.help-text {
  margin-top: 5px;
  font-size: 0.85em;
  color: #666;
}
</style>
