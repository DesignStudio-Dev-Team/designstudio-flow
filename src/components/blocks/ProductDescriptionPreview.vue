<template>
  <section class="dsf-product-description" :style="blockStyle">
    <div class="dsf-product-description__inner" :style="innerStyle">
      <h2
        v-if="settings.showHeading !== false"
        class="dsf-product-description__heading"
        :style="{ color: settings.headingColor || 'var(--dsf-theme-text, inherit)' }"
      >
        {{ settings.headingText || 'Description' }}
      </h2>

      <!--
        descriptionHtml is the product long description, sanitized server-side with
        wp_kses_post() in DSF_Product_Templates::build_product_context().
      -->
      <div
        v-if="product.descriptionHtml"
        class="dsf-product-description__body"
        v-html="product.descriptionHtml"
      ></div>
      <p v-else class="dsf-product-description__empty">No description available for this product.</p>
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

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    color: props.settings?.textColor || 'var(--dsf-theme-text, inherit)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 900
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-description {
  width: 100%;
}

.dsf-product-description__inner {
  margin: 0 auto;
}

.dsf-product-description__heading {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 1.875rem);
  font-weight: 700;
  line-height: 1.2;
  margin: 0 0 1rem;
}

.dsf-product-description__body {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.7;
}

.dsf-product-description__body :deep(p) {
  margin: 0 0 1rem;
}

.dsf-product-description__body :deep(p:last-child) {
  margin-bottom: 0;
}

.dsf-product-description__body :deep(img) {
  max-width: 100%;
  height: auto;
}

.dsf-product-description__body :deep(ul),
.dsf-product-description__body :deep(ol) {
  margin: 0 0 1rem;
  padding-left: 1.5rem;
}

.dsf-product-description__empty {
  font-family: var(--dsf-theme-body-font, inherit);
  opacity: 0.6;
  font-style: italic;
  margin: 0;
}
</style>
