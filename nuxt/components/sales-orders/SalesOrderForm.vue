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
            <select v-model="line.itemId" required @change="updateAvailableQty(line)">
              <option value="">Select an item</option>
              <option v-for="item in items" :key="item.id" :value="item.id">
                {{ item.name }} (Available: {{ item.quantityAvailable }})
              </option>
            </select>
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
  items: any[]
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

watch(() => props.order, (newOrder) => {
  if (newOrder) {
    formOrder.value = {
      ...newOrder,
      orderDate: newOrder.orderDate.split('T')[0],
      lines: newOrder.lines || []
    }
  } else {
    resetForm()
  }
}, { immediate: true })

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

const addLine = () => {
  formOrder.value.lines.push({
    itemId: '',
    quantityOrdered: 1
  })
}

const removeLine = (index: number) => {
  formOrder.value.lines.splice(index, 1)
}

const getAvailableQty = (itemId: any) => {
  const item = props.items.find(i => i.id === itemId)
  return item?.quantityAvailable || 0
}

const updateAvailableQty = (line: any) => {
  const maxQty = getAvailableQty(line.itemId)
  if (line.quantityOrdered > maxQty) {
    line.quantityOrdered = maxQty
  }
}

const save = () => {
  emit('save', formOrder.value)
}
</script>
