<template>
  <div>
    <div class="header">
      <h2>Item Receipts</h2>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Receipt Date</th>
            <th>Purchase Order</th>
            <th>Reference</th>
            <th>Status</th>
            <th>Items</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="receipt in receipts" :key="receipt.id">
            <td>{{ formatDate(receipt.receiptDate) }}</td>
            <td>{{ receipt.purchaseOrder.orderNumber }}</td>
            <td>{{ receipt.purchaseOrder.reference || '-' }}</td>
            <td>{{ receipt.status }}</td>
            <td>{{ receipt.lines?.length || 0 }}</td>
            <td>
              <div class="actions">
                <button class="btn btn-secondary btn-small" @click="viewReceipt(receipt)">View</button>
                <button class="btn btn-danger btn-small" @click="deleteReceipt(receipt.id)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="receipts.length === 0" style="text-align: center; padding: 20px; color: #95a5a6;">
        No item receipts found. Receive items from purchase orders to create receipts.
      </div>
    </div>

    <!-- View Receipt Modal -->
    <div v-if="viewingReceipt" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Receipt Details</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="detail-row">
            <strong>Purchase Order:</strong> {{ viewingReceipt.purchaseOrder.orderNumber }}
          </div>
          <div class="detail-row" v-if="viewingReceipt.purchaseOrder.reference">
            <strong>Reference:</strong> {{ viewingReceipt.purchaseOrder.reference }}
          </div>
          <div class="detail-row">
            <strong>Receipt Date:</strong> {{ formatDate(viewingReceipt.receiptDate) }}
          </div>
          <div class="detail-row">
            <strong>Status:</strong> {{ viewingReceipt.status }}
          </div>
          <div class="detail-row" v-if="viewingReceipt.notes">
            <strong>Notes:</strong> {{ viewingReceipt.notes }}
          </div>
          
          <h4 style="margin-top: 20px;">Items Received</h4>
          <table style="margin-top: 10px;">
            <thead>
              <tr>
                <th>Item</th>
                <th>Quantity Received</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in viewingReceipt.lines" :key="line.id">
                <td>{{ line.item.itemName }}</td>
                <td>{{ line.quantityReceived }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const receipts = ref([])
const viewingReceipt = ref(null)

const loadReceipts = async () => {
  try {
    receipts.value = await api.getItemReceipts()
  } catch (error) {
    console.error('Failed to load item receipts:', error)
  }
}

const viewReceipt = async (receipt: any) => {
  try {
    const fullReceipt = await api.getItemReceipt(receipt.id)
    viewingReceipt.value = fullReceipt
  } catch (error) {
    console.error('Failed to load receipt:', error)
  }
}

const closeModal = () => {
  viewingReceipt.value = null
}

const deleteReceipt = async (id: number) => {
  if (!confirm('Are you sure you want to delete this receipt?')) return
  
  try {
    await api.deleteItemReceipt(id)
    await loadReceipts()
  } catch (error) {
    console.error('Failed to delete receipt:', error)
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString()
}

onMounted(() => {
  loadReceipts()
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  padding: 0;
  max-width: 800px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #ddd;
}

.modal-header h3 {
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #95a5a6;
}

.close-btn:hover {
  color: #34495e;
}

.modal-body {
  padding: 20px;
}

.detail-row {
  margin-bottom: 12px;
}

.detail-row strong {
  display: inline-block;
  min-width: 120px;
}
</style>
