<template>
  <section class="dsf-tabbed-showcase" :class="[`dsf-tabbed-showcase--${presentationStyle}`, `dsf-tabbed-showcase--tab-style-${tabStyle}`]" :style="sectionStyle">
    <div class="dsf-tabbed-showcase__inner">
      <header class="dsf-tabbed-showcase__header">
        <InlineText v-if="isEditor || settings.title" v-model="settings.title" tagName="h2" class="dsf-tabbed-showcase__title" :is-editor="isEditor" placeholder="Featured Products" />
      <InlineText v-if="showDescription && (isEditor || settings.description)" v-model="settings.description" tagName="p" class="dsf-tabbed-showcase__description" :is-editor="isEditor" :multiline="true" placeholder="Explore featured products and collections for your space." />
      </header>

      <div class="dsf-tabbed-showcase__tabs" role="tablist" aria-label="Featured product categories">
        <template v-for="(tab, index) in tabs" :key="index">
          <span v-if="index > 0" class="dsf-tabbed-showcase__separator" aria-hidden="true">|</span>
          <button :id="tabId(index)" type="button" role="tab" class="dsf-tabbed-showcase__tab" :class="{ 'is-active': index === activeIndex }" :aria-selected="index === activeIndex ? 'true' : 'false'" :aria-controls="panelId(index)" @click="activeIndex = index" @keydown="onTabKeydown($event, index)">
            <InlineText v-model="tab.label" tagName="span" :is-editor="isEditor" :placeholder="`Tab ${index + 1}`" />
            <svg v-if="tabStyle === 'chevron' && index === activeIndex" class="dsf-tabbed-showcase__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M4.29289 8.29289C4.68342 7.90237 5.31658 7.90237 5.70711 8.29289L12 14.5858L18.2929 8.29289C18.6834 7.90237 19.3166 7.90237 19.7071 8.29289C20.0976 8.68342 20.0976 9.3166 19.7071 9.70711L12.7071 16.7071C12.3166 17.0976 11.6834 17.0976 11.2929 16.7071L4.29289 9.70711C3.90237 9.31658 3.90237 8.68342 4.29289 8.29289Z" /></svg>
          </button>
        </template>
      </div>

      <div :id="panelId(activeIndex)" class="dsf-tabbed-showcase__panel" role="tabpanel" :aria-labelledby="tabId(activeIndex)">
        <div class="dsf-tabbed-showcase__carousel" :class="{ 'is-carousel': isCarousel }">
          <button v-if="isCarousel" type="button" class="dsf-tabbed-showcase__nav dsf-tabbed-showcase__nav--prev" :disabled="!canScrollPrev" aria-label="Show previous items" @click="scrollPrev">‹</button>
          <div class="dsf-tabbed-showcase__cards">
          <article v-for="(item, index) in visibleItems" :key="item.id || index" class="dsf-tabbed-showcase__card">
            <a :href="itemHref(item)" class="dsf-tabbed-showcase__card-link" @click="handleLinkClick">
              <div class="dsf-tabbed-showcase__image-wrap">
                <img v-if="item.image" :src="activeTab.source === 'images' ? safeImageUrl(item.image) : item.image" :alt="item.name || item.title || ''" class="dsf-tabbed-showcase__image" />
                <div v-else class="dsf-tabbed-showcase__placeholder" aria-hidden="true"></div>
              </div>
              <template v-if="activeTab.source === 'images' || item.placeholder">
                <InlineText v-if="isEditor || item.title" v-model="item.title" tagName="h3" class="dsf-tabbed-showcase__card-title" :is-editor="isEditor" :placeholder="`Card ${index + 1}`" />
                <InlineText v-if="isEditor || item.subtitle" v-model="item.subtitle" tagName="p" class="dsf-tabbed-showcase__card-subtitle" :is-editor="isEditor" placeholder="Add a subtitle" />
                <InlineText v-if="isEditor || item.secondarySubtitle" v-model="item.secondarySubtitle" tagName="p" class="dsf-tabbed-showcase__card-secondary-subtitle" :is-editor="isEditor" placeholder="Optional second subtitle" />
              </template>
              <template v-else>
                <h3 class="dsf-tabbed-showcase__card-title">{{ item.name }}</h3>
                <p v-if="item.meta" class="dsf-tabbed-showcase__card-subtitle">{{ item.meta }}</p>
              </template>
            </a>
          </article>
          </div>
          <button v-if="isCarousel" type="button" class="dsf-tabbed-showcase__nav dsf-tabbed-showcase__nav--next" :disabled="!canScrollNext" aria-label="Show next items" @click="scrollNext">›</button>
        </div>
      </div>

      <div v-if="settings.showButtons !== false" class="dsf-tabbed-showcase__actions">
        <a :href="safeActionUrl(settings.primaryUrl)" class="dsf-tabbed-showcase__button dsf-tabbed-showcase__button--primary" @click="handleLinkClick"><InlineText v-model="settings.primaryText" tagName="span" :is-editor="isEditor" placeholder="Explore All" /></a>
        <a :href="safeActionUrl(settings.secondaryUrl)" class="dsf-tabbed-showcase__button dsf-tabbed-showcase__button--secondary" @click="handleLinkClick"><InlineText v-model="settings.secondaryText" tagName="span" :is-editor="isEditor" placeholder="Learn More" /></a>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, blockId: { type: [String, Number], default: '' }, previewMode: { type: String, default: 'desktop' } })
const wpData = window.dsfEditorData || window.dsfFrontendData || {}
const activeIndex = ref(0)
const products = ref([])
const carouselIndex = ref(0)
const tabs = computed(() => {
  const raw = Array.isArray(props.settings?.tabs) ? props.settings.tabs : []
  const cleaned = raw.filter((tab) => tab && typeof tab === 'object').slice(0, 6)
  return cleaned.length ? cleaned : [{ label: 'Featured', source: 'images', productIds: [], images: [] }]
})
const activeTab = computed(() => tabs.value[Math.min(activeIndex.value, tabs.value.length - 1)] || tabs.value[0])
const presentationStyle = computed(() => ['image', 'products', 'tabs'].includes(props.settings?.style) ? props.settings.style : 'image')
const tabStyle = computed(() => ['modern', 'underline', 'chevron'].includes(props.settings?.tabStyle) ? props.settings.tabStyle : 'modern')
const showDescription = computed(() => props.settings?.showDescription !== false)
const activeItems = computed(() => {
  const items = activeTab.value.source === 'images' ? (Array.isArray(activeTab.value.images) ? activeTab.value.images.slice(0, 6) : []) : products.value.slice(0, 6)
  if (items.length) return items
  return Array.from({ length: 3 }, (_, index) => ({ id: `placeholder-${index}`, placeholder: true, title: `Featured item ${index + 1}`, subtitle: 'Add content in the builder', image: '', url: '#' }))
})
const isCarousel = computed(() => activeItems.value.length > 3)
const visibleItems = computed(() => isCarousel.value ? activeItems.value.slice(carouselIndex.value, carouselIndex.value + 3) : activeItems.value)
const canScrollPrev = computed(() => carouselIndex.value > 0)
const canScrollNext = computed(() => carouselIndex.value < activeItems.value.length - 3)
const sectionStyle = computed(() => ({ backgroundColor: props.settings?.backgroundColor || '#FFFFFF', padding: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 56}px ${getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24}px`, marginTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`, marginBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`, '--dsf-tabbed-showcase-title-color': props.settings?.titleColor || '#111111', '--dsf-tabbed-showcase-accent': props.settings?.accentColor || '#2C7FA3', '--dsf-tabbed-showcase-tab-text-color': props.settings?.tabTextColor || '#64748B', '--dsf-tabbed-showcase-active-tab-text-color': props.settings?.activeTabTextColor || '#111111', '--dsf-tabbed-showcase-modern-tabs-background': props.settings?.modernTabsBackgroundColor || '#F3F4F6', '--dsf-tabbed-showcase-modern-active-tab-background': props.settings?.modernActiveTabBackgroundColor || '#FFFFFF', '--dsf-tabbed-showcase-primary-button-background': props.settings?.primaryButtonColor || 'var(--dsf-theme-primary, #2C5F5D)', '--dsf-tabbed-showcase-primary-button-text': props.settings?.primaryButtonTextColor || '#FFFFFF', '--dsf-tabbed-showcase-secondary-button-background': props.settings?.secondaryButtonColor || 'var(--dsf-theme-primary, #2C5F5D)', '--dsf-tabbed-showcase-secondary-button-text': props.settings?.secondaryButtonTextColor || '#FFFFFF' }))
const uid = computed(() => String(props.blockId || 'tabbed-showcase'))
function tabId(index) { return `dsf-tabbed-showcase-tab-${uid.value}-${index}` }
function panelId(index) { return `dsf-tabbed-showcase-panel-${uid.value}-${index}` }
function itemHref(item) { return safePublicUrl(item?.url || item?.permalink, '#') }
function safeImageUrl(url) { return safePublicUrl(url, '') }
function safeActionUrl(url) { return safePublicUrl(url, '#') }
function handleLinkClick(event) { if (props.isEditor || event.currentTarget.getAttribute('href') === '#') event.preventDefault() }
function scrollPrev() { carouselIndex.value = Math.max(0, carouselIndex.value - 1) }
function scrollNext() { carouselIndex.value = Math.min(activeItems.value.length - 3, carouselIndex.value + 1) }
function onTabKeydown(event, index) { const count = tabs.value.length; let next = null; if (event.key === 'ArrowRight') next = (index + 1) % count; else if (event.key === 'ArrowLeft') next = (index - 1 + count) % count; else if (event.key === 'Home') next = 0; else if (event.key === 'End') next = count - 1; if (next !== null) { event.preventDefault(); activeIndex.value = next } }
function normalizeIds(value) { return [...new Set((Array.isArray(value) ? value : []).map((id) => Number.parseInt(id, 10)).filter((id) => Number.isFinite(id) && id > 0))] }
async function fetchProducts() {
  const ids = normalizeIds(activeTab.value.productIds)
  products.value = []
  if (activeTab.value.source !== 'products' || !ids.length || !wpData.isWooActive || !wpData.ajaxUrl || !wpData.nonce) return
  const formData = new FormData(); formData.append('action', 'dsf_get_products'); formData.append('nonce', wpData.nonce); formData.append('product_ids', JSON.stringify(ids))
  try { const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData }); const result = await response.json(); if (result.success && Array.isArray(result.data?.products)) { products.value = ids.map((id) => result.data.products.find((product) => Number(product.id) === id)).filter(Boolean).map((product) => ({ ...product, image: safePublicUrl(product.image, ''), permalink: safePublicUrl(product.permalink, '#'), meta: product.price || '' })) } } catch (error) { console.error('Tabbed showcase product loading failed', error) }
}
watch(() => activeIndex.value, () => { carouselIndex.value = 0; fetchProducts() })
watch(() => tabs.value.map((tab) => `${tab.source}:${normalizeIds(tab.productIds).join(',')}`), () => { if (activeIndex.value >= tabs.value.length) activeIndex.value = 0; carouselIndex.value = 0; fetchProducts() }, { immediate: true })
</script>

<style scoped>
.dsf-tabbed-showcase { width: 100%; box-sizing: border-box; container-type: inline-size; }
.dsf-tabbed-showcase__inner { max-width: 1500px; margin: 0 auto; text-align: center; }
.dsf-tabbed-showcase__header { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.5rem 3rem; align-items: end; max-width: 1050px; margin: 0 auto 4rem; text-align: left; }
.dsf-tabbed-showcase__title { margin: 0; color: var(--dsf-tabbed-showcase-title-color); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2rem, 3.5vw, 3rem); font-weight: 700; line-height: 1.08; }
.dsf-tabbed-showcase__description { margin: 0; color: #64748b; font-size: clamp(1rem, 1.8vw, 1.45rem); line-height: 1.35; }
.dsf-tabbed-showcase__tabs { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1.4rem; margin-bottom: 4rem; }
.dsf-tabbed-showcase__tab { padding: 0; border: 0; background: transparent; color: var(--dsf-tabbed-showcase-tab-text-color); font: inherit; font-weight: 700; cursor: pointer; }
.dsf-tabbed-showcase__tab.is-active { color: var(--dsf-tabbed-showcase-active-tab-text-color); }
.dsf-tabbed-showcase__tab.is-active::after { display: block; width: 0; height: 0; margin: 0.7rem auto -1.7rem; border-right: 10px solid transparent; border-left: 10px solid transparent; border-top: 14px solid var(--dsf-tabbed-showcase-accent); content: ''; }
.dsf-tabbed-showcase__separator { color: var(--dsf-tabbed-showcase-tab-text-color); font-weight: 700; }
.dsf-tabbed-showcase__carousel { display: flex; align-items: center; gap: 1rem; }
.dsf-tabbed-showcase__cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: clamp(1rem, 4vw, 4rem); flex: 1; min-width: 0; }
.dsf-tabbed-showcase__nav { display: flex; flex: 0 0 auto; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border: 0; border-radius: 999px; background: var(--dsf-tabbed-showcase-theme-primary); color: #fff; font-size: 2rem; line-height: 1; cursor: pointer; }
.dsf-tabbed-showcase__nav:disabled { opacity: 0.35; cursor: not-allowed; }
.dsf-tabbed-showcase__card-link { display: block; color: inherit; text-decoration: none; }
.dsf-tabbed-showcase__image-wrap { display: flex; align-items: center; justify-content: center; aspect-ratio: 1.45 / 1; overflow: hidden; background: #fafafa; }
.dsf-tabbed-showcase__image { width: 100%; height: 100%; object-fit: contain; mix-blend-mode: multiply; transition: transform 0.25s ease; }
.dsf-tabbed-showcase--image .dsf-tabbed-showcase__image-wrap { background: transparent; }
.dsf-tabbed-showcase--image .dsf-tabbed-showcase__image { object-fit: cover; mix-blend-mode: normal; }
.dsf-tabbed-showcase__card-link:hover .dsf-tabbed-showcase__image { transform: scale(1.03); }
.dsf-tabbed-showcase__placeholder { width: 70%; height: 60%; background: #f0f1f2; }
.dsf-tabbed-showcase__card-title { margin: 1rem 0 0.55rem; color: var(--dsf-tabbed-showcase-title-color); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(1rem, 1.3vw, 1.35rem); font-weight: 700; line-height: 1.2; }
.dsf-tabbed-showcase__card-subtitle { margin: 0; color: var(--dsf-tabbed-showcase-title-color); font-size: clamp(0.95rem, 1.2vw, 1.15rem); line-height: 1.35; }
.dsf-tabbed-showcase__card-secondary-subtitle { margin: 0.35rem 0 0; color: #64748b; font-size: 0.9rem; line-height: 1.35; }
.dsf-tabbed-showcase__actions { display: flex; justify-content: center; gap: 1.25rem; margin-top: 3rem; }
.dsf-tabbed-showcase__button { min-width: 280px; padding: 0.85rem 1.5rem; border-radius: 0.75rem; font-weight: 700; text-decoration: none; }
.dsf-tabbed-showcase__button--primary { background: var(--dsf-tabbed-showcase-primary-button-background); color: var(--dsf-tabbed-showcase-primary-button-text); }
.dsf-tabbed-showcase__button--secondary { background: var(--dsf-tabbed-showcase-secondary-button-background); color: var(--dsf-tabbed-showcase-secondary-button-text); }
.dsf-tabbed-showcase__button:hover { filter: brightness(0.95); }
.dsf-tabbed-showcase__button :deep(.dsf-inline-text) { display: block; }
.dsf-tabbed-showcase__tab :deep(.dsf-inline-text), .dsf-tabbed-showcase__button :deep(.dsf-inline-text) { min-width: 1rem; }
.dsf-tabbed-showcase__tab:focus-visible, .dsf-tabbed-showcase__button:focus-visible, .dsf-tabbed-showcase__card-link:focus-visible { outline: 3px solid currentColor; outline-offset: 4px; }
.dsf-tabbed-showcase--products .dsf-tabbed-showcase__tabs { margin-bottom: 2.5rem; }
.dsf-tabbed-showcase--products .dsf-tabbed-showcase__card { padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #fff; text-align: left; }
.dsf-tabbed-showcase--products .dsf-tabbed-showcase__image-wrap { aspect-ratio: 1 / 1; background: #f8fafc; }
.dsf-tabbed-showcase--products .dsf-tabbed-showcase__card-title { font-size: 1rem; }
.dsf-tabbed-showcase--products .dsf-tabbed-showcase__card-subtitle { color: #64748b; font-size: 0.95rem; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__tabs { justify-content: flex-start; gap: 0; margin-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__tab { padding: 0.75rem 1.25rem; color: var(--dsf-tabbed-showcase-tab-text-color); font-weight: 600; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__tab.is-active { color: var(--dsf-tabbed-showcase-active-tab-text-color); box-shadow: inset 0 -3px 0 var(--dsf-tabbed-showcase-accent); }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__tab.is-active::after, .dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__separator { display: none; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__panel { padding: 1.5rem; border: 1px solid #e5e7eb; border-top: 0; text-align: left; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__cards { gap: 1.25rem; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__card { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 1rem; align-items: center; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__image-wrap { aspect-ratio: 1 / 1; }
.dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__card-title { margin-top: 0; }
.dsf-tabbed-showcase--tab-style-modern .dsf-tabbed-showcase__tabs { gap: 0.5rem; margin-bottom: 3rem; padding: 0.35rem; border-radius: 999px; background: var(--dsf-tabbed-showcase-modern-tabs-background); }
.dsf-tabbed-showcase--tab-style-modern .dsf-tabbed-showcase__tab { padding: 0.7rem 1.2rem; border-radius: 999px; color: var(--dsf-tabbed-showcase-tab-text-color); font-size: 0.95rem; transition: color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; }
.dsf-tabbed-showcase--tab-style-modern .dsf-tabbed-showcase__tab.is-active { background: var(--dsf-tabbed-showcase-modern-active-tab-background); color: var(--dsf-tabbed-showcase-active-tab-text-color); box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12); }
.dsf-tabbed-showcase--tab-style-modern .dsf-tabbed-showcase__tab.is-active::after, .dsf-tabbed-showcase--tab-style-modern .dsf-tabbed-showcase__separator { display: none; }
.dsf-tabbed-showcase--tab-style-underline .dsf-tabbed-showcase__tabs { gap: 0.25rem; margin-bottom: 2.5rem; border-bottom: 1px solid #e5e7eb; }
.dsf-tabbed-showcase--tab-style-underline .dsf-tabbed-showcase__tab { padding: 0.8rem 1.25rem 0.95rem; color: var(--dsf-tabbed-showcase-tab-text-color); font-weight: 600; }
.dsf-tabbed-showcase--tab-style-underline .dsf-tabbed-showcase__tab.is-active { color: var(--dsf-tabbed-showcase-active-tab-text-color); box-shadow: inset 0 -3px 0 var(--dsf-tabbed-showcase-accent); }
.dsf-tabbed-showcase--tab-style-underline .dsf-tabbed-showcase__tab.is-active::after, .dsf-tabbed-showcase--tab-style-underline .dsf-tabbed-showcase__separator { display: none; }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__tabs { gap: 1.5rem; margin-bottom: 3rem; }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__tab { position: relative; padding: 0.7rem 0.25rem 1rem; color: var(--dsf-tabbed-showcase-tab-text-color); font-weight: 600; }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__tab.is-active { color: var(--dsf-tabbed-showcase-active-tab-text-color); }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__tab.is-active::after { display: none; }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__chevron { display: block; width: 1rem; height: 1rem; margin: 0.55rem auto -1.25rem; color: var(--dsf-tabbed-showcase-accent); }
.dsf-tabbed-showcase--tab-style-chevron .dsf-tabbed-showcase__separator { color: #cbd5e1; }
@container (max-width: 760px) { .dsf-tabbed-showcase__header { grid-template-columns: 1fr; margin-bottom: 2.5rem; } .dsf-tabbed-showcase__tabs { margin-bottom: 2.5rem; gap: 0.8rem 1rem; } .dsf-tabbed-showcase__cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dsf-tabbed-showcase__actions { flex-direction: column; align-items: center; } }
@container (max-width: 480px) { .dsf-tabbed-showcase__cards { grid-template-columns: 1fr; } .dsf-tabbed-showcase__button { width: 100%; min-width: 0; } }
@container (max-width: 760px) { .dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__cards { grid-template-columns: 1fr; } }
@container (max-width: 480px) { .dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__card { grid-template-columns: 96px minmax(0, 1fr); } .dsf-tabbed-showcase--tabs .dsf-tabbed-showcase__panel { padding: 1rem; } }
</style>
