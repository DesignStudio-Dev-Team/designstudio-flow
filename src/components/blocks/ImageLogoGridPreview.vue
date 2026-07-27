<template>
  <section class="dsf-image-logo-grid" :class="`dsf-image-logo-grid--count-${items.length}`" :style="sectionStyle">
    <div class="dsf-image-logo-grid__inner">
      <div class="dsf-image-logo-grid__intro">
        <InlineText v-if="isEditor || settings.title" v-model="settings.title" tagName="h2" class="dsf-image-logo-grid__title" :is-editor="isEditor" placeholder="The hottest brands" />
        <InlineText v-if="isEditor || settings.description" v-model="settings.description" tagName="p" class="dsf-image-logo-grid__description" :is-editor="isEditor" :multiline="true" placeholder="Add a description" />
      </div>

      <div class="dsf-image-logo-grid__cards">
        <a v-for="(item, index) in items" :key="index" :href="safeUrl(item.url)" class="dsf-image-logo-grid__card" @click="handleLinkClick">
          <div class="dsf-image-logo-grid__image-wrap">
            <img v-if="safeUrl(item.image, '')" :src="safeUrl(item.image, '')" alt="" class="dsf-image-logo-grid__image" />
            <div v-else class="dsf-image-logo-grid__placeholder" aria-hidden="true"></div>
          </div>
          <div class="dsf-image-logo-grid__logo-wrap">
            <img v-if="safeUrl(item.logo, '')" :src="safeUrl(item.logo, '')" alt="" class="dsf-image-logo-grid__logo" />
            <span v-else class="dsf-image-logo-grid__logo-placeholder">Add logo</span>
          </div>
        </a>
        <div v-if="!items.length && isEditor" class="dsf-image-logo-grid__empty">Add up to 8 image and logo cards.</div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const items = computed(() => (Array.isArray(props.settings?.items) ? props.settings.items : []).slice(0, 8).filter((item) => item && typeof item === 'object'))
const sectionStyle = computed(() => ({ backgroundColor: props.settings?.backgroundColor || '#FFFFFF', padding: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 48}px ${getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24}px`, marginTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`, marginBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 25}px`, '--dsf-image-logo-title-color': props.settings?.titleColor || '#111111', '--dsf-image-logo-body-color': props.settings?.bodyColor || '#111111', '--dsf-image-logo-columns': String(Math.min(4, Math.max(1, items.value.length || 1))) }))
function safeUrl(url, fallback = '#') { return safePublicUrl(url, fallback) }
function handleLinkClick(event) { if (props.isEditor || event.currentTarget.getAttribute('href') === '#') event.preventDefault() }
</script>

<style scoped>
.dsf-image-logo-grid { width: 100%; box-sizing: border-box; container-type: inline-size; }
.dsf-image-logo-grid__inner { max-width: 1500px; margin: 0 auto; }
.dsf-image-logo-grid__intro { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.5rem 3rem; align-items: end; margin: 0 auto 4rem; max-width: 1050px; }
.dsf-image-logo-grid__title { margin: 0; color: var(--dsf-image-logo-title-color); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2rem, 3.5vw, 3rem); font-weight: 700; line-height: 1.08; }
.dsf-image-logo-grid__description { margin: 0; color: var(--dsf-image-logo-body-color); font-size: clamp(1rem, 1.8vw, 1.45rem); line-height: 1.35; }
.dsf-image-logo-grid__cards { display: grid; grid-template-columns: repeat(var(--dsf-image-logo-columns), minmax(0, 1fr)); gap: 3rem 1.5rem; }
.dsf-image-logo-grid--count-5 .dsf-image-logo-grid__cards { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.dsf-image-logo-grid--count-5 .dsf-image-logo-grid__card:nth-child(-n+3) { grid-column: span 2; }
.dsf-image-logo-grid--count-5 .dsf-image-logo-grid__card:nth-child(n+4) { grid-column: span 3; }
.dsf-image-logo-grid--count-6 .dsf-image-logo-grid__cards { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.dsf-image-logo-grid--count-6 .dsf-image-logo-grid__card:nth-child(-n+4) { grid-column: span 1; }
.dsf-image-logo-grid--count-6 .dsf-image-logo-grid__card:nth-child(n+5) { grid-column: span 2; }
.dsf-image-logo-grid--count-7 .dsf-image-logo-grid__cards { grid-template-columns: repeat(12, minmax(0, 1fr)); }
.dsf-image-logo-grid--count-7 .dsf-image-logo-grid__card:nth-child(-n+4) { grid-column: span 3; }
.dsf-image-logo-grid--count-7 .dsf-image-logo-grid__card:nth-child(n+5) { grid-column: span 4; }
.dsf-image-logo-grid__card { position: relative; display: block; min-width: 0; color: inherit; text-decoration: none; }
.dsf-image-logo-grid__image-wrap { aspect-ratio: 1.5 / 1; overflow: hidden; background: #e5e7eb; }
.dsf-image-logo-grid__image { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform 0.25s ease; }
.dsf-image-logo-grid__card:hover .dsf-image-logo-grid__image { transform: scale(1.03); }
.dsf-image-logo-grid__placeholder { width: 100%; height: 100%; background: #e5e7eb; }
.dsf-image-logo-grid__logo-wrap { display: flex; align-items: center; justify-content: center; min-height: 110px; margin: -2rem 2.5rem 0; padding: 1rem 1.5rem; border: 2px solid #d1d5db; border-radius: 1rem; background: #fff; position: relative; z-index: 1; }
.dsf-image-logo-grid__logo { display: block; max-width: 100%; max-height: 72px; object-fit: contain; }
.dsf-image-logo-grid__logo-placeholder { color: #9ca3af; font-size: 0.9rem; }
.dsf-image-logo-grid__empty { grid-column: 1 / -1; padding: 3rem; border: 2px dashed #d1d5db; color: #6b7280; text-align: center; }
.dsf-image-logo-grid__card:focus-visible { outline: 3px solid currentColor; outline-offset: 4px; }
@container (max-width: 900px) { .dsf-image-logo-grid__cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } .dsf-image-logo-grid--count-5 .dsf-image-logo-grid__cards, .dsf-image-logo-grid--count-6 .dsf-image-logo-grid__cards, .dsf-image-logo-grid--count-7 .dsf-image-logo-grid__cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } .dsf-image-logo-grid--count-5 .dsf-image-logo-grid__card, .dsf-image-logo-grid--count-6 .dsf-image-logo-grid__card, .dsf-image-logo-grid--count-7 .dsf-image-logo-grid__card { grid-column: span 1; } }
@container (max-width: 680px) { .dsf-image-logo-grid__intro { grid-template-columns: 1fr; margin-bottom: 2.5rem; } .dsf-image-logo-grid__cards { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2.5rem 1rem; } .dsf-image-logo-grid--count-5 .dsf-image-logo-grid__cards, .dsf-image-logo-grid--count-6 .dsf-image-logo-grid__cards, .dsf-image-logo-grid--count-7 .dsf-image-logo-grid__cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dsf-image-logo-grid__logo-wrap { margin-right: 1rem; margin-left: 1rem; min-height: 82px; padding: 0.75rem; } }
@container (max-width: 420px) { .dsf-image-logo-grid__cards { grid-template-columns: 1fr; } .dsf-image-logo-grid--count-7 .dsf-image-logo-grid__card { grid-column: span 1; } }
</style>
