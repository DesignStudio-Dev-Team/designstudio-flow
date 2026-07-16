<template>
  <section class="dsf-product-specs" :class="`dsf-product-specs--${layout}`" :style="blockStyle">
    <div class="dsf-product-specs__inner" :style="innerStyle">
      <h2
        v-if="settings.showHeading !== false"
        class="dsf-product-specs__heading"
        :style="{ color: settings.headingColor || 'var(--dsf-theme-text, inherit)' }"
      >
        {{ settings.headingText || 'Specifications' }}
      </h2>

      <template v-if="specs.length">
        <!-- Cards grid -->
        <div v-if="layout === 'cards'" class="dsf-product-specs__cards" :style="cardsStyle">
          <div v-for="(spec, i) in specs" :key="i" class="dsf-product-specs__card" :style="cardStyle">
            <span class="dsf-product-specs__card-name" :style="labelStyle">{{ spec.name }}</span>
            <span class="dsf-product-specs__card-value" :style="valueStyle">{{ spec.value }}</span>
          </div>
        </div>

        <!-- Inline pills -->
        <ul v-else-if="layout === 'inline'" class="dsf-product-specs__pills">
          <li v-for="(spec, i) in specs" :key="i" class="dsf-product-specs__pill" :style="pillStyle">
            <span class="dsf-product-specs__pill-name" :style="labelStyle">{{ spec.name }}</span>
            <span class="dsf-product-specs__pill-value" :style="valueStyle">{{ spec.value }}</span>
          </li>
        </ul>

        <!-- Striped / bordered table -->
        <table v-else class="dsf-product-specs__table">
          <tbody>
            <tr v-for="(spec, i) in specs" :key="i" :style="rowStyle(i)">
              <th scope="row" class="dsf-product-specs__th" :style="labelStyle">{{ spec.name }}</th>
              <td class="dsf-product-specs__td" :style="valueStyle">{{ spec.value }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <p v-else class="dsf-product-specs__empty">No specifications available for this product.</p>
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

const LAYOUTS = ['striped', 'cards', 'inline', 'bordered']
const layout = computed(() => (LAYOUTS.includes(props.settings?.layout) ? props.settings.layout : 'striped'))

const customFieldKeys = computed(() => String(props.settings?.customFieldKeys || '')
  .split(',')
  .map((key) => key.trim())
  .filter(Boolean)
  .slice(0, 12))

const specs = computed(() => {
  const attributes = Array.isArray(product.value?.specs) ? product.value.specs : []
  const fields = product.value?.customFields && typeof product.value.customFields === 'object'
    ? product.value.customFields
    : {}
  const custom = customFieldKeys.value
    .filter((key) => typeof fields[key] === 'string' && fields[key])
    .map((key) => ({ name: key.replace(/[_-]+/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()), value: fields[key] }))
  return [...attributes, ...custom]
})

const accent = computed(() => props.settings?.accentColor || 'var(--dsf-gray-100, #f3f4f6)')

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return { paddingTop: `${paddingY}px`, paddingBottom: `${paddingY}px` }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 760
  return { maxWidth: `${maxWidth}px` }
})

const labelStyle = computed(() => ({ color: props.settings?.labelColor || 'var(--dsf-theme-text, inherit)' }))
const valueStyle = computed(() => ({ color: props.settings?.valueColor || 'var(--dsf-theme-text, inherit)' }))

const cardsStyle = computed(() => {
  const columns = Math.max(1, Math.min(3, Number(props.settings?.columns) || 1))
  return { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }
})

const cardStyle = computed(() => ({ borderColor: props.settings?.accentColor || 'var(--dsf-gray-200, #e5e7eb)' }))
const pillStyle = computed(() => ({ background: accent.value }))

function rowStyle(index) {
  if (layout.value === 'striped' && index % 2 === 1) {
    return { background: accent.value }
  }
  return {}
}
</script>

<style scoped>
.dsf-product-specs {
  width: 100%;
}

.dsf-product-specs__inner {
  margin: 0 auto;
}

.dsf-product-specs__heading {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 1.875rem);
  font-weight: 700;
  line-height: 1.2;
  margin: 0 0 1rem;
}

.dsf-product-specs__table {
  width: 100%;
  border-collapse: collapse;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
}

.dsf-product-specs__th,
.dsf-product-specs__td {
  padding: 0.7rem 0.9rem;
  text-align: left;
  vertical-align: top;
}

.dsf-product-specs__th {
  width: 38%;
  font-weight: 600;
}

.dsf-product-specs--bordered .dsf-product-specs__th,
.dsf-product-specs--bordered .dsf-product-specs__td {
  border: 1px solid var(--dsf-gray-200, #e5e7eb);
}

.dsf-product-specs__cards {
  display: grid;
  gap: 0.75rem;
}

.dsf-product-specs__card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--dsf-gray-200, #e5e7eb);
  border-radius: 10px;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-product-specs__card-name {
  font-size: var(--dsf-theme-text-sm, 0.85rem);
  font-weight: 600;
  opacity: 0.75;
}

.dsf-product-specs__card-value {
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 600;
}

.dsf-product-specs__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-product-specs__pill {
  display: inline-flex;
  align-items: baseline;
  gap: 0.4rem;
  padding: 0.4rem 0.75rem;
  border-radius: 999px;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.85rem);
}

.dsf-product-specs__pill-name {
  font-weight: 600;
  opacity: 0.75;
}

.dsf-product-specs__empty {
  font-family: var(--dsf-theme-body-font, inherit);
  opacity: 0.6;
  font-style: italic;
  margin: 0;
}
</style>
