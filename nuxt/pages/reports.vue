<template>
  <div>
    <div class="header">
      <h2>Reports</h2>
    </div>

    <div class="reports-grid">
      <!-- Reports Dashboard Cards -->
      <div class="card report-card" @click="activeReport = 'backordered'">
        <div class="report-icon">📦</div>
        <h3>Backordered Items</h3>
        <p>View items that are on backorder and need to be fulfilled</p>
        <div class="report-actions">
          <button class="btn btn-primary btn-small" @click.stop="activeReport = 'backordered'">
            View Report
          </button>
          <button class="btn btn-secondary btn-small" @click.stop="downloadBackorderedCsv">
            Download CSV
          </button>
        </div>
      </div>
    </div>

    <!-- Active Report Section -->
    <div v-if="activeReport === 'backordered'" class="report-section">
      <div class="report-header">
        <h3>Backordered Items Report</h3>
        <button class="btn btn-secondary btn-small" @click="activeReport = null">
          ← Back to Reports
        </button>
      </div>
      
      <div v-if="loading" class="loading-state">
        <p>Loading report...</p>
      </div>

      <div v-else-if="error" class="error-state card">
        <p>{{ error }}</p>
        <button class="btn btn-primary" @click="loadBackorderedItems">Retry</button>
      </div>

      <div v-else class="card">
        <div class="report-summary">
          <div class="summary-item">
            <span class="summary-value">{{ backorderedItems.length }}</span>
            <span class="summary-label">Items Backordered</span>
          </div>
          <div class="summary-item">
            <span class="summary-value">{{ totalBackordered }}</span>
            <span class="summary-label">Total Units Backordered</span>
          </div>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Item Number</th>
                <th>Name</th>
                <th>Available</th>
                <th>On Order</th>
                <th>Backordered</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in backorderedItems" :key="item.itemNumber">
                <td><strong>{{ item.itemNumber }}</strong></td>
                <td>{{ item.name }}</td>
                <td>{{ item.quantityAvailable }}</td>
                <td>{{ item.quantityOnOrder }}</td>
                <td class="backordered-qty">{{ item.quantityBackordered }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="backorderedItems.length === 0" class="empty-state">
            No backordered items found. Your inventory is in good shape!
          </div>
        </div>

        <div class="report-footer">
          <button class="btn btn-secondary" @click="downloadBackorderedCsv">
            Download as CSV
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface BackorderedItem {
  itemNumber: string
  name: string
  quantityAvailable: number
  quantityOnOrder: number
  quantityBackordered: number
}

interface BackorderedItemsResponse {
  items: BackorderedItem[]
  total: number
}

const api = useApi()
const activeReport = ref<string | null>(null)
const backorderedItems = ref<BackorderedItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const totalBackordered = computed(() => {
  return backorderedItems.value.reduce((sum, item) => sum + item.quantityBackordered, 0)
})

const loadBackorderedItems = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.getBackorderedItemsReport() as BackorderedItemsResponse
    backorderedItems.value = response?.items || []
  } catch (err) {
    console.error('Failed to load backordered items report:', err)
    error.value = 'Failed to load report. Please check that the backend is running.'
  } finally {
    loading.value = false
  }
}

const downloadBackorderedCsv = () => {
  api.downloadBackorderedItemsCsv()
}

watch(activeReport, (newReport) => {
  if (newReport === 'backordered') {
    loadBackorderedItems()
  }
})
</script>

<style scoped>
.reports-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.report-card {
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  text-align: center;
  padding: 30px;
}

.report-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.report-icon {
  font-size: 3rem;
  margin-bottom: 15px;
}

.report-card h3 {
  color: #2c3e50;
  margin-bottom: 10px;
}

.report-card p {
  color: #7f8c8d;
  font-size: 0.9rem;
  margin-bottom: 20px;
}

.report-actions {
  display: flex;
  justify-content: center;
  gap: 10px;
}

.report-section {
  margin-top: 20px;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.report-header h3 {
  color: #2c3e50;
  margin: 0;
}

.report-summary {
  display: flex;
  gap: 40px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 20px;
}

.summary-item {
  text-align: center;
}

.summary-value {
  display: block;
  font-size: 2rem;
  font-weight: 700;
  color: #e74c3c;
}

.summary-label {
  display: block;
  font-size: 0.85rem;
  color: #7f8c8d;
  margin-top: 5px;
}

.table-wrapper {
  overflow-x: auto;
}

.backordered-qty {
  color: #e74c3c;
  font-weight: 600;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #27ae60;
  font-size: 1.1rem;
}

.report-footer {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ecf0f1;
  display: flex;
  justify-content: flex-end;
}

.loading-state {
  text-align: center;
  padding: 40px;
  color: #7f8c8d;
}

.error-state {
  text-align: center;
  padding: 40px;
  color: #e74c3c;
}

.error-state .btn {
  margin-top: 15px;
}
</style>
