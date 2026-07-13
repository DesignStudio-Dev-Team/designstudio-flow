<template>
  <section
    class="dsf-shop-filters"
    :class="`dsf-shop-filters--${layout}`"
    :style="blockStyle"
  >
    <div class="dsf-shop-filters__inner" :style="innerStyle">
      <form
        v-if="settings.showPrice !== false"
        class="dsf-shop-filters__price"
        method="get"
        :action="priceFilter.action || undefined"
        @submit="isEditor && $event.preventDefault()"
      >
        <span class="dsf-shop-filters__label">Price</span>
        <input
          class="dsf-shop-filters__input"
          type="number"
          name="min_price"
          min="0"
          step="any"
          inputmode="decimal"
          placeholder="Min"
          :value="priceFilter.min"
          aria-label="Minimum price"
        />
        <span class="dsf-shop-filters__dash" aria-hidden="true">–</span>
        <input
          class="dsf-shop-filters__input"
          type="number"
          name="max_price"
          min="0"
          step="any"
          inputmode="decimal"
          placeholder="Max"
          :value="priceFilter.max"
          aria-label="Maximum price"
        />
        <button type="submit" class="dsf-shop-filters__apply">Apply</button>
        <a
          v-if="hasActivePriceFilter"
          :href="priceFilter.action || '#'"
          class="dsf-shop-filters__clear"
          @click="isEditor && $event.preventDefault()"
        >Clear</a>
      </form>

      <nav
        v-if="settings.showCategories !== false && categories.length"
        class="dsf-shop-filters__categories"
        aria-label="Product categories"
      >
        <a
          v-for="(cat, i) in categories"
          :key="i"
          :href="cat.url || '#'"
          class="dsf-shop-filters__chip"
          :class="{ 'is-current': cat.current }"
          :aria-current="cat.current ? 'page' : undefined"
          @click="isEditor && $event.preventDefault()"
        >
          {{ cat.name }}<span v-if="settings.showCounts !== false" class="dsf-shop-filters__chip-count">{{ cat.count }}</span>
        </a>
      </nav>

      <p v-if="isEditor && !categories.length && settings.showCategories !== false" class="dsf-shop-filters__note">
        Category filters appear here once your store has product categories.
      </p>
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

const layout = computed(() => (props.settings?.layout === 'panel' ? 'panel' : 'bar'))

const categories = computed(() => {
  const raw = Array.isArray(archive.value?.categories) ? archive.value.categories : []
  return raw.filter((c) => c && typeof c === 'object' && typeof c.name === 'string').slice(0, 20)
})

const priceFilter = computed(() => {
  const raw = archive.value?.priceFilter
  return raw && typeof raw === 'object'
    ? { min: raw.min ?? '', max: raw.max ?? '', action: typeof raw.action === 'string' ? raw.action : '' }
    : { min: '', max: '', action: '' }
})

const hasActivePriceFilter = computed(() => priceFilter.value.min !== '' || priceFilter.value.max !== '')

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 12
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-filters-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1200}px` }))
</script>

<style scoped>
.dsf-shop-filters {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-shop-filters__inner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.9rem 1.5rem;
  margin: 0 auto;
}

.dsf-shop-filters--panel .dsf-shop-filters__inner {
  flex-direction: column;
  align-items: stretch;
  padding: 1.1rem 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-shop-filters__price {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.dsf-shop-filters__label {
  font-weight: 700;
  margin-right: 0.2rem;
}

.dsf-shop-filters__input {
  width: 5.2rem;
  padding: 0.45rem 0.65rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  font: inherit;
}

.dsf-shop-filters__dash {
  opacity: 0.5;
}

.dsf-shop-filters__apply {
  padding: 0.45rem 1rem;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-filters-accent);
  color: #fff;
  font-weight: 700;
  font-size: inherit;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.dsf-shop-filters__apply:hover {
  opacity: 0.9;
}

.dsf-shop-filters__clear {
  color: inherit;
  opacity: 0.6;
  text-decoration: underline;
}

.dsf-shop-filters__categories {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.dsf-shop-filters__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.85rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  color: inherit;
  text-decoration: none;
  font-weight: 600;
  transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.dsf-shop-filters__chip:hover {
  border-color: var(--dsf-filters-accent);
  color: var(--dsf-filters-accent);
}

.dsf-shop-filters__chip.is-current {
  background: var(--dsf-filters-accent);
  border-color: var(--dsf-filters-accent);
  color: #fff;
}

.dsf-shop-filters__chip-count {
  font-size: 0.72rem;
  opacity: 0.65;
  font-weight: 700;
}

.dsf-shop-filters__note {
  margin: 0;
  opacity: 0.6;
  font-style: italic;
}
</style>
