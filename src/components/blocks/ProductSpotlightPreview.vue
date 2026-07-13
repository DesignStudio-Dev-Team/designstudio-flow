<template>
  <section class="dsf-product-spotlight" :style="blockStyle">
    <div
      ref="rootEl"
      class="dsf-product-spotlight__stage"
      :class="[
        `dsf-product-spotlight__stage--${backdrop}`,
        { 'dsf-product-spotlight__stage--image-right': settings.imageSide === 'right' },
      ]"
      :style="stageStyle"
    >
      <!-- Media side: glowing frame + vertical glass thumb rail -->
      <div class="dsf-product-spotlight__media">
        <ul
          v-if="images.length > 1"
          class="dsf-product-spotlight__rail"
          aria-label="Product images"
        >
          <li v-for="(img, i) in images.slice(0, 5)" :key="img.id || i">
            <button
              type="button"
              class="dsf-product-spotlight__thumb"
              :class="{ 'is-active': i === activeIndex }"
              :aria-label="`Show image ${i + 1}`"
              :aria-current="i === activeIndex ? 'true' : undefined"
              @click="activeIndex = i"
            >
              <img :src="img.thumb" :alt="img.alt || ''" loading="lazy" decoding="async" />
            </button>
          </li>
        </ul>

        <div class="dsf-product-spotlight__frame">
          <span v-if="showSaleBadge" class="dsf-product-spotlight__chip dsf-product-spotlight__chip--sale">
            {{ settings.saleBadgeText || 'Sale' }}
          </span>
          <Transition name="dsf-spot-swap" mode="out-in">
            <img
              v-if="activeImage.large"
              :key="activeImage.large"
              :src="activeImage.large"
              :srcset="activeImage.srcset || undefined"
              :alt="activeImage.alt || ''"
              decoding="async"
              fetchpriority="high"
            />
            <span v-else class="dsf-product-spotlight__frame-empty" aria-hidden="true"></span>
          </Transition>
        </div>
      </div>

      <!-- Buy side -->
      <div class="dsf-product-spotlight__details">
        <p v-if="settings.eyebrowText" class="dsf-product-spotlight__eyebrow">
          <span class="dsf-product-spotlight__eyebrow-line" aria-hidden="true"></span>
          {{ settings.eyebrowText }}
        </p>

        <h1
          class="dsf-product-spotlight__title"
          :style="{ color: settings.titleColor || 'var(--dsf-theme-text, inherit)' }"
        >
          {{ product.name }}
        </h1>

        <div class="dsf-product-spotlight__chips">
          <span
            v-if="settings.showRating !== false && hasRating"
            class="dsf-product-spotlight__chip"
            :aria-label="ratingLabel"
          >
            <span class="dsf-product-spotlight__stars" aria-hidden="true">
              <span class="dsf-product-spotlight__stars-fill" :style="{ width: ratingPercent + '%' }">★★★★★</span>
              <span class="dsf-product-spotlight__stars-base">★★★★★</span>
            </span>
            {{ averageDisplay }} · {{ product.reviewCount || 0 }}
          </span>
          <span
            v-if="settings.showStock !== false"
            class="dsf-product-spotlight__chip"
            :class="product.isInStock ? 'is-in-stock' : 'is-out-of-stock'"
          >
            <span class="dsf-product-spotlight__dot" aria-hidden="true"></span>
            {{ product.isInStock ? 'In stock' : 'Out of stock' }}
          </span>
          <span v-if="settings.showSku && product.sku" class="dsf-product-spotlight__chip">
            SKU {{ product.sku }}
          </span>
        </div>

        <!-- priceHtml sanitized server-side with wp_kses_post (build_product_context). -->
        <div
          v-if="product.priceHtml"
          class="dsf-product-spotlight__price"
          :style="{ color: settings.priceColor || 'var(--dsf-spot-accent)' }"
          v-html="product.priceHtml"
        ></div>

        <!-- shortDescriptionHtml sanitized server-side with wp_kses_post. -->
        <div
          v-if="settings.showShortDescription !== false && product.shortDescriptionHtml"
          class="dsf-product-spotlight__excerpt"
          v-html="product.shortDescriptionHtml"
        ></div>

        <!-- Woo's own add-to-cart form, server-rendered + kses'd (Woo-form allowlist). -->
        <div v-if="showCart && cartHtml" class="dsf-product-spotlight__buy" v-html="cartHtml"></div>
        <div v-else-if="showCart" class="dsf-product-spotlight__buy dsf-product-spotlight__buy--placeholder">
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

const backdrop = computed(() => (props.settings?.backdrop === 'none' ? 'none' : 'soft'))

const images = computed(() => (Array.isArray(product.value?.gallery) ? product.value.gallery : []))
const activeIndex = ref(0)
watch(images, () => { activeIndex.value = 0 })
const activeImage = computed(() => images.value[activeIndex.value] || images.value[0] || {})

const accent = computed(() => props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)')

const showSaleBadge = computed(() => props.settings?.showSaleBadge !== false && Boolean(product.value?.onSale))
const showCart = computed(() => props.settings?.showAddToCart !== false)
const cartHtml = computed(() => product.value?.addToCartHtml || '')

const hasRating = computed(() => Number(product.value?.ratingCount || 0) > 0)
const averageDisplay = computed(() => Number(product.value?.averageRating || 0).toFixed(1))
const ratingPercent = computed(() => {
  const avg = Number(product.value?.averageRating || 0)
  return Math.max(0, Math.min(100, (avg / 5) * 100))
})
const ratingLabel = computed(
  () => `Rated ${averageDisplay.value} out of 5 by ${Number(product.value?.reviewCount || 0)} customers`
)

const rootEl = ref(null)
useWooCartForm(rootEl, () => !props.isEditor && renderMode !== 'snapshot' && showCart.value && Boolean(cartHtml.value))

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 56
  const style = {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
    '--dsf-spot-accent': accent.value,
  }
  if (props.settings?.buttonColor) style['--dsf-spot-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-spot-btn-color'] = props.settings.buttonTextColor
  return style
})

const stageStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1240
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-spotlight {
  width: 100%;
}

/* The stage: one composed surface holding media + details. */
.dsf-product-spotlight__stage {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  gap: clamp(1.75rem, 4vw, 4rem);
  align-items: center;
  margin: 0 auto;
  border-radius: 28px;
  padding: clamp(1.25rem, 3vw, 3rem);
}

.dsf-product-spotlight__stage--soft {
  background:
    radial-gradient(120% 140% at 0% 0%, color-mix(in srgb, var(--dsf-spot-accent) 10%, transparent), transparent 55%),
    radial-gradient(120% 140% at 100% 100%, color-mix(in srgb, var(--dsf-spot-accent) 7%, transparent), transparent 55%),
    color-mix(in srgb, var(--dsf-spot-accent) 3%, var(--dsf-theme-surface, #fff));
  border: 1px solid color-mix(in srgb, var(--dsf-spot-accent) 12%, transparent);
}

.dsf-product-spotlight__stage--image-right .dsf-product-spotlight__media { order: 2; }
.dsf-product-spotlight__stage--image-right .dsf-product-spotlight__details { order: 1; }

/* ---- Media ---- */
.dsf-product-spotlight__media {
  display: flex;
  gap: 14px;
  align-items: stretch;
  min-width: 0;
}

.dsf-product-spotlight__rail {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
  flex-shrink: 0;
}

.dsf-product-spotlight__thumb {
  display: block;
  width: 58px;
  height: 58px;
  padding: 0;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 14px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.6);
  backdrop-filter: blur(8px);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.dsf-product-spotlight__thumb:hover {
  transform: translateY(-2px);
}

.dsf-product-spotlight__thumb.is-active {
  border-color: var(--dsf-spot-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dsf-spot-accent) 22%, transparent);
}

.dsf-product-spotlight__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-product-spotlight__frame {
  position: relative;
  flex: 1;
  min-width: 0;
  aspect-ratio: 1 / 1;
  border-radius: 22px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
  box-shadow:
    0 24px 48px -20px color-mix(in srgb, var(--dsf-spot-accent) 35%, rgba(0, 0, 0, 0.35)),
    0 4px 14px rgba(0, 0, 0, 0.06);
}

.dsf-product-spotlight__frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-product-spotlight__frame-empty {
  display: block;
  width: 100%;
  height: 100%;
}

.dsf-spot-swap-enter-active,
.dsf-spot-swap-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.dsf-spot-swap-enter-from,
.dsf-spot-swap-leave-to {
  opacity: 0;
  transform: scale(1.015);
}

/* ---- Glass chips ---- */
.dsf-product-spotlight__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.32rem 0.75rem;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--dsf-theme-text, #111827);
  white-space: nowrap;
}

.dsf-product-spotlight__chip--sale {
  position: absolute;
  top: 16px;
  left: 16px;
  z-index: 1;
  border: 0;
  background: var(--dsf-spot-accent);
  color: #fff;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-size: 0.72rem;
  font-weight: 700;
}

.dsf-product-spotlight__dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: currentColor;
}

.dsf-product-spotlight__chip.is-in-stock { color: #15803d; }
.dsf-product-spotlight__chip.is-out-of-stock { color: #b91c1c; }

/* ---- Details ---- */
.dsf-product-spotlight__details {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  min-width: 0;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-product-spotlight__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0;
  color: var(--dsf-spot-accent);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.dsf-product-spotlight__eyebrow-line {
  width: 28px;
  height: 2px;
  border-radius: 2px;
  background: var(--dsf-spot-accent);
}

.dsf-product-spotlight__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(2rem, 4vw, 3.1rem);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.03em;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-product-spotlight__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.dsf-product-spotlight__stars {
  position: relative;
  display: inline-block;
  color: #e5e7eb;
  letter-spacing: 1.5px;
  font-size: 0.9rem;
  line-height: 1;
}

.dsf-product-spotlight__stars-fill {
  position: absolute;
  inset: 0;
  overflow: hidden;
  white-space: nowrap;
  color: #f59e0b;
}

.dsf-product-spotlight__price {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.5rem, 2.6vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.01em;
}

.dsf-product-spotlight__price :deep(del) {
  opacity: 0.4;
  font-weight: 400;
  font-size: 0.72em;
  margin-right: 0.5rem;
}

.dsf-product-spotlight__price :deep(ins) {
  text-decoration: none;
}

.dsf-product-spotlight__excerpt {
  max-width: 46ch;
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.7;
  opacity: 0.8;
}

.dsf-product-spotlight__excerpt :deep(p) { margin: 0 0 0.75rem; }
.dsf-product-spotlight__excerpt :deep(p:last-child) { margin-bottom: 0; }

/* ---- Frosted buy panel ---- */
.dsf-product-spotlight__buy {
  margin-top: 0.35rem;
  padding: 1.25rem 1.35rem;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.75);
  background: rgba(255, 255, 255, 0.55);
  backdrop-filter: blur(14px);
  box-shadow: 0 10px 30px -18px rgba(0, 0, 0, 0.25);
}

.dsf-product-spotlight__buy--placeholder {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-style: dashed;
  border-color: var(--dsf-gray-300, #d1d5db);
  color: var(--dsf-gray-500, #6b7280);
  font-size: 0.875rem;
}

/* Style Woo's injected form controls to match the stage. */
.dsf-product-spotlight__buy :deep(form.cart) {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
}

.dsf-product-spotlight__buy :deep(form.cart .variations),
.dsf-product-spotlight__buy :deep(form.cart .single_variation_wrap) {
  flex-basis: 100%;
}

.dsf-product-spotlight__buy :deep(input.qty) {
  width: 4.6rem;
  padding: 0.75rem 0.5rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  text-align: center;
  background: #fff;
  font: inherit;
}

.dsf-product-spotlight__buy :deep(.single_add_to_cart_button) {
  flex: 1;
  min-width: 180px;
  background: var(--dsf-spot-btn-bg, var(--dsf-spot-accent)) !important;
  color: var(--dsf-spot-btn-color, #fff) !important;
  border: 0 !important;
  border-radius: 999px;
  padding: 0.9rem 2rem;
  font-weight: 700;
  font-size: 1rem;
  letter-spacing: 0.01em;
  cursor: pointer;
  box-shadow: 0 12px 24px -12px color-mix(in srgb, var(--dsf-spot-btn-bg, var(--dsf-spot-accent)) 65%, transparent);
  transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.dsf-product-spotlight__buy :deep(.single_add_to_cart_button:hover) {
  transform: translateY(-2px);
  box-shadow: 0 16px 30px -12px color-mix(in srgb, var(--dsf-spot-btn-bg, var(--dsf-spot-accent)) 75%, transparent);
}

.dsf-product-spotlight__buy :deep(table.variations) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0.75rem;
}

.dsf-product-spotlight__buy :deep(table.variations td),
.dsf-product-spotlight__buy :deep(table.variations th) {
  padding: 0.4rem 0.5rem 0.4rem 0;
  text-align: left;
  vertical-align: middle;
}

.dsf-product-spotlight__buy :deep(table.variations select) {
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 12px;
  background: #fff;
  font: inherit;
}

.dsf-product-spotlight__buy :deep(.woocommerce-variation-price) {
  margin-bottom: 0.5rem;
  font-weight: 700;
}

/* ---- Responsive ---- */
@media (max-width: 960px) {
  .dsf-product-spotlight__stage {
    grid-template-columns: 1fr;
    gap: 1.75rem;
  }

  .dsf-product-spotlight__stage--image-right .dsf-product-spotlight__media { order: 0; }
  .dsf-product-spotlight__stage--image-right .dsf-product-spotlight__details { order: 1; }

  .dsf-product-spotlight__media {
    flex-direction: column-reverse;
  }

  .dsf-product-spotlight__rail {
    flex-direction: row;
    justify-content: flex-start;
  }

  .dsf-product-spotlight__thumb {
    width: 52px;
    height: 52px;
  }
}
</style>
