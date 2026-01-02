<template>
  <div>
    <div class="header">
      <h2>Items</h2>
      <button class="btn btn-primary" @click="showForm = true; editingItem = null">
        Add Item
      </button>
    </div>

    <div v-if="!showForm" class="card">
      <!-- Filters -->
      <div class="filters">
        <div class="filter-buttons">
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'all', 'btn-secondary': filter !== 'all' }]"
            @click="filter = 'all'; loadItems()"
          >
            All Items
          </button>
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'in-stock', 'btn-secondary': filter !== 'in-stock' }]"
            @click="filter = 'in-stock'; loadItems()"
          >
            In Stock
          </button>
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'backordered', 'btn-secondary': filter !== 'backordered' }]"
            @click="filter = 'backordered'; loadItems()"
          >
            Backordered
          </button>
        </div>
        <div class="search-box">
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Search items..." 
            @input="handleSearch"
            class="search-input"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th @click="sortBy('itemId')" class="sortable">
                Item ID 
                <span v-if="sortField === 'itemId'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('itemName')" class="sortable">
                Item Name
                <span v-if="sortField === 'itemName'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('quantityAvailable')" class="sortable">
                Available
                <span v-if="sortField === 'quantityAvailable'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('quantityOnHand')" class="sortable">
                On Hand
                <span v-if="sortField === 'quantityOnHand'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('quantityOnOrder')" class="sortable">
                On Order
                <span v-if="sortField === 'quantityOnOrder'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('quantityBackOrdered')" class="sortable">
                Backordered
                <span v-if="sortField === 'quantityBackOrdered'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th @click="sortBy('quantityCommitted')" class="sortable">
                Committed
                <span v-if="sortField === 'quantityCommitted'">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th>Valuation</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in sortedItems" :key="item.id">
              <td><strong>{{ item.itemId }}</strong></td>
              <td>{{ item.itemName }}</td>
              <td>{{ item.quantityAvailable }}</td>
              <td>{{ item.quantityOnHand }}</td>
              <td>{{ item.quantityOnOrder }}</td>
              <td>{{ item.quantityBackOrdered || 0 }}</td>
              <td>{{ item.quantityCommitted }}</td>
              <td>{{ formatCurrency(calculateValuation(item)) }}</td>
              <td>
                <div class="actions">
                  <button class="btn btn-secondary btn-small" @click="editItem(item)">Edit</button>
                  <button class="btn btn-danger btn-small" @click="deleteItem(item.id)">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="sortedItems.length === 0 && !loading" style="text-align: center; padding: 20px; color: #95a5a6;">
          No items found. Add one to get started!
        </div>
        <div v-if="loading" style="text-align: center; padding: 20px; color: #95a5a6;">
          Loading...
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="hasMore || page > 1" class="pagination">
        <button 
          class="btn btn-secondary btn-small" 
          @click="previousPage" 
          :disabled="page <= 1"
        >
          Previous
        </button>
        <span class="page-info">Page {{ page }}</span>
        <button 
          class="btn btn-secondary btn-small" 
          @click="nextPage" 
          :disabled="!hasMore"
        >
          Next
        </button>
      </div>
    </div>

    <ItemsItemForm 
      v-else 
      :item="editingItem" 
      @save="handleSave" 
      @cancel="showForm = false; editingItem = null" 
    />
  </div>
</template>

<script setup lang="ts">
interface Item {
  id: number
  itemId: string
  itemName: string
  quantityAvailable: number
  quantityOnHand: number
  quantityOnOrder: number
  quantityBackOrdered?: number
  quantityCommitted: number
}

const api = useApi()
const items = ref<Item[]>([])
const showForm = ref(false)
const editingItem = ref<Item | null>(null)
const filter = ref('all')
const searchQuery = ref('')
const sortField = ref<keyof Item>('itemId')
const sortDirection = ref<'asc' | 'desc'>('asc')
const page = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

const sortedItems = computed(() => {
  let filtered = [...items.value]
  
  // Apply filters
  if (filter.value === 'in-stock') {
    filtered = filtered.filter(item => item.quantityAvailable > 0)
  } else if (filter.value === 'backordered') {
    filtered = filtered.filter(item => (item.quantityBackOrdered || 0) > 0)
  }
  
  // Apply sorting
  filtered.sort((a, b) => {
    const aVal = a[sortField.value]
    const bVal = b[sortField.value]
    
    if (typeof aVal === 'string' && typeof bVal === 'string') {
      return sortDirection.value === 'asc' 
        ? aVal.localeCompare(bVal)
        : bVal.localeCompare(aVal)
    }
    
    const aNum = Number(aVal) || 0
    const bNum = Number(bVal) || 0
    return sortDirection.value === 'asc' ? aNum - bNum : bNum - aNum
  })
  
  return filtered
})

const loadItems = async () => {
  loading.value = true
  try {
    const response = await api.getItems({
      page: page.value,
      limit: 50,
      search: searchQuery.value
    })
    items.value = response?.items || []
    hasMore.value = response?.hasMore || false
  } catch (error) {
    console.error('Failed to load items:', error)
    items.value = []
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }
  searchTimeout.value = setTimeout(() => {
    page.value = 1
    loadItems()
  }, 300)
}

const sortBy = (field: keyof Item) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const editItem = (item: Item) => {
  editingItem.value = { ...item }
  showForm.value = true
}

const deleteItem = async (id: number) => {
  if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
    return
  }
  
  try {
    await api.deleteItem(id)
    await loadItems()
  } catch (error) {
    console.error('Failed to delete item:', error)
    alert('Failed to delete item. It may be in use by purchase or sales orders.')
  }
}

const handleSave = async () => {
  showForm.value = false
  editingItem.value = null
  await loadItems()
}

const nextPage = () => {
  if (hasMore.value) {
    page.value++
    loadItems()
  }
}

const previousPage = () => {
  if (page.value > 1) {
    page.value--
    loadItems()
  }
}

const calculateValuation = (item: Item): number => {
  // Simple valuation based on available quantity
  // In a real app, this would use price data
  return item.quantityAvailable * 10 // $10 per unit as placeholder
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

onMounted(() => {
  loadItems()
})
</script>

<style scoped>
.filters {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  gap: 20px;
  flex-wrap: wrap;
}

.filter-buttons {
  display: flex;
  gap: 10px;
}

.search-box {
  flex: 1;
  max-width: 300px;
}

.search-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.search-input:focus {
  outline: none;
  border-color: #3498db;
}

.table-wrapper {
  overflow-x: auto;
}

.sortable {
  cursor: pointer;
  user-select: none;
}

.sortable:hover {
  background: #e8e8e8;
}

.sortable span {
  font-size: 10px;
  margin-left: 4px;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
}

.page-info {
  color: #2c3e50;
  font-weight: 500;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
