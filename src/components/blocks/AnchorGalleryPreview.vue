<template>
  <section class="dsf-anchor-gallery" :class="[`dsf-anchor-gallery--${layout}`, `dsf-anchor-gallery--align-${textAlign}`, `dsf-anchor-gallery--titles-${titlePosition}`, `dsf-anchor-gallery--count-${displayItems.length}`]" :style="sectionStyle">
    <header v-if="(showEyebrow && (isEditor || settings.eyebrow)) || settings.title || settings.description" class="dsf-anchor-gallery__header">
      <div class="dsf-anchor-gallery__header-copy">
        <InlineText v-if="showEyebrow && (isEditor || settings.eyebrow)" tagName="p" class="dsf-anchor-gallery__eyebrow" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" />
        <InlineText v-if="isEditor || settings.title" tagName="h2" class="dsf-anchor-gallery__title" v-model="settings.title" :is-editor="isEditor" placeholder="Explore our featured collections" />
      </div>
      <InlineText v-if="isEditor || settings.description" tagName="p" class="dsf-anchor-gallery__description" v-model="settings.description" :is-editor="isEditor" :multiline="true" placeholder="Discover products and experiences selected for your space." />
    </header>

    <div class="dsf-anchor-gallery__grid">
      <a
        v-for="(item, index) in displayItems"
        :key="index"
        class="dsf-anchor-gallery__tile"
        :class="{ 'dsf-anchor-gallery__tile--overlay': titlePosition === 'overlay', 'dsf-anchor-gallery__tile--empty': !hasMedia(item) }"
        :href="itemHref(item)"
        @click="handleTileClick($event, item)">
        <div class="dsf-anchor-gallery__media">
          <MediaVisual :mode="item.mediaType || 'image'" :image="item.image" :video="item.video" :alt="item.title || ''">
            <div class="dsf-anchor-gallery__placeholder" aria-hidden="true"></div>
          </MediaVisual>
        </div>
        <InlineText tagName="span" class="dsf-anchor-gallery__tile-title" :style="{ textAlign }" v-model="item.title" :is-editor="isEditor" :placeholder="`Tile ${index + 1}`" />
      </a>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'
import MediaVisual from '../common/MediaVisual.vue'
import { safePublicUrl } from '../../utils/safeUrl'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  previewMode: { type: String, default: 'desktop' },
})

const layout = computed(() => props.settings?.layout === 'grid' ? 'grid' : 'anchor')
const maxItems = computed(() => layout.value === 'grid' ? 8 : 5)
const showEyebrow = computed(() => props.settings?.showEyebrow !== false)
const titlePosition = computed(() => props.settings?.titlePosition === 'overlay' ? 'overlay' : 'below')
const textAlign = computed(() => ['left', 'center', 'right'].includes(props.settings?.textAlign) ? props.settings.textAlign : 'center')
const displayItems = computed(() => {
  const items = Array.isArray(props.settings?.items) ? props.settings.items : []
  return items.slice(0, maxItems.value).filter((item) => item && typeof item === 'object')
})

const sectionStyle = computed(() => ({
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  padding: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 40}px ${getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24}px`,
  marginTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`,
  marginBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`,
  '--dsf-anchor-gallery-gap': `${Math.max(0, Math.min(40, Number(props.settings?.gap) || 16))}px`,
  '--dsf-anchor-gallery-columns': Math.min(4, Math.max(1, displayItems.value.length || 1)),
  '--dsf-anchor-gallery-title-color': props.settings?.titleColor || '#111827',
  '--dsf-anchor-gallery-description-color': props.settings?.descriptionColor || '#6B7280',
}))

function itemHref(item) {
  return safePublicUrl(item?.url, '#')
}

function hasMedia(item) {
  return Boolean(safePublicUrl(item?.image, '') || safePublicUrl(item?.video, ''))
}

function handleTileClick(event, item) {
  if (props.isEditor || itemHref(item) === '#') event.preventDefault()
}
</script>

<style scoped>
.dsf-anchor-gallery { width: 100%; box-sizing: border-box; container-type: inline-size; }
.dsf-anchor-gallery__header { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.5rem 3rem; align-items: end; max-width: 1050px; margin: 0 auto 4rem; }
.dsf-anchor-gallery__eyebrow { margin: 0 0 10px; color: var(--dsf-anchor-gallery-description-color); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.dsf-anchor-gallery__title { margin: 0; color: var(--dsf-anchor-gallery-title-color); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2rem, 3.2vw, 3rem); font-weight: 600; line-height: 1.1; }
.dsf-anchor-gallery__description { margin: 0; color: var(--dsf-anchor-gallery-description-color); font-family: var(--dsf-theme-body-font, inherit); font-size: clamp(1rem, 1.8vw, 1.45rem); line-height: 1.35; }
.dsf-anchor-gallery__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--dsf-anchor-gallery-gap); max-width: 1400px; margin: 0 auto; }
.dsf-anchor-gallery--grid .dsf-anchor-gallery__grid { grid-template-columns: repeat(var(--dsf-anchor-gallery-columns), minmax(0, 1fr)); }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-5 .dsf-anchor-gallery__grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-5 .dsf-anchor-gallery__tile:nth-child(-n+3) { grid-column: span 2; }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-5 .dsf-anchor-gallery__tile:nth-child(n+4) { grid-column: span 3; }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-6 .dsf-anchor-gallery__grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-6 .dsf-anchor-gallery__tile:nth-child(-n+4) { grid-column: span 1; }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-6 .dsf-anchor-gallery__tile:nth-child(n+5) { grid-column: span 2; }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__tile:nth-child(-n+4) { grid-column: span 3; }
.dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__tile:nth-child(n+5) { grid-column: span 4; }
.dsf-anchor-gallery--anchor .dsf-anchor-gallery__grid { grid-template-columns: minmax(0, 1.55fr) repeat(2, minmax(0, 0.75fr)); grid-template-rows: repeat(2, minmax(180px, 1fr)); }
.dsf-anchor-gallery--anchor .dsf-anchor-gallery__tile:first-child { grid-row: span 2; }
.dsf-anchor-gallery__tile { position: relative; display: block; min-width: 0; overflow: hidden; color: inherit; text-decoration: none; }
.dsf-anchor-gallery__media { position: relative; aspect-ratio: 1.8 / 1; overflow: hidden; background: #E5E7EB; }
.dsf-anchor-gallery--anchor .dsf-anchor-gallery__tile:first-child .dsf-anchor-gallery__media { height: 100%; aspect-ratio: auto; }
.dsf-anchor-gallery__media :deep(.dsf-media-visual__el) { transition: transform 0.35s ease; }
.dsf-anchor-gallery__tile:hover .dsf-anchor-gallery__media :deep(.dsf-media-visual__el) { transform: scale(1.035); }
.dsf-anchor-gallery__placeholder { width: 100%; height: 100%; min-height: 180px; background: #E5E7EB; }
.dsf-anchor-gallery__tile-title { display: block; width: 100%; box-sizing: border-box; padding: 12px 4px 0; color: var(--dsf-anchor-gallery-title-color); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(1rem, 1.35vw, 1.35rem); font-weight: 600; line-height: 1.2; text-align: center; }
.dsf-anchor-gallery--anchor.dsf-anchor-gallery--titles-below .dsf-anchor-gallery__tile:first-child .dsf-anchor-gallery__media { height: auto; aspect-ratio: 1.55 / 1; }
.dsf-anchor-gallery__tile--overlay .dsf-anchor-gallery__media { aspect-ratio: auto; height: 100%; min-height: 220px; }
.dsf-anchor-gallery__tile--overlay .dsf-anchor-gallery__tile-title { position: absolute; right: 0; bottom: 0; left: 0; z-index: 1; padding: 44px 18px 16px; color: #FFFFFF; text-align: left; text-shadow: 0 1px 3px rgba(0,0,0,0.35); background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.72) 100%); }
.dsf-anchor-gallery__tile--overlay .dsf-anchor-gallery__media::after { position: absolute; inset: 0; pointer-events: none; content: ''; background: linear-gradient(to bottom, transparent 48%, rgba(0,0,0,0.52) 100%); }
.dsf-anchor-gallery--align-left .dsf-anchor-gallery__tile-title { text-align: left; }
.dsf-anchor-gallery--align-center .dsf-anchor-gallery__tile-title { text-align: center; }
.dsf-anchor-gallery--align-right .dsf-anchor-gallery__tile-title { text-align: right; }
.dsf-anchor-gallery__tile:focus-visible { outline: 3px solid currentColor; outline-offset: 3px; }

@container (max-width: 760px) {
  .dsf-anchor-gallery__header { grid-template-columns: 1fr; margin-bottom: 42px; }
  .dsf-anchor-gallery--grid .dsf-anchor-gallery__grid { grid-template-columns: repeat(min(2, var(--dsf-anchor-gallery-columns)), minmax(0, 1fr)); }
  .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-5 .dsf-anchor-gallery__grid, .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-6 .dsf-anchor-gallery__grid, .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-5 .dsf-anchor-gallery__tile, .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-6 .dsf-anchor-gallery__tile, .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__tile { grid-column: span 1; }
  .dsf-anchor-gallery--anchor .dsf-anchor-gallery__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: auto; }
  .dsf-anchor-gallery--anchor .dsf-anchor-gallery__tile:first-child { grid-row: auto; grid-column: 1 / -1; }
  .dsf-anchor-gallery--anchor .dsf-anchor-gallery__tile:first-child .dsf-anchor-gallery__media { aspect-ratio: 1.55 / 1; }
}

@container (max-width: 480px) {
  .dsf-anchor-gallery__grid, .dsf-anchor-gallery--anchor .dsf-anchor-gallery__grid { grid-template-columns: 1fr; }
  .dsf-anchor-gallery--grid .dsf-anchor-gallery__grid { grid-template-columns: 1fr; }
  .dsf-anchor-gallery--grid.dsf-anchor-gallery--count-7 .dsf-anchor-gallery__tile { grid-column: span 1; }
  .dsf-anchor-gallery__tile--overlay .dsf-anchor-gallery__media, .dsf-anchor-gallery--anchor .dsf-anchor-gallery__tile:first-child .dsf-anchor-gallery__media { min-height: 180px; aspect-ratio: 1.45 / 1; }
}
</style>
