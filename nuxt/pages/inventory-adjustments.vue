<template>
  <div>
    <div class="header">
      <h2>Inventory Adjustments</h2>
      <button class="btn btn-primary" @click="showForm = true">
        New Adjustment
      </button>
    </div>

    <div v-if="!showForm" class="card">
      <table>
        <thead>
          <tr>
            <th>Adjustment #</th>
            <th>Date</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Items</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="adjustment in adjustments" :key="adjustment.id">
            <td><strong>{{ adjustment.adjustmentNumber }}</strong></td>
            <td>{{ formatDate(adjustment.adjustmentDate) }}</td>
            <td>{{ formatReason(adjustment.reason) }}</td>
            <td>
              <span :class="['status-badge', `status-${adjustment.status}`]">
                {{ adjustment.status }}
              </span>
            </td>
            <td>{{ adjustment.lines?.length || 0 }}</td>
            <td>
              <div class="actions">
                <button class="btn btn-secondary btn-small" @click="viewAdjustment(adjustment)">View</button>
                <button class="btn btn-danger btn-small" @click="deleteAdjustment(adjustment.id)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="adjustments.length === 0 && !loading" style="text-align: center; padding: 20px; color: #95a5a6;">
        No inventory adjustments found. Create one to manually adjust inventory.
      </div>
      <div v-if="loading" style="text-align: center; padding: 20px; color: #95a5a6;">
        Loading...
      </div>
    </div>

    <InventoryAdjustmentsInventoryAdjustmentForm 
      v-if="showForm"
      @save="handleSave" 
      @cancel="showForm = false" 
    />

    <!-- View Adjustment Modal -->
    <div v-if="viewingAdjustment" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Adjustment Details</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="detail-row">
            <strong>Adjustment #:</strong> {{ viewingAdjustment.adjustmentNumber }}
          </div>
          <div class="detail-row">
            <strong>Date:</strong> {{ formatDate(viewingAdjustment.adjustmentDate) }}
          </div>
          <div class="detail-row">
            <strong>Reason:</strong> {{ formatReason(viewingAdjustment.reason) }}
          </div>
          <div class="detail-row">
            <strong>Status:</strong> {{ viewingAdjustment.status }}
          </div>
          <div class="detail-row" v-if="viewingAdjustment.memo">
            <strong>Memo:</strong> {{ viewingAdjustment.memo }}
          </div>
          
          <h4 style="margin-top: 20px;">Adjustment Lines</h4>
          <table style="margin-top: 10px;">
            <thead>
              <tr>
                <th>Item</th>
                <th>Quantity Change</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in viewingAdjustment.lines" :key="line.id">
                <td>{{ line.item.itemName }} ({{ line.item.itemId }})</td>
                <td :class="line.quantityChange > 0 ? 'positive-qty' : 'negative-qty'">
                  {{ line.quantityChange > 0 ? '+' : '' }}{{ line.quantityChange }}
                </td>
                <td>{{ line.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface AdjustmentLine {
  id: number
  item: {
    id: number
    itemId: string
    itemName: string
  }
  quantityChange: number
  notes?: string
}

interface Adjustment {
  id: number
  uuid: string
  adjustmentNumber: string
  adjustmentDate: string
  reason: string
  memo?: string
  status: string
  lines: AdjustmentLine[]
}

const api = useApi()
const adjustments = ref<Adjustment[]>([])
const showForm = ref(false)
const viewingAdjustment = ref<Adjustment | null>(null)
const loading = ref(false)

const reasons: Record<string, string> = {
  'physical_count': 'Physical Count',
  'damaged': 'Damaged Goods',
  'lost': 'Lost/Missing',
  'found': 'Found/Recovered',
  'correction': 'Correction',
  'transfer_in': 'Transfer In',
  'transfer_out': 'Transfer Out',
  'production': 'Production Output',
  'scrap': 'Scrap/Waste',
  'sample': 'Sample/Demo',
  'other': 'Other',
}

const loadAdjustments = async () => {
  loading.value = true
  try {
    adjustments.value = await api.getInventoryAdjustments() || []
  } catch (error) {
    console.error('Failed to load inventory adjustments:', error)
    adjustments.value = []
  } finally {
    loading.value = false
  }
}

const viewAdjustment = async (adjustment: Adjustment) => {
  try {
    const fullAdjustment = await api.getInventoryAdjustment(adjustment.id)
    viewingAdjustment.value = fullAdjustment
  } catch (error) {
    console.error('Failed to load adjustment:', error)
  }
}

const closeModal = () => {
  viewingAdjustment.value = null
}

const deleteAdjustment = async (id: number) => {
  if (!confirm('Are you sure you want to delete this adjustment? Note: This will not reverse the inventory changes.')) return
  
  try {
    await api.deleteInventoryAdjustment(id)
    await loadAdjustments()
  } catch (error) {
    console.error('Failed to delete adjustment:', error)
  }
}

const handleSave = async () => {
  showForm.value = false
  await loadAdjustments()
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString()
}

const formatReason = (reason: string) => {
  return reasons[reason] || reason
}

onMounted(() => {
  loadAdjustments()
})
</script>

<style scoped>
.status-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-approved {
  background: #d4edda;
  color: #155724;
}

.status-pending {
  background: #fff3cd;
  color: #856404;
}

.status-cancelled {
  background: #f8d7da;
  color: #721c24;
}

.positive-qty {
  color: #27ae60;
  font-weight: 600;
}

.negative-qty {
  color: #e74c3c;
  font-weight: 600;
}

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
