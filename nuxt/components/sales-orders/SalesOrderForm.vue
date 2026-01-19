<template>
  <div class="card">
    <h3>{{ formOrder.id ? 'Edit' : 'Create' }} Sales Order</h3>
    
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
        <label>Notes</label>
        <textarea v-model="formOrder.notes" placeholder="Additional notes..."></textarea>
      </div>
      
      <div class="line-items">
        <h4>Line Items</h4>
        <div v-for="(line, index) in formOrder.lines" :key="index" class="line-item" style="grid-template-columns: 2fr 1fr auto;">
          <div class="form-group">
            <label>Item *</label>
            <ItemComboBox 
              v-model="line.itemId" 
              required 
              show-quantity
              placeholder="Search for an item..."
              @itemSelected="updateAvailableQty(line, $event)"
            />
          </div>
          
          <div class="form-group">
            <label>Quantity *</label>
            <input 
              v-model.number="line.quantityOrdered" 
              type="number" 
              min="1" 
              :max="getAvailableQty(line.itemId)"
              required 
            />
            <span v-if="getAvailableQty(line.itemId)" class="error" style="font-size: 12px;">
              Max: {{ getAvailableQty(line.itemId) }}
            </span>
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
  notes: '',
  lines: []
})

const resetForm = () => {
  formOrder.value = {
    id: null,
    orderNumber: '',
    orderDate: new Date().toISOString().split('T')[0],
    status: 'pending',
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
    availableQty: 0
  })
}

const removeLine = (index: number) => {
  formOrder.value.lines.splice(index, 1)
}

const getAvailableQty = (itemId: any) => {
  const line = formOrder.value.lines.find(l => l.itemId === itemId)
  return line?.availableQty || 0
}

const updateAvailableQty = (line: any, item: any) => {
  line.availableQty = item.quantityAvailable || 0
  const maxQty = line.availableQty
  if (line.quantityOrdered > maxQty) {
    line.quantityOrdered = maxQty
  }
}

const save = () => {
  emit('save', formOrder.value)
}
</script>
