<template>
  <div>
    <div class="header">
      <h2>Inventory Adjustments</h2>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="showFilters = !showFilters">
          {{ showFilters ? 'Hide' : 'Show' }} Filters
        </button>
        <button class="btn btn-primary" @click="showForm = true">
          New Adjustment
        </button>
      </div>
    </div>

    <div v-if="showFilters" class="filter-panel card">
      <div class="filter-row">
        <div class="filter-item">
          <label>Status:</label>
          <select v-model="filters.status" @change="loadAdjustments">
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="pending_approval">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="posted">Posted</option>
            <option value="void">Void</option>
          </select>
        </div>
        <div class="filter-item">
          <label>Type:</label>
          <select v-model="filters.type" @change="loadAdjustments">
            <option value="">All</option>
            <option value="quantity_adjustment">Quantity Adjustment</option>
            <option value="cost_revaluation">Cost Revaluation</option>
            <option value="physical_count">Physical Count</option>
            <option value="cycle_count">Cycle Count</option>
            <option value="write_down">Write-Down</option>
            <option value="write_off">Write-Off</option>
          </select>
        </div>
        <button class="btn btn-secondary btn-small" @click="clearFilters">Clear</button>
      </div>
    </div>

    <div v-if="!showForm" class="card">
      <table>
        <thead>
          <tr>
            <th>Adjustment #</th>
            <th>Date</th>
            <th>Location</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Qty Change</th>
            <th>Value Change</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="adjustment in adjustments" :key="adjustment.id">
            <td><strong>{{ adjustment.adjustmentNumber }}</strong></td>
            <td>{{ formatDate(adjustment.adjustmentDate) }}</td>
            <td>{{ adjustment.location?.locationName || adjustment.location?.locationCode || '-' }}</td>
            <td>{{ formatType(adjustment.adjustmentType) }}</td>
            <td>{{ formatReason(adjustment.reason) }}</td>
            <td>
              <span :class="['status-badge', `status-${adjustment.status}`]">
                {{ formatStatus(adjustment.status) }}
              </span>
            </td>
            <td :class="adjustment.totalQuantityChange > 0 ? 'positive-qty' : 'negative-qty'">
              {{ formatNumber(adjustment.totalQuantityChange) }}
            </td>
            <td :class="adjustment.totalValueChange > 0 ? 'positive-qty' : 'negative-qty'">
              ${{ formatNumber(adjustment.totalValueChange) }}
            </td>
            <td>
              <div class="actions">
                <button class="btn btn-secondary btn-small" @click="viewAdjustment(adjustment)">View</button>
                <button 
                  v-if="adjustment.status === 'draft'" 
                  class="btn btn-primary btn-small" 
                  @click="editAdjustment(adjustment)"
                >Edit</button>
                <button 
                  v-if="adjustment.status === 'approved'" 
                  class="btn btn-primary btn-small" 
                  @click="postAdjustment(adjustment.id)"
                >Post</button>
                <button 
                  v-if="adjustment.status === 'posted'" 
                  class="btn btn-warning btn-small" 
                  @click="showReverseDialog(adjustment.id)"
                >Reverse</button>
                <button 
                  v-if="adjustment.status === 'draft'" 
                  class="btn btn-danger btn-small" 
                  @click="deleteAdjustment(adjustment.id)"
                >Delete</button>
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
      :adjustment="editingAdjustment"
      @save="handleSave" 
      @cancel="handleCancel" 
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
            <strong>Location:</strong> {{ viewingAdjustment.location?.locationName || viewingAdjustment.location?.locationCode || '-' }}
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
                <th>Cost Impact</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in viewingAdjustment.lines" :key="line.id">
                <td>{{ line.item.itemName }} ({{ line.item.itemId }})</td>
                <td :class="line.quantityChange > 0 ? 'positive-qty' : 'negative-qty'">
                  {{ line.quantityChange > 0 ? '+' : '' }}{{ line.quantityChange }}
                </td>
                <td :class="line.totalCostImpact && line.totalCostImpact !== 0 ? (line.totalCostImpact > 0 ? 'positive-qty' : 'negative-qty') : ''">
                  {{ line.totalCostImpact ? `$${line.totalCostImpact.toFixed(2)}` : '-' }}
                </td>
                <td>{{ line.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reverse Adjustment Dialog -->
    <div v-if="reversingAdjustmentId" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Reverse Adjustment</h3>
          <button class="close-btn" @click="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="reverseReason">Reason for Reversal *</label>
            <textarea 
              id="reverseReason" 
              v-model="reverseReason" 
              rows="4" 
              placeholder="Enter reason for reversing this adjustment..."
              required
            ></textarea>
          </div>
          <div class="button-group">
            <button class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button class="btn btn-danger" @click="reverseAdjustment">Reverse Adjustment</button>
          </div>
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
  totalCostImpact?: number
  notes?: string
}

interface Location {
  id: number
  locationCode: string
  locationName: string
}

interface Adjustment {
  id: number
  uuid: string
  adjustmentNumber: string
  adjustmentDate: string
  adjustmentType: string
  reason: string
  memo?: string
  status: string
  location: Location
  totalQuantityChange: number
  totalValueChange: number
  lines: AdjustmentLine[]
  lineCount?: number
}

const api = useApi()
const adjustments = ref<Adjustment[]>([])
const showForm = ref(false)
const editingAdjustment = ref<Adjustment | null>(null)
const showFilters = ref(false)
const viewingAdjustment = ref<Adjustment | null>(null)
const reversingAdjustmentId = ref<number | null>(null)
const reverseReason = ref('')
const loading = ref(false)

const filters = ref({
  status: '',
  type: ''
})

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

const types: Record<string, string> = {
  'quantity_adjustment': 'Quantity Adjustment',
  'cost_revaluation': 'Cost Revaluation',
  'physical_count': 'Physical Count',
  'cycle_count': 'Cycle Count',
  'write_down': 'Write-Down',
  'write_off': 'Write-Off',
  'assembly': 'Assembly',
  'disassembly': 'Disassembly',
}

const loadAdjustments = async () => {
  loading.value = true
  try {
    const params: any = {}
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.type) params.type = filters.value.type
    
    adjustments.value = await api.getInventoryAdjustments(params) || []
  } catch (error) {
    console.error('Failed to load inventory adjustments:', error)
    adjustments.value = []
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  filters.value = { status: '', type: '' }
  loadAdjustments()
}

const viewAdjustment = async (adjustment: Adjustment) => {
  try {
    const fullAdjustment = await api.getInventoryAdjustment(adjustment.id)
    viewingAdjustment.value = fullAdjustment
  } catch (error) {
    console.error('Failed to load adjustment:', error)
  }
}

const editAdjustment = async (adjustment: Adjustment) => {
  try {
    const fullAdjustment = await api.getInventoryAdjustment(adjustment.id)
    editingAdjustment.value = fullAdjustment
    showForm.value = true
  } catch (error) {
    console.error('Failed to load adjustment for editing:', error)
    alert('Failed to load adjustment for editing')
  }
}

const closeModal = () => {
  viewingAdjustment.value = null
  reversingAdjustmentId.value = null
  reverseReason.value = ''
}

const postAdjustment = async (id: number) => {
  if (!confirm('Are you sure you want to post this adjustment? This will update inventory and cost layers.')) return
  
  try {
    await api.postInventoryAdjustment(id)
    await loadAdjustments()
  } catch (error) {
    console.error('Failed to post adjustment:', error)
    alert('Failed to post adjustment')
  }
}

const showReverseDialog = (id: number) => {
  reversingAdjustmentId.value = id
  reverseReason.value = ''
}

const reverseAdjustment = async () => {
  if (!reversingAdjustmentId.value) return
  if (!reverseReason.value.trim()) {
    alert('Please provide a reason for reversal')
    return
  }
  
  try {
    await api.reverseInventoryAdjustment(reversingAdjustmentId.value, reverseReason.value)
    await loadAdjustments()
    closeModal()
  } catch (error) {
    console.error('Failed to reverse adjustment:', error)
    alert('Failed to reverse adjustment')
  }
}

const deleteAdjustment = async (id: number) => {
  if (!confirm('Are you sure you want to delete this draft adjustment?')) return
  
  try {
    await api.deleteInventoryAdjustment(id)
    await loadAdjustments()
  } catch (error) {
    console.error('Failed to delete adjustment:', error)
    alert('Failed to delete adjustment')
  }
}

const handleSave = async () => {
  showForm.value = false
  editingAdjustment.value = null
  await loadAdjustments()
}

const handleCancel = () => {
  showForm.value = false
  editingAdjustment.value = null
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString()
}

const formatReason = (reason: string) => {
  return reasons[reason] || reason
}

const formatType = (type: string) => {
  return types[type] || type
}

const formatStatus = (status: string) => {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const formatNumber = (num: number) => {
  if (num === 0) return '0'
  return num > 0 ? `+${num.toFixed(2)}` : num.toFixed(2)
}

onMounted(() => {
  loadAdjustments()
})
</script>

<style scoped>
.header-actions {
  display: flex;
  gap: 10px;
}

.filter-panel {
  margin-bottom: 20px;
  padding: 15px;
}

.filter-row {
  display: flex;
  gap: 15px;
  align-items: flex-end;
}

.filter-item {
  display: flex;
  flex-direction: column;
}

.filter-item label {
  font-size: 12px;
  margin-bottom: 5px;
  color: #7f8c8d;
}

.filter-item select {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  min-width: 200px;
}

.status-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-draft {
  background: #ecf0f1;
  color: #7f8c8d;
}

.status-pending_approval {
  background: #fff3cd;
  color: #856404;
}

.status-approved {
  background: #d4edda;
  color: #155724;
}

.status-posted {
  background: #d1ecf1;
  color: #0c5460;
}

.status-void {
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

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  resize: vertical;
}

.button-group {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
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
