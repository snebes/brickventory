<template>
  <div class="location-selector">
    <label v-if="label" :for="id">{{ label }}</label>
    <select 
      :id="id"
      :value="modelValue" 
      @change="$emit('update:modelValue', $event.target.value ? parseInt($event.target.value) : null)"
      :required="required"
      :disabled="disabled"
    >
      <option value="">{{ placeholder }}</option>
      <option v-for="location in filteredLocations" :key="location.id" :value="location.id">
        {{ location.locationCode }} - {{ location.locationName }}
      </option>
    </select>
  </div>
</template>

<script setup lang="ts">
interface Props {
  modelValue?: number | null
  label?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  filterType?: 'fulfillment' | 'receiving' | 'all'
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select a location',
  required: false,
  disabled: false,
  filterType: 'all',
  id: 'location-select'
})

defineEmits(['update:modelValue'])

const api = useApi()
const locations = ref<any[]>([])
const loading = ref(true)

const filteredLocations = computed(() => {
  return locations.value
})

const loadLocations = async () => {
  loading.value = true
  try {
    let response
    
    if (props.filterType === 'fulfillment') {
      response = await api.getFulfillmentLocations()
    } else if (props.filterType === 'receiving') {
      response = await api.getReceivingLocations()
    } else {
      response = await api.getLocations({ active: true })
    }
    
    locations.value = response.locations || []
  } catch (error) {
    console.error('Error loading locations:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadLocations()
})
</script>

<style scoped>
.location-selector {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

label {
  font-weight: 500;
  font-size: 0.95rem;
}

select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
  background: white;
  cursor: pointer;
}

select:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}
</style>
