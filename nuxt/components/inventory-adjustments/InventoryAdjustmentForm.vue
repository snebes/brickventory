<template>
  <div class="card">
    <h3>{{ isEditMode ? 'Edit' : 'New' }} Inventory Adjustment</h3>
    
    <form @submit.prevent="handleSubmit">
      <div class="form-row">
        <div class="form-group">
          <label for="adjustmentNumber">Adjustment Number</label>
          <input
            id="adjustmentNumber"
            v-model="formData.adjustmentNumber"
            type="text"
            placeholder="Auto-generated if empty"
            :disabled="isEditMode"
          />
        </div>

        <div class="form-group">
          <label for="adjustmentDate">Date *</label>
          <input
            id="adjustmentDate"
            v-model="formData.adjustmentDate"
            type="date"
            required
            :disabled="isEditMode"
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
        <div class="table-wrapper">
          <table class="line-items-table">
            <thead>
              <tr>
                <th class="item-col">Item *</th>
                <th class="quantity-col">Qty Change *</th>
                <th class="notes-col">Notes</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, index) in formData.lines" :key="index">
                <td class="item-col">
                  <ItemComboBox
                    :modelValue="line.itemId"
                    @update:modelValue="(val) => line.itemId = val"
                    @itemSelected="(item) => handleItemSelect(index, item)"
                    placeholder="Search for item..."
                    showQuantity
                  />
                </td>
                <td class="quantity-col">
                  <input
                    v-model.number="line.quantityChange"
                    type="number"
                    placeholder="e.g., 10 or -5"
                    required
                    class="quantity-input"
                  />
                  <small class="help-text">+ add, - remove</small>
                </td>
                <td class="notes-col">
                  <input
                    v-model="line.notes"
                    type="text"
                    placeholder="Optional"
                    class="notes-input"
                  />
                </td>
                <td class="actions-col">
                  <button 
                    type="button" 
                    class="btn btn-danger btn-small" 
                    @click="removeLine(index)"
                    :disabled="formData.lines.length === 1"
                  >
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="formData.lines.length === 0" class="no-items">
          No items added yet. Click "Add Line" to add items.
        </div>
        <div class="line-items-footer">
          <button type="button" class="btn btn-secondary" @click="addLine">
            + Add Line
          </button>
          <div v-if="formData.lines.length > 0" class="total-summary">
            <strong>Total Lines: {{ formData.lines.length }}</strong>
          </div>
        </div>
      </div>

      <div v-if="error" class="error">{{ error }}</div>
      <div v-if="success" class="success">{{ success }}</div>

      <div class="form-actions">
        <button type="submit" class="btn btn-success" :disabled="saving">
          {{ saving ? 'Saving...' : (isEditMode ? 'Update' : 'Save & Apply') }}
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
  id: number | null
  adjustmentNumber: string
  adjustmentDate: string
  locationId: number | null
  reason: string
  memo: string
  lines: AdjustmentLine[]
}

interface Adjustment {
  id: number
  adjustmentNumber: string
  adjustmentDate: string
  location: { id: number }
  reason: string
  memo?: string
  lines: Array<{
    item: { id: number }
    quantityChange: number
    notes?: string
  }>
}

const props = defineProps<{
  adjustment?: Adjustment | null
}>()

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

const getDefaultFormData = (): FormData => ({
  id: null,
  adjustmentNumber: '',
  adjustmentDate: new Date().toISOString().split('T')[0],
  locationId: null,
  reason: '',
  memo: '',
  lines: [{ itemId: null, quantityChange: 0, notes: '' }]
})

const formData = ref<FormData>(getDefaultFormData())

const error = ref('')
const success = ref('')
const saving = ref(false)

const isEditMode = computed(() => !!props.adjustment?.id)

// Watch for prop changes to populate form in edit mode
watch(() => props.adjustment, (newAdjustment) => {
  if (newAdjustment) {
    formData.value = {
      id: newAdjustment.id,
      adjustmentNumber: newAdjustment.adjustmentNumber,
      adjustmentDate: newAdjustment.adjustmentDate.split(' ')[0], // Handle datetime format
      locationId: newAdjustment.location?.id || null,
      reason: newAdjustment.reason,
      memo: newAdjustment.memo || '',
      lines: (newAdjustment.lines || []).map(line => ({
        itemId: line.item?.id || null,
        quantityChange: line.quantityChange,
        notes: line.notes || ''
      }))
    }
    // Ensure at least one line
    if (formData.value.lines.length === 0) {
      formData.value.lines.push({ itemId: null, quantityChange: 0, notes: '' })
    }
  } else {
    formData.value = getDefaultFormData()
  }
}, { immediate: true })

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
    const payload = {
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
    }

    if (isEditMode.value && formData.value.id) {
      await api.updateInventoryAdjustment(formData.value.id, payload)
      success.value = 'Inventory adjustment updated successfully!'
    } else {
      await api.createInventoryAdjustment(payload)
      success.value = 'Inventory adjustment created and applied successfully!'
    }
    
    setTimeout(() => {
      emit('save')
    }, 500)
  } catch (err: any) {
    error.value = err?.message || `Failed to ${isEditMode.value ? 'update' : 'create'} adjustment. Please try again.`
    console.error(`Failed to ${isEditMode.value ? 'update' : 'create'} adjustment:`, err)
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
  padding: 20px;
  border: 1px solid #ddd;
  border-radius: 4px;
  background-color: #fafafa;
}

.table-wrapper {
  overflow-x: auto;
  margin-bottom: 15px;
}

.line-items-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 4px;
  overflow: hidden;
}

.line-items-table th {
  background: #2c3e50;
  color: white;
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.line-items-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: top;
}

.line-items-table tbody tr:hover {
  background-color: #f5f8fa;
}

.line-items-table tbody tr:last-child td {
  border-bottom: none;
}

.item-col {
  width: 45%;
  min-width: 250px;
}

.quantity-col {
  width: 20%;
  min-width: 120px;
}

.notes-col {
  width: 25%;
  min-width: 150px;
}

.actions-col {
  width: 10%;
  min-width: 80px;
  text-align: center;
}

.quantity-input,
.notes-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.quantity-input {
  text-align: center;
}

.no-items {
  text-align: center;
  padding: 20px;
  color: #95a5a6;
  font-style: italic;
}

.line-items-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #ddd;
}

.total-summary {
  font-size: 14px;
  color: #2c3e50;
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
