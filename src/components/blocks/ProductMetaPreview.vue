<template>
  <section
    class="dsf-product-meta"
    :class="[`dsf-product-meta--${layout}`, { 'dsf-product-meta--center': settings.alignment === 'center' }]"
    :style="blockStyle"
  >
    <div class="dsf-product-meta__inner" :style="innerStyle">
      <div v-if="settings.showSku !== false && product.sku" class="dsf-product-meta__row">
        <span class="dsf-product-meta__label" :style="labelStyle">SKU:</span>
        <span class="dsf-product-meta__value">{{ product.sku }}</span>
      </div>

      <div v-if="settings.showCategories !== false && categories.length" class="dsf-product-meta__row">
        <span class="dsf-product-meta__label" :style="labelStyle">
          {{ categories.length === 1 ? 'Category:' : 'Categories:' }}
        </span>
        <span class="dsf-product-meta__value">
          <template v-for="(term, i) in categories" :key="`c-${i}`">
            <a
              :href="term.url || '#'"
              class="dsf-product-meta__link"
              :style="linkStyle"
              @click="isEditor && $event.preventDefault()"
            >{{ term.name }}</a><template v-if="i < categories.length - 1">, </template>
          </template>
        </span>
      </div>

      <div v-if="settings.showTags !== false && tags.length" class="dsf-product-meta__row">
        <span class="dsf-product-meta__label" :style="labelStyle">
          {{ tags.length === 1 ? 'Tag:' : 'Tags:' }}
        </span>
        <span class="dsf-product-meta__value">
          <template v-for="(term, i) in tags" :key="`t-${i}`">
            <a
              :href="term.url || '#'"
              class="dsf-product-meta__link"
              :style="linkStyle"
              @click="isEditor && $event.preventDefault()"
            >{{ term.name }}</a><template v-if="i < tags.length - 1">, </template>
          </template>
        </span>
      </div>

      <p v-if="isEditor && isEmpty" class="dsf-product-meta__empty">
        SKU, categories, and tags appear here (based on the preview product).
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

const layout = computed(() => (props.settings?.layout === 'inline' ? 'inline' : 'stacked'))

function cleanTerms(raw) {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((t) => t && typeof t === 'object' && typeof t.name === 'string' && t.name)
    .slice(0, 20)
}

const categories = computed(() => cleanTerms(product.value?.categories))
const tags = computed(() => cleanTerms(product.value?.tags))

const isEmpty = computed(
  () =>
    !(props.settings?.showSku !== false && product.value?.sku) &&
    !(props.settings?.showCategories !== false && categories.value.length) &&
    !(props.settings?.showTags !== false && tags.value.length)
)

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
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

const labelStyle = computed(() => ({
  color: props.settings?.labelColor || 'var(--dsf-theme-text, inherit)',
}))

const linkStyle = computed(() => ({
  color: props.settings?.linkColor || 'var(--dsf-theme-primary, inherit)',
}))
</script>

<style scoped>
.dsf-product-meta {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-product-meta__inner {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.dsf-product-meta--inline .dsf-product-meta__inner {
  flex-direction: row;
  flex-wrap: wrap;
  gap: 0.4rem 1.25rem;
}

.dsf-product-meta--center .dsf-product-meta__inner {
  justify-content: center;
  text-align: center;
}

.dsf-product-meta__row {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: baseline;
}

.dsf-product-meta--center .dsf-product-meta__row {
  justify-content: center;
}

.dsf-product-meta__label {
  font-weight: 700;
}

.dsf-product-meta__value {
  opacity: 0.9;
}

.dsf-product-meta__link {
  text-decoration: none;
}

.dsf-product-meta__link:hover {
  text-decoration: underline;
}

.dsf-product-meta__empty {
  margin: 0;
  opacity: 0.6;
  font-style: italic;
}
</style>
