<template>
  <div class="card">
    <h3>{{ item ? 'Edit Item' : 'Add Item' }}</h3>
    
    <form @submit.prevent="handleSubmit">
      <div class="form-group">
        <label for="itemId">Item ID *</label>
        <input
          id="itemId"
          v-model="formData.itemId"
          type="text"
          required
          :disabled="!!item"
          placeholder="e.g., 3001"
        />
      </div>

      <div class="form-group">
        <label for="itemName">Item Name *</label>
        <input
          id="itemName"
          v-model="formData.itemName"
          type="text"
          required
          placeholder="e.g., Brick 2x4"
        />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="quantityOnHand">Quantity On Hand</label>
          <input
            id="quantityOnHand"
            v-model.number="formData.quantityOnHand"
            type="number"
            min="0"
            placeholder="0"
          />
        </div>

        <div class="form-group">
          <label for="quantityOnOrder">Quantity On Order</label>
          <input
            id="quantityOnOrder"
            v-model.number="formData.quantityOnOrder"
            type="number"
            min="0"
            placeholder="0"
          />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="quantityBackOrdered">Quantity Backordered</label>
          <input
            id="quantityBackOrdered"
            v-model.number="formData.quantityBackOrdered"
            type="number"
            min="0"
            placeholder="0"
          />
        </div>

        <div class="form-group">
          <label for="quantityCommitted">Quantity Committed</label>
          <input
            id="quantityCommitted"
            v-model.number="formData.quantityCommitted"
            type="number"
            min="0"
            placeholder="0"
          />
        </div>
      </div>

      <div class="form-group">
        <label for="partId">Part ID</label>
        <input
          id="partId"
          v-model="formData.partId"
          type="text"
          placeholder="e.g., 3001"
        />
      </div>

      <div class="form-group">
        <label for="colorId">Color ID</label>
        <input
          id="colorId"
          v-model="formData.colorId"
          type="text"
          maxlength="5"
          placeholder="e.g., 1"
        />
      </div>

      <div class="form-group">
        <label for="elementIds">Element IDs</label>
        <input
          id="elementIds"
          v-model="formData.elementIds"
          type="text"
          placeholder="e.g., 300101"
        />
      </div>

      <div v-if="error" class="error">{{ error }}</div>
      <div v-if="success" class="success">{{ success }}</div>

      <div class="form-actions">
        <button type="submit" class="btn btn-success" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save' }}
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
  id?: number
  itemId: string
  itemName: string
  quantityOnHand: number
  quantityOnOrder: number
  quantityBackOrdered: number
  quantityCommitted: number
  partId: string
  colorId: string
  elementIds: string
}

interface Props {
  item?: Item | null
}

const props = defineProps<Props>()
const emit = defineEmits(['save', 'cancel'])
const api = useApi()

const formData = ref<Item>({
  itemId: '',
  itemName: '',
  quantityOnHand: 0,
  quantityOnOrder: 0,
  quantityBackOrdered: 0,
  quantityCommitted: 0,
  partId: '',
  colorId: '',
  elementIds: ''
})

const error = ref('')
const success = ref('')
const saving = ref(false)

// Initialize form data if editing
watch(() => props.item, (newItem) => {
  if (newItem) {
    formData.value = { ...newItem }
  }
}, { immediate: true })

const handleSubmit = async () => {
  error.value = ''
  success.value = ''
  saving.value = true

  try {
    if (props.item?.id) {
      await api.updateItem(props.item.id, formData.value)
      success.value = 'Item updated successfully!'
    } else {
      await api.createItem(formData.value)
      success.value = 'Item created successfully!'
    }
    
    setTimeout(() => {
      emit('save')
    }, 500)
  } catch (err: any) {
    error.value = err?.message || 'Failed to save item. Please try again.'
    console.error('Failed to save item:', err)
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

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}
</style>
