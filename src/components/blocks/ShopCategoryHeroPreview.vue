<template>
  <section class="dsf-shop-category-hero" :class="{ 'is-center': settings.alignment === 'center', 'has-image': settings.showImage !== false && category?.image }" :style="blockStyle">
    <img v-if="settings.showImage !== false && category?.image" class="dsf-shop-category-hero__image" :src="category.image" alt="" />
    <div class="dsf-shop-category-hero__shade"></div>
    <div class="dsf-shop-category-hero__inner" :style="innerStyle">
      <a v-if="settings.showParentLink !== false && category?.parentUrl" :href="category.parentUrl" @click="isEditor && $event.preventDefault()">← {{ category.parentName }}</a>
      <p v-else class="dsf-shop-category-hero__eyebrow">Product category</p>
      <h1>{{ archive.title || 'Shop' }}</h1>
      <div v-if="settings.showDescription !== false && archive.descriptionHtml" class="dsf-shop-category-hero__description" v-html="archive.descriptionHtml"></div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useShopContext } from '../../utils/useShopContext'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const { archive } = useShopContext()
const category = computed(() => archive.value?.currentCategory || null)
const blockStyle = computed(() => ({ '--dsf-category-overlay': props.settings?.overlayColor || 'rgba(15, 23, 42, .5)', color: props.settings?.textColor || '#fff', paddingTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 56}px`, paddingBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 56}px` }))
const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1280}px` }))
</script>

<style scoped>
.dsf-shop-category-hero { position: relative; overflow: hidden; isolation: isolate; background: #172033; }.dsf-shop-category-hero__image,.dsf-shop-category-hero__shade { position: absolute; inset: 0; width: 100%; height: 100%; }.dsf-shop-category-hero__image { z-index: -2; object-fit: cover; }.dsf-shop-category-hero__shade { z-index: -1; background: var(--dsf-category-overlay); }.dsf-shop-category-hero__inner { position: relative; margin: 0 auto; }.dsf-shop-category-hero.is-center .dsf-shop-category-hero__inner { text-align: center; }.dsf-shop-category-hero a,.dsf-shop-category-hero__eyebrow { color: inherit; font: 800 .75rem/1.3 var(--dsf-theme-body-font, sans-serif); letter-spacing: .1em; text-decoration: none; text-transform: uppercase; }.dsf-shop-category-hero h1 { max-width: 13ch; margin: .7rem 0; font: 800 clamp(2.6rem, 6vw, 5.2rem)/.94 var(--dsf-theme-heading-font, sans-serif); letter-spacing: -.06em; }.dsf-shop-category-hero.is-center h1 { margin-inline: auto; }.dsf-shop-category-hero__description { max-width: 58ch; line-height: 1.65; }.dsf-shop-category-hero.is-center .dsf-shop-category-hero__description { margin-inline: auto; }
</style>
