<template>
  <section class="dsf-product-summary" :style="blockStyle">
    <div class="dsf-product-summary__inner" :style="innerStyle">
      <component
        :is="headingTag"
        v-if="settings.showTitle !== false"
        class="dsf-product-summary__title"
        :style="titleStyle"
      >
        {{ product.name }}
      </component>

      <div v-if="showMeta" class="dsf-product-summary__meta">
        <span v-if="settings.showSku && product.sku" class="dsf-product-summary__sku">
          SKU: {{ product.sku }}
        </span>
        <span
          v-if="settings.showStock !== false"
          class="dsf-product-summary__stock"
          :class="product.isInStock ? 'is-in-stock' : 'is-out-of-stock'"
        >
          {{ product.isInStock ? 'In stock' : 'Out of stock' }}
        </span>
      </div>

      <div
        v-if="settings.showRating !== false && hasRating"
        class="dsf-product-summary__rating"
        :aria-label="ratingLabel"
      >
        <span class="dsf-product-summary__stars" aria-hidden="true">
          <span class="dsf-product-summary__stars-fill" :style="{ width: ratingPercent + '%' }">★★★★★</span>
          <span class="dsf-product-summary__stars-base">★★★★★</span>
        </span>
        <span class="dsf-product-summary__rating-count">({{ product.reviewCount || 0 }})</span>
      </div>

      <!--
        priceHtml is WooCommerce price markup sanitized server-side with wp_kses_post()
        in DSF_Product_Templates::build_product_context() before localization.
      -->
      <div
        v-if="settings.showPrice !== false && product.priceHtml"
        class="dsf-product-summary__price"
        :style="priceStyle"
        v-html="product.priceHtml"
      ></div>

      <!--
        shortDescriptionHtml is sanitized server-side with wp_kses_post() before localization.
      -->
      <div
        v-if="settings.showShortDescription !== false && product.shortDescriptionHtml"
        class="dsf-product-summary__excerpt"
        v-html="product.shortDescriptionHtml"
      ></div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
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

const { product } = useProductContext()

const headingTag = computed(() => (props.settings?.headingTag === 'h2' ? 'h2' : 'h1'))

const showMeta = computed(
  () =>
    (props.settings?.showSku && Boolean(product.value?.sku)) ||
    props.settings?.showStock !== false
)

const hasRating = computed(() => Number(product.value?.ratingCount || 0) > 0)
const ratingPercent = computed(() => {
  const avg = Number(product.value?.averageRating || 0)
  return Math.max(0, Math.min(100, (avg / 5) * 100))
})
const ratingLabel = computed(
  () => `Rated ${Number(product.value?.averageRating || 0).toFixed(1)} out of 5`
)

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    textAlign: props.settings?.alignment === 'center' ? 'center' : 'left',
    color: props.settings?.textColor || 'var(--dsf-theme-text, inherit)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 640
  return {
    maxWidth: `${maxWidth}px`,
    marginLeft: props.settings?.alignment === 'center' ? 'auto' : '0',
    marginRight: props.settings?.alignment === 'center' ? 'auto' : '0',
  }
})

const titleStyle = computed(() => ({
  color: props.settings?.titleColor || 'var(--dsf-theme-text, inherit)',
}))

const priceStyle = computed(() => ({
  color: props.settings?.priceColor || 'var(--dsf-theme-primary, inherit)',
}))
</script>

<style scoped>
.dsf-product-summary {
  width: 100%;
}

.dsf-product-summary__inner {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-product-summary__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h1, 2.25rem);
  font-weight: 700;
  line-height: 1.15;
  margin: 0;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-product-summary__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  opacity: 0.85;
}

.dsf-product-summary__stock.is-in-stock {
  color: #15803d;
}

.dsf-product-summary__stock.is-out-of-stock {
  color: #b91c1c;
}

.dsf-product-summary__rating {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-product-summary__stars {
  position: relative;
  display: inline-block;
  color: #d1d5db;
  letter-spacing: 1px;
}

.dsf-product-summary__stars-fill {
  position: absolute;
  inset: 0;
  overflow: hidden;
  white-space: nowrap;
  color: #f59e0b;
}

.dsf-product-summary__price {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.5rem);
  font-weight: 700;
}

.dsf-product-summary__price :deep(del) {
  opacity: 0.55;
  font-weight: 400;
  margin-right: 0.4rem;
}

.dsf-product-summary__excerpt {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.6;
}

.dsf-product-summary__excerpt :deep(p) {
  margin: 0 0 0.75rem;
}

.dsf-product-summary__excerpt :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
