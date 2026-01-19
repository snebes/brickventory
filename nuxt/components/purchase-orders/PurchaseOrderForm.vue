<template>
  <div class="card">
    <h3>{{ formOrder.id ? 'Edit' : 'Create' }} Purchase Order</h3>
    
    <form @submit.prevent="save">
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
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Reference</label>
        <input v-model="formOrder.reference" type="text" placeholder="Vendor reference, PO number, etc." />
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
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  order?: any
}>()

const emit = defineEmits(['save', 'cancel'])

const formOrder = ref({
  id: null,
  orderNumber: '',
  orderDate: new Date().toISOString().split('T')[0],
  status: 'pending',
  reference: '',
  notes: '',
  lines: []
})

const resetForm = () => {
  formOrder.value = {
    id: null,
    orderNumber: '',
    orderDate: new Date().toISOString().split('T')[0],
    status: 'pending',
    reference: '',
    notes: '',
    lines: []
  }
}

watch(() => props.order, (newOrder) => {
  if (newOrder) {
    formOrder.value = {
      ...newOrder,
      orderDate: newOrder.orderDate.split('T')[0],
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
  emit('save', formOrder.value)
}
</script>
