<template>
  <section class="dsf-product-reviews" :style="blockStyle">
    <div class="dsf-product-reviews__inner" :style="innerStyle">
      <h2
        v-if="settings.showHeading !== false"
        class="dsf-product-reviews__heading"
        :style="{ color: settings.headingColor || 'var(--dsf-theme-text, inherit)' }"
      >
        {{ settings.headingText || 'Customer Reviews' }}
      </h2>

      <div
        v-if="settings.showSummary !== false && hasRating"
        class="dsf-product-reviews__summary"
        :aria-label="ratingLabel"
      >
        <span class="dsf-product-reviews__average">{{ averageDisplay }}</span>
        <span class="dsf-product-reviews__stars" aria-hidden="true">
          <span class="dsf-product-reviews__stars-fill" :style="{ width: ratingPercent + '%' }">★★★★★</span>
          <span class="dsf-product-reviews__stars-base">★★★★★</span>
        </span>
        <span class="dsf-product-reviews__count">
          Based on {{ reviewCount }} {{ reviewCount === 1 ? 'review' : 'reviews' }}
        </span>
      </div>

      <!--
        reviewsHtml is WooCommerce's own reviews template captured server-side and
        sanitized with the Woo-form allowlist in build_reviews_html() before localization.
      -->
      <div
        v-if="product.reviewsHtml"
        class="dsf-product-reviews__body"
        v-html="product.reviewsHtml"
      ></div>
      <p v-else class="dsf-product-reviews__empty">
        {{ isEditor ? 'Customer reviews and the review form appear here on the live product page.' : 'There are no reviews yet.' }}
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { product } = useProductContext()

const reviewCount = computed(() => Math.max(0, Number(product.value?.reviewCount) || 0))
const hasRating = computed(() => Number(product.value?.ratingCount || 0) > 0)

const averageDisplay = computed(() => Number(product.value?.averageRating || 0).toFixed(1))
const ratingPercent = computed(() => {
  const avg = Number(product.value?.averageRating || 0)
  return Math.max(0, Math.min(100, (avg / 5) * 100))
})
const ratingLabel = computed(
  () => `Rated ${averageDisplay.value} out of 5 based on ${reviewCount.value} reviews`
)

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-reviews-accent': props.settings?.accentColor || '#f59e0b',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 900
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-reviews {
  width: 100%;
}

.dsf-product-reviews__inner {
  margin: 0 auto;
}

.dsf-product-reviews__heading {
  margin: 0 0 1rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 1.75rem);
  font-weight: 800;
  letter-spacing: -0.01em;
}

.dsf-product-reviews__summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1.25rem;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-product-reviews__average {
  font-size: var(--dsf-theme-h3, 1.5rem);
  font-weight: 800;
  line-height: 1;
}

.dsf-product-reviews__stars {
  position: relative;
  display: inline-block;
  color: #d1d5db;
  letter-spacing: 2px;
  font-size: 1.1rem;
  line-height: 1;
}

.dsf-product-reviews__stars-fill {
  position: absolute;
  inset: 0;
  overflow: hidden;
  white-space: nowrap;
  color: var(--dsf-reviews-accent);
}

.dsf-product-reviews__count {
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  opacity: 0.7;
}

.dsf-product-reviews__body {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.6;
}

/* Light structural polish over Woo's reviews markup. */
.dsf-product-reviews__body :deep(ol.commentlist) {
  list-style: none;
  margin: 0 0 1.5rem;
  padding: 0;
}

.dsf-product-reviews__body :deep(ol.commentlist > li) {
  padding: 1rem 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.dsf-product-reviews__body :deep(img.avatar) {
  border-radius: 999px;
  width: 40px;
  height: 40px;
  float: left;
  margin-right: 0.75rem;
}

.dsf-product-reviews__body :deep(.star-rating) {
  color: var(--dsf-reviews-accent);
}

.dsf-product-reviews__body :deep(input[type='submit']),
.dsf-product-reviews__body :deep(button[type='submit']) {
  padding: 0.65rem 1.4rem;
  border: 0;
  border-radius: 8px;
  background: var(--dsf-theme-primary, #2c5f5d);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.dsf-product-reviews__body :deep(textarea),
.dsf-product-reviews__body :deep(input[type='text']),
.dsf-product-reviews__body :deep(input[type='email']) {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 8px;
  font: inherit;
}

.dsf-product-reviews__empty {
  margin: 0;
  font-family: var(--dsf-theme-body-font, inherit);
  opacity: 0.6;
  font-style: italic;
}
</style>
