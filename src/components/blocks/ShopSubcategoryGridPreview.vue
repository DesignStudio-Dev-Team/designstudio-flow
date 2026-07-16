<template>
  <section class="dsf-shop-subcategory-grid" :style="blockStyle"><div class="dsf-shop-subcategory-grid__inner" :style="innerStyle">
    <div v-if="items.length" class="dsf-shop-subcategory-grid__grid" :style="gridStyle">
      <a v-for="item in items" :key="item.id" :href="item.url || '#'" class="dsf-shop-subcategory-grid__card" @click="isEditor && $event.preventDefault()">
        <div class="dsf-shop-subcategory-grid__image" :class="`is-${settings.imageAspect || 'landscape'}`"><img v-if="item.image" :src="item.image" :alt="item.name || ''" /><span v-else></span></div>
        <div class="dsf-shop-subcategory-grid__copy"><h2>{{ item.name }}</h2><p v-if="settings.showDescription !== false && item.description">{{ item.description }}</p><small v-if="settings.showCount !== false">{{ item.count }} {{ item.count === 1 ? 'product' : 'products' }}</small></div>
      </a>
    </div><p v-else-if="isEditor" class="dsf-shop-subcategory-grid__empty">Child product categories will appear here when this template is used on a category that has them.</p>
  </div></section>
</template>
<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useShopContext } from '../../utils/useShopContext'
const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const { archive } = useShopContext()
const items = computed(() => Array.isArray(archive.value?.subcategories) ? archive.value.subcategories.slice(0, 24) : [])
const blockStyle = computed(() => ({ '--dsf-subcategory-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)', paddingTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 32}px`, paddingBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 32}px` }))
const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1200}px` }))
const gridStyle = computed(() => ({ gridTemplateColumns: `repeat(${Math.max(2, Math.min(4, Number(props.settings?.columns) || 3))}, minmax(0, 1fr))` }))
</script>
<style scoped>
.dsf-shop-subcategory-grid__inner { margin: 0 auto; }.dsf-shop-subcategory-grid__grid { display: grid; gap: 1.15rem; }.dsf-shop-subcategory-grid__card { overflow: hidden; border: 1px solid #e3e8ef; border-radius: 22px; color: inherit; background: #fff; text-decoration: none; box-shadow: 0 8px 24px rgb(15 23 42 / 6%); transition: transform .2s ease, box-shadow .2s ease; }.dsf-shop-subcategory-grid__card:hover { transform: translateY(-4px); box-shadow: 0 18px 35px rgb(15 23 42 / 12%); }.dsf-shop-subcategory-grid__image { background: #e8eef1; }.dsf-shop-subcategory-grid__image.is-square { aspect-ratio: 1; }.dsf-shop-subcategory-grid__image.is-landscape { aspect-ratio: 1.45; }.dsf-shop-subcategory-grid__image.is-portrait { aspect-ratio: .8; }.dsf-shop-subcategory-grid__image img { width: 100%; height: 100%; object-fit: cover; }.dsf-shop-subcategory-grid__copy { padding: 1.15rem; }.dsf-shop-subcategory-grid h2 { margin: 0; font: 800 1.15rem/1.2 var(--dsf-theme-heading-font, sans-serif); }.dsf-shop-subcategory-grid p { margin: .55rem 0; color: #64748b; font-size: .9rem; line-height: 1.5; }.dsf-shop-subcategory-grid small { color: var(--dsf-subcategory-accent); font-weight: 800; }.dsf-shop-subcategory-grid__empty { padding: 1rem; border-radius: 12px; background: #f1f5f9; color: #64748b; }@media(max-width:720px){.dsf-shop-subcategory-grid__grid{grid-template-columns:1fr !important}}
</style>
