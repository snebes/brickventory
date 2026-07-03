<template>
  <div>
    <div class="header">
      <h2>Locations</h2>
      <button class="btn btn-primary" @click="showForm = true; editingLocation = null">
        Add Location
      </button>
    </div>

    <div v-if="!showForm" class="card">
      <!-- Filters -->
      <div class="filters">
        <div class="filter-buttons">
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'all', 'btn-secondary': filter !== 'all' }]"
            @click="filter = 'all'; loadLocations()"
          >
            All Locations
          </button>
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'active', 'btn-secondary': filter !== 'active' }]"
            @click="filter = 'active'; loadLocations()"
          >
            Active
          </button>
          <button 
            :class="['btn', 'btn-small', { 'btn-primary': filter === 'inactive', 'btn-secondary': filter !== 'inactive' }]"
            @click="filter = 'inactive'; loadLocations()"
          >
            Inactive
          </button>
        </div>
      </div>

      <!-- Locations Grid -->
      <div v-if="loading" class="loading">Loading locations...</div>
      <div v-else-if="locations.length === 0" class="empty-state">
        <p>No locations found.</p>
        <button class="btn btn-primary" @click="showForm = true; editingLocation = null">
          Create First Location
        </button>
      </div>
      <div v-else class="locations-grid">
        <div v-for="location in locations" :key="location.id" class="location-card">
          <div class="location-header">
            <h3>{{ location.locationName }}</h3>
            <span :class="['badge', location.active ? 'badge-success' : 'badge-secondary']">
              {{ location.active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="location-body">
            <div class="location-info">
              <strong>Code:</strong> {{ location.locationCode }}
            </div>
            <div class="location-info">
              <strong>Type:</strong> {{ formatLocationType(location.locationType) }}
            </div>
            <div class="location-info" v-if="location.address">
              <strong>Address:</strong> {{ formatAddress(location.address) }}
            </div>
            <div class="location-features">
              <span v-if="location.useBinManagement" class="feature-badge">Bin Management</span>
              <span v-if="location.isTransferSource" class="feature-badge">Can Ship</span>
              <span v-if="location.isTransferDestination" class="feature-badge">Can Receive</span>
            </div>
          </div>
          <div class="location-actions">
            <button class="btn btn-small btn-secondary" @click="viewLocation(location)">
              View Details
            </button>
            <button class="btn btn-small btn-secondary" @click="editLocation(location)">
              Edit
            </button>
            <button 
              v-if="location.active"
              class="btn btn-small btn-secondary" 
              @click="deactivateLocation(location)"
            >
              Deactivate
            </button>
            <button 
              v-else
              class="btn btn-small btn-primary" 
              @click="activateLocation(location)"
            >
              Activate
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Location Form -->
    <div v-else class="card">
      <h3>{{ editingLocation ? 'Edit Location' : 'New Location' }}</h3>
      <form @submit.prevent="saveLocation">
        <div class="form-grid">
          <div class="form-group">
            <label for="locationCode">Location Code *</label>
            <input 
              type="text" 
              id="locationCode" 
              v-model="form.locationCode" 
              required
              placeholder="e.g., WH-EAST, STORE-01"
            />
          </div>

          <div class="form-group">
            <label for="locationName">Location Name *</label>
            <input 
              type="text" 
              id="locationName" 
              v-model="form.locationName" 
              required
              placeholder="e.g., East Warehouse, Store #1"
            />
          </div>

          <div class="form-group">
            <label for="locationType">Location Type *</label>
            <select id="locationType" v-model="form.locationType" required>
              <option value="warehouse">Warehouse</option>
              <option value="store">Store</option>
              <option value="distribution_center">Distribution Center</option>
              <option value="virtual">Virtual</option>
              <option value="vendor_location">Vendor Location</option>
            </select>
          </div>

          <div class="form-group">
            <label for="country">Country</label>
            <input 
              type="text" 
              id="country" 
              v-model="form.country"
              maxlength="2"
              placeholder="US"
            />
          </div>
        </div>

        <div class="form-section">
          <h4>Operational Settings</h4>
          <div class="form-checkboxes">
            <label>
              <input type="checkbox" v-model="form.active" />
              Active
            </label>
            <label>
              <input type="checkbox" v-model="form.useBinManagement" />
              Use Bin Management
            </label>
            <label v-if="form.useBinManagement">
              <input type="checkbox" v-model="form.requiresBinOnReceipt" />
              Require Bin on Receipt
            </label>
            <label v-if="form.useBinManagement">
              <input type="checkbox" v-model="form.requiresBinOnFulfillment" />
              Require Bin on Fulfillment
            </label>
          </div>
        </div>

        <div class="form-section">
          <h4>Inventory Settings</h4>
          <div class="form-checkboxes">
            <label>
              <input type="checkbox" v-model="form.allowNegativeInventory" />
              Allow Negative Inventory
            </label>
            <label>
              <input type="checkbox" v-model="form.isTransferSource" />
              Can Transfer Out (Source)
            </label>
            <label>
              <input type="checkbox" v-model="form.isTransferDestination" />
              Can Transfer In (Destination)
            </label>
            <label>
              <input type="checkbox" v-model="form.makeInventoryAvailable" />
              Make Inventory Available
            </label>
          </div>
        </div>

        <div class="form-section">
          <h4>Contact Information</h4>
          <div class="form-grid">
            <div class="form-group">
              <label for="contactPhone">Contact Phone</label>
              <input 
                type="tel" 
                id="contactPhone" 
                v-model="form.contactPhone"
                placeholder="+1-555-123-4567"
              />
            </div>
            <div class="form-group">
              <label for="contactEmail">Contact Email</label>
              <input 
                type="email" 
                id="contactEmail" 
                v-model="form.contactEmail"
                placeholder="warehouse@example.com"
              />
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Location' }}
          </button>
          <button type="button" class="btn btn-secondary" @click="cancelForm">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()

const showForm = ref(false)
const loading = ref(true)
const saving = ref(false)
const filter = ref('all')
const locations = ref<any[]>([])
const editingLocation = ref<any>(null)

const form = ref({
  locationCode: '',
  locationName: '',
  locationType: 'warehouse',
  active: true,
  country: 'US',
  useBinManagement: false,
  requiresBinOnReceipt: false,
  requiresBinOnFulfillment: false,
  allowNegativeInventory: false,
  isTransferSource: true,
  isTransferDestination: true,
  makeInventoryAvailable: true,
  contactPhone: '',
  contactEmail: '',
})

const formatLocationType = (type: string) => {
  return type.split('_').map((word: string) => 
    word.charAt(0).toUpperCase() + word.slice(1)
  ).join(' ')
}

const formatAddress = (address: any) => {
  if (!address) return 'N/A'
  const parts = []
  if (address.street) parts.push(address.street)
  if (address.city) parts.push(address.city)
  if (address.state) parts.push(address.state)
  if (address.zip) parts.push(address.zip)
  return parts.join(', ') || 'N/A'
}

const loadLocations = async () => {
  loading.value = true
  try {
    const params: any = {}
    if (filter.value === 'active') params.active = true
    if (filter.value === 'inactive') params.active = false
    
    const response = await api.getLocations(params)
    locations.value = response.locations || []
  } catch (error) {
    console.error('Error loading locations:', error)
    alert('Failed to load locations')
  } finally {
    loading.value = false
  }
}

const viewLocation = (location: any) => {
  navigateTo(`/location-details?id=${location.id}`)
}

const editLocation = (location: any) => {
  editingLocation.value = location
  form.value = {
    locationCode: location.locationCode,
    locationName: location.locationName,
    locationType: location.locationType,
    active: location.active,
    country: location.country || 'US',
    useBinManagement: location.useBinManagement,
    requiresBinOnReceipt: location.requiresBinOnReceipt,
    requiresBinOnFulfillment: location.requiresBinOnFulfillment,
    allowNegativeInventory: location.allowNegativeInventory,
    isTransferSource: location.isTransferSource,
    isTransferDestination: location.isTransferDestination,
    makeInventoryAvailable: location.makeInventoryAvailable,
    contactPhone: location.contactPhone || '',
    contactEmail: location.contactEmail || '',
  }
  showForm.value = true
}

const saveLocation = async () => {
  saving.value = true
  try {
    if (editingLocation.value) {
      await api.updateLocation(editingLocation.value.id, form.value)
    } else {
      await api.createLocation(form.value)
    }
    
    alert('Location saved successfully')
    cancelForm()
    await loadLocations()
  } catch (error) {
    console.error('Error saving location:', error)
    alert('Failed to save location')
  } finally {
    saving.value = false
  }
}

const activateLocation = async (location: any) => {
  if (confirm(`Are you sure you want to activate ${location.locationName}?`)) {
    try {
      await api.activateLocation(location.id)
      alert('Location activated successfully')
      await loadLocations()
    } catch (error) {
      console.error('Error activating location:', error)
      alert('Failed to activate location')
    }
  }
}

const deactivateLocation = async (location: any) => {
  if (confirm(`Are you sure you want to deactivate ${location.locationName}? This location must have zero inventory on hand.`)) {
    try {
      await api.deactivateLocation(location.id)
      alert('Location deactivated successfully')
      await loadLocations()
    } catch (error: any) {
      console.error('Error deactivating location:', error)
      alert(error.data?.error || 'Failed to deactivate location')
    }
  }
}

const cancelForm = () => {
  showForm.value = false
  editingLocation.value = null
  form.value = {
    locationCode: '',
    locationName: '',
    locationType: 'warehouse',
    active: true,
    country: 'US',
    useBinManagement: false,
    requiresBinOnReceipt: false,
    requiresBinOnFulfillment: false,
    allowNegativeInventory: false,
    isTransferSource: true,
    isTransferDestination: true,
    makeInventoryAvailable: true,
    contactPhone: '',
    contactEmail: '',
  }
}

onMounted(() => {
  loadLocations()
})
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.filters {
  margin-bottom: 20px;
}

.filter-buttons {
  display: flex;
  gap: 10px;
}

.locations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.location-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  background: white;
}

.location-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.location-header h3 {
  margin: 0;
  font-size: 1.2rem;
}

.badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 500;
}

.badge-success {
  background: #d4edda;
  color: #155724;
}

.badge-secondary {
  background: #e2e3e5;
  color: #383d41;
}

.location-body {
  margin-bottom: 15px;
}

.location-info {
  margin-bottom: 8px;
  font-size: 0.95rem;
}

.location-features {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.feature-badge {
  padding: 3px 10px;
  background: #e7f3ff;
  color: #0066cc;
  border-radius: 10px;
  font-size: 0.8rem;
}

.location-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.form-section {
  margin-bottom: 25px;
}

.form-section h4 {
  margin-bottom: 15px;
  font-size: 1.1rem;
  color: #333;
}

.form-checkboxes {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.form-checkboxes label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: normal;
}

.form-actions {
  display: flex;
  gap: 10px;
  padding-top: 20px;
  border-top: 1px solid #ddd;
}

.loading, .empty-state {
  text-align: center;
  padding: 40px;
  color: #666;
}

.empty-state p {
  margin-bottom: 20px;
}
</style>
