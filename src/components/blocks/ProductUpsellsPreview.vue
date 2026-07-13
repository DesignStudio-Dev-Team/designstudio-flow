<template>
  <section class="dsf-product-upsells" :style="blockStyle">
    <div class="dsf-product-upsells__inner" :style="innerStyle">
      <div v-if="settings.showHeading !== false" class="dsf-product-upsells__head">
        <h2 class="dsf-product-upsells__heading" :style="{ color: settings.headingColor || 'var(--dsf-theme-text, inherit)' }">
          {{ settings.headingText || 'Pairs well with' }}
        </h2>
      </div>

      <ul v-if="cards.length" class="dsf-product-upsells__grid" :style="gridStyle">
        <li v-for="card in cards" :key="card.id" class="dsf-product-upsells__card">
          <a
            :href="card.permalink || '#'"
            class="dsf-product-upsells__link"
            @click="isEditor && $event.preventDefault()"
          >
            <span class="dsf-product-upsells__frame">
              <span v-if="card.onSale" class="dsf-product-upsells__badge">Sale</span>
              <img v-if="card.image" :src="card.image" :alt="card.imageAlt || card.name" loading="lazy" decoding="async" />
            </span>
            <span class="dsf-product-upsells__name">{{ card.name }}</span>
            <!-- priceHtml sanitized server-side with wp_kses_post (build_product_cards). -->
            <span v-if="settings.showPrice !== false && card.priceHtml" class="dsf-product-upsells__price" v-html="card.priceHtml"></span>
          </a>
        </li>
      </ul>

      <p v-else class="dsf-product-upsells__empty">
        {{ isEditor ? 'Upsells appear here — pick them on the product\'s Linked Products → Upsells.' : 'No suggestions found.' }}
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

const cards = computed(() => {
  const raw = Array.isArray(product.value?.upsellProducts) ? product.value.upsellProducts : []
  const count = Math.max(2, Math.min(8, Number(props.settings?.count) || 4))
  return raw.filter((c) => c && typeof c === 'object').slice(0, count)
})

const gridStyle = computed(() => {
  const desktopCols = Math.max(2, Math.min(4, Number(props.settings?.columns) || 4))
  const cols = props.previewMode === 'mobile' ? 2 : props.previewMode === 'tablet' ? Math.min(3, desktopCols) : desktopCols
  return { gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 40
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
    '--dsf-upsells-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1200
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-upsells { width: 100%; }
.dsf-product-upsells__inner { margin: 0 auto; }

.dsf-product-upsells__head { margin-bottom: 1.25rem; }

.dsf-product-upsells__heading {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 1.75rem);
  font-weight: 800;
  letter-spacing: -0.01em;
}

.dsf-product-upsells__grid {
  display: grid;
  gap: 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-product-upsells__link {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  text-decoration: none;
  color: inherit;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-product-upsells__frame {
  position: relative;
  display: block;
  aspect-ratio: 1 / 1;
  border-radius: 16px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-product-upsells__frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.25s ease;
}

.dsf-product-upsells__link:hover .dsf-product-upsells__frame img {
  transform: scale(1.04);
}

.dsf-product-upsells__badge {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 1;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  background: var(--dsf-upsells-accent);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.dsf-product-upsells__name {
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  font-weight: 600;
  line-height: 1.35;
}

.dsf-product-upsells__link:hover .dsf-product-upsells__name {
  color: var(--dsf-upsells-accent);
}

.dsf-product-upsells__price {
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  font-weight: 700;
}

.dsf-product-upsells__price :deep(del) {
  opacity: 0.5;
  font-weight: 400;
  margin-right: 0.35rem;
}

.dsf-product-upsells__empty {
  margin: 0;
  font-family: var(--dsf-theme-body-font, inherit);
  opacity: 0.6;
  font-style: italic;
}
</style>
