<template>
  <div 
    class="dsf-block-preview dsf-ecommerce-showcase"
    :style="previewStyle"
  >
    <!-- Header with Title and Shop All Link -->
    <div class="dsf-ecommerce-showcase__header">
      <InlineText 
        v-model="settings.title" 
        tagName="h2"
        class="dsf-ecommerce-showcase__title"
        :style="{ color: settings.titleColor || '#1F2937' }"
        :is-editor="isEditor"
        placeholder="Section Title"
      />
      <a 
        v-if="settings.showShopAll" 
        href="#" 
        class="dsf-ecommerce-showcase__shop-all"
        @click.prevent
      >
        {{ settings.shopAllText || 'SHOP ALL' }}
      </a>

      <span
        v-if="settings.displayMode === 'products' && settings.showProductCount !== false"
        class="dsf-ecommerce-showcase__count"
        :style="{ color: settings.countColor || '#6B7280' }"
      >
        {{ productCountText }}
      </span>
      
      <!-- Pagination indicator (when slider mode) -->
      <div v-if="isSliderMode" class="dsf-ecommerce-showcase__pagination">
        <span class="dsf-pagination-text">{{ currentPage }}/{{ totalPages }}</span>
      </div>
    </div>
    
    <!-- Content Container -->
    <div class="dsf-ecommerce-showcase__container">
      <!-- Overflow wrapper for clipping -->
      <div ref="viewportRef" class="dsf-ecommerce-showcase__viewport">
        <div 
          ref="itemsContainer"
          class="dsf-ecommerce-showcase__track"
          :style="{ transform: `translateX(-${scrollOffset}px)` }"
        >
          <!-- Category Mode -->
          <template v-if="settings.displayMode !== 'products'">
            <a 
              v-for="cat in displayCategories" 
              :key="cat.id"
              :href="cat.url || '#'"
              class="dsf-showcase-category"
              @click.stop
            >
              <div class="dsf-showcase-category__image">
                <img v-if="cat.image" :src="cat.image" :alt="cat.name" />
                <Folder v-else :size="48" style="color: #CBD5E1;" />
              </div>
              <span class="dsf-showcase-category__name">{{ cat.name }}</span>
            </a>
          </template>
          
          <!-- Product Mode -->
          <template v-else>
            <article
              v-for="product in displayProducts" 
              :key="product.id"
              class="dsf-showcase-product"
              :style="{ width: productWidth + 'px' }"
            >
              <a
                :href="product.permalink || '#'"
                class="dsf-showcase-product__details"
                @click.stop="handleProductLink"
              >
                <div class="dsf-showcase-product__image">
                  <img
                    v-if="product.image"
                    :src="product.image"
                    :alt="product.name"
                    :style="{ objectFit: productImageFit }"
                  />
                  <Package v-else :size="48" style="color: #CBD5E1;" />
                  <span v-if="product.onSale" class="dsf-showcase-product__badge">SALE</span>
                  <span v-if="!isEditor" class="dsf-showcase-product__hover-cta" aria-hidden="true">View details →</span>
                </div>
                <div class="dsf-showcase-product__info">
                  <!-- Products with no price, or that Syndified marks as not sold
                       online, show no price at all rather than an empty slot. -->
                  <div v-if="hasPrice(product)" class="dsf-showcase-product__price" :style="priceStyle(product)">
                    <template v-if="product.onSale">
                      <span class="dsf-showcase-product__price--regular">{{ formatPrice(product.regularPrice) }}</span>
                      <span class="dsf-showcase-product__price--sale">{{ formatPrice(product.salePrice) }}</span>
                    </template>
                    <template v-else>
                      {{ formatPrice(product.price) }}
                    </template>
                  </div>
                  <h4 class="dsf-showcase-product__name">{{ product.name }}</h4>
                </div>
              </a>
              <template v-if="settings.showAddToCart !== false">
                <a
                  v-if="isSoldOnline(product)"
                  :href="productActionUrl(product)"
                  class="dsf-showcase-product__cart"
                  :style="buttonStyle"
                  @click.stop="handleProductLink"
                >
                  {{ settings.buttonText || 'Add to Cart' }}
                </a>
                <!-- Not sold online: Syndified's own CTAs replace the cart button. -->
                <template v-else>
                  <a
                    v-for="(cta, ctaIndex) in ctaButtonsFor(product)"
                    :key="ctaIndex"
                    :href="product.permalink || '#'"
                    class="dsf-showcase-product__cart"
                    :style="buttonStyle"
                    @click.stop="handleProductLink"
                  >{{ cta.label }}</a>
                </template>
              </template>
            </article>
          </template>
        </div>
      </div>
      
      <!-- Slider Navigation Arrows -->
      <button 
        v-if="isSliderMode && canScrollPrev"
        type="button"
        class="dsf-ecommerce-showcase__nav dsf-ecommerce-showcase__nav--prev"
        aria-label="Show previous products"
        @click="scrollPrev"
      >
        <ArrowLeft :size="20" />
      </button>
      <button 
        v-if="isSliderMode && canScrollNext"
        type="button"
        class="dsf-ecommerce-showcase__nav dsf-ecommerce-showcase__nav--next"
        aria-label="Show next products"
        @click="scrollNext"
      >
        <ArrowRight :size="20" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Folder, Package, ArrowRight, ArrowLeft } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const wpData = window.dsfEditorData || window.dsfFrontendData || {}
const itemsContainer = ref(null)
const viewportRef = ref(null)
const products = ref([])
const isLoading = ref(false)
const currentPage = ref(1)
const scrollOffset = ref(0)
const containerWidth = ref(800) // Default width

// Item dimensions
const ITEM_GAP = 24 // 1.5rem gap

// Calculate visible items based on container width
const itemsPerPage = computed(() => {
  const width = containerWidth.value
  if (width >= 1280) return 5
  if (width >= 1024) return 4
  if (width >= 768) return 3
  return 2
})

const productWidth = computed(() => {
  const columns = itemsPerPage.value
  if (!columns) return 0
  const totalGap = ITEM_GAP * (columns - 1)
  const width = (containerWidth.value - totalGap) / columns
  return Math.max(0, width)
})

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const displayItems = computed(() => {
  if (props.settings?.displayMode === 'products') {
    return displayProducts.value
  }
  return displayCategories.value
})

const isSliderMode = computed(() => displayItems.value.length > itemsPerPage.value)
const totalPages = computed(() => Math.ceil(displayItems.value.length / itemsPerPage.value))
const canScrollNext = computed(() => currentPage.value < totalPages.value)
const canScrollPrev = computed(() => currentPage.value > 1)
const totalProductCount = computed(() => products.value.length || (props.isEditor ? DEMO_PRODUCTS.length : 0))
const productCountText = computed(() => {
  const template = props.settings?.countText || '{count} products total'
  return String(template).replace(/\{count\}/g, String(totalProductCount.value))
})
const buttonStyle = computed(() => ({
  backgroundColor: props.settings?.buttonColor || '#2C5F5D',
  color: props.settings?.buttonTextColor || '#FFFFFF',
}))
const productImageFit = computed(() => (
  ['contain', 'cover', 'scale-down'].includes(props.settings?.imageFit)
    ? props.settings.imageFit
    : 'contain'
))

// Categories
const displayCategories = computed(() => {
  const allCategories = wpData.categories || []
  const selectedIds = props.settings?.categoryIds || []
  const limit = props.settings?.limit || 5
  
  let categories = []
  
  if (selectedIds.length > 0) {
    categories = selectedIds
      .map(id => allCategories.find(c => c.id === id))
      .filter(Boolean)
  } else if (allCategories.length > 0) {
    categories = allCategories
  } else {
    // Demo categories
    categories = [
      { id: 1, name: 'Accessories', image: 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?auto=format&fit=crop&w=300&q=80', url: '#' },
      { id: 2, name: 'Casual Seating', image: 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=300&q=80', url: '#' },
      { id: 3, name: 'Dining Furniture', image: 'https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=300&q=80', url: '#' },
      { id: 4, name: 'Fire Pit Tables', image: 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=300&q=80', url: '#' },
      { id: 5, name: 'Side Tables', image: 'https://images.unsplash.com/photo-1532372320572-cda25653a26d?auto=format&fit=crop&w=300&q=80', url: '#' },
    ]
  }
  
  return categories.slice(0, limit)
})

const DEMO_PRODUCTS = [
  { id: 1, name: '42" Rd. Chat Height Capri Fire Pit', price: '$3,499.00', image: null, onSale: false, permalink: '#', addToCartUrl: '#' },
  { id: 2, name: '42" Rd. Occasional Height Capri Fire Pit', price: '$3,499.00', image: null, onSale: false, permalink: '#', addToCartUrl: '#' },
  { id: 3, name: '36 X 58 Occasional Height', price: '$4,999.00', image: null, onSale: false, permalink: '#', addToCartUrl: '#' },
  { id: 4, name: '30" X 50" Santorini Firepit', price: '$3,799.00', image: null, onSale: false, permalink: '#', addToCartUrl: '#' },
  { id: 5, name: 'Paso Robles Round Counter Height Fire Table', regularPrice: '$2,799.00', salePrice: '$2,379.00', image: null, onSale: true, permalink: '#', addToCartUrl: '#' },
  { id: 6, name: 'Modern Fire Bowl', price: '$2,199.00', image: null, onSale: false, permalink: '#', addToCartUrl: '#' },
]

// Products
const displayProducts = computed(() => {
  if (products.value.length > 0) {
    return products.value.slice(0, props.settings?.limit || 10)
  }

  // Demo products exist to give the editor canvas something to lay out. They
  // carry invented prices, so visitors must never see them — on the frontend an
  // empty result stays empty.
  if (!props.isEditor) {
    return []
  }

  return DEMO_PRODUCTS.slice(0, props.settings?.limit || 10)
})

function priceStyle(product) {
  return {
    '--price-color': props.settings?.priceColor || '#6B7280',
    '--sale-color': props.settings?.salePriceColor || '#DC2626',
  }
}

function formatPrice(value) {
  if (value === null || value === undefined) {
    return ''
  }
  const text = String(value).trim()
  if (!text) {
    return ''
  }
  return text.includes('$') ? text : `$${text}`
}

function productActionUrl(product) {
  return product?.addToCartUrl || product?.add_to_cart_url || product?.permalink || '#'
}

/**
 * Whether this product may be sold online. The server decides (no price, or the
 * Syndified plugin marking the product as not sellable) and reports it as
 * sold_online; demo/editor products have no flag and are treated as sellable.
 */
function isSoldOnline(product) {
  return product?.sold_online !== false
}

function hasPrice(product) {
  return Boolean(formatPrice(product?.onSale ? product?.salePrice : product?.price))
}

/** CTAs Syndified defines for a product that is not sold online. */
function ctaButtonsFor(product) {
  return Array.isArray(product?.cta_buttons) ? product.cta_buttons : []
}

function handleProductLink(event) {
  if (props.isEditor) {
    event.preventDefault()
  }
}

function scrollNext() {
  if (canScrollNext.value) {
    currentPage.value++
    updateScrollOffset()
  }
}

function scrollPrev() {
  if (canScrollPrev.value) {
    currentPage.value--
    updateScrollOffset()
  }
}

function updateScrollOffset() {
  const columns = itemsPerPage.value
  const itemTotalWidth = productWidth.value + ITEM_GAP
  scrollOffset.value = (currentPage.value - 1) * itemTotalWidth * columns
}

// Reset page when viewport changes (to avoid being on invalid page)
watch(itemsPerPage, () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = Math.max(1, totalPages.value)
  }
  updateScrollOffset()
})

// ResizeObserver for container width
let resizeObserver = null

function updateContainerWidth() {
  if (viewportRef.value) {
    containerWidth.value = viewportRef.value.offsetWidth
  }
}

onMounted(() => {
  nextTick(() => {
    updateContainerWidth()
    
    if (typeof ResizeObserver !== 'undefined' && viewportRef.value) {
      resizeObserver = new ResizeObserver(() => {
        updateContainerWidth()
      })
      resizeObserver.observe(viewportRef.value)
    }
  })
  
  fetchProducts()
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})

// Fetch products when category changes
async function fetchProducts() {
  if (props.settings?.displayMode !== 'products') return
  if (!props.settings?.categoryId) return
  if (!wpData.isWooActive) return
  
  isLoading.value = true
  
  const formData = new FormData()
  formData.append('action', 'dsf_get_products')
  formData.append('nonce', wpData.nonce)
  formData.append('category_id', props.settings.categoryId)
  formData.append('limit', props.settings?.limit || 10)
  
  // Include pinned product IDs if any are selected
  if (props.settings?.pinnedProductIds?.length) {
    formData.append('product_ids', JSON.stringify(props.settings.pinnedProductIds))
  }
  
  try {
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData })
    const result = await response.json()
    if (result.success) {
      products.value = result.data.products.map(p => ({
        ...p,
        addToCartUrl: p.addToCartUrl || p.add_to_cart_url || p.permalink || '#',
        onSale: p.sale_price && p.sale_price !== p.price,
        regularPrice: p.regular_price || p.price,
        salePrice: p.sale_price || '',
        price: p.price || '',
      }))
    }
  } catch (error) {
    console.error('Error fetching products:', error)
  } finally {
    isLoading.value = false
  }
}

watch(() => [
  props.settings?.displayMode,
  props.settings?.categoryId,
  props.settings?.pinnedProductIds,
  props.settings?.limit
], () => {
  fetchProducts()
}, { deep: true })
</script>

<style scoped>
.dsf-list-item {
  display:flex !important;
}
.dsf-ecommerce-showcase__header {
  display: flex;
  align-items: baseline;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  position: relative;
}

.dsf-ecommerce-showcase {
  container-type: inline-size;
}

.dsf-ecommerce-showcase__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 37px);
  font-weight: 700;
  margin: 0;
  line-height: 1.2;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-ecommerce-showcase__shop-all {
  font-family: var(--dsf-theme-body-font, inherit);
  color: #2C5F5D;
  font-size: var(--dsf-theme-text-2xl, 24px);
  font-weight: 600;
  text-decoration: none;
  letter-spacing: 0.05em;
  white-space: nowrap;
}

.dsf-ecommerce-showcase__shop-all:hover {
  text-decoration: underline;
}

.dsf-ecommerce-showcase__count {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 16px);
  font-weight: 500;
  white-space: nowrap;
}

.dsf-ecommerce-showcase__pagination {
  margin-left: auto;
  color: var(--dsf-gray-500);
  font-size: var(--dsf-theme-text-2xl, 24px);
}

.dsf-ecommerce-showcase__container {
  position: relative;
}

/* Viewport clips the content */
.dsf-ecommerce-showcase__viewport {
  overflow: hidden;
}

/* Track holds items and slides */
.dsf-ecommerce-showcase__track {
  display: flex;
  gap: 1.5rem;
  transition: transform 0.3s ease;
}

/* Navigation Arrow */
.dsf-ecommerce-showcase__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border-radius: 9999px;
  background: #2C5F5D;
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  z-index: 10;
}

.dsf-ecommerce-showcase__nav:hover {
  background: #387875;
  transform: translateY(-50%) scale(1.08);
  border-radius: 9999px;
}

.dsf-ecommerce-showcase__nav--next {
  right: -20px;
}

.dsf-ecommerce-showcase__nav--prev {
  left: -20px;
}

/* Category Cards */
.dsf-showcase-category {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  color: inherit;
  flex-shrink: 0;
  width: 160px;
}

.dsf-showcase-category__image {
  width: 160px;
  height: 160px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--dsf-gray-100);
  display: flex;
  align-items: center;
  justify-content: center;
}

.dsf-showcase-category__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-showcase-category__name {
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 600;
  font-size: var(--dsf-theme-text-2xl, 24px);
  text-align: center;
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Product Cards */
.dsf-showcase-product {
  flex-shrink: 0;
  width: calc((100% - 96px) / 5);
}

.dsf-showcase-product__details {
  color: inherit;
  text-decoration: none;
}

.dsf-showcase-product__image {
  position: relative;
  aspect-ratio: 1;
  background: #f8f8f8;
  border-radius: var(--dsf-radius-md);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
}

.dsf-showcase-product__hover-cta {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%) translateY(10px);
  top: auto;
  right: auto;
  height: 32px;
  padding: 0 16px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.9);
  color: #1F2937;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--dsf-theme-text-sm, 13px);
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  opacity: 0;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  pointer-events: none;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.dsf-showcase-product:hover .dsf-showcase-product__hover-cta {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.dsf-showcase-product__image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
}

.dsf-showcase-product__badge {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  background: #2C5F5D;
  color: white;
  font-size: 0.625rem;
  font-weight: 700;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  letter-spacing: 0.05em;
}

.dsf-showcase-product__info {
  text-align: left;
}

.dsf-showcase-product__price {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-2xl, 24px);
  color: var(--price-color, #6B7280);
  margin-bottom: 0.25rem;
  line-height: 1.2;
}

.dsf-showcase-product__price--regular {
  text-decoration: line-through;
  color: var(--dsf-gray-400);
  margin-right: 0.5rem;
}

.dsf-showcase-product__price--sale {
  color: var(--sale-color, #16A34A);
  font-weight: 600;
}

.dsf-showcase-product__name {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-2xl, 24px);
  font-weight: 500;
  color: var(--dsf-gray-800);
  margin: 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-showcase-product__cart {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  margin-top: 0.9rem;
  padding: 0.65rem 1rem;
  border-radius: var(--dsf-radius-md);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 16px);
  font-weight: 700;
  line-height: 1.2;
  text-align: center;
  text-decoration: none;
  transition: filter 0.2s ease, transform 0.2s ease;
}

.dsf-showcase-product__cart:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

@container (max-width: 1024px) {
  .dsf-ecommerce-showcase__header {
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .dsf-showcase-product {
    width: calc((100% - 72px) / 4);
  }
}

@container (max-width: 768px) {
  .dsf-ecommerce-showcase__header {
    flex-direction: column;
    align-items: flex-start;
  }

  .dsf-ecommerce-showcase__track {
    gap: 1rem;
  }

  .dsf-showcase-category { width: 140px; }
  .dsf-showcase-product { width: calc((100% - 48px) / 3); }
}

@container (max-width: 520px) {
  .dsf-showcase-product { width: calc((100% - 24px) / 2); }
}
</style>
