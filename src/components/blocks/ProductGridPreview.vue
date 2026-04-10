<template>
  <div
    class="dsf-block-preview dsf-product-grid-preview"
    :style="previewStyle"
  >
    <!-- WooCommerce notice in editor -->
    <div v-if="!isWooActive && isEditor" class="dsf-product-grid-preview__notice">
      <span>⚠️ WooCommerce is not active. Showing demo products.</span>
    </div>

    <!-- Section Title -->
    <InlineText
      v-model="settings.title"
      tagName="h2"
      class="dsf-product-grid-preview__title"
      :style="{ color: settings.titleColor || '#1F2937' }"
      :is-editor="isEditor"
      placeholder="Featured Products"
    />

    <!-- Main Layout: sidebar + grid -->
    <div
      class="dsf-product-grid-preview__layout"
      :class="[
        filtersEnabled ? 'dsf-product-grid-preview__layout--has-sidebar' : '',
        filtersEnabled && filterPosition === 'right' ? 'dsf-product-grid-preview__layout--sidebar-right' : '',
      ]"
    >
      <!-- Filter Sidebar -->
      <aside v-if="filtersEnabled" class="dsf-filter-sidebar">

        <!-- Active Filters -->
        <div v-if="activeFilterCount > 0" class="dsf-filter-sidebar__active">
          <div class="dsf-filter-sidebar__active-header">
            <span class="dsf-filter-sidebar__active-label">Active Filters</span>
            <button class="dsf-filter-sidebar__clear-all" @click="clearAllFilters">Clear all</button>
          </div>
          <div class="dsf-filter-sidebar__chips">
            <span
              v-for="chip in activeChips"
              :key="chip.key"
              class="dsf-filter-chip"
            >
              {{ chip.label }}
              <button class="dsf-filter-chip__remove" @click="removeChip(chip)">×</button>
            </span>
          </div>
        </div>

        <!-- Price Range Filter -->
        <div v-if="settings.filterShowPrice !== false" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('price')">
            <span class="dsf-filter-group__title">Price</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.price ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.price" class="dsf-filter-group__body">
            <div class="dsf-price-range">
              <div class="dsf-price-range__labels">
                <span>${{ priceFilter[0] }}</span>
                <span>${{ priceFilter[1] }}</span>
              </div>
              <div class="dsf-price-range__track" ref="priceTrack">
                <div
                  class="dsf-price-range__fill"
                  :style="priceRangeStyle"
                ></div>
                <input
                  type="range"
                  class="dsf-price-range__input dsf-price-range__input--min"
                  :min="priceMin"
                  :max="priceMax"
                  :value="priceFilter[0]"
                  @input="onPriceMinInput"
                />
                <input
                  type="range"
                  class="dsf-price-range__input dsf-price-range__input--max"
                  :min="priceMin"
                  :max="priceMax"
                  :value="priceFilter[1]"
                  @input="onPriceMaxInput"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Category Filter -->
        <div v-if="settings.filterShowCategory !== false && allCategories.length > 0" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('category')">
            <span class="dsf-filter-group__title">Category</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.category ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.category" class="dsf-filter-group__body">
            <label
              v-for="cat in allCategories"
              :key="cat.value"
              class="dsf-filter-option"
            >
              <input
                type="checkbox"
                class="dsf-filter-option__check"
                :value="cat.value"
                v-model="activeCategories"
              />
              <span class="dsf-filter-option__label">{{ cat.label }}</span>
              <span class="dsf-filter-option__count">({{ cat.count }})</span>
            </label>
          </div>
        </div>

        <!-- Brand Filter -->
        <div v-if="settings.filterShowBrand !== false && allBrands.length > 0" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('brand')">
            <span class="dsf-filter-group__title">Brand</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.brand ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.brand" class="dsf-filter-group__body">
            <label
              v-for="brand in allBrands"
              :key="brand.value"
              class="dsf-filter-option"
            >
              <input
                type="checkbox"
                class="dsf-filter-option__check"
                :value="brand.value"
                v-model="activeBrands"
              />
              <span class="dsf-filter-option__label">{{ brand.label }}</span>
              <span class="dsf-filter-option__count">({{ brand.count }})</span>
            </label>
          </div>
        </div>

        <!-- Material Filter -->
        <div v-if="settings.filterShowMaterial && allMaterials.length > 0" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('material')">
            <span class="dsf-filter-group__title">Material</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.material ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.material" class="dsf-filter-group__body">
            <label
              v-for="mat in allMaterials"
              :key="mat.value"
              class="dsf-filter-option"
            >
              <input
                type="checkbox"
                class="dsf-filter-option__check"
                :value="mat.value"
                v-model="activeMaterials"
              />
              <span class="dsf-filter-option__label">{{ mat.label }}</span>
              <span class="dsf-filter-option__count">({{ mat.count }})</span>
            </label>
          </div>
        </div>

        <!-- Color Filter -->
        <div v-if="settings.filterShowColor && allColors.length > 0" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('color')">
            <span class="dsf-filter-group__title">Color</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.color ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.color" class="dsf-filter-group__body dsf-filter-group__body--colors">
            <label
              v-for="color in allColors"
              :key="color.value"
              class="dsf-filter-option dsf-filter-option--color"
            >
              <input
                type="checkbox"
                class="dsf-filter-option__check"
                :value="color.value"
                v-model="activeColors"
              />
              <span
                class="dsf-filter-option__swatch"
                :style="{ backgroundColor: colorSwatch(color.value) }"
              ></span>
              <span class="dsf-filter-option__label">{{ color.label }}</span>
              <span class="dsf-filter-option__count">({{ color.count }})</span>
            </label>
          </div>
        </div>

        <!-- Tags Filter -->
        <div v-if="settings.filterShowTags && allTags.length > 0" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('tags')">
            <span class="dsf-filter-group__title">Tags</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.tags ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.tags" class="dsf-filter-group__body dsf-filter-group__body--tags">
            <label
              v-for="tag in allTags"
              :key="tag.value"
              class="dsf-filter-option dsf-filter-option--tag"
            >
              <input
                type="checkbox"
                class="dsf-filter-option__check"
                :value="tag.value"
                v-model="activeTags"
              />
              <span class="dsf-filter-option__label">{{ tag.label }}</span>
              <span class="dsf-filter-option__count">({{ tag.count }})</span>
            </label>
          </div>
        </div>

        <!-- Rating Filter -->
        <div v-if="settings.filterShowRating" class="dsf-filter-group">
          <button class="dsf-filter-group__header" @click="toggleGroup('rating')">
            <span class="dsf-filter-group__title">Rating</span>
            <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups.rating ? 'dsf-filter-group__chevron--open' : '']" />
          </button>
          <div v-if="openGroups.rating" class="dsf-filter-group__body">
            <label
              v-for="r in [4, 3, 2, 1]"
              :key="r"
              class="dsf-filter-option dsf-filter-option--rating"
            >
              <input
                type="radio"
                class="dsf-filter-option__check"
                name="rating"
                :value="r"
                v-model="activeRating"
              />
              <span class="dsf-filter-rating-stars">
                <Star
                  v-for="s in 5"
                  :key="s"
                  :size="14"
                  :class="s <= r ? 'dsf-star--filled' : 'dsf-star--empty'"
                />
              </span>
              <span class="dsf-filter-option__label">& up</span>
            </label>
            <label class="dsf-filter-option dsf-filter-option--rating">
              <input
                type="radio"
                class="dsf-filter-option__check"
                name="rating"
                :value="null"
                v-model="activeRating"
              />
              <span class="dsf-filter-option__label">All ratings</span>
            </label>
          </div>
        </div>

      </aside>

      <!-- Product Area -->
      <div class="dsf-product-grid-preview__main">
        <!-- Results Bar -->
        <div v-if="filtersEnabled && !loading" class="dsf-product-grid-preview__results-bar">
          <span class="dsf-product-grid-preview__results-count">
            {{ filteredProducts.length }} {{ filteredProducts.length === 1 ? 'product' : 'products' }}
          </span>
        </div>

        <!-- Loading Skeleton -->
        <template v-if="loading">
          <div
            class="dsf-product-grid-preview__items"
            :style="gridStyle"
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
        </template>

        <!-- Products Grid -->
        <template v-else-if="filteredProducts.length > 0">
          <div
            class="dsf-product-grid-preview__items"
            :style="gridStyle"
          >
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="dsf-product-card-preview"
            >
              <div class="dsf-product-card-preview__image">
                <img v-if="product.image" :src="product.image" :alt="product.name" />
                <ShoppingBag v-else :size="32" />
              </div>
              <div class="dsf-product-card-preview__body">
                <h4 class="dsf-product-card-preview__name">{{ product.name }}</h4>
                <div class="dsf-product-card-preview__meta">
                  <div v-if="settings.showPrice !== false" class="dsf-product-card-preview__price">
                    {{ product.price || '$99.00' }}
                  </div>
                  <div v-if="product.rating > 0" class="dsf-product-card-preview__rating">
                    <Star v-for="s in 5" :key="s" :size="12" :class="s <= Math.round(product.rating) ? 'dsf-star--filled' : 'dsf-star--empty'" />
                  </div>
                </div>
                <button v-if="settings.showButton !== false" class="dsf-product-card-preview__btn">
                  {{ settings.buttonText || 'Add to Cart' }}
                </button>
              </div>
            </div>
          </div>
        </template>

        <!-- No Results -->
        <div v-else class="dsf-product-grid-preview__no-results">
          <ShoppingBag :size="40" class="dsf-product-grid-preview__no-results-icon" />
          <p>No products match your filters.</p>
          <button class="dsf-product-grid-preview__no-results-btn" @click="clearAllFilters">Clear filters</button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, reactive, onMounted, watch } from 'vue'
import { ShoppingBag, ChevronDown, Star } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const products = ref([])
const loading = ref(false)
const wpData = window.dsfEditorData || {}

// ─── Filter state ─────────────────────────────────────────────────────────────
// Bounds pre-seeded to match the demo product price range so the slider works
// immediately in the editor without needing a reactive sync on mount.
const priceMin = ref(89)
const priceMax = ref(899)
const priceFilter = ref([89, 899])
const activeCategories = ref([])
const activeBrands = ref([])
const activeMaterials = ref([])
const activeColors = ref([])
const activeTags = ref([])
const activeRating = ref(null)
const openGroups = reactive({ price: true, category: true, brand: true, material: false, color: false, tags: false, rating: false })

// ─── Computed ─────────────────────────────────────────────────────────────────
const isWooActive = computed(() => wpData.isWooActive || false)
const filtersEnabled = computed(() => props.settings?.enableFilters === true)
const filterPosition = computed(() => props.settings?.filterPosition || 'left')

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const gridStyle = computed(() => {
  const cols = filtersEnabled.value
    ? Math.max(1, (parseInt(props.settings?.columns) || 3) - 1)
    : (parseInt(props.settings?.columns) || 3)
  return { '--columns': cols }
})

// All products before client filters (fetched or demo)
const sourceProducts = computed(() => {
  if (products.value.length > 0) return products.value

  // Demo products for editor when WooCommerce is not active
  return Array.from({ length: 8 }, (_, i) => ({
    id: i + 1,
    name: ['Premium Teak Chair', 'Acme Lounger', 'Coastal Sofa', 'Rattan Table', 'Oak Bench', 'Cedar Swing', 'Bamboo Planter', 'Iron Firepit'][i] || 'Product',
    price: ['$349.00', '$229.00', '$899.00', '$479.00', '$199.00', '$699.00', '$89.00', '$399.00'][i] || '$99.00',
    price_num: [349, 229, 899, 479, 199, 699, 89, 399][i] || 99,
    rating: [4.8, 4.2, 4.5, 3.9, 4.0, 4.7, 3.5, 4.3][i] || 0,
    image: null,
    categories: [['Chairs'], ['Chairs', 'Outdoor'], ['Sofas'], ['Tables'], ['Benches'], ['Swings'], ['Planters'], ['Fire Pits']][i] || [],
    tags: [['bestseller', 'sale'], ['sale'], ['new'], ['bestseller'], ['clearance'], ['new', 'featured'], ['sale'], ['featured']][i] || [],
    attributes: {
      brand: [['Acme'], ['Acme'], ['Coastal Living'], ['Rattan Co'], ['OakWorks'], ['SwingMaster'], ['GreenLife'], ['IronCraft']][i] || [],
      material: [['Teak'], ['Aluminum'], ['Rattan'], ['Rattan'], ['Oak'], ['Cedar'], ['Bamboo'], ['Iron']][i] || [],
      color: [['Brown'], ['Gray'], ['Beige'], ['Natural'], ['Brown'], ['Brown'], ['Green'], ['Black']][i] || [],
    },
  }))
})

// Derive available filter options from a product list
function buildOptions(items, accessor) {
  const counts = {}
  items.forEach(p => {
    const values = accessor(p)
    if (Array.isArray(values)) {
      values.forEach(v => { counts[v] = (counts[v] || 0) + 1 })
    }
  })
  return Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .map(([v, c]) => ({ value: v, label: v, count: c }))
}

/**
 * Returns sourceProducts filtered by ALL active filters EXCEPT the given group.
 * Used so each group's options reflect what the other active filters allow,
 * making the sidebar "smart" — options that would yield zero results disappear.
 */
function filterExcluding(exclude) {
  let result = sourceProducts.value

  if (exclude !== 'price') {
    result = result.filter(p => {
      const n = p.price_num || 0
      return n >= priceFilter.value[0] && n <= priceFilter.value[1]
    })
  }

  if (exclude !== 'category' && activeCategories.value.length > 0) {
    result = result.filter(p => activeCategories.value.some(c => (p.categories || []).includes(c)))
  }

  if (exclude !== 'brand' && activeBrands.value.length > 0) {
    result = result.filter(p => activeBrands.value.some(b => (p.attributes?.brand || []).includes(b)))
  }

  if (exclude !== 'material' && activeMaterials.value.length > 0) {
    result = result.filter(p => activeMaterials.value.some(m => (p.attributes?.material || []).includes(m)))
  }

  if (exclude !== 'color' && activeColors.value.length > 0) {
    result = result.filter(p => activeColors.value.some(c => (p.attributes?.color || []).includes(c)))
  }

  if (exclude !== 'tag' && activeTags.value.length > 0) {
    result = result.filter(p => activeTags.value.some(t => (p.tags || []).includes(t)))
  }

  if (exclude !== 'rating' && activeRating.value !== null) {
    result = result.filter(p => (p.rating || 0) >= activeRating.value)
  }

  return result
}

// Each group's options are built from products filtered by every OTHER active filter.
// This makes options that would produce zero results disappear automatically.
const allCategories = computed(() => buildOptions(filterExcluding('category'), p => p.categories || []))
const allBrands     = computed(() => buildOptions(filterExcluding('brand'),    p => p.attributes?.brand || []))
const allMaterials  = computed(() => buildOptions(filterExcluding('material'), p => p.attributes?.material || []))
const allColors     = computed(() => buildOptions(filterExcluding('color'),    p => p.attributes?.color || []))
const allTags       = computed(() => buildOptions(filterExcluding('tag'),      p => p.tags || []))

// Sync price range bounds when real products load from WooCommerce.
// Not immediate — price bounds from demo products are always [89, 899] and
// set at ref initialisation, so we only need to update after a real fetch.
watch(products, (items) => {
  if (items.length === 0) return
  const prices = items.map(p => p.price_num || 0).filter(n => n > 0)
  if (prices.length === 0) return
  const min = Math.floor(Math.min(...prices))
  const max = Math.ceil(Math.max(...prices))
  priceMin.value = min
  priceMax.value = max
  priceFilter.value = [min, max]
})

const filteredProducts = computed(() => {
  if (!filtersEnabled.value) {
    return sourceProducts.value.slice(0, props.settings?.limit || 6)
  }

  let result = sourceProducts.value

  // Price
  result = result.filter(p => {
    const n = p.price_num || 0
    return n >= priceFilter.value[0] && n <= priceFilter.value[1]
  })

  // Categories
  if (activeCategories.value.length > 0) {
    result = result.filter(p =>
      activeCategories.value.some(c => (p.categories || []).includes(c))
    )
  }

  // Brand
  if (activeBrands.value.length > 0) {
    result = result.filter(p =>
      activeBrands.value.some(b => (p.attributes?.brand || []).includes(b))
    )
  }

  // Material
  if (activeMaterials.value.length > 0) {
    result = result.filter(p =>
      activeMaterials.value.some(m => (p.attributes?.material || []).includes(m))
    )
  }

  // Color
  if (activeColors.value.length > 0) {
    result = result.filter(p =>
      activeColors.value.some(c => (p.attributes?.color || []).includes(c))
    )
  }

  // Tags
  if (activeTags.value.length > 0) {
    result = result.filter(p =>
      activeTags.value.some(t => (p.tags || []).includes(t))
    )
  }

  // Rating
  if (activeRating.value !== null) {
    result = result.filter(p => (p.rating || 0) >= activeRating.value)
  }

  return result
})

// Active filter chips for the summary strip
const activeChips = computed(() => {
  const chips = []
  activeCategories.value.forEach(v => chips.push({ key: `cat-${v}`, label: v, type: 'category', value: v }))
  activeBrands.value.forEach(v => chips.push({ key: `brand-${v}`, label: v, type: 'brand', value: v }))
  activeMaterials.value.forEach(v => chips.push({ key: `mat-${v}`, label: v, type: 'material', value: v }))
  activeColors.value.forEach(v => chips.push({ key: `col-${v}`, label: v, type: 'color', value: v }))
  activeTags.value.forEach(v => chips.push({ key: `tag-${v}`, label: `#${v}`, type: 'tag', value: v }))
  if (activeRating.value !== null) chips.push({ key: 'rating', label: `${activeRating.value}+ stars`, type: 'rating', value: activeRating.value })
  const isPriceFiltered = priceFilter.value[0] > priceMin.value || priceFilter.value[1] < priceMax.value
  if (isPriceFiltered) chips.push({ key: 'price', label: `$${priceFilter.value[0]} – $${priceFilter.value[1]}`, type: 'price' })
  return chips
})

const activeFilterCount = computed(() => activeChips.value.length)

// Price range track fill style
const priceRangeStyle = computed(() => {
  const range = priceMax.value - priceMin.value
  if (range === 0) return { left: '0%', width: '100%' }
  const left = ((priceFilter.value[0] - priceMin.value) / range) * 100
  const right = ((priceMax.value - priceFilter.value[1]) / range) * 100
  return { left: `${left}%`, right: `${right}%`, width: 'auto' }
})

// ─── Methods ─────────────────────────────────────────────────────────────────
function toggleGroup(key) {
  openGroups[key] = !openGroups[key]
}

function onPriceMinInput(e) {
  const val = parseInt(e.target.value)
  if (val < priceFilter.value[1]) priceFilter.value = [val, priceFilter.value[1]]
}

function onPriceMaxInput(e) {
  const val = parseInt(e.target.value)
  if (val > priceFilter.value[0]) priceFilter.value = [priceFilter.value[0], val]
}

function removeChip(chip) {
  if (chip.type === 'category') activeCategories.value = activeCategories.value.filter(v => v !== chip.value)
  else if (chip.type === 'brand') activeBrands.value = activeBrands.value.filter(v => v !== chip.value)
  else if (chip.type === 'material') activeMaterials.value = activeMaterials.value.filter(v => v !== chip.value)
  else if (chip.type === 'color') activeColors.value = activeColors.value.filter(v => v !== chip.value)
  else if (chip.type === 'tag') activeTags.value = activeTags.value.filter(v => v !== chip.value)
  else if (chip.type === 'rating') activeRating.value = null
  else if (chip.type === 'price') priceFilter.value = [priceMin.value, priceMax.value]
}

function clearAllFilters() {
  activeCategories.value = []
  activeBrands.value = []
  activeMaterials.value = []
  activeColors.value = []
  activeTags.value = []
  activeRating.value = null
  priceFilter.value = [priceMin.value, priceMax.value]
}

function colorSwatch(colorName) {
  const map = {
    red: '#ef4444', blue: '#3b82f6', green: '#22c55e', yellow: '#eab308',
    orange: '#f97316', purple: '#a855f7', pink: '#ec4899', black: '#111827',
    white: '#f9fafb', gray: '#9ca3af', grey: '#9ca3af', brown: '#92400e',
    natural: '#d4a574', beige: '#f5f0e8', tan: '#d2b48c', teak: '#8B6914',
    aluminum: '#9ca3af', iron: '#374151', bamboo: '#84cc16',
  }
  return map[colorName.toLowerCase()] || '#9ca3af'
}

// ─── Data fetching ─────────────────────────────────────────────────────────────
watch(
  () => [
    props.settings?.source,
    props.settings?.categoryId,
    props.settings?.productIds,
    props.settings?.pinnedProductIds,
    props.settings?.limit,
    props.settings?.enableFilters,
  ],
  () => { if (wpData.isWooActive) fetchProducts() },
  { deep: true }
)

onMounted(async () => {
  if (wpData.isWooActive) await fetchProducts()
})

async function fetchProducts() {
  loading.value = true
  const formData = new FormData()
  formData.append('action', 'dsf_get_products')
  formData.append('nonce', wpData.nonce)

  const productIds = props.settings?.source === 'manual'
    ? props.settings?.productIds
    : props.settings?.pinnedProductIds
  if (productIds?.length) formData.append('product_ids', JSON.stringify(productIds))
  if (props.settings?.categoryId) formData.append('category_id', props.settings.categoryId)
  formData.append('source', props.settings?.source || 'category')
  // Fetch more when filters are enabled so client-side filtering has enough data
  const limit = filtersEnabled.value ? Math.max(100, props.settings?.limit || 6) : (props.settings?.limit || 6)
  formData.append('limit', limit)

  try {
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData })
    const data = await response.json()
    if (data.success) products.value = data.data.products
  } catch (error) {
    console.error('Error fetching products:', error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* ── Block wrapper ─────────────────────────────────────────────────────────── */
.dsf-product-grid-preview {
  container-type: inline-size;
  position: relative;
}

.dsf-product-grid-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  text-align: center;
  font-size: 1.875rem;
  font-weight: 600;
  margin-bottom: 2rem;
  line-height: 1.2;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* ── Layout ────────────────────────────────────────────────────────────────── */
.dsf-product-grid-preview__layout {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
  max-width: 1200px;
  margin: 0 auto;
}

.dsf-product-grid-preview__layout--sidebar-right {
  flex-direction: row-reverse;
}

.dsf-product-grid-preview__main {
  flex: 1;
  min-width: 0;
}

/* ── Filter Sidebar ────────────────────────────────────────────────────────── */
.dsf-filter-sidebar {
  width: 240px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
}

/* Active filters strip */
.dsf-filter-sidebar__active {
  padding: 12px 16px;
  border-bottom: 1px solid var(--dsf-gray-100);
  background: #f9fafb;
}

.dsf-filter-sidebar__active-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.dsf-filter-sidebar__active-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--dsf-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dsf-filter-sidebar__clear-all {
  background: none;
  border: none;
  color: var(--dsf-primary-600);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}

.dsf-filter-sidebar__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.dsf-filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--dsf-primary-50, #e8f5e9);
  color: var(--dsf-primary-700, #2e7d32);
  border-radius: 20px;
  padding: 3px 10px 3px 10px;
  font-size: 0.75rem;
  font-weight: 500;
}

.dsf-filter-chip__remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--dsf-primary-600, #388e3c);
  font-size: 1rem;
  line-height: 1;
  padding: 0;
  margin-left: 2px;
}

/* Filter group */
.dsf-filter-group {
  border-bottom: 1px solid var(--dsf-gray-100);
}

.dsf-filter-group:last-child {
  border-bottom: none;
}

.dsf-filter-group__header {
  width: 100%;
  background: none;
  border: none;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  text-align: left;
}

.dsf-filter-group__header:hover {
  background: #f9fafb;
}

.dsf-filter-group__title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--dsf-gray-800);
}

.dsf-filter-group__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.dsf-filter-group__chevron--open {
  transform: rotate(180deg);
}

.dsf-filter-group__body {
  padding: 4px 16px 14px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dsf-filter-group__body--colors {
  gap: 6px;
}

.dsf-filter-group__body--tags {
  flex-direction: row;
  flex-wrap: wrap;
  gap: 6px;
  padding-bottom: 14px;
}

.dsf-filter-option--tag {
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid var(--dsf-gray-200);
  background: #f9fafb;
  gap: 4px;
  font-size: 0.8125rem;
  cursor: pointer;
}

.dsf-filter-option--tag:hover {
  border-color: var(--dsf-primary-400);
  background: var(--dsf-primary-50, #e8f5e9);
}

.dsf-filter-option--tag input[type="checkbox"] {
  display: none;
}

.dsf-filter-option--tag .dsf-filter-option__label {
  flex: unset;
}

.dsf-filter-option--tag .dsf-filter-option__count {
  font-size: 0.7rem;
}

/* Filter options */
.dsf-filter-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 0;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--dsf-gray-700);
}

.dsf-filter-option:hover .dsf-filter-option__label {
  color: var(--dsf-gray-900);
}

.dsf-filter-option__check {
  accent-color: var(--dsf-primary-600);
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  cursor: pointer;
}

.dsf-filter-option__label {
  flex: 1;
  line-height: 1.3;
}

.dsf-filter-option__count {
  color: var(--dsf-gray-400);
  font-size: 0.75rem;
}

/* Color swatch */
.dsf-filter-option__swatch {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.12);
  flex-shrink: 0;
}

/* Rating stars */
.dsf-filter-option--rating {
  align-items: center;
}

.dsf-filter-rating-stars {
  display: flex;
  gap: 1px;
}

.dsf-star--filled {
  color: #f59e0b;
  fill: #f59e0b;
}

.dsf-star--empty {
  color: #d1d5db;
  fill: #d1d5db;
}

/* Price range slider */
.dsf-price-range {
  padding: 4px 0;
}

.dsf-price-range__labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--dsf-gray-700);
  margin-bottom: 12px;
  font-weight: 500;
}

.dsf-price-range__track {
  position: relative;
  height: 4px;
  background: var(--dsf-gray-200);
  border-radius: 4px;
  margin: 0 0 8px;
}

.dsf-price-range__fill {
  position: absolute;
  height: 100%;
  background: var(--dsf-primary-600);
  border-radius: 4px;
}

.dsf-price-range__input {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 100%;
  left: 0;
  appearance: none;
  background: transparent;
  pointer-events: none;
  height: 20px;
  margin: 0;
}

.dsf-price-range__input::-webkit-slider-thumb {
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid var(--dsf-primary-600);
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  pointer-events: all;
  cursor: pointer;
}

.dsf-price-range__input::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid var(--dsf-primary-600);
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  pointer-events: all;
  cursor: pointer;
}

/* ── Results bar ───────────────────────────────────────────────────────────── */
.dsf-product-grid-preview__results-bar {
  display: flex;
  align-items: center;
  margin-bottom: 1rem;
}

.dsf-product-grid-preview__results-count {
  font-size: 0.875rem;
  color: var(--dsf-gray-500);
}

/* ── Product grid ──────────────────────────────────────────────────────────── */
.dsf-product-grid-preview__items {
  display: grid;
  grid-template-columns: repeat(var(--columns, 3), 1fr);
  gap: 1.5rem;
}

/* ── Product card ──────────────────────────────────────────────────────────── */
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
  mix-blend-mode: multiply;
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
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 600;
  color: var(--dsf-gray-900);
  margin: 0;
  font-size: 1rem;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.dsf-product-card-preview__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.dsf-product-card-preview__price {
  font-family: var(--dsf-theme-body-font, inherit);
  color: var(--dsf-primary-600);
  font-weight: 600;
  font-size: 1.125rem;
  line-height: 1.2;
}

.dsf-product-card-preview__rating {
  display: flex;
  gap: 1px;
}

.dsf-product-card-preview__btn {
  font-family: var(--dsf-theme-body-font, inherit);
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
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dsf-product-card-preview__btn:hover {
  background: var(--dsf-primary-700);
}

/* ── No results ────────────────────────────────────────────────────────────── */
.dsf-product-grid-preview__no-results {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  gap: 12px;
  color: var(--dsf-gray-400);
  text-align: center;
}

.dsf-product-grid-preview__no-results p {
  font-size: 0.875rem;
  color: var(--dsf-gray-500);
  margin: 0;
}

.dsf-product-grid-preview__no-results-btn {
  background: none;
  border: 1px solid var(--dsf-gray-300);
  border-radius: 6px;
  padding: 6px 16px;
  font-size: 0.875rem;
  color: var(--dsf-gray-600);
  cursor: pointer;
}

.dsf-product-grid-preview__no-results-btn:hover {
  background: var(--dsf-gray-50);
}

/* ── Skeletons ─────────────────────────────────────────────────────────────── */
.dsf-skeleton-card {
  background: white;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  border: 1px solid var(--dsf-gray-200);
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

.dsf-shimmer { position: relative; overflow: hidden; }

.dsf-shimmer::after {
  content: '';
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
  transform: translateX(-100%);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer { 100% { transform: translateX(100%); } }

/* ── Notice ────────────────────────────────────────────────────────────────── */
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

/* ── Responsive ────────────────────────────────────────────────────────────── */
@container (max-width: 900px) {
  .dsf-product-grid-preview__layout--has-sidebar {
    flex-direction: column;
  }

  .dsf-filter-sidebar {
    width: 100%;
  }
}

@container (max-width: 1024px) {
  .dsf-product-grid-preview__items {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}

@container (max-width: 600px) {
  .dsf-product-grid-preview__items {
    grid-template-columns: 1fr !important;
  }
}
</style>
