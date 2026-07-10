<template>
  <section
    class="dsf-product-highlights"
    :class="[`dsf-product-highlights--${layout}`, { 'dsf-product-highlights--cards': settings.cardStyle !== false }]"
    :style="blockStyle"
  >
    <div class="dsf-product-highlights__inner" :style="innerStyle">
      <ul class="dsf-product-highlights__list" :style="listStyle">
        <li v-for="(item, i) in items" :key="i" class="dsf-product-highlights__item">
          <span class="dsf-product-highlights__icon" :style="iconStyle" aria-hidden="true">
            <component :is="iconFor(item.icon)" :size="20" />
          </span>
          <span class="dsf-product-highlights__text">
            <strong v-if="item.title">{{ item.title }}</strong>
            <small v-if="item.description">{{ item.description }}</small>
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { iconFor } from '../../utils/landingIcons'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const DEFAULT_ITEMS = [
  { icon: 'rocket', title: 'Free shipping', description: 'On orders over $50' },
  { icon: 'shield-check', title: '2-year warranty', description: 'Covered from day one' },
  { icon: 'check', title: '30-day returns', description: 'No questions asked' },
]

const items = computed(() => {
  const raw = Array.isArray(props.settings?.items) ? props.settings.items : []
  const clean = raw.filter((item) => item && typeof item === 'object' && (item.title || item.description))
  return clean.length ? clean.slice(0, 8) : DEFAULT_ITEMS
})

const layout = computed(() => (props.settings?.layout === 'grid' ? 'grid' : 'row'))
const accent = computed(() => props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)')

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 24
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1100
  return { maxWidth: `${maxWidth}px` }
})

const listStyle = computed(() => {
  if (layout.value !== 'grid') return {}
  const columns = Math.max(2, Math.min(4, Number(props.settings?.columns) || 3))
  return { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }
})

const iconStyle = computed(() => ({
  color: accent.value,
  background: props.settings?.iconBackground || 'color-mix(in srgb, currentColor 10%, transparent)',
}))
</script>

<style scoped>
.dsf-product-highlights { width: 100%; }
.dsf-product-highlights__inner { margin: 0 auto; }

.dsf-product-highlights__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  justify-content: center;
}

.dsf-product-highlights--grid .dsf-product-highlights__list {
  display: grid;
}

.dsf-product-highlights__item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  font-family: var(--dsf-theme-body-font, inherit);
  min-width: 0;
}

.dsf-product-highlights--row .dsf-product-highlights__item {
  flex: 1 1 220px;
  max-width: 340px;
}

.dsf-product-highlights--cards .dsf-product-highlights__item {
  padding: 1rem 1.15rem;
  border: 1px solid var(--dsf-gray-200, #e5e7eb);
  border-radius: 14px;
  background: #fff;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.dsf-product-highlights--cards .dsf-product-highlights__item:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
}

.dsf-product-highlights__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 44px;
  height: 44px;
  border-radius: 12px;
}

.dsf-product-highlights__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.dsf-product-highlights__text strong {
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  font-weight: 700;
  line-height: 1.3;
}

.dsf-product-highlights__text small {
  font-size: 0.8rem;
  opacity: 0.65;
  line-height: 1.35;
}

@media (max-width: 640px) {
  .dsf-product-highlights--grid .dsf-product-highlights__list {
    grid-template-columns: 1fr !important;
  }
}
</style>
