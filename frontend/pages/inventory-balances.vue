<template>
  <div>
    <div class="header">
      <h2>Inventory Balances</h2>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="refreshData">
          Refresh
        </button>
      </div>
    </div>

    <div class="card">
      <!-- Summary Cards -->
      <div class="summary-cards" v-if="summary">
        <div class="summary-card">
          <div class="summary-label">Total Items</div>
          <div class="summary-value">{{ summary.totalItems }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Total Locations</div>
          <div class="summary-value">{{ summary.totalLocations }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">On Hand</div>
          <div class="summary-value">{{ summary.totalOnHand }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Available</div>
          <div class="summary-value">{{ summary.totalAvailable }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Committed</div>
          <div class="summary-value">{{ summary.totalCommitted }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">On Order</div>
          <div class="summary-value">{{ summary.totalOnOrder }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters">
        <div class="filter-row">
          <div class="form-group">
            <label>View By:</label>
            <select v-model="viewMode" @change="loadData">
              <option value="all">All Balances</option>
              <option value="by-item">By Item</option>
              <option value="by-location">By Location</option>
            </select>
          </div>

          <div class="form-group" v-if="viewMode === 'by-item'">
            <label>Item:</label>
            <input 
              type="number" 
              v-model="selectedItemId" 
              placeholder="Item ID"
              @change="loadData"
            />
          </div>

          <div class="form-group" v-if="viewMode === 'by-location'">
            <label>Location:</label>
            <select v-model="selectedLocationId" @change="loadData">
              <option value="">Select Location</option>
              <option v-for="location in locations" :key="location.id" :value="location.id">
                {{ location.locationName }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Balances Table -->
      <div v-if="loading" class="loading">Loading inventory balances...</div>
      <div v-else-if="balances.length === 0" class="empty-state">
        <p>No inventory balances found.</p>
      </div>
      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Location</th>
              <th>Bin</th>
              <th>On Hand</th>
              <th>Available</th>
              <th>Committed</th>
              <th>On Order</th>
              <th>In Transit</th>
              <th>Reserved</th>
              <th>Avg Cost</th>
              <th>Total Value</th>
              <th>Last Movement</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="balance in balances" :key="balance.id">
              <td>
                <div class="item-info">
                  <strong>{{ balance.itemCode || balance.itemId }}</strong>
                  <div class="item-name">{{ balance.itemName }}</div>
                </div>
              </td>
              <td>
                <div class="location-info">
                  <strong>{{ balance.locationCode }}</strong>
                  <div class="location-name">{{ balance.locationName }}</div>
                </div>
              </td>
              <td>{{ balance.binLocation || '-' }}</td>
              <td :class="{ 'low-stock': balance.quantityOnHand < 10 }">
                {{ balance.quantityOnHand }}
              </td>
              <td :class="{ 'text-success': balance.quantityAvailable > 0, 'text-danger': balance.quantityAvailable <= 0 }">
                {{ balance.quantityAvailable }}
              </td>
              <td>{{ balance.quantityCommitted }}</td>
              <td>{{ balance.quantityOnOrder }}</td>
              <td>{{ balance.quantityInTransit }}</td>
              <td>{{ balance.quantityReserved }}</td>
              <td>${{ balance.averageCost.toFixed(2) }}</td>
              <td class="text-right">
                <strong>${{ balance.totalValue.toFixed(2) }}</strong>
              </td>
              <td class="text-muted">
                {{ formatDate(balance.lastMovementDate) }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="balances.length > 0">
            <tr class="totals-row">
              <td colspan="3"><strong>Totals:</strong></td>
              <td><strong>{{ calculateTotal('quantityOnHand') }}</strong></td>
              <td><strong>{{ calculateTotal('quantityAvailable') }}</strong></td>
              <td><strong>{{ calculateTotal('quantityCommitted') }}</strong></td>
              <td><strong>{{ calculateTotal('quantityOnOrder') }}</strong></td>
              <td><strong>{{ calculateTotal('quantityInTransit') }}</strong></td>
              <td><strong>{{ calculateTotal('quantityReserved') }}</strong></td>
              <td colspan="2" class="text-right">
                <strong>${{ calculateTotalValue().toFixed(2) }}</strong>
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()

const loading = ref(true)
const viewMode = ref('all')
const selectedItemId = ref<number | null>(null)
const selectedLocationId = ref<number | null>(null)
const balances = ref<any[]>([])
const locations = ref<any[]>([])
const summary = ref<any>(null)

const formatDate = (dateString: string | null) => {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

const calculateTotal = (field: string) => {
  return balances.value.reduce((sum, balance) => sum + (balance[field] || 0), 0)
}

const calculateTotalValue = () => {
  return balances.value.reduce((sum, balance) => sum + (balance.totalValue || 0), 0)
}

const loadSummary = async () => {
  try {
    summary.value = await api.getInventoryBalanceSummary()
  } catch (error) {
    console.error('Error loading summary:', error)
  }
}

const loadLocations = async () => {
  try {
    const response = await api.getLocations({ active: true })
    locations.value = response.locations || []
  } catch (error) {
    console.error('Error loading locations:', error)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    let response

    if (viewMode.value === 'by-item' && selectedItemId.value) {
      response = await api.getInventoryBalancesByItem(selectedItemId.value)
      balances.value = response.balances || []
    } else if (viewMode.value === 'by-location' && selectedLocationId.value) {
      response = await api.getInventoryBalancesByLocation(selectedLocationId.value)
      balances.value = response.balances || []
    } else {
      response = await api.getInventoryBalances()
      balances.value = response.balances || []
    }
  } catch (error) {
    console.error('Error loading balances:', error)
    alert('Failed to load inventory balances')
  } finally {
    loading.value = false
  }
}

const refreshData = async () => {
  await loadSummary()
  await loadData()
}

onMounted(async () => {
  await loadLocations()
  await loadSummary()
  await loadData()
})
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.summary-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}

.summary-card:nth-child(2) {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.summary-card:nth-child(3) {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.summary-card:nth-child(4) {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.summary-card:nth-child(5) {
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.summary-card:nth-child(6) {
  background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
}

.summary-label {
  font-size: 0.9rem;
  opacity: 0.95;
  margin-bottom: 8px;
}

.summary-value {
  font-size: 2rem;
  font-weight: bold;
}

.filters {
  margin-bottom: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 6px;
}

.filter-row {
  display: flex;
  gap: 20px;
  align-items: end;
}

.filter-row .form-group {
  flex: 1;
  max-width: 300px;
}

.filter-row label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead th {
  background: #f8f9fa;
  padding: 12px 8px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid #dee2e6;
  font-size: 0.9rem;
}

tbody td {
  padding: 12px 8px;
  border-bottom: 1px solid #dee2e6;
}

tfoot td {
  padding: 12px 8px;
  border-top: 2px solid #dee2e6;
  background: #f8f9fa;
}

.item-info, .location-info {
  line-height: 1.4;
}

.item-name, .location-name {
  font-size: 0.85rem;
  color: #6c757d;
}

.low-stock {
  color: #dc3545;
  font-weight: 600;
}

.text-success {
  color: #28a745;
  font-weight: 600;
}

.text-danger {
  color: #dc3545;
  font-weight: 600;
}

.text-right {
  text-align: right;
}

.text-muted {
  color: #6c757d;
  font-size: 0.9rem;
}

.loading, .empty-state {
  text-align: center;
  padding: 40px;
  color: #666;
}

.totals-row {
  font-weight: 600;
}
</style>
