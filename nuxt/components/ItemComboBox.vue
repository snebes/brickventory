<template>
  <div class="combobox-wrapper" ref="wrapperRef">
    <div class="combobox-input-container">
      <input
        ref="inputRef"
        v-model="searchQuery"
        type="text"
        :placeholder="placeholder"
        :required="required"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown="handleKeyDown"
        class="combobox-input"
      />
      <span v-if="loading" class="loading-indicator">⏳</span>
    </div>
    
    <div v-if="showDropdown" class="combobox-dropdown" ref="dropdownRef">
      <div
        v-for="(item, index) in items"
        :key="item.id"
        :class="['combobox-option', { 'selected': index === selectedIndex }]"
        @mousedown.prevent="selectItem(item)"
        @mouseenter="selectedIndex = index"
      >
        <strong>{{ item.itemId }}</strong> - {{ item.itemName }}
        <span v-if="showQuantity && item.quantityAvailable !== undefined" class="quantity">
          (Available: {{ item.quantityAvailable }})
        </span>
      </div>
      
      <div v-if="loading" class="combobox-loading">
        Loading...
      </div>
      
      <div v-if="!loading && items.length === 0" class="combobox-empty">
        No items found
      </div>
      
      <div v-if="hasMore && !loading" class="combobox-more" @mousedown.prevent="loadMore">
        Load more...
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Item {
  id: number
  itemId: string
  itemName: string
  quantityAvailable?: number
}

interface Props {
  modelValue: number | string | null
  placeholder?: string
  required?: boolean
  showQuantity?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select an item',
  required: false,
  showQuantity: false
})

const emit = defineEmits(['update:modelValue', 'itemSelected'])

const api = useApi()
const wrapperRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const searchQuery = ref('')
const items = ref<Item[]>([])
const selectedItem = ref<Item | null>(null)
const showDropdown = ref(false)
const loading = ref(false)
const currentPage = ref(1)
const hasMore = ref(false)
const selectedIndex = ref(-1)
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)

// Watch for modelValue changes to update the display
watch(() => props.modelValue, async (newValue) => {
  if (newValue && !selectedItem.value) {
    // First check if the item is already in the loaded items
    const existingItem = items.value.find(i => i.id === newValue)
    if (existingItem) {
      selectedItem.value = existingItem
      searchQuery.value = `${existingItem.itemId} - ${existingItem.itemName}`
    } else {
      // Load items to find the selected one
      await loadItems('', 1, true)
      const item = items.value.find(i => i.id === newValue)
      if (item) {
        selectedItem.value = item
        searchQuery.value = `${item.itemId} - ${item.itemName}`
      }
    }
  } else if (!newValue) {
    selectedItem.value = null
    searchQuery.value = ''
  }
}, { immediate: true })

const loadItems = async (search: string = '', page: number = 1, forSelection: boolean = false) => {
  loading.value = true
  try {
    const response = await api.getItems({
      page,
      limit: 20,
      search
    })
    
    if (forSelection) {
      // When loading for selection, replace items
      items.value = response.items || []
    } else if (page === 1) {
      items.value = response.items || []
    } else {
      // Append items for pagination
      items.value = [...items.value, ...(response.items || [])]
    }
    
    hasMore.value = response.hasMore || false
    currentPage.value = page
  } catch (error) {
    console.error('Failed to load items:', error)
    items.value = []
    hasMore.value = false
  } finally {
    loading.value = false
  }
}

const handleInput = () => {
  if (debounceTimer.value) {
    clearTimeout(debounceTimer.value)
  }
  
  debounceTimer.value = setTimeout(async () => {
    selectedIndex.value = -1
    await loadItems(searchQuery.value, 1)
    showDropdown.value = true
  }, 300)
}

const handleFocus = async () => {
  if (items.value.length === 0) {
    await loadItems('', 1)
  }
  showDropdown.value = true
}

const handleBlur = () => {
  // Delay to allow click events on dropdown items
  setTimeout(() => {
    showDropdown.value = false
    // Restore the selected item text if user didn't select anything
    if (selectedItem.value) {
      searchQuery.value = `${selectedItem.value.itemId} - ${selectedItem.value.itemName}`
    } else if (searchQuery.value && !items.value.find(i => 
      `${i.itemId} - ${i.itemName}` === searchQuery.value
    )) {
      searchQuery.value = ''
    }
  }, 200)
}

const handleKeyDown = (event: KeyboardEvent) => {
  if (!showDropdown.value) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      showDropdown.value = true
      event.preventDefault()
    }
    return
  }
  
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      selectedIndex.value = Math.min(selectedIndex.value + 1, items.value.length - 1)
      scrollToSelected()
      break
    case 'ArrowUp':
      event.preventDefault()
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
      scrollToSelected()
      break
    case 'Enter':
      event.preventDefault()
      if (selectedIndex.value >= 0 && selectedIndex.value < items.value.length) {
        selectItem(items.value[selectedIndex.value])
      }
      break
    case 'Escape':
      event.preventDefault()
      showDropdown.value = false
      break
  }
}

const scrollToSelected = () => {
  if (!dropdownRef.value || selectedIndex.value < 0 || selectedIndex.value >= items.value.length) return
  
  const selectedElement = dropdownRef.value.children[selectedIndex.value] as HTMLElement
  if (selectedElement) {
    selectedElement.scrollIntoView({ block: 'nearest' })
  }
}

const selectItem = (item: Item) => {
  selectedItem.value = item
  searchQuery.value = `${item.itemId} - ${item.itemName}`
  emit('update:modelValue', item.id)
  emit('itemSelected', item)
  showDropdown.value = false
  selectedIndex.value = -1
}

const loadMore = async () => {
  if (!loading.value && hasMore.value) {
    await loadItems(searchQuery.value, currentPage.value + 1)
  }
}

// Click outside to close dropdown
onMounted(() => {
  const handleClickOutside = (event: MouseEvent) => {
    if (wrapperRef.value && !wrapperRef.value.contains(event.target as Node)) {
      showDropdown.value = false
    }
  }
  
  document.addEventListener('click', handleClickOutside)
  
  onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    if (debounceTimer.value) {
      clearTimeout(debounceTimer.value)
    }
  })
})
</script>

<style scoped>
.combobox-wrapper {
  position: relative;
  width: 100%;
}

.combobox-input-container {
  position: relative;
  width: 100%;
}

.combobox-input {
  width: 100%;
  padding: 8px 30px 8px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
}

.combobox-input:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.loading-indicator {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 12px;
}

.combobox-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 250px;
  overflow-y: auto;
  background: white;
  border: 1px solid #ccc;
  border-top: none;
  border-radius: 0 0 4px 4px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  margin-top: -1px;
}

.combobox-option {
  padding: 8px 12px;
  cursor: pointer;
  transition: background-color 0.15s;
}

.combobox-option:hover,
.combobox-option.selected {
  background-color: #f0f0f0;
}

.combobox-option strong {
  color: #2c3e50;
}

.combobox-option .quantity {
  color: #7f8c8d;
  font-size: 12px;
  margin-left: 8px;
}

.combobox-loading,
.combobox-empty {
  padding: 8px 12px;
  text-align: center;
  color: #7f8c8d;
  font-size: 14px;
}

.combobox-more {
  padding: 8px 12px;
  text-align: center;
  color: #3498db;
  cursor: pointer;
  border-top: 1px solid #ecf0f1;
  font-size: 14px;
}

.combobox-more:hover {
  background-color: #f8f9fa;
}
</style>
