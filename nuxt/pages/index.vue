<template>
  <div>
    <div class="header">
      <h2>Purchase Orders</h2>
      <button class="btn btn-primary" @click="showForm = true; editingOrder = null">
        Create Purchase Order
      </button>
    </div>

    <div v-if="!showForm" class="card">
      <table>
        <thead>
          <tr>
            <th>Order Number</th>
            <th>Date</th>
            <th>Reference</th>
            <th>Status</th>
            <th>Lines</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>{{ order.orderNumber }}</td>
            <td>{{ formatDate(order.orderDate) }}</td>
            <td>{{ order.reference || '-' }}</td>
            <td>{{ order.status }}</td>
            <td>{{ order.lines?.length || 0 }}</td>
            <td>
              <div class="actions">
                <button class="btn btn-secondary btn-small" @click="editOrder(order)">Edit</button>
                <button class="btn btn-danger btn-small" @click="deleteOrder(order.id)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="orders.length === 0" style="text-align: center; padding: 20px; color: #95a5a6;">
        No purchase orders found. Create one to get started!
      </div>
    </div>

    <PurchaseOrdersPurchaseOrderForm 
      v-else 
      :order="editingOrder" 
      :items="items"
      @save="handleSave" 
      @cancel="showForm = false; editingOrder = null" 
    />
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const orders = ref([])
const items = ref([])
const showForm = ref(false)
const editingOrder = ref(null)

const loadOrders = async () => {
  try {
    orders.value = await api.getPurchaseOrders()
  } catch (error) {
    console.error('Failed to load purchase orders:', error)
  }
}

const loadItems = async () => {
  try {
    items.value = await api.getItems()
  } catch (error) {
    console.error('Failed to load items:', error)
  }
}

const editOrder = async (order: any) => {
  try {
    const fullOrder = await api.getPurchaseOrder(order.id)
    editingOrder.value = fullOrder
    showForm.value = true
  } catch (error) {
    console.error('Failed to load order:', error)
  }
}

const deleteOrder = async (id: number) => {
  if (!confirm('Are you sure you want to delete this order?')) return
  
  try {
    await api.deletePurchaseOrder(id)
    await loadOrders()
  } catch (error) {
    console.error('Failed to delete order:', error)
  }
}

const handleSave = async (order: any) => {
  try {
    if (order.id) {
      await api.updatePurchaseOrder(order.id, order)
    } else {
      await api.createPurchaseOrder(order)
    }
    showForm.value = false
    editingOrder.value = null
    await loadOrders()
  } catch (error) {
    console.error('Failed to save order:', error)
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString()
}

onMounted(() => {
  loadOrders()
  loadItems()
})
</script>
