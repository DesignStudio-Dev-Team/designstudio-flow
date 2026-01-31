<template>
  <div class="dsf-products-selector">
    <!-- Search Section -->
    <div class="dsf-settings-card">
      <div v-if="!config?.hideSearchCardTitle" class="dsf-setting-card__title">Search Products</div>
      <p v-if="!config?.hideSearchCardTitle" class="dsf-setting-card__desc">Search and select products to display in this block</p>
      
      <div class="dsf-relative" :class="{ 'dsf-mt-3': !config?.hideSearchCardTitle }">
        <Search :size="16" class="dsf-search-icon" />
        <input 
          type="text"
          class="dsf-input dsf-pl-10"
          :placeholder="config?.searchPlaceholder || 'Search products...'"
          v-model="searchQuery"
          @input="debouncedSearch"
        />
      </div>
      
      <!-- Search Results -->
      <div v-if="isSearching" class="dsf-loading-state">
        Searching...
      </div>
      
      <div v-else-if="searchResults.length > 0" class="dsf-list-container dsf-search-results">
        <div 
          v-for="product in searchResults" 
          :key="product.id"
          class="dsf-list-item dsf-search-item"
          :class="{ 'dsf-list-item--selected': isSelected(product.id) }"
          @click="toggleProduct(product)"
        >
          <img 
            :src="product.image || placeholderImage" 
            :alt="product.name"
            class="dsf-list-item__image"
          />
          <div class="dsf-list-item__content">
            <div class="dsf-list-item__title">{{ product.name }}</div>
            <div class="dsf-list-item__price" v-html="product.price"></div>
          </div>
          <div class="dsf-list-item__action">
            <div class="dsf-checkbox" :class="{ 'dsf-checkbox--checked': isSelected(product.id) }">
              <Check v-if="isSelected(product.id)" :size="12" />
            </div>
          </div>
        </div>
      </div>
      
       <div v-if="searchQuery && !isSearching && searchResults.length === 0" class="dsf-empty-state">
        No products found
      </div>
    </div>
    
    <!-- Selected Products -->
    <div v-if="selectedProducts.length > 0" class="dsf-settings-card">
      <div class="dsf-setting-card__header">
        <span class="dsf-setting-card__title">Selected Products</span>
        <span class="dsf-badge dsf-badge--blue">{{ selectedProducts.length }} selected</span>
      </div>
      <p class="dsf-setting-card__desc">These products will be displayed in your block</p>
      
      <div class="dsf-list-container dsf-selected-container dsf-mt-3">
        <draggable 
          v-model="selectedProducts" 
          item-key="id"
          handle=".dsf-drag-handle"
          @end="updateOrder"
          class="dsf-drag-list"
        >
          <template #item="{ element, index }">
             <div class="dsf-list-item dsf-selected-item">
              <div class="dsf-drag-handle-wrapper">
                 <GripVertical :size="16" class="dsf-drag-handle" />
              </div>
              <img 
                :src="element.image || placeholderImage" 
                :alt="element.name"
                :title="element.name"
                class="dsf-list-item__image"
              />
              <div class="dsf-list-item__content">
                <div class="dsf-list-item__title">{{ element.name }}</div>
              </div>
              
              <div class="dsf-list-item__actions">
                <button 
                  class="dsf-icon-btn dsf-text-remove"
                  @click.stop="removeProduct(element.id)"
                  title="Remove product"
                >
                  <Trash2 :size="18" />
                </button>
              </div>
            </div>
          </template>
        </draggable>
      </div>
    </div>
    
    <!-- WooCommerce Info -->
    <div v-if="!isWooActive" class="dsf-info-box">
      <ShoppingBag :size="20" />
      <div class="dsf-info-box__content">
        <div class="dsf-info-box__title">WooCommerce Integration</div>
        <p>In a live WordPress environment, these products would be pulled directly from your WooCommerce catalog.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { Search, Check, GripVertical, ShoppingBag, X, Trash2 } from 'lucide-vue-next'
import draggable from 'vuedraggable'

const props = defineProps({
  value: {
    type: Array,
    default: () => []
  },
  config: Object
})

const emit = defineEmits(['update'])

const wpData = window.dsfEditorData || {}
const isWooActive = wpData.isWooActive || false

const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const selectedProducts = ref([])

const placeholderImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCBmaWxsPSIjZTVlN2ViIiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIvPjwvc3ZnPg=='

let searchTimeout = null

// Load initial selected products
onMounted(async () => {
  if (props.value && props.value.length > 0) {
    await fetchSelectedProducts(props.value)
  }
})

async function fetchSelectedProducts(ids) {
  if (!ids.length) return
  
  const formData = new FormData()
  formData.append('action', 'dsf_get_products')
  formData.append('nonce', wpData.nonce)
  formData.append('product_ids', JSON.stringify(ids))
  
  try {
    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      body: formData,
    })
    const data = await response.json()
    if (data.success) {
      // Sort products to match ID order
      const fetched = data.data.products
      selectedProducts.value = ids
        .map(id => fetched.find(p => p.id === id))
        .filter(Boolean)
    }
  } catch (error) {
    console.error('Error fetching selected products:', error)
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(searchProducts, 300)
}

async function searchProducts() {
  if (!searchQuery.value || searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }
  
  isSearching.value = true
  
  const formData = new FormData()
  formData.append('action', 'dsf_search_products')
  formData.append('nonce', wpData.nonce)
  formData.append('search', searchQuery.value)
  
  try {
    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      body: formData,
    })
    const data = await response.json()
    
    if (data.success) {
      searchResults.value = data.data.products
    }
  } catch (error) {
    console.error('Search error:', error)
  } finally {
    isSearching.value = false
  }
}

function isSelected(productId) {
  return (props.value || []).includes(productId)
}

function toggleProduct(product) {
  const current = props.value || []
  
  if (isSelected(product.id)) {
    selectedProducts.value = selectedProducts.value.filter(p => p.id !== product.id)
    emit('update', current.filter(id => id !== product.id))
  } else {
    selectedProducts.value.push(product)
    emit('update', [...current, product.id])
  }
}

function removeProduct(productId) {
  selectedProducts.value = selectedProducts.value.filter(p => p.id !== productId)
  emit('update', (props.value || []).filter(id => id !== productId))
}

function moveUp(index) {
  if (index === 0) return
  
  const items = [...selectedProducts.value]
  const item = items.splice(index, 1)[0]
  items.splice(index - 1, 0, item)
  selectedProducts.value = items
  
  updateOrder()
}

function moveDown(index) {
  if (index === selectedProducts.value.length - 1) return
  
  const items = [...selectedProducts.value]
  const item = items.splice(index, 1)[0]
  items.splice(index + 1, 0, item)
  selectedProducts.value = items
  
  updateOrder()
}

function updateOrder() {
  emit('update', selectedProducts.value.map(p => p.id))
}
</script>

<style scoped>
.dsf-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--dsf-gray-400);
}

.dsf-pl-10 {
  padding-left: 2.5rem;
}

.dsf-list-container {
  margin-top: 0.75rem;
  max-height: 240px;
  overflow-y: auto;
}

.dsf-selected-container {
  max-height: none;
  overflow-y: visible;
}

/* List Item Base */
.dsf-list-item {
  display: flex !important; /* Force flex to override generic list-item styles */
  flex-direction: row; /* Force row layout */
  flex-wrap: nowrap;   /* Prevent wrapping */
  align-items: center; /* Vertically center everything */
  padding: 0.75rem;
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-sm);
  margin-bottom: 0.5rem;
  transition: all 0.15s;
  height: auto; 
  min-height: 56px;
}

/* ... (skip search item hover styles) ... */

/* Selected Items Styles (Drag List) */
.dsf-drag-list {
  list-style: none; /* No bullets */
  padding: 0;
  margin: 0;
}

.dsf-selected-item {
  background: #EFF6FF; /* bg-blue-50 */
  border: 1px solid #BFDBFE; /* border-blue-200 */
  border-radius: 12px; /* Smoother radius */
  padding: 8px 16px; /* Reduced vertical padding */
  display: flex !important;
  flex-direction: row; /* Force row */
  flex-wrap: nowrap;   /* No wrap */
  align-items: center;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
  height: auto;
  min-height: 64px; /* Slightly taller for selected items */
}

/* ... (skip hover styles) ... */

.dsf-list-item__image {
  width: 40px; /* Slightly larger base size */
  height: 40px;
  border-radius: 4px;
  object-fit: cover;
  margin-right: 1rem; /* Ensure spacing between image and content */
  background: var(--dsf-gray-100);
  flex-shrink: 0; /* Prevent image from shrinking */
}

/* Override image size for selected items */
.dsf-selected-item .dsf-list-item__image {
  width: 48px;
  height: 48px;
  object-fit: contain;
  background: white;
  border: 1px solid #E5E7EB;
  padding: 2px;
  border-radius: 6px;
  margin-right: 1rem;
}

.dsf-list-item__content {
  flex: 1;
  min-width: 0; /* Enable truncation */
  display: flex; 
  flex-direction: column; /* Stack Title and Price vertically */
  justify-content: center;
  align-items: flex-start;
  margin-right: 0.5rem;
}

/* Selected item content might just be title, ensure it centers vertically or matches */
.dsf-selected-item .dsf-list-item__content {
  flex-direction: row; /* Keep title inline if possible or column if needed, usually just title */
  align-items: center;
}

.dsf-list-item__title {
  font-weight: 500;
  color: var(--dsf-gray-800);
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%; /* Ensure it truncates */
  line-height: 1.2;
}

.dsf-selected-item .dsf-list-item__title {
  font-weight: 600;
  color: #111827;
  font-size: 1rem;
}

/* Actions */
.dsf-list-item__actions {
  margin-left: auto;
}

.dsf-text-btn {
  background: none;
  border: none;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  padding: 6px 12px;
  border-radius: 6px;
  transition: all 0.15s;
}

.dsf-icon-btn {
  background: white;
  border: 1px solid var(--dsf-gray-200);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
  color: var(--dsf-gray-500);
}

.dsf-icon-btn:hover {
  background: #FEF2F2;
  border-color: #FECACA;
  color: #DC2626;
}

.dsf-text-remove {
  color: #DC2626; /* Red text */
}

.dsf-text-remove:hover {
  background-color: rgba(220, 38, 38, 0.1);
}

/* Drag Handle */
.dsf-drag-handle-wrapper {
  color: #9CA3AF; /* gray-400 */
  margin-right: 1rem;
  cursor: grab;
  display: flex;
  align-items: center;
}

.dsf-drag-handle-wrapper:hover {
  color: #6B7280;
}

.dsf-drag-handle-wrapper:active {
  cursor: grabbing;
}

/* Checkbox */
.dsf-checkbox {
  width: 20px;
  height: 20px;
  border: 1px solid var(--dsf-gray-300);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  transition: all 0.15s;
}

.dsf-checkbox--checked {
  background-color: var(--dsf-primary-500);
  border-color: var(--dsf-primary-500);
}

/* Badge */
.dsf-badge--blue {
  background-color: #DBEAFE; /* blue-100 */
  color: #1E40AF; /* blue-800 */
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 9999px;
  font-weight: 600;
}

.dsf-info-box {
  margin-top: 1rem;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: var(--dsf-radius-lg);
  padding: 1rem;
  display: flex;
  gap: 0.75rem;
  color: #1E40AF;
}

.dsf-info-box__title {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.dsf-info-box p {
  margin: 0;
  font-size: 0.875rem;
  color: #3B82F6;
}
</style>
