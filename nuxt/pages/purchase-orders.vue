<template>
  <div>
    <div class="header">
      <h2>Purchase Orders</h2>
      <button class="btn btn-primary" @click="showForm = true; editingOrder = null">
        Create Purchase Order
      </button>
    </div>

    <div v-if="!showForm && !showReceiveForm" class="card">
      <div class="filters" style="margin-bottom: 15px;">
        <select v-model="vendorFilter" @change="loadOrders" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
          <option value="">All Vendors</option>
          <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
            {{ vendor.vendorName }}
          </option>
        </select>
      </div>

      <table>
        <thead>
          <tr>
            <th>Order Number</th>
            <th>Vendor</th>
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
            <td>{{ order.vendor?.vendorName || '-' }}</td>
            <td>{{ formatDate(order.orderDate) }}</td>
            <td>{{ order.reference || '-' }}</td>
            <td>{{ order.status }}</td>
            <td>{{ order.lines?.length || 0 }}</td>
            <td>
              <div class="actions">
                <button class="btn btn-success btn-small" @click="receiveOrder(order)" :disabled="order.status === 'received'">Receive</button>
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
      v-if="showForm" 
      :order="editingOrder" 
      @save="handleSave" 
      @cancel="showForm = false; editingOrder = null" 
    />

    <ItemReceiptsReceiptForm
      v-if="showReceiveForm"
      :purchase-order="receivingOrder"
      @save="handleReceive"
      @cancel="showReceiveForm = false; receivingOrder = null"
    />
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const orders = ref([])
const vendors = ref([])
const vendorFilter = ref('')
const showForm = ref(false)
const editingOrder = ref(null)
const showReceiveForm = ref(false)
const receivingOrder = ref(null)

const loadVendors = async () => {
  try {
    const response = await api.getVendors()
    vendors.value = response?.vendors || response || []
  } catch (error) {
    console.error('Failed to load vendors:', error)
  }
}

const loadOrders = async () => {
  try {
    let url = '/api/purchase-orders'
    if (vendorFilter.value) {
      url += `?vendorId=${vendorFilter.value}`
    }
    orders.value = await $fetch(url)
  } catch (error) {
    console.error('Failed to load purchase orders:', error)
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

const receiveOrder = async (order: any) => {
  try {
    const fullOrder = await api.getPurchaseOrder(order.id)
    receivingOrder.value = fullOrder
    showReceiveForm.value = true
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
  } catch (error: any) {
    console.error('Failed to save order:', error)
    const errorMessage = error?.data?.error || error?.message || 'Unknown error'
    alert('Failed to save order: ' + errorMessage)
  }
}

const handleReceive = async (receiptData: any) => {
  try {
    await api.createItemReceipt(receiptData)
    showReceiveForm.value = false
    receivingOrder.value = null
    await loadOrders()
    alert('Items received successfully!')
  } catch (error) {
    console.error('Failed to receive items:', error)
    alert('Failed to receive items: ' + (error.message || 'Unknown error'))
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString()
}

onMounted(() => {
  loadVendors()
  loadOrders()
})
</script>
