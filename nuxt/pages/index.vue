<template>
  <div>
    <div class="header">
      <h2>Dashboard</h2>
    </div>

    <div v-if="loading" class="loading-state">
      <p>Loading metrics...</p>
    </div>

    <div v-else-if="error" class="error-state card">
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadMetrics">Retry</button>
    </div>

    <div v-else class="dashboard-grid">
      <!-- Inventory Overview -->
      <div class="card metric-card">
        <h3>Inventory Overview</h3>
        <div class="metric-grid">
          <div class="metric">
            <div class="metric-value">{{ metrics?.items?.total || 0 }}</div>
            <div class="metric-label">Total Items</div>
          </div>
          <div class="metric">
            <div class="metric-value">{{ metrics?.items?.quantityOnHand || 0 }}</div>
            <div class="metric-label">Quantity On Hand</div>
          </div>
          <div class="metric">
            <div class="metric-value">{{ metrics?.items?.quantityAvailable || 0 }}</div>
            <div class="metric-label">Available</div>
          </div>
          <div class="metric">
            <div class="metric-value">{{ metrics?.items?.quantityOnOrder || 0 }}</div>
            <div class="metric-label">On Order</div>
          </div>
        </div>
      </div>

      <!-- Inventory Valuation -->
      <div class="card metric-card valuation-card">
        <h3>Inventory Valuation</h3>
        <div class="valuation-amount">
          {{ formatCurrency(metrics?.inventoryValuation || 0) }}
        </div>
        <div class="valuation-note">Based on on-hand quantity</div>
      </div>

      <!-- Purchase Orders -->
      <div class="card metric-card">
        <h3>Purchase Orders</h3>
        <div class="order-stats">
          <div class="stat-row">
            <span class="stat-label">Total:</span>
            <span class="stat-value">{{ metrics?.purchaseOrders?.total || 0 }}</span>
          </div>
          <div class="stat-row" v-if="metrics?.purchaseOrders?.byStatus?.pending">
            <span class="stat-label status-pending">Pending:</span>
            <span class="stat-value">{{ metrics?.purchaseOrders?.byStatus?.pending || 0 }}</span>
          </div>
          <div class="stat-row" v-if="metrics?.purchaseOrders?.byStatus?.received">
            <span class="stat-label status-received">Received:</span>
            <span class="stat-value">{{ metrics?.purchaseOrders?.byStatus?.received || 0 }}</span>
          </div>
        </div>
        <NuxtLink to="/purchase-orders" class="card-link">View Purchase Orders →</NuxtLink>
      </div>

      <!-- Sales Orders -->
      <div class="card metric-card">
        <h3>Sales Orders</h3>
        <div class="order-stats">
          <div class="stat-row">
            <span class="stat-label">Total:</span>
            <span class="stat-value">{{ metrics?.salesOrders?.total || 0 }}</span>
          </div>
          <div class="stat-row" v-if="metrics?.salesOrders?.byStatus?.pending">
            <span class="stat-label status-pending">Pending:</span>
            <span class="stat-value">{{ metrics?.salesOrders?.byStatus?.pending || 0 }}</span>
          </div>
          <div class="stat-row" v-if="metrics?.salesOrders?.byStatus?.fulfilled">
            <span class="stat-label status-fulfilled">Fulfilled:</span>
            <span class="stat-value">{{ metrics?.salesOrders?.byStatus?.fulfilled || 0 }}</span>
          </div>
        </div>
        <NuxtLink to="/sales-orders" class="card-link">View Sales Orders →</NuxtLink>
      </div>

      <!-- Item Receipts -->
      <div class="card metric-card">
        <h3>Item Receipts</h3>
        <div class="metric">
          <div class="metric-value">{{ metrics?.itemReceipts?.total || 0 }}</div>
          <div class="metric-label">Total Receipts</div>
        </div>
        <NuxtLink to="/item-receipts" class="card-link">View Item Receipts →</NuxtLink>
      </div>

      <!-- Inventory Adjustments -->
      <div class="card metric-card">
        <h3>Inventory Adjustments</h3>
        <div class="metric">
          <div class="metric-value">{{ metrics?.inventoryAdjustments?.total || 0 }}</div>
          <div class="metric-label">Total Adjustments</div>
        </div>
        <NuxtLink to="/inventory-adjustments" class="card-link">View Adjustments →</NuxtLink>
      </div>

      <!-- Committed Inventory -->
      <div class="card metric-card">
        <h3>Committed Inventory</h3>
        <div class="metric">
          <div class="metric-value">{{ metrics?.items?.quantityCommitted || 0 }}</div>
          <div class="metric-label">Units Committed to Orders</div>
        </div>
        <NuxtLink to="/items" class="card-link">View Items →</NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface DashboardMetrics {
  items: {
    total: number
    quantityOnHand: number
    quantityAvailable: number
    quantityOnOrder: number
    quantityCommitted: number
  }
  inventoryValuation: number
  purchaseOrders: {
    total: number
    byStatus: Record<string, number>
  }
  salesOrders: {
    total: number
    byStatus: Record<string, number>
  }
  itemReceipts: {
    total: number
  }
  inventoryAdjustments: {
    total: number
  }
}

const api = useApi()
const metrics = ref<DashboardMetrics | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const loadMetrics = async () => {
  loading.value = true
  error.value = null
  try {
    metrics.value = await api.getDashboardMetrics() as DashboardMetrics
  } catch (err) {
    console.error('Failed to load dashboard metrics:', err)
    error.value = 'Failed to load dashboard metrics. Please check that the backend is running.'
  } finally {
    loading.value = false
  }
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

onMounted(() => {
  loadMetrics()
})
</script>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

.metric-card {
  display: flex;
  flex-direction: column;
}

.metric-card h3 {
  color: #2c3e50;
  margin-bottom: 15px;
  font-size: 1.1rem;
  border-bottom: 2px solid #ecf0f1;
  padding-bottom: 10px;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.metric {
  text-align: center;
  padding: 10px;
}

.metric-value {
  font-size: 2rem;
  font-weight: 700;
  color: #3498db;
}

.metric-label {
  font-size: 0.85rem;
  color: #7f8c8d;
  margin-top: 5px;
}

.valuation-card {
  background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
  color: white;
}

.valuation-card h3 {
  color: white;
  border-bottom-color: rgba(255, 255, 255, 0.3);
}

.valuation-amount {
  font-size: 2.5rem;
  font-weight: 700;
  text-align: center;
  margin: 20px 0;
}

.valuation-note {
  text-align: center;
  font-size: 0.85rem;
  opacity: 0.8;
}

.order-stats {
  margin-bottom: 15px;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #ecf0f1;
}

.stat-row:last-child {
  border-bottom: none;
}

.stat-label {
  color: #7f8c8d;
}

.stat-value {
  font-weight: 600;
  color: #2c3e50;
}

.status-pending {
  color: #f39c12;
}

.status-received, .status-fulfilled {
  color: #27ae60;
}

.card-link {
  margin-top: auto;
  padding-top: 15px;
  color: #3498db;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  border-top: 1px solid #ecf0f1;
}

.card-link:hover {
  color: #2980b9;
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
