<template>
  <div class="card">
    <h3>New Inventory Adjustment</h3>
    
    <form @submit.prevent="handleSubmit">
      <div class="form-row">
        <div class="form-group">
          <label for="adjustmentNumber">Adjustment Number</label>
          <input
            id="adjustmentNumber"
            v-model="formData.adjustmentNumber"
            type="text"
            placeholder="Auto-generated if empty"
          />
        </div>

        <div class="form-group">
          <label for="adjustmentDate">Date *</label>
          <input
            id="adjustmentDate"
            v-model="formData.adjustmentDate"
            type="date"
            required
          />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <LocationsLocationSelector
            id="location-select"
            v-model="formData.locationId"
            label="Location *"
            placeholder="Select a location"
            :required="true"
          />
          <small class="help-text">Location where inventory will be adjusted (required)</small>
        </div>

        <div class="form-group">
          <label for="reason">Reason *</label>
          <select id="reason" v-model="formData.reason" required>
            <option value="" disabled>Select a reason</option>
            <option v-for="(name, id) in reasons" :key="id" :value="id">
              {{ name }}
            </option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group full-width">
          <label for="memo">Memo</label>
          <input
            id="memo"
            v-model="formData.memo"
            type="text"
            placeholder="Optional notes for this adjustment"
          />
        </div>
      </div>

      <div class="line-items">
        <h4>Items to Adjust</h4>
        <div class="line-item-header">
          <span>Item</span>
          <span>Qty Change</span>
          <span>Notes</span>
          <span></span>
        </div>
        
        <div v-for="(line, index) in formData.lines" :key="index" class="line-item">
          <div class="form-group">
            <ItemComboBox
              :modelValue="line.itemId"
              @update:modelValue="(val) => line.itemId = val"
              @itemSelected="(item) => handleItemSelect(index, item)"
              placeholder="Search for item..."
              showQuantity
            />
          </div>
          <div class="form-group">
            <input
              v-model.number="line.quantityChange"
              type="number"
              placeholder="e.g., 10 or -5"
              required
            />
            <small class="help-text">Positive = add, Negative = remove</small>
          </div>
          <div class="form-group">
            <input
              v-model="line.notes"
              type="text"
              placeholder="Optional"
            />
          </div>
          <button 
            type="button" 
            class="btn btn-danger btn-small" 
            @click="removeLine(index)"
            :disabled="formData.lines.length === 1"
          >
            Remove
          </button>
        </div>

        <button type="button" class="btn btn-secondary" @click="addLine">
          + Add Line
        </button>
      </div>

      <div v-if="error" class="error">{{ error }}</div>
      <div v-if="success" class="success">{{ success }}</div>

      <div class="form-actions">
        <button type="submit" class="btn btn-success" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save & Apply' }}
        </button>
        <button type="button" class="btn btn-secondary" @click="$emit('cancel')" :disabled="saving">
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
interface Item {
  id: number
  itemId: string
  itemName: string
  quantityOnHand: number
}

interface AdjustmentLine {
  itemId: number | null
  quantityChange: number
  notes: string
}

interface FormData {
  adjustmentNumber: string
  adjustmentDate: string
  locationId: number | null
  reason: string
  memo: string
  lines: AdjustmentLine[]
}

const emit = defineEmits(['save', 'cancel'])
const api = useApi()

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

const formData = ref<FormData>({
  adjustmentNumber: '',
  adjustmentDate: new Date().toISOString().split('T')[0],
  locationId: null,
  reason: '',
  memo: '',
  lines: [{ itemId: null, quantityChange: 0, notes: '' }]
})

const error = ref('')
const success = ref('')
const saving = ref(false)

const handleItemSelect = (index: number, item: Item | null) => {
  if (item) {
    formData.value.lines[index].itemId = item.id
  }
}

const addLine = () => {
  formData.value.lines.push({ itemId: null, quantityChange: 0, notes: '' })
}

const removeLine = (index: number) => {
  if (formData.value.lines.length > 1) {
    formData.value.lines.splice(index, 1)
  }
}

const handleSubmit = async () => {
  error.value = ''
  success.value = ''

  // Validate location (required - NetSuite ERP pattern)
  if (!formData.value.locationId) {
    error.value = 'Please select a location'
    return
  }

  // Validate reason
  if (!formData.value.reason) {
    error.value = 'Please select a reason'
    return
  }

  const validLines = formData.value.lines.filter(l => l.itemId !== null && l.quantityChange !== 0)
  if (validLines.length === 0) {
    error.value = 'Please add at least one item with a non-zero quantity change'
    return
  }

  saving.value = true

  try {
    await api.createInventoryAdjustment({
      adjustmentNumber: formData.value.adjustmentNumber || undefined,
      adjustmentDate: formData.value.adjustmentDate,
      locationId: formData.value.locationId,
      reason: formData.value.reason,
      memo: formData.value.memo || undefined,
      lines: validLines.map(l => ({
        itemId: l.itemId,
        quantityChange: l.quantityChange,
        notes: l.notes || undefined
      }))
    })
    
    success.value = 'Inventory adjustment created and applied successfully!'
    
    setTimeout(() => {
      emit('save')
    }, 500)
  } catch (err: any) {
    error.value = err?.message || 'Failed to create adjustment. Please try again.'
    console.error('Failed to create adjustment:', err)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
h3 {
  margin-bottom: 20px;
  color: #2c3e50;
}

h4 {
  margin-bottom: 15px;
  color: #2c3e50;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.line-items {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #ddd;
}

.line-item-header {
  display: grid;
  grid-template-columns: 2fr 1fr 1.5fr auto;
  gap: 10px;
  margin-bottom: 10px;
  font-weight: 600;
  color: #2c3e50;
}

.line-item {
  display: grid;
  grid-template-columns: 2fr 1fr 1.5fr auto;
  gap: 10px;
  margin-bottom: 15px;
  align-items: start;
}

.line-item .form-group {
  margin-bottom: 0;
}

.help-text {
  display: block;
  font-size: 11px;
  color: #95a5a6;
  margin-top: 4px;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}
</style>
