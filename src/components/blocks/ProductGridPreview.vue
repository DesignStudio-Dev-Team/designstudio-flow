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
            <button type="button" class="dsf-filter-sidebar__clear-all" @click="clearAllFilters">Clear all</button>
          </div>
          <div class="dsf-filter-sidebar__chips">
            <span
              v-for="chip in activeChips"
              :key="chip.key"
              class="dsf-filter-chip"
            >
              {{ chip.label }}
              <button type="button" class="dsf-filter-chip__remove" @click="removeChip(chip)">×</button>
            </span>
          </div>
        </div>

        <!-- Price Range Filter -->
        <div v-if="filterVisibility.price" class="dsf-filter-group">
          <button type="button" class="dsf-filter-group__header" @click="toggleGroup('price')">
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
        <div v-if="filterVisibility.category && allCategories.length > 0" class="dsf-filter-group">
          <button type="button" class="dsf-filter-group__header" @click="toggleGroup('category')">
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

        <!-- Dynamic Attribute Filters -->
        <template v-for="attrKey in enabledAttributeKeys" :key="attrKey">
          <div v-if="getAttributeOptions(attrKey).length > 0" class="dsf-filter-group">
            <button type="button" class="dsf-filter-group__header" @click="toggleGroup(attrKey)">
              <span class="dsf-filter-group__title">{{ humanizeKey(attrKey) }}</span>
              <ChevronDown :size="16" :class="['dsf-filter-group__chevron', openGroups[attrKey] ? 'dsf-filter-group__chevron--open' : '']" />
            </button>
            <div v-if="openGroups[attrKey]" class="dsf-filter-group__body">
              <label
                v-for="opt in getAttributeOptions(attrKey)"
                :key="opt.value"
                class="dsf-filter-option"
              >
                <input
                  type="checkbox"
                  class="dsf-filter-option__check"
                  :value="opt.value"
                  :checked="isAttrValueActive(attrKey, opt.value)"
                  @change="toggleAttrValue(attrKey, opt.value, $event.target.checked)"
                />
                <span class="dsf-filter-option__label">{{ opt.label }}</span>
                <span class="dsf-filter-option__count">({{ opt.count }})</span>
              </label>
            </div>
          </div>
        </template>

        <!-- Tags Filter -->
        <div v-if="settings.filterShowTags && allTags.length > 0" class="dsf-filter-group">
          <button type="button" class="dsf-filter-group__header" @click="toggleGroup('tags')">
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
          <button type="button" class="dsf-filter-group__header" @click="toggleGroup('rating')">
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
        <!-- Search + Results -->
        <div
          v-if="!loading && (filtersEnabled || searchEnabled)"
          class="dsf-product-grid-preview__toolbar"
        >
          <div
            v-if="searchEnabled"
            class="dsf-product-grid-preview__search"
            role="search"
          >
            <Search :size="16" class="dsf-product-grid-preview__search-icon" />
            <input
              v-model="searchQuery"
              type="search"
              class="dsf-product-grid-preview__search-input"
              :placeholder="settings.searchPlaceholder || 'Search products'"
              aria-label="Search products in this grid"
              autocomplete="off"
              autocapitalize="off"
              spellcheck="false"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="dsf-product-grid-preview__search-clear"
              @click="clearSearch"
            >
              Clear
            </button>
          </div>

          <div class="dsf-product-grid-preview__results-bar" aria-live="polite">
            <span class="dsf-product-grid-preview__results-count">
              {{ filteredProducts.length }} {{ filteredProducts.length === 1 ? 'product' : 'products' }}
              <template v-if="searchEnabled && normalizedSearchQuery">
                for "{{ searchQuery.trim() }}"
              </template>
            </span>
          </div>
        </div>

        <!-- Loading Skeleton -->
        <template v-if="loading">
          <div
            class="dsf-product-grid-preview__items"
            :style="gridStyle"
          >
            <div
              v-for="n in (settings.perPage || 12)"
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
              v-for="product in pagedProducts"
              :key="product.id"
              class="dsf-product-card-preview"
              :class="`dsf-product-card-preview--${cardStyle}`"
            >
              <!-- ── Classic ── -->
              <template v-if="cardStyle === 'classic'">
                <a :href="product.permalink || '#'" class="dsf-product-card-preview__image-link">
                  <div class="dsf-product-card-preview__image">
                    <img v-if="product.image" :src="product.image" :alt="product.name" />
                    <ShoppingBag v-else :size="32" />
                  </div>
                </a>
                <div class="dsf-product-card-preview__body">
                  <div v-if="product.attributes?.brand?.[0]" class="dsf-product-card-preview__brand">{{ product.attributes.brand[0] }}</div>
                  <a :href="product.permalink || '#'" class="dsf-product-card-preview__name-link">
                    <h4 class="dsf-product-card-preview__name">{{ product.name }}</h4>
                  </a>
                  <div class="dsf-product-card-preview__sub">
                    <span v-if="product.categories?.[0]">{{ product.categories[0] }}</span>
                    <template v-for="(vals, key) in product.attributes" :key="key">
                      <template v-if="key !== 'brand' && vals?.[0]">
                        <span class="dsf-product-card-preview__sub-sep">·</span>
                        <span>{{ vals[0] }}</span>
                      </template>
                    </template>
                  </div>
                  <div class="dsf-product-card-preview__meta">
                    <div v-if="settings.showPrice !== false" class="dsf-product-card-preview__price">{{ product.price || '$99.00' }}</div>
                    <div v-if="product.rating > 0" class="dsf-product-card-preview__rating">
                      <Star v-for="s in 5" :key="s" :size="12" :class="s <= Math.round(product.rating) ? 'dsf-star--filled' : 'dsf-star--empty'" />
                    </div>
                  </div>
                  <button
                    v-if="settings.showButton !== false && product.price_num"
                    type="button"
                    class="dsf-product-card-preview__btn"
                    :class="cartButtonClass(product)"
                    :disabled="cartState[product.id] === 'loading'"
                    @click.stop.prevent="handleAddToCart(product)"
                  >{{ cartButtonLabel(product) }}</button>
                </div>
              </template>

              <!-- ── Minimal ── -->
              <template v-else-if="cardStyle === 'minimal'">
                <div class="dsf-product-card-preview__image">
                  <img v-if="product.image" :src="product.image" :alt="product.name" />
                  <ShoppingBag v-else :size="32" />
                  <div class="dsf-product-card-preview__image-actions">
                    <a :href="product.permalink || '#'" class="dsf-product-card-preview__icon-btn" :title="'View ' + product.name">
                      <Eye :size="16" />
                    </a>
                    <button
                      v-if="settings.showButton !== false && product.price_num"
                      type="button"
                      class="dsf-product-card-preview__icon-btn dsf-product-card-preview__icon-btn--cart"
                      :class="{ 'dsf-product-card-preview__icon-btn--added': cartState[product.id] === 'added' }"
                      :disabled="cartState[product.id] === 'loading'"
                      :title="settings.buttonText || 'Add to Cart'"
                      @click.stop.prevent="handleAddToCart(product)"
                    >
                      <ShoppingCart :size="16" />
                    </button>
                  </div>
                </div>
                <div class="dsf-product-card-preview__body">
                  <div v-if="product.attributes?.brand?.[0]" class="dsf-product-card-preview__brand">{{ product.attributes.brand[0] }}</div>
                  <a :href="product.permalink || '#'" class="dsf-product-card-preview__name-link">
                    <h4 class="dsf-product-card-preview__name">{{ product.name }}</h4>
                  </a>
                  <div class="dsf-product-card-preview__sub">
                    <span v-if="product.categories?.[0]">{{ product.categories[0] }}</span>
                    <template v-for="(vals, key) in product.attributes" :key="key">
                      <template v-if="key !== 'brand' && vals?.[0]">
                        <span class="dsf-product-card-preview__sub-sep">·</span>
                        <span>{{ vals[0] }}</span>
                      </template>
                    </template>
                  </div>
                  <div v-if="settings.showPrice !== false" class="dsf-product-card-preview__price">{{ product.price || '$99.00' }}</div>
                </div>
              </template>

              <!-- ── Modern ── -->
              <template v-else-if="cardStyle === 'modern'">
                <div class="dsf-product-card-preview__image">
                  <a
                    :href="product.permalink || '#'"
                    class="dsf-product-card-preview__image-link dsf-product-card-preview__image-link--modern"
                    :aria-label="`View ${product.name}`"
                  >
                    <img v-if="product.image" :src="product.image" :alt="product.name" />
                    <ShoppingBag v-else :size="40" />
                  </a>
                  <div class="dsf-product-card-preview__overlay">
                    <div v-if="product.attributes?.brand?.[0]" class="dsf-product-card-preview__brand">{{ product.attributes.brand[0] }}</div>
                    <a :href="product.permalink || '#'" class="dsf-product-card-preview__name-link">
                      <h4 class="dsf-product-card-preview__name">{{ product.name }}</h4>
                    </a>
                    <div class="dsf-product-card-preview__sub">
                      <span v-if="product.categories?.[0]">{{ product.categories[0] }}</span>
                      <template v-for="(vals, key) in product.attributes" :key="key">
                        <template v-if="key !== 'brand' && vals?.[0]">
                          <span class="dsf-product-card-preview__sub-sep">·</span>
                          <span>{{ vals[0] }}</span>
                        </template>
                      </template>
                    </div>
                    <div class="dsf-product-card-preview__meta">
                      <div v-if="settings.showPrice !== false" class="dsf-product-card-preview__price">{{ product.price || '$99.00' }}</div>
                      <div v-if="product.rating > 0" class="dsf-product-card-preview__rating">
                        <Star v-for="s in 5" :key="s" :size="11" :class="s <= Math.round(product.rating) ? 'dsf-star--filled' : 'dsf-star--empty'" />
                      </div>
                    </div>
                    <button
                      v-if="settings.showButton !== false && product.price_num"
                      type="button"
                      class="dsf-product-card-preview__btn"
                      :class="cartButtonClass(product)"
                      :disabled="cartState[product.id] === 'loading'"
                      @click.stop.prevent="handleAddToCart(product)"
                    >{{ cartButtonLabel(product) }}</button>
                  </div>
                </div>
              </template>
            </div>
          </div>
          <!-- Pagination -->
          <div v-if="totalPages > 1" class="dsf-product-grid-preview__pagination">
            <button
              type="button"
              class="dsf-pagination__btn"
              :disabled="currentPage === 1"
              @click="goToPage(currentPage - 1)"
            >‹</button>
            <button
              v-for="page in totalPages"
              :key="page"
              type="button"
              class="dsf-pagination__btn"
              :class="{ 'dsf-pagination__btn--active': page === currentPage }"
              @click="goToPage(page)"
            >{{ page }}</button>
            <button
              type="button"
              class="dsf-pagination__btn"
              :disabled="currentPage === totalPages"
              @click="goToPage(currentPage + 1)"
            >›</button>
          </div>
        </template>

        <!-- No Results -->
        <div v-else class="dsf-product-grid-preview__no-results">
          <ShoppingBag :size="40" class="dsf-product-grid-preview__no-results-icon" />
          <p>{{ noResultsMessage }}</p>
          <button
            v-if="hasActiveControls"
            type="button"
            class="dsf-product-grid-preview__no-results-btn"
            @click="resetVisibleControls"
          >
            {{ resetActionLabel }}
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, reactive, onMounted, watch } from 'vue'
import { ShoppingBag, ChevronDown, Star, Search, Eye, ShoppingCart } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { navigateToUrl } from '../../utils/browserNavigation'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  blockId: {
    type: [String, Number],
    default: '',
  },
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

function getWpData() {
  const editorData = window.dsfEditorData
  if (editorData && Object.keys(editorData).length > 0) {
    return editorData
  }
  return window.dsfFrontendData || editorData || {}
}

const products = ref([])
const loading = ref(Boolean(getWpData().isWooActive))
const hasFetchedProducts = ref(!Boolean(getWpData().isWooActive))
const urlStateHydrated = ref(false)
const syncingUrlState = ref(false)

// ─── Pagination state ─────────────────────────────────────────────────────────
const currentPage = ref(1)

// ─── Cart state ───────────────────────────────────────────────────────────────
// cartState[productId] = 'idle' | 'loading' | 'added' | 'error'
const cartState = reactive({})

// ─── Filter state ─────────────────────────────────────────────────────────────
const priceMin = ref(89)
const priceMax = ref(899)
const priceFilter = ref([89, 899])
const activeCategories = ref([])
const activeAttributeValues = reactive({}) // { [attrKey]: string[] }
const activeTags = ref([])
const activeRating = ref(null)
const searchQuery = ref('')
const openGroups = reactive({ price: true, category: true, tags: false, rating: false })

const demoProducts = Array.from({ length: 8 }, (_, i) => ({
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

// ─── Computed ─────────────────────────────────────────────────────────────────
const isWooActive = computed(() => getWpData().isWooActive || false)
const filtersEnabled = computed(() => props.settings?.enableFilters === true)
const cardStyle = computed(() => props.settings?.cardStyle || 'classic')
const searchEnabled = computed(() => props.settings?.enableSearch === true)
const filterPosition = computed(() => props.settings?.filterPosition || 'left')
const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase())
const dataReadyForUrlState = computed(() => !isWooActive.value || hasFetchedProducts.value)
const selectedSourceCategoryId = computed(() => {
  const value = Number.parseInt(props.settings?.categoryId, 10)
  return Number.isFinite(value) && value > 0 ? value : 0
})
const availableCategories = computed(() => Array.isArray(getWpData().categories) ? getWpData().categories : [])
const selectedSourceCategory = computed(() =>
  availableCategories.value.find((category) => Number(category?.id) === selectedSourceCategoryId.value) || null
)
const filterVisibility = computed(() => ({
  price: filtersEnabled.value && props.settings?.filterShowPrice !== false,
  category: filtersEnabled.value && props.settings?.filterShowCategory !== false,
  tags: filtersEnabled.value && props.settings?.filterShowTags === true,
  rating: filtersEnabled.value && props.settings?.filterShowRating === true,
}))
const enabledAttributeKeys = computed(() =>
  filtersEnabled.value ? (Array.isArray(props.settings?.filterAttributes) ? props.settings.filterAttributes : []) : []
)
const blockUrlNamespace = computed(() => {
  const raw = String(props.blockId || 'product-grid').trim().toLowerCase()
  const sanitized = raw.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')
  return sanitized || 'product_grid'
})
const filterParamKeys = computed(() => {
  const base = {
    category: `dsf_pg_${blockUrlNamespace.value}_cat`,
    tags: `dsf_pg_${blockUrlNamespace.value}_tags`,
    rating: `dsf_pg_${blockUrlNamespace.value}_rating`,
    minPrice: `dsf_pg_${blockUrlNamespace.value}_min_price`,
    maxPrice: `dsf_pg_${blockUrlNamespace.value}_max_price`,
  }
  enabledAttributeKeys.value.forEach((key) => {
    base[key] = `dsf_pg_${blockUrlNamespace.value}_attr_${key}`
  })
  return base
})
const shouldSyncUrlFilters = computed(() =>
  !props.isEditor &&
  filtersEnabled.value &&
  typeof window !== 'undefined' &&
  typeof window.history?.replaceState === 'function'
)
const canPersistSearch = computed(() =>
  !props.isEditor &&
  searchEnabled.value &&
  typeof window !== 'undefined' &&
  typeof window.sessionStorage !== 'undefined'
)
const searchStorageKey = computed(() => {
  const pageId = getWpData().postId || (typeof window !== 'undefined' ? window.location.pathname : 'page')
  return `dsf_pg_search_${pageId}_${blockUrlNamespace.value}`
})

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

const sourceProducts = computed(() => {
  const items = products.value.length > 0 ? products.value : (isWooActive.value ? [] : demoProducts)

  if (props.settings?.source !== 'category' || selectedSourceCategoryId.value <= 0) {
    return items
  }

  return items.filter((product) => {
    if (Array.isArray(product.category_ids) && product.category_ids.length > 0) {
      return product.category_ids.includes(selectedSourceCategoryId.value)
    }

    if (selectedSourceCategory.value?.name) {
      return (product.categories || []).includes(selectedSourceCategory.value.name)
    }

    return true
  })
})

function buildOptions(items, accessor) {
  const counts = {}
  items.forEach((product) => {
    const values = accessor(product)
    if (!Array.isArray(values)) return
    values.forEach((value) => {
      counts[value] = (counts[value] || 0) + 1
    })
  })
  return Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .map(([value, count]) => ({ value, label: value, count }))
}

function buildDistinctValues(items, accessor) {
  const values = new Map()
  items.forEach((item) => {
    const nextValues = accessor(item)
    if (!Array.isArray(nextValues)) return
    nextValues.forEach((value) => {
      if (typeof value === 'string' && value && !values.has(value)) {
        values.set(value, value)
      }
    })
  })
  return Array.from(values.values())
}

function normalizeFilterToken(value) {
  return String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function serializeFilterValues(values) {
  return [...new Set(values.map(normalizeFilterToken).filter(Boolean))].sort().join(',')
}

function parseFilterTokens(value) {
  if (!value) return []
  return value
    .split(',')
    .map((token) => normalizeFilterToken(token))
    .filter(Boolean)
}

function matchFilterTokens(tokens, values) {
  const lookup = new Map()
  values.forEach((value) => {
    const token = normalizeFilterToken(value)
    if (token && !lookup.has(token)) {
      lookup.set(token, value)
    }
  })
  return tokens
    .map((token) => lookup.get(token))
    .filter(Boolean)
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value))
}

function syncPriceBounds(items, { resetRange = true } = {}) {
  const prices = items
    .map((product) => product.price_num || 0)
    .filter((price) => Number.isFinite(price) && price > 0)

  if (prices.length === 0) return

  const min = Math.floor(Math.min(...prices))
  const max = Math.ceil(Math.max(...prices))
  priceMin.value = min
  priceMax.value = max

  if (resetRange) {
    priceFilter.value = [min, max]
    return
  }

  const nextMin = clamp(priceFilter.value[0], min, max)
  const nextMax = clamp(priceFilter.value[1], min, max)
  priceFilter.value = nextMin <= nextMax ? [nextMin, nextMax] : [min, max]
}

function applySelectedFilters(items, exclude = '') {
  let result = items

  if (filterVisibility.value.price && exclude !== 'price') {
    result = result.filter((product) => {
      const numPrice = parseFloat(product.price_num)
      // Products with no price (price_num = 0 / NaN) always pass the price filter
      if (!numPrice) return true
      // Upper bound is open: slider value $99 includes products up to $99.99
      return numPrice >= priceFilter.value[0] && numPrice < priceFilter.value[1] + 1
    })
  }

  if (filterVisibility.value.category && exclude !== 'category' && activeCategories.value.length > 0) {
    result = result.filter((product) => activeCategories.value.some((category) => (product.categories || []).includes(category)))
  }

  enabledAttributeKeys.value.forEach((attrKey) => {
    const active = activeAttributeValues[attrKey] || []
    if (exclude !== `attr:${attrKey}` && active.length > 0) {
      result = result.filter((product) => active.some((v) => (product.attributes?.[attrKey] || []).includes(v)))
    }
  })

  if (filterVisibility.value.tags && exclude !== 'tags' && activeTags.value.length > 0) {
    result = result.filter((product) => activeTags.value.some((tag) => (product.tags || []).includes(tag)))
  }

  if (filterVisibility.value.rating && exclude !== 'rating' && activeRating.value !== null) {
    result = result.filter((product) => (product.rating || 0) >= activeRating.value)
  }

  return result
}

function filterExcluding(exclude) {
  return applySelectedFilters(sourceProducts.value, exclude)
}

function flattenProductAttributes(product) {
  return Object.values(product.attributes || {}).flat().filter(Boolean)
}

function productMatchesSearch(product, term) {
  if (!term) return true
  const haystack = [
    product.name,
    ...(product.categories || []),
    ...(product.tags || []),
    ...flattenProductAttributes(product),
  ]
    .join(' ')
    .toLowerCase()

  return haystack.includes(term)
}

const allCategoryValues = computed(() => buildDistinctValues(sourceProducts.value, (product) => product.categories || []))
const allTagValues = computed(() => buildDistinctValues(sourceProducts.value, (product) => product.tags || []))

const allCategories = computed(() => buildOptions(filterExcluding('category'), (product) => product.categories || []))
const allTags = computed(() => buildOptions(filterExcluding('tags'), (product) => product.tags || []))

function getAttributeOptions(attrKey) {
  return buildOptions(filterExcluding(`attr:${attrKey}`), (product) => product.attributes?.[attrKey] || [])
}

function isAttrValueActive(attrKey, value) {
  return (activeAttributeValues[attrKey] || []).includes(value)
}

function toggleAttrValue(attrKey, value, checked) {
  if (!activeAttributeValues[attrKey]) {
    activeAttributeValues[attrKey] = []
  }
  if (checked) {
    if (!activeAttributeValues[attrKey].includes(value)) {
      activeAttributeValues[attrKey] = [...activeAttributeValues[attrKey], value]
    }
  } else {
    activeAttributeValues[attrKey] = activeAttributeValues[attrKey].filter((v) => v !== value)
  }
}

function humanizeKey(key) {
  return String(key || '').split('_').filter(Boolean).map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join(' ')
}

const perPage = computed(() => Math.max(1, parseInt(props.settings?.perPage, 10) || 12))

const filteredProducts = computed(() => {
  let result = filtersEnabled.value ? applySelectedFilters(sourceProducts.value) : sourceProducts.value

  if (searchEnabled.value && normalizedSearchQuery.value) {
    result = result.filter((product) => productMatchesSearch(product, normalizedSearchQuery.value))
  }

  return result
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / perPage.value)))

const pagedProducts = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredProducts.value.slice(start, start + perPage.value)
})

const activeChips = computed(() => {
  const chips = []

  if (filterVisibility.value.category) {
    activeCategories.value.forEach((value) => chips.push({ key: `cat-${value}`, label: value, type: 'category', value }))
  }

  enabledAttributeKeys.value.forEach((attrKey) => {
    const active = activeAttributeValues[attrKey] || []
    active.forEach((value) => chips.push({ key: `attr-${attrKey}-${value}`, label: value, type: 'attr', attrKey, value }))
  })

  if (filterVisibility.value.tags) {
    activeTags.value.forEach((value) => chips.push({ key: `tag-${value}`, label: `#${value}`, type: 'tag', value }))
  }

  if (filterVisibility.value.rating && activeRating.value !== null) {
    chips.push({ key: 'rating', label: `${activeRating.value}+ stars`, type: 'rating', value: activeRating.value })
  }

  if (filterVisibility.value.price && (priceFilter.value[0] > priceMin.value || priceFilter.value[1] < priceMax.value)) {
    chips.push({ key: 'price', label: `$${priceFilter.value[0]} – $${priceFilter.value[1]}`, type: 'price' })
  }

  return chips
})

const activeFilterCount = computed(() => activeChips.value.length)
const hasActiveControls = computed(() => activeFilterCount.value > 0 || (searchEnabled.value && normalizedSearchQuery.value.length > 0))
const noResultsMessage = computed(() => {
  if (searchEnabled.value && normalizedSearchQuery.value && activeFilterCount.value > 0) {
    return 'No products match your search within the current filters.'
  }
  if (searchEnabled.value && normalizedSearchQuery.value) {
    return 'No products match your search.'
  }
  if (activeFilterCount.value > 0) {
    return 'No products match your filters.'
  }
  return 'No products available right now.'
})
const resetActionLabel = computed(() => {
  if (searchEnabled.value && normalizedSearchQuery.value && activeFilterCount.value > 0) {
    return 'Clear search & filters'
  }
  if (searchEnabled.value && normalizedSearchQuery.value) {
    return 'Clear search'
  }
  return 'Clear filters'
})

const priceRangeStyle = computed(() => {
  const range = priceMax.value - priceMin.value
  if (range === 0) return { left: '0%', width: '100%' }
  const left = ((priceFilter.value[0] - priceMin.value) / range) * 100
  const right = ((priceMax.value - priceFilter.value[1]) / range) * 100
  return { left: `${left}%`, right: `${right}%`, width: 'auto' }
})

watch(products, (items) => {
  if (items.length === 0) return
  syncPriceBounds(items, { resetRange: !urlStateHydrated.value })
})

watch(
  () => [shouldSyncUrlFilters.value, dataReadyForUrlState.value],
  ([canSync, isReady]) => {
    if (!canSync) {
      urlStateHydrated.value = false
      return
    }
    if (isReady && !urlStateHydrated.value) {
      hydrateFiltersFromUrl()
    }
  },
  { immediate: true }
)

watch(
  [priceFilter, activeCategories, activeAttributeValues, activeTags, activeRating],
  () => {
    currentPage.value = 1
    if (urlStateHydrated.value && !syncingUrlState.value) {
      navigateWithFilters()
    }
  },
  { deep: true }
)

watch(searchQuery, () => {
  currentPage.value = 1
})

watch(
  searchQuery,
  (value) => {
    if (!canPersistSearch.value) return
    const nextValue = value.trim()
    if (nextValue) {
      window.sessionStorage.setItem(searchStorageKey.value, value)
    } else {
      window.sessionStorage.removeItem(searchStorageKey.value)
    }
  }
)

// ─── Methods ─────────────────────────────────────────────────────────────────
// ─── Add to cart ──────────────────────────────────────────────────────────────
function isVariableProduct(product) {
  return product.product_type === 'variable' || product.product_type === 'variable-subscription'
}

function navigateToProduct(product) {
  navigateToUrl(product?.permalink || '#')
}

async function handleAddToCart(product) {
  // In editor just show demo feedback
  if (props.isEditor) {
    cartState[product.id] = 'added'
    setTimeout(() => { cartState[product.id] = 'idle' }, 1800)
    return
  }

  // Variable products → go to product page to pick variants
  if (isVariableProduct(product)) {
    navigateToProduct(product)
    return
  }

  // Out of stock → go to product page
  if (product.stock_status === 'outofstock') {
    navigateToProduct(product)
    return
  }

  const wpData = getWpData()
  const wcAjaxUrl = wpData.wcAjaxUrl || '/?wc-ajax=add_to_cart'

  cartState[product.id] = 'loading'

  try {
    const formData = new FormData()
    formData.append('product_id', product.id)
    formData.append('quantity', 1)

    const response = await fetch(wcAjaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    const rawResponse = await response.text()
    const data = rawResponse ? JSON.parse(rawResponse) : {}

    if (!response.ok) {
      throw new Error(`Add to cart request failed with status ${response.status}`)
    }

    if (data.error) {
      // WooCommerce returned an error (e.g. needs variation selection)
      navigateToProduct(product)
      return
    }

    cartState[product.id] = 'added'

    // Trigger WooCommerce cart fragment refresh so the cart widget updates
    if (typeof jQuery !== 'undefined') {
      jQuery(document.body).trigger('wc_fragment_refresh')
      jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, null])
    }

    // Reset button label after 2.5s
    setTimeout(() => { cartState[product.id] = 'idle' }, 2500)
  } catch {
    if (product.add_to_cart_url) {
      navigateToUrl(product.add_to_cart_url)
      return
    }

    cartState[product.id] = 'error'
    setTimeout(() => { cartState[product.id] = 'idle' }, 2000)
  }
}

function cartButtonLabel(product) {
  const state = cartState[product.id]
  if (state === 'loading') return '...'
  if (state === 'added') return '✓ Added'
  if (state === 'error') return 'Try again'
  if (isVariableProduct(product)) return 'Select options'
  if (product.stock_status === 'outofstock') return 'Out of stock'
  return props.settings?.buttonText || 'Add to Cart'
}

function cartButtonClass(product) {
  return {
    'dsf-product-card-preview__btn--loading': cartState[product.id] === 'loading',
    'dsf-product-card-preview__btn--added': cartState[product.id] === 'added',
    'dsf-product-card-preview__btn--error': cartState[product.id] === 'error',
  }
}

function goToPage(page) {
  currentPage.value = Math.max(1, Math.min(page, totalPages.value))
  if (!props.isEditor) {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function toggleGroup(key) {
  openGroups[key] = !openGroups[key]
}

function onPriceMinInput(event) {
  const value = parseInt(event.target.value, 10)
  if (value < priceFilter.value[1]) {
    priceFilter.value = [value, priceFilter.value[1]]
  }
}

function onPriceMaxInput(event) {
  const value = parseInt(event.target.value, 10)
  if (value > priceFilter.value[0]) {
    priceFilter.value = [priceFilter.value[0], value]
  }
}

function removeChip(chip) {
  if (chip.type === 'category') activeCategories.value = activeCategories.value.filter((value) => value !== chip.value)
  else if (chip.type === 'attr') activeAttributeValues[chip.attrKey] = (activeAttributeValues[chip.attrKey] || []).filter((v) => v !== chip.value)
  else if (chip.type === 'tag') activeTags.value = activeTags.value.filter((value) => value !== chip.value)
  else if (chip.type === 'rating') activeRating.value = null
  else if (chip.type === 'price') priceFilter.value = [priceMin.value, priceMax.value]
}

function clearAllFilters() {
  activeCategories.value = []
  Object.keys(activeAttributeValues).forEach((key) => { activeAttributeValues[key] = [] })
  activeTags.value = []
  activeRating.value = null
  priceFilter.value = [priceMin.value, priceMax.value]
}

function clearSearch() {
  searchQuery.value = ''
}

function resetVisibleControls() {
  clearAllFilters()
  clearSearch()
}


function hydrateFiltersFromUrl() {
  if (!shouldSyncUrlFilters.value) return

  const params = new URLSearchParams(window.location.search)
  const keys = filterParamKeys.value

  syncingUrlState.value = true

  activeCategories.value = filterVisibility.value.category
    ? matchFilterTokens(parseFilterTokens(params.get(keys.category)), allCategoryValues.value)
    : []

  enabledAttributeKeys.value.forEach((attrKey) => {
    const allValues = buildDistinctValues(sourceProducts.value, (product) => product.attributes?.[attrKey] || [])
    activeAttributeValues[attrKey] = matchFilterTokens(parseFilterTokens(params.get(keys[attrKey])), allValues)
  })

  activeTags.value = filterVisibility.value.tags
    ? matchFilterTokens(parseFilterTokens(params.get(keys.tags)), allTagValues.value)
    : []

  if (filterVisibility.value.rating) {
    const parsedRating = Number.parseInt(params.get(keys.rating), 10)
    activeRating.value = Number.isFinite(parsedRating) ? clamp(parsedRating, 1, 4) : null
  } else {
    activeRating.value = null
  }

  if (filterVisibility.value.price) {
    const minParam = Number.parseInt(params.get(keys.minPrice), 10)
    const maxParam = Number.parseInt(params.get(keys.maxPrice), 10)
    let nextMin = Number.isFinite(minParam) ? clamp(minParam, priceMin.value, priceMax.value) : priceMin.value
    let nextMax = Number.isFinite(maxParam) ? clamp(maxParam, priceMin.value, priceMax.value) : priceMax.value

    if (nextMin > nextMax) {
      [nextMin, nextMax] = [nextMax, nextMin]
    }

    priceFilter.value = [nextMin, nextMax]
  } else {
    priceFilter.value = [priceMin.value, priceMax.value]
  }

  syncingUrlState.value = false
  urlStateHydrated.value = true
  normalizeFilterUrlInPlace()
}

function buildCurrentFilterUrl() {
  const url = new URL(window.location.href)
  const keys = filterParamKeys.value
  const params = url.searchParams

  Object.values(keys).forEach((key) => params.delete(key))

  if (filterVisibility.value.category && activeCategories.value.length > 0) {
    params.set(keys.category, serializeFilterValues(activeCategories.value))
  }

  enabledAttributeKeys.value.forEach((attrKey) => {
    const active = activeAttributeValues[attrKey] || []
    if (active.length > 0) {
      params.set(keys[attrKey], serializeFilterValues(active))
    }
  })

  if (filterVisibility.value.tags && activeTags.value.length > 0) {
    params.set(keys.tags, serializeFilterValues(activeTags.value))
  }

  if (filterVisibility.value.rating && activeRating.value !== null) {
    params.set(keys.rating, String(activeRating.value))
  }

  if (filterVisibility.value.price && (priceFilter.value[0] > priceMin.value || priceFilter.value[1] < priceMax.value)) {
    params.set(keys.minPrice, String(priceFilter.value[0]))
    params.set(keys.maxPrice, String(priceFilter.value[1]))
  }

  return `${url.pathname}${params.toString() ? `?${params.toString()}` : ''}${url.hash}`
}

function normalizeFilterUrlInPlace() {
  if (!shouldSyncUrlFilters.value || syncingUrlState.value) return

  const nextUrl = buildCurrentFilterUrl()
  const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`
  if (nextUrl !== currentUrl) {
    window.history.replaceState(window.history.state, '', nextUrl)
  }
}

function navigateWithFilters() {
  if (!shouldSyncUrlFilters.value || syncingUrlState.value) return

  const nextUrl = buildCurrentFilterUrl()
  const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`

  if (nextUrl !== currentUrl) {
    navigateToUrl(nextUrl)
  }
}

function restorePersistedSearch() {
  if (!canPersistSearch.value) return
  const storedValue = window.sessionStorage.getItem(searchStorageKey.value)
  if (storedValue) {
    searchQuery.value = storedValue
  }
}

// ─── Data fetching ───────────────────────────────────────────────────────────
watch(
  () => [
    props.settings?.source,
    props.settings?.categoryId,
    props.settings?.productIds,
    props.settings?.pinnedProductIds,
    props.settings?.enableFilters,
    props.settings?.enableSearch,
  ],
  () => {
    if (isWooActive.value) {
      fetchProducts()
    }
  },
  { deep: true }
)

onMounted(async () => {
  restorePersistedSearch()
  if (isWooActive.value) {
    await fetchProducts()
  }
})

async function fetchProducts() {
  loading.value = true
  hasFetchedProducts.value = false

  const formData = new FormData()
  const wpData = getWpData()
  formData.append('action', 'dsf_get_products')
  formData.append('nonce', wpData.nonce)

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

  try {
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData })
    const data = await response.json()
    products.value = data.success ? (data.data.products || []) : []
  } catch (error) {
    console.error('Error fetching products:', error)
    products.value = []
  } finally {
    hasFetchedProducts.value = true
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
  font-size: var(--dsf-theme-h3, 1.875rem);
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
  font-size: var(--dsf-theme-text-xs, 0.75rem);
  font-weight: 600;
  color: var(--dsf-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dsf-filter-sidebar__clear-all {
  background: none;
  border: none;
  color: var(--dsf-primary-600);
  font-size: var(--dsf-theme-text-xs, 0.75rem);
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
  font-size: var(--dsf-theme-text-xs, 0.75rem);
  font-weight: 500;
}

.dsf-filter-chip__remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--dsf-primary-600, #388e3c);
  font-size: var(--dsf-theme-text-base, 1rem);
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
  font-size: var(--dsf-theme-text-sm, 0.875rem);
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
  font-size: var(--dsf-theme-text-sm, 0.8125rem);
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
  font-size: var(--dsf-theme-text-xs, 0.7rem);
}

/* Filter options */
.dsf-filter-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 0;
  cursor: pointer;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
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
  font-size: var(--dsf-theme-text-xs, 0.75rem);
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
  font-size: var(--dsf-theme-text-sm, 0.875rem);
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
.dsf-product-grid-preview__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.dsf-product-grid-preview__search {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex: 1 1 320px;
  min-width: min(100%, 260px);
  max-width: 420px;
  padding: 0 0.875rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 999px;
  background: white;
}

.dsf-product-grid-preview__search:focus-within {
  border-color: var(--dsf-primary-500, #2e7d32);
}

.dsf-product-grid-preview__search-icon {
  color: var(--dsf-gray-400);
  flex-shrink: 0;
}

.dsf-product-grid-preview__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--dsf-gray-800);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  padding: 0.875rem 0;
}

.dsf-product-grid-preview__search-input:focus {
  outline: none;
}

.dsf-product-grid-preview__search-input::placeholder {
  color: var(--dsf-gray-400);
}

.dsf-product-grid-preview__search-clear {
  border: none;
  background: none;
  color: var(--dsf-primary-600);
  font-size: var(--dsf-theme-text-xs, 0.75rem);
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  flex-shrink: 0;
}

.dsf-product-grid-preview__results-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-left: auto;
}

.dsf-product-grid-preview__results-count {
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  color: var(--dsf-gray-500);
}

/* ── Product grid ──────────────────────────────────────────────────────────── */
.dsf-product-grid-preview__items {
  display: grid;
  grid-template-columns: repeat(var(--columns, 3), 1fr);
  gap: 1.5rem;
}

/* ── Product card – shared base ────────────────────────────────────────────── */
.dsf-product-card-preview {
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dsf-product-card-preview__image-link {
  display: block;
  flex-shrink: 0;
  text-decoration: none;
}

.dsf-product-card-preview__image {
  aspect-ratio: 1;
  background: var(--dsf-gray-100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
  position: relative;
  overflow: hidden;
}

.dsf-product-card-preview__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.35s ease;
}

.dsf-product-card-preview__name-link {
  text-decoration: none;
}

.dsf-product-card-preview__body {
  display: flex;
  flex-direction: column;
  flex: 1;
}

/* Brand label */
.dsf-product-card-preview__brand {
  font-size: var(--dsf-theme-text-xs, 0.75rem);
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--dsf-gray-500);
  margin-bottom: 0.25rem;
}

/* Title: fixed 2-line reserved height — cards stay equal regardless of title length */
.dsf-product-card-preview__name {
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 600;
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.9375rem);
  line-height: 1.45;
  min-height: calc(0.9375rem * 1.45 * 2);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Category / attribute line */
.dsf-product-card-preview__sub {
  font-size: var(--dsf-theme-text-xs, 0.75rem);
  color: var(--dsf-primary-600);
  margin-top: 0.25rem;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dsf-product-card-preview__sub-sep {
  margin: 0 0.25rem;
  color: var(--dsf-gray-300);
}

.dsf-product-card-preview__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.dsf-product-card-preview__price {
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 700;
  font-size: var(--dsf-theme-text-base, 1.0625rem);
  line-height: 1.2;
}

.dsf-product-card-preview__rating {
  display: flex;
  gap: 1px;
}

.dsf-product-card-preview__btn {
  font-family: var(--dsf-theme-body-font, inherit);
  width: 100%;
  padding: 0.7rem 1rem;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 500;
  cursor: pointer;
  margin-top: auto;
  transition: background-color 0.2s, color 0.2s, border-color 0.2s;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Classic ───────────────────────────────────────────────────────────────── */
.dsf-product-card-preview--classic {
  background: #fff;
  border-radius: var(--dsf-radius-lg);
  border: 1px solid var(--dsf-gray-200);
  overflow: hidden;
}

.dsf-product-card-preview--classic:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 24px -4px rgba(0,0,0,.1), 0 4px 8px -2px rgba(0,0,0,.06);
}

.dsf-product-card-preview--classic .dsf-product-card-preview__image {
  border-bottom: 1px solid var(--dsf-gray-100);
  border-radius: var(--dsf-radius-lg) var(--dsf-radius-lg) 0 0;
}

.dsf-product-card-preview--classic .dsf-product-card-preview__image img {
  mix-blend-mode: multiply;
}

.dsf-product-card-preview--classic:hover .dsf-product-card-preview__image img {
  transform: scale(1.05);
}

.dsf-product-card-preview--classic .dsf-product-card-preview__body {
  padding: 1rem 1.125rem 1.125rem;
  gap: 0;
}

.dsf-product-card-preview--classic .dsf-product-card-preview__name {
  color: var(--dsf-gray-900);
  margin-top: 0.125rem;
}

.dsf-product-card-preview--classic .dsf-product-card-preview__name-link:hover .dsf-product-card-preview__name {
  color: var(--dsf-primary-600);
}

.dsf-product-card-preview--classic .dsf-product-card-preview__meta {
  margin-top: 0.625rem;
}

.dsf-product-card-preview--classic .dsf-product-card-preview__price {
  color: var(--dsf-primary-600);
}

.dsf-product-card-preview--classic .dsf-product-card-preview__btn {
  background: var(--dsf-primary-600);
  color: #fff;
  margin-top: 0.875rem;
}

.dsf-product-card-preview--classic .dsf-product-card-preview__btn:hover {
  background: var(--dsf-primary-700);
}

/* ── Minimal ───────────────────────────────────────────────────────────────── */
.dsf-product-card-preview--minimal {
  background: #fff;
  border-radius: var(--dsf-radius-lg);
  border: 1px solid var(--dsf-gray-200);
  overflow: visible;
}

.dsf-product-card-preview--minimal .dsf-product-card-preview__image {
  border-radius: var(--dsf-radius-lg) var(--dsf-radius-lg) 0 0;
  overflow: hidden;
}

.dsf-product-card-preview--minimal:hover .dsf-product-card-preview__image img {
  transform: scale(1.04);
}

/* Action buttons that appear on image hover */
.dsf-product-card-preview__image-actions {
  position: absolute;
  bottom: 0.75rem;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  opacity: 0;
  transform: translateY(6px);
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.dsf-product-card-preview--minimal:hover .dsf-product-card-preview__image-actions {
  opacity: 1;
  transform: translateY(0);
}

.dsf-product-card-preview__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fff;
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  cursor: pointer;
  color: var(--dsf-gray-700);
  text-decoration: none;
  transition: background 0.15s, color 0.15s, transform 0.15s;
}

.dsf-product-card-preview__icon-btn:hover {
  background: var(--dsf-gray-900);
  color: #fff;
  transform: scale(1.08);
}

.dsf-product-card-preview__icon-btn--cart:hover {
  background: var(--dsf-primary-600);
  color: #fff;
}

.dsf-product-card-preview__icon-btn--added {
  background: var(--dsf-primary-600) !important;
  color: #fff !important;
}

/* ── Cart button states ─────────────────────────────────────────────────────── */
.dsf-product-card-preview__btn--loading {
  opacity: 0.65;
  cursor: wait;
}

.dsf-product-card-preview__btn--added {
  background: #16a34a !important;
  border-color: #16a34a !important;
  color: #fff !important;
}

.dsf-product-card-preview__btn--error {
  background: #dc2626 !important;
  border-color: #dc2626 !important;
  color: #fff !important;
}

.dsf-product-card-preview--minimal .dsf-product-card-preview__body {
  padding: 0.875rem 1rem 1rem;
  gap: 0;
  border-top: 1px solid var(--dsf-gray-100);
}

.dsf-product-card-preview--minimal .dsf-product-card-preview__name {
  color: var(--dsf-gray-900);
  font-weight: 600;
  margin-top: 0.125rem;
}

.dsf-product-card-preview--minimal .dsf-product-card-preview__name-link:hover .dsf-product-card-preview__name {
  color: var(--dsf-primary-600);
}

.dsf-product-card-preview--minimal .dsf-product-card-preview__price {
  color: var(--dsf-gray-900);
  font-size: var(--dsf-theme-text-base, 1rem);
  margin-top: 0.625rem;
  margin-top: auto;
  padding-top: 0.625rem;
}

/* ── Modern ────────────────────────────────────────────────────────────────── */
.dsf-product-card-preview--modern {
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__image-link {
  display: block;
  flex: 1;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__image-link--modern {
  display: flex;
  align-items: center;
  justify-content: center;
  position: absolute;
  inset: 0;
  z-index: 1;
  height: 100%;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__image {
  aspect-ratio: 3 / 4;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  height: 100%;
}

.dsf-product-card-preview--modern:hover .dsf-product-card-preview__image img {
  transform: scale(1.06);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.82) 0%, rgba(0,0,0,0.3) 50%, transparent 100%);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 1.25rem;
  gap: 0.3rem;
  z-index: 2;
  pointer-events: none;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__name-link,
.dsf-product-card-preview--modern .dsf-product-card-preview__btn {
  pointer-events: auto;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__brand {
  color: rgba(255,255,255,0.65);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__name {
  color: #fff;
  font-size: var(--dsf-theme-text-base, 1rem);
  min-height: calc(1rem * 1.45 * 2);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__name-link:hover .dsf-product-card-preview__name {
  text-decoration: underline;
  text-underline-offset: 3px;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__sub {
  color: rgba(255,255,255,0.55);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__sub-sep {
  color: rgba(255,255,255,0.3);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__meta {
  margin-top: 0.25rem;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__price {
  color: #fff;
  font-size: var(--dsf-theme-text-base, 1rem);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__rating .dsf-star--filled,
.dsf-product-card-preview--modern .dsf-product-card-preview__rating .dsf-star--empty {
  color: rgba(255,255,255,0.75);
}

.dsf-product-card-preview--modern .dsf-product-card-preview__btn {
  background: rgba(255,255,255,0.14);
  color: #fff;
  border: 1.5px solid rgba(255,255,255,0.45);
  border-radius: var(--dsf-radius-md);
  backdrop-filter: blur(6px);
  margin-top: 0.625rem;
}

.dsf-product-card-preview--modern .dsf-product-card-preview__btn:hover {
  background: #fff;
  color: var(--dsf-gray-900);
  border-color: transparent;
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
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  color: var(--dsf-gray-500);
  margin: 0;
}

.dsf-product-grid-preview__no-results-btn {
  background: none;
  border: 1px solid var(--dsf-gray-300);
  border-radius: 6px;
  padding: 6px 16px;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
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

  .dsf-product-grid-preview__toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .dsf-product-grid-preview__search {
    max-width: none;
  }

  .dsf-product-grid-preview__results-bar {
    margin-left: 0;
    justify-content: flex-start;
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

.dsf-product-grid-preview__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  margin-top: 2rem;
  flex-wrap: wrap;
}

.dsf-pagination__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 0.5rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 8px;
  background: #fff;
  color: var(--dsf-gray-700);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.dsf-pagination__btn:hover:not(:disabled) {
  background: var(--dsf-gray-50);
  border-color: var(--dsf-gray-300);
}

.dsf-pagination__btn--active {
  background: var(--dsf-gray-900);
  border-color: var(--dsf-gray-900);
  color: #fff;
}

.dsf-pagination__btn:disabled {
  opacity: 0.35;
  cursor: default;
}
</style>
