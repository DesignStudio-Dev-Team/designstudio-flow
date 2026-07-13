<template>
  <section class="dsf-shop-products" :style="blockStyle">
    <div class="dsf-shop-products__inner" :style="innerStyle">
      <div v-if="showToolbar" class="dsf-shop-products__toolbar">
        <p v-if="settings.showCount !== false" class="dsf-shop-products__count">
          {{ resultText }}
        </p>
        <form
          v-if="settings.showSorting !== false"
          class="dsf-shop-products__sort"
          method="get"
          @submit="isEditor && $event.preventDefault()"
        >
          <label class="dsf-shop-products__sort-label" :for="`dsf-orderby-${blockId}`">Sort</label>
          <select
            :id="`dsf-orderby-${blockId}`"
            name="orderby"
            class="dsf-shop-products__sort-select"
            :value="archive.orderby || 'menu_order'"
            @change="onSortChange"
          >
            <option v-for="option in orderbyOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </form>
      </div>

      <ul v-if="cards.length" class="dsf-shop-products__grid" :style="gridStyle">
        <li v-for="card in cards" :key="card.id" class="dsf-shop-products__card">
          <a
            :href="card.permalink || '#'"
            class="dsf-shop-products__media"
            :class="`dsf-shop-products__media--${imageAspect}`"
            tabindex="-1"
            aria-hidden="true"
            @click="isEditor && $event.preventDefault()"
          >
            <span v-if="card.onSale" class="dsf-shop-products__badge">Sale</span>
            <img v-if="card.image" :src="card.image" :alt="''" loading="lazy" decoding="async" />
          </a>

          <div class="dsf-shop-products__body">
            <a
              :href="card.permalink || '#'"
              class="dsf-shop-products__name"
              @click="isEditor && $event.preventDefault()"
            >{{ card.name }}</a>

            <span
              v-if="settings.showRating !== false && Number(card.ratingCount) > 0"
              class="dsf-shop-products__rating"
              :aria-label="`Rated ${Number(card.averageRating || 0).toFixed(1)} out of 5`"
            >
              <span class="dsf-shop-products__stars" aria-hidden="true">
                <span class="dsf-shop-products__stars-fill" :style="{ width: ratingPercent(card) + '%' }">★★★★★</span>
                <span class="dsf-shop-products__stars-base">★★★★★</span>
              </span>
              <span class="dsf-shop-products__rating-count">({{ card.ratingCount }})</span>
            </span>

            <!-- priceHtml sanitized server-side with wp_kses_post (build_product_cards). -->
            <span v-if="settings.showPrice !== false && card.priceHtml" class="dsf-shop-products__price" v-html="card.priceHtml"></span>

            <a
              v-if="settings.showAddToCart !== false"
              :href="cardButtonUrl(card)"
              class="dsf-shop-products__button"
              @click="isEditor && $event.preventDefault()"
            >{{ card.addToCartUrl ? 'Add to cart' : 'View product' }}</a>
          </div>
        </li>
      </ul>

      <div v-else class="dsf-shop-products__empty">
        <template v-if="isEditor">
          <div class="dsf-shop-products__ghosts" :style="gridStyle" aria-hidden="true">
            <span v-for="i in 6" :key="i" class="dsf-shop-products__ghost"></span>
          </div>
          <p class="dsf-shop-products__note">
            The archive's products render here. Pick a preview category in Page Settings → Shop.
          </p>
        </template>
        <p v-else class="dsf-shop-products__note">No products were found matching your selection.</p>
      </div>

      <nav
        v-if="settings.showPagination !== false && pagination.length"
        class="dsf-shop-products__pagination"
        aria-label="Products pagination"
      >
        <template v-for="(link, i) in pagination" :key="i">
          <span v-if="link.current" class="dsf-shop-products__page is-current" aria-current="page">{{ link.label }}</span>
          <a
            v-else
            :href="link.url || '#'"
            class="dsf-shop-products__page"
            @click="isEditor && $event.preventDefault()"
          >{{ link.label }}</a>
        </template>
      </nav>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useShopContext } from '../../utils/useShopContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { archive } = useShopContext()

const ASPECTS = { square: '1 / 1', portrait: '3 / 4', landscape: '4 / 3' }
const imageAspect = computed(() =>
  Object.prototype.hasOwnProperty.call(ASPECTS, props.settings?.imageAspect) ? props.settings.imageAspect : 'square'
)

const cards = computed(() => {
  const raw = Array.isArray(archive.value?.products) ? archive.value.products : []
  return raw.filter((c) => c && typeof c === 'object')
})

const pagination = computed(() => {
  const raw = Array.isArray(archive.value?.pagination) ? archive.value.pagination : []
  return raw.filter((l) => l && typeof l === 'object')
})

const orderbyOptions = computed(() => {
  const raw = Array.isArray(archive.value?.orderbyOptions) ? archive.value.orderbyOptions : []
  return raw.filter((o) => o && typeof o === 'object' && typeof o.value === 'string')
})

const showToolbar = computed(
  () => props.settings?.showCount !== false || props.settings?.showSorting !== false
)

const resultText = computed(() => {
  const total = Number(archive.value?.total) || 0
  if (!total) return 'No products'
  const perPage = Math.max(1, Number(archive.value?.perPage) || total)
  const page = Math.max(1, Number(archive.value?.currentPage) || 1)
  const first = (page - 1) * perPage + 1
  const last = Math.min(total, page * perPage)
  return total === 1 ? 'Showing the single result' : `Showing ${first}–${last} of ${total} products`
})

function ratingPercent(card) {
  const avg = Number(card?.averageRating || 0)
  return Math.max(0, Math.min(100, (avg / 5) * 100))
}

function cardButtonUrl(card) {
  return card.addToCartUrl || card.permalink || '#'
}

function onSortChange(event) {
  // The main query handles ?orderby= natively (WC_Query); submit in place.
  if (props.isEditor) return
  event.target.form?.submit()
}

const gridStyle = computed(() => {
  const desktopCols = Math.max(2, Math.min(5, Number(props.settings?.columns) || 4))
  const cols = props.previewMode === 'mobile' ? 2 : props.previewMode === 'tablet' ? Math.min(3, desktopCols) : desktopCols
  return { gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 24
  const style = {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-shop-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
  if (props.settings?.buttonColor) style['--dsf-shop-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-shop-btn-color'] = props.settings.buttonTextColor
  return style
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1200
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-shop-products {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-shop-products__inner {
  margin: 0 auto;
}

/* ---- Toolbar ---- */
.dsf-shop-products__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.dsf-shop-products__count {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  opacity: 0.7;
}

.dsf-shop-products__sort {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}

.dsf-shop-products__sort-label {
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  opacity: 0.7;
}

.dsf-shop-products__sort-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  background: #fff;
  font: inherit;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  cursor: pointer;
}

/* ---- Grid ---- */
.dsf-shop-products__grid {
  display: grid;
  gap: 22px 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-shop-products__card {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  min-width: 0;
}

.dsf-shop-products__media {
  position: relative;
  display: block;
  border-radius: 16px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-shop-products__media--square { aspect-ratio: 1 / 1; }
.dsf-shop-products__media--portrait { aspect-ratio: 3 / 4; }
.dsf-shop-products__media--landscape { aspect-ratio: 4 / 3; }

.dsf-shop-products__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.25s ease;
}

.dsf-shop-products__card:hover .dsf-shop-products__media img {
  transform: scale(1.04);
}

.dsf-shop-products__badge {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 1;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  background: var(--dsf-shop-accent);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.dsf-shop-products__body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.dsf-shop-products__name {
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  font-weight: 600;
  line-height: 1.35;
}

.dsf-shop-products__name:hover {
  color: var(--dsf-shop-accent);
}

.dsf-shop-products__rating {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
}

.dsf-shop-products__stars {
  position: relative;
  display: inline-block;
  color: #e5e7eb;
  letter-spacing: 1px;
  line-height: 1;
}

.dsf-shop-products__stars-fill {
  position: absolute;
  inset: 0;
  overflow: hidden;
  white-space: nowrap;
  color: #f59e0b;
}

.dsf-shop-products__rating-count { opacity: 0.6; }

.dsf-shop-products__price {
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  font-weight: 700;
}

.dsf-shop-products__price :deep(del) {
  opacity: 0.5;
  font-weight: 400;
  margin-right: 0.35rem;
}

.dsf-shop-products__button {
  align-self: flex-start;
  margin-top: 0.25rem;
  padding: 0.5rem 1.1rem;
  border-radius: 999px;
  background: var(--dsf-shop-btn-bg, var(--dsf-shop-accent));
  color: var(--dsf-shop-btn-color, #fff);
  font-size: var(--dsf-theme-text-sm, 0.85rem);
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dsf-shop-products__button:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

/* ---- Empty / editor ghosts ---- */
.dsf-shop-products__ghosts {
  display: grid;
  gap: 18px;
}

.dsf-shop-products__ghost {
  aspect-ratio: 1 / 1;
  border-radius: 16px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-shop-products__note {
  margin: 0.75rem 0 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

/* ---- Pagination ---- */
.dsf-shop-products__pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  margin-top: 1.75rem;
}

.dsf-shop-products__page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 38px;
  height: 38px;
  padding: 0 0.6rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.dsf-shop-products__page:hover {
  border-color: var(--dsf-shop-accent);
  color: var(--dsf-shop-accent);
}

.dsf-shop-products__page.is-current {
  background: var(--dsf-shop-accent);
  border-color: var(--dsf-shop-accent);
  color: #fff;
}
</style>
