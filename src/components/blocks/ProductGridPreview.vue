<template>
  <div 
    class="dsf-block-preview dsf-product-grid-preview"
    :style="previewStyle"
  >
    <!-- Actual Content -->
    <template v-if="!loading">
      <div v-if="!isWooActive && isEditor" class="dsf-product-grid-preview__notice">
        <span>⚠️ WooCommerce is not active. Showing demo products.</span>
      </div>
      
      <InlineText 
        v-model="settings.title" 
        tagName="h2"
        class="dsf-product-grid-preview__title"
        :style="{ color: settings.titleColor || '#1F2937' }"
        :is-editor="isEditor"
        placeholder="Featured Products"
      />
      
      <div 
        class="dsf-product-grid-preview__items"
        :style="{ '--columns': settings.columns || 3 }"
      >
        <div 
          v-for="product in displayProducts" 
          :key="product.id"
          class="dsf-product-card-preview"
        >
          <div class="dsf-product-card-preview__image">
            <img v-if="product.image" :src="product.image" :alt="product.name" />
            <ShoppingBag v-else :size="32" />
          </div>
          <div class="dsf-product-card-preview__body">
            <h4 class="dsf-product-card-preview__name">{{ product.name }}</h4>
            <div v-if="settings.showPrice !== false" class="dsf-product-card-preview__price">
              {{ product.price || '$99.00' }}
            </div>
            <button v-if="settings.showButton !== false" class="dsf-product-card-preview__btn">
              {{ settings.buttonText || 'Add to Cart' }}
            </button>
          </div>
        </div>
      </div>
    </template>
    
    <!-- Skeleton Loading State -->
    <div v-else class="dsf-product-grid-preview__skeleton">
      <!-- Title Skeleton -->
      <div class="dsf-skeleton-title dsf-shimmer"></div>

      <div 
        class="dsf-product-grid-preview__items"
        :style="{ '--columns': settings.columns || 3 }"
      >
        <div 
          v-for="n in (settings.limit || 6)" 
          :key="n" 
          class="dsf-skeleton-card"
        >
          <div class="dsf-skeleton-image dsf-shimmer"></div>
          <div class="dsf-skeleton-body">
            <div class="dsf-skeleton-text dsf-w-3-4 dsf-shimmer"></div>
            <div class="dsf-skeleton-text dsf-w-1-2 dsf-shimmer"></div>
            <div class="dsf-skeleton-btn dsf-shimmer"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { ShoppingBag } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const products = ref([])
const loading = ref(false)
const wpData = window.dsfEditorData || {}

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 60}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
}))

const isWooActive = computed(() => wpData.isWooActive || false)

const displayProducts = computed(() => {
  if (products.value.length > 0) {
    return products.value.slice(0, props.settings?.limit || 6)
  }
  
  // Fallback demo products
  const limit = props.settings?.limit || 6
  return Array.from({ length: Math.min(limit, 6) }, (_, i) => ({
    id: i + 1,
    name: ['Premium Product', 'Popular Item', 'Best Seller', 'New Arrival', 'Featured', 'Top Pick'][i] || 'Product',
    price: ['$99.00', '$79.00', '$129.00', '$49.00', '$159.00', '$89.00'][i] || '$99.00',
    image: null,
  }))
})

// Watch for settings changes that affect products
watch(
  () => [
    props.settings?.source,
    props.settings?.categoryId,
    props.settings?.productIds,
    props.settings?.pinnedProductIds,
    props.settings?.limit
  ],
  () => {
    if (wpData.isWooActive) {
      fetchProducts()
    }
  },
  { deep: true }
)

onMounted(async () => {
  // Fetch products from WooCommerce
  if (wpData.isWooActive) {
    await fetchProducts()
  }
})

async function fetchProducts() {
  loading.value = true
  const formData = new FormData()
  formData.append('action', 'dsf_get_products')
  formData.append('nonce', wpData.nonce)
  
  // Send both Pins and Category to support hybrid mode (Pins first)
  const productIds = props.settings?.source === 'manual' 
    ? props.settings?.productIds 
    : props.settings?.pinnedProductIds

  if (productIds?.length) {
    formData.append('product_ids', JSON.stringify(productIds))
  }
  
  if (props.settings?.categoryId) {
    formData.append('category_id', props.settings.categoryId)
  }
  
  formData.append('source', props.settings?.source || 'category')
  
  formData.append('limit', props.settings?.limit || 6)
  
  try {
    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      body: formData,
    })
    const data = await response.json()
    
    if (data.success) {
      products.value = data.data.products
    }
  } catch (error) {
    console.error('Error fetching products:', error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.dsf-product-grid-preview__title {
  text-align: center;
  font-size: 1.875rem;
  font-weight: 600;
  margin-bottom: 2rem;
  /* color comes from inline style now */
}

.dsf-product-grid-preview {
  container-type: inline-size;
}

.dsf-product-grid-preview__items {
  display: grid;
  grid-template-columns: repeat(var(--columns, 3), 1fr);
  gap: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.dsf-product-card-preview {
  background: white;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  border: 1px solid var(--dsf-gray-200);
  transition: all 0.2s ease;
}

.dsf-product-card-preview:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.dsf-product-card-preview__image {
  aspect-ratio: 1;
  background: var(--dsf-gray-100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
  border-bottom: 1px solid var(--dsf-gray-100);
  position: relative;
  overflow: hidden;
}

.dsf-product-card-preview__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  mix-blend-mode: multiply; /* Ensures product blends with gray bg if it has white bg */
  transition: transform 0.3s ease;
}

.dsf-product-card-preview:hover .dsf-product-card-preview__image img {
  transform: scale(1.05);
}

.dsf-product-card-preview__body {
  padding: 1.25rem;
  background: white;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-product-card-preview__name {
  font-weight: 600;
  color: var(--dsf-gray-900);
  margin: 0;
  font-size: 1rem;
  line-height: 1.4;
}

.dsf-product-card-preview__price {
  color: var(--dsf-primary-600);
  font-weight: 600;
  font-size: 1.125rem;
}

.dsf-product-card-preview__btn {
  width: 100%;
  padding: 0.75rem;
  background: var(--dsf-primary-600);
  color: white;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-weight: 500;
  cursor: pointer;
  margin-top: 0.5rem;
  transition: background-color 0.2s;
}

.dsf-product-card-preview__btn:hover {
  background: var(--dsf-primary-700);
}

/* Skeleton Loader */

.dsf-skeleton-title {
  height: 2.5rem;
  width: 300px;
  background: var(--dsf-gray-100);
  margin: 0 auto 2rem;
  border-radius: 8px;
}

.dsf-skeleton-card {
  background: white;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  border: 1px solid var(--dsf-gray-200);
  height: 100%;
}

.dsf-skeleton-image {
  aspect-ratio: 1;
  width: 100%;
  background: var(--dsf-gray-100);
  border-bottom: 1px solid var(--dsf-gray-100);
}

.dsf-skeleton-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-skeleton-text {
  height: 1rem;
  background: var(--dsf-gray-100);
  border-radius: 4px;
}

.dsf-skeleton-btn {
  height: 42px;
  width: 100%;
  background: var(--dsf-gray-100);
  border-radius: 6px;
  margin-top: 0.5rem;
}

.dsf-w-3-4 { width: 75%; }
.dsf-w-1-2 { width: 50%; }

.dsf-shimmer {
  position: relative;
  overflow: hidden;
}

.dsf-shimmer::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.5),
    transparent
  );
  transform: translateX(-100%);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  100% { transform: translateX(100%); }
}

.dsf-product-grid-preview__notice {
  background: #FEF3C7;
  color: #92400E;
  padding: 0.75rem 1rem;
  border-radius: var(--dsf-radius-md);
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
  text-align: center;
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

.dsf-product-grid-preview {
  position: relative;
}

@container (max-width: 1024px) {
  .dsf-product-grid-preview__items {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}

@container (max-width: 768px) {
  .dsf-product-grid-preview__items {
    grid-template-columns: 1fr !important;
  }
}
</style>
