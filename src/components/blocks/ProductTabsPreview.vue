<template>
  <section
    class="dsf-product-tabs"
    :class="`dsf-product-tabs--${style}`"
    :style="blockStyle"
  >
    <div class="dsf-product-tabs__inner" :style="innerStyle">
      <div class="dsf-product-tabs__list" role="tablist" :aria-label="'Product information'" :style="accentVars">
        <button
          v-for="(tab, i) in tabs"
          :id="tabId(i)"
          :key="i"
          ref="tabButtons"
          type="button"
          role="tab"
          class="dsf-product-tabs__tab"
          :class="{ 'is-active': i === activeIndex }"
          :aria-selected="i === activeIndex ? 'true' : 'false'"
          :aria-controls="panelId(i)"
          :tabindex="i === activeIndex ? 0 : -1"
          @click="activeIndex = i"
          @keydown="onTabKeydown($event, i)"
        >
          {{ tab.label || `Tab ${i + 1}` }}
        </button>
      </div>

      <div
        v-for="(tab, i) in tabs"
        v-show="i === activeIndex"
        :id="panelId(i)"
        :key="`panel-${i}`"
        class="dsf-product-tabs__panel"
        role="tabpanel"
        :aria-labelledby="tabId(i)"
        tabindex="0"
      >
        <!-- Description / custom / reviews HTML are sanitized server-side (wp_kses_post or a Woo-form allowlist). -->
        <div v-if="tab.source === 'description'" v-html="product.descriptionHtml || emptyDescription"></div>

        <table v-else-if="tab.source === 'specs'" class="dsf-product-tabs__specs">
          <tbody>
            <tr v-for="(spec, s) in specs" :key="s" :class="{ 'is-striped': s % 2 === 1 }">
              <th scope="row">{{ spec.name }}</th>
              <td>{{ spec.value }}</td>
            </tr>
            <tr v-if="!specs.length">
              <td class="dsf-product-tabs__empty">No specifications available.</td>
            </tr>
          </tbody>
        </table>

        <template v-else-if="tab.source === 'reviews'">
          <div v-if="product.reviewsHtml" class="dsf-product-tabs__reviews" v-html="product.reviewsHtml"></div>
          <p v-else class="dsf-product-tabs__empty">Reviews appear here on the live product page.</p>
        </template>

        <div v-else v-html="tab.content || ''"></div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { product } = useProductContext()

const STYLES = ['underline', 'pills', 'boxed']
const ALLOWED_SOURCES = ['description', 'specs', 'reviews', 'custom']

const style = computed(() => (STYLES.includes(props.settings?.style) ? props.settings.style : 'underline'))

const tabs = computed(() => {
  const raw = Array.isArray(props.settings?.tabs) ? props.settings.tabs : []
  const clean = raw
    .filter((t) => t && typeof t === 'object')
    .map((t) => ({
      label: typeof t.label === 'string' ? t.label : '',
      source: ALLOWED_SOURCES.includes(t.source) ? t.source : 'description',
      content: typeof t.content === 'string' ? t.content : '',
    }))
  return clean.length ? clean : [{ label: 'Description', source: 'description', content: '' }]
})

const specs = computed(() => (Array.isArray(product.value?.specs) ? product.value.specs : []))
const emptyDescription = '<p>No description available.</p>'

const activeIndex = ref(0)
watch(tabs, (next) => {
  if (activeIndex.value >= next.length) activeIndex.value = 0
})

const uid = computed(() => String(props.blockId || 'pt'))
function tabId(i) {
  return `dsf-pt-tab-${uid.value}-${i}`
}
function panelId(i) {
  return `dsf-pt-panel-${uid.value}-${i}`
}

const tabButtons = ref([])
function onTabKeydown(event, index) {
  const count = tabs.value.length
  let next = null
  if (event.key === 'ArrowRight') next = (index + 1) % count
  else if (event.key === 'ArrowLeft') next = (index - 1 + count) % count
  else if (event.key === 'Home') next = 0
  else if (event.key === 'End') next = count - 1
  if (next === null) return
  event.preventDefault()
  activeIndex.value = next
  const el = tabButtons.value?.[next]
  if (el && typeof el.focus === 'function') el.focus()
}

const accentVars = computed(() => ({
  '--dsf-pt-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
}))

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return { paddingTop: `${paddingY}px`, paddingBottom: `${paddingY}px` }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 900
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-product-tabs { width: 100%; }
.dsf-product-tabs__inner { margin: 0 auto; }

.dsf-product-tabs__list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-bottom: 1.25rem;
}

.dsf-product-tabs__tab {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 600;
  padding: 0.6rem 1rem;
  border: 0;
  background: transparent;
  color: var(--dsf-theme-text, inherit);
  cursor: pointer;
  opacity: 0.65;
}
.dsf-product-tabs__tab.is-active { opacity: 1; }

/* Underline */
.dsf-product-tabs--underline .dsf-product-tabs__list { border-bottom: 1px solid var(--dsf-gray-200, #e5e7eb); gap: 0.5rem; }
.dsf-product-tabs--underline .dsf-product-tabs__tab { border-bottom: 2px solid transparent; margin-bottom: -1px; }
.dsf-product-tabs--underline .dsf-product-tabs__tab.is-active { border-bottom-color: var(--dsf-pt-accent); }

/* Pills */
.dsf-product-tabs--pills .dsf-product-tabs__tab { border-radius: 999px; }
.dsf-product-tabs--pills .dsf-product-tabs__tab.is-active { background: var(--dsf-pt-accent); color: #fff; }

/* Boxed */
.dsf-product-tabs--boxed .dsf-product-tabs__list { gap: 0; }
.dsf-product-tabs--boxed .dsf-product-tabs__tab { border: 1px solid transparent; border-radius: 8px 8px 0 0; }
.dsf-product-tabs--boxed .dsf-product-tabs__tab.is-active {
  border-color: var(--dsf-gray-200, #e5e7eb);
  border-bottom-color: #fff;
}

.dsf-product-tabs__panel {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.7;
}
.dsf-product-tabs__panel :deep(p) { margin: 0 0 1rem; }
.dsf-product-tabs__panel :deep(p:last-child) { margin-bottom: 0; }
.dsf-product-tabs__panel :deep(img) { max-width: 100%; height: auto; }

.dsf-product-tabs__specs { width: 100%; border-collapse: collapse; }
.dsf-product-tabs__specs th,
.dsf-product-tabs__specs td { padding: 0.7rem 0.9rem; text-align: left; vertical-align: top; }
.dsf-product-tabs__specs th { width: 38%; font-weight: 600; }
.dsf-product-tabs__specs tr.is-striped { background: var(--dsf-gray-50, #f9fafb); }
.dsf-product-tabs__empty { opacity: 0.6; font-style: italic; }
</style>
