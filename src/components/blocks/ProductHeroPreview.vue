<template>
  <section
    class="dsf-product-hero"
    :class="{ 'dsf-product-hero--image-right': settings.imageSide === 'right' }"
    :style="blockStyle"
  >
    <div ref="rootEl" class="dsf-product-hero__inner" :style="innerStyle">
      <!-- Gallery column -->
      <div class="dsf-product-hero__media">
        <div class="dsf-product-hero__frame">
          <span v-if="showSaleBadge" class="dsf-product-hero__badge">{{ settings.saleBadgeText || 'Sale' }}</span>
          <img
            v-if="activeImage.large"
            :src="activeImage.large"
            :srcset="activeImage.srcset || undefined"
            :alt="activeImage.alt || ''"
            decoding="async"
            fetchpriority="high"
          />
        </div>
        <ul v-if="images.length > 1" class="dsf-product-hero__thumbs">
          <li v-for="(img, i) in images.slice(0, 6)" :key="img.id || i">
            <button
              type="button"
              class="dsf-product-hero__thumb"
              :class="{ 'is-active': i === activeIndex }"
              :aria-label="`Show image ${i + 1}`"
              :aria-current="i === activeIndex ? 'true' : undefined"
              @click="activeIndex = i"
            >
              <img :src="img.thumb" :alt="img.alt || ''" loading="lazy" decoding="async" />
            </button>
          </li>
        </ul>
      </div>

      <!-- Details column -->
      <div class="dsf-product-hero__details">
        <p v-if="settings.eyebrowText" class="dsf-product-hero__eyebrow" :style="{ color: accent }">
          {{ settings.eyebrowText }}
        </p>

        <h1 class="dsf-product-hero__title" :style="{ color: settings.titleColor || 'var(--dsf-theme-text, inherit)' }">
          {{ product.name }}
        </h1>

        <div v-if="settings.showRating !== false && hasRating" class="dsf-product-hero__rating" :aria-label="ratingLabel">
          <span class="dsf-product-hero__stars" aria-hidden="true">
            <span class="dsf-product-hero__stars-fill" :style="{ width: ratingPercent + '%' }">★★★★★</span>
            <span class="dsf-product-hero__stars-base">★★★★★</span>
          </span>
          <span class="dsf-product-hero__rating-count">{{ product.averageRating?.toFixed ? product.averageRating.toFixed(1) : product.averageRating }} · {{ product.reviewCount || 0 }} reviews</span>
        </div>

        <!-- priceHtml sanitized server-side with wp_kses_post (build_product_context). -->
        <div
          v-if="settings.showPrice !== false && product.priceHtml"
          class="dsf-product-hero__price"
          :style="{ color: settings.priceColor || accent }"
          v-html="product.priceHtml"
        ></div>

        <!-- shortDescriptionHtml sanitized server-side with wp_kses_post. -->
        <div
          v-if="settings.showShortDescription !== false && product.shortDescriptionHtml"
          class="dsf-product-hero__excerpt"
          v-html="product.shortDescriptionHtml"
        ></div>

        <div v-if="showStockRow" class="dsf-product-hero__meta">
          <span
            v-if="settings.showStock !== false"
            class="dsf-product-hero__stock"
            :class="product.isInStock ? 'is-in' : 'is-out'"
          >
            <span class="dsf-product-hero__stock-dot" aria-hidden="true"></span>
            {{ product.isInStock ? 'In stock' : 'Out of stock' }}
          </span>
          <span v-if="settings.showSku && product.sku" class="dsf-product-hero__sku">SKU {{ product.sku }}</span>
        </div>

        <!-- Woo's own add-to-cart form, server-rendered + kses'd (Woo-form allowlist). -->
        <div v-if="showCart && cartHtml" class="dsf-product-hero__cart" v-html="cartHtml"></div>
        <div v-else-if="showCart" class="dsf-product-hero__cart-placeholder">
          <ShoppingCart :size="16" />
          <span>Add-to-cart appears here on the live product page.</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch, inject } from 'vue'
import { ShoppingCart } from 'lucide-vue-next'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'
import { useWooCartForm } from '../../utils/useWooCartForm'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { product } = useProductContext()
const renderMode = inject('dsfRenderMode', null)

const images = computed(() => (Array.isArray(product.value?.gallery) ? product.value.gallery : []))
const activeIndex = ref(0)
watch(images, () => { activeIndex.value = 0 })
const activeImage = computed(() => images.value[activeIndex.value] || images.value[0] || {})

const accent = computed(() => props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)')

const showSaleBadge = computed(() => props.settings?.showSaleBadge !== false && Boolean(product.value?.onSale))
const showCart = computed(() => props.settings?.showAddToCart !== false)
const cartHtml = computed(() => product.value?.addToCartHtml || '')

const showStockRow = computed(
  () => props.settings?.showStock !== false || (props.settings?.showSku && Boolean(product.value?.sku))
)

const hasRating = computed(() => Number(product.value?.ratingCount || 0) > 0)
const ratingPercent = computed(() => {
  const avg = Number(product.value?.averageRating || 0)
  return Math.max(0, Math.min(100, (avg / 5) * 100))
})
const ratingLabel = computed(
  () => `Rated ${Number(product.value?.averageRating || 0).toFixed(1)} out of 5`
)

const rootEl = ref(null)
useWooCartForm(rootEl, () => !props.isEditor && renderMode !== 'snapshot' && showCart.value && Boolean(cartHtml.value))

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 48
  const style = {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
    '--dsf-hero-accent': accent.value,
  }
  // Explicit add-to-cart button color (falls back to the hero accent).
  if (props.settings?.buttonColor) style['--dsf-cart-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-cart-btn-color'] = props.settings.buttonTextColor
  return style
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1200
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-hero {
  width: 100%;
}

.dsf-product-hero__inner {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: clamp(1.5rem, 4vw, 3.5rem);
  align-items: start;
  margin: 0 auto;
}

.dsf-product-hero--image-right .dsf-product-hero__media { order: 2; }
.dsf-product-hero--image-right .dsf-product-hero__details { order: 1; }

/* Media column */
.dsf-product-hero__media {
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: sticky;
  top: 24px;
}

.dsf-product-hero__frame {
  position: relative;
  aspect-ratio: 1 / 1;
  border-radius: 20px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-product-hero__frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-product-hero__badge {
  position: absolute;
  top: 14px;
  left: 14px;
  z-index: 1;
  padding: 0.3rem 0.8rem;
  border-radius: 999px;
  background: var(--dsf-hero-accent);
  color: #fff;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.dsf-product-hero__thumbs {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-product-hero__thumb {
  display: block;
  width: 100%;
  aspect-ratio: 1 / 1;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 12px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.dsf-product-hero__thumb.is-active { border-color: var(--dsf-hero-accent); }
.dsf-product-hero__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Details column */
.dsf-product-hero__details {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-product-hero__eyebrow {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.dsf-product-hero__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.75rem, 3.4vw, var(--dsf-theme-h1, 2.6rem));
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.02em;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-product-hero__rating {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-product-hero__stars {
  position: relative;
  display: inline-block;
  color: #e5e7eb;
  letter-spacing: 2px;
  font-size: 1rem;
}

.dsf-product-hero__stars-fill {
  position: absolute;
  inset: 0;
  overflow: hidden;
  white-space: nowrap;
  color: #f59e0b;
}

.dsf-product-hero__rating-count { opacity: 0.7; }

.dsf-product-hero__price {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.35rem, 2.4vw, 1.9rem);
  font-weight: 800;
}

.dsf-product-hero__price :deep(del) {
  opacity: 0.45;
  font-weight: 400;
  margin-right: 0.5rem;
}

.dsf-product-hero__excerpt {
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.65;
  opacity: 0.85;
}

.dsf-product-hero__excerpt :deep(p) { margin: 0 0 0.75rem; }
.dsf-product-hero__excerpt :deep(p:last-child) { margin-bottom: 0; }

.dsf-product-hero__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-product-hero__stock {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
}

.dsf-product-hero__stock-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: currentColor;
}

.dsf-product-hero__stock.is-in { color: #15803d; }
.dsf-product-hero__stock.is-out { color: #b91c1c; }
.dsf-product-hero__sku { opacity: 0.6; }

.dsf-product-hero__cart {
  margin-top: 0.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--dsf-gray-200, #e5e7eb);
}

/* Style Woo's injected form controls to match the hero. */
.dsf-product-hero__cart :deep(input.qty) {
  width: 4.5rem;
  padding: 0.65rem 0.5rem;
  border: 1px solid var(--dsf-gray-300, #d1d5db);
  border-radius: 10px;
}

.dsf-product-hero__cart :deep(.single_add_to_cart_button) {
  background: var(--dsf-cart-btn-bg, var(--dsf-hero-accent)) !important;
  color: var(--dsf-cart-btn-color, #fff) !important;
  border: 0 !important;
  border-radius: 10px;
  padding: 0.8rem 1.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dsf-product-hero__cart :deep(.single_add_to_cart_button:hover) {
  opacity: 0.92;
  transform: translateY(-1px);
}

.dsf-product-hero__cart :deep(table.variations) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

.dsf-product-hero__cart :deep(table.variations td),
.dsf-product-hero__cart :deep(table.variations th) {
  padding: 0.4rem 0.5rem 0.4rem 0;
  text-align: left;
  vertical-align: middle;
}

.dsf-product-hero__cart :deep(table.variations select) {
  width: 100%;
  padding: 0.6rem 0.65rem;
  border: 1px solid var(--dsf-gray-300, #d1d5db);
  border-radius: 10px;
}

.dsf-product-hero__cart-placeholder {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.25rem;
  padding: 0.9rem 1.1rem;
  border: 1px dashed var(--dsf-gray-300, #d1d5db);
  border-radius: 12px;
  color: var(--dsf-gray-500, #6b7280);
  font-size: 0.875rem;
}

@media (max-width: 860px) {
  .dsf-product-hero__inner { grid-template-columns: 1fr; }
  .dsf-product-hero__media { position: static; }
  .dsf-product-hero--image-right .dsf-product-hero__media { order: 0; }
  .dsf-product-hero--image-right .dsf-product-hero__details { order: 1; }
}
</style>
