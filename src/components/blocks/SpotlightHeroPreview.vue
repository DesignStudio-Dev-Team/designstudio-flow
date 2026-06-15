<template>
  <div class="dsf-block-preview dsf-spotlight-hero">
    <div class="dsf-spotlight-hero__grid" :style="gridStyle">
      <!-- Main media tile (image or video) -->
      <div class="dsf-spotlight-hero__main" :style="mainStyle">
        <!-- Video -->
        <template v-if="isVideoMode">
          <iframe
            v-if="videoEmbedUrl"
            :src="videoEmbedUrl"
            class="dsf-spotlight-hero__media dsf-spotlight-hero__media--embed"
            frameborder="0"
            allow="autoplay; fullscreen; picture-in-picture"
            allowfullscreen
          />
          <video
            v-else-if="videoFileUrl"
            class="dsf-spotlight-hero__media"
            autoplay
            muted
            loop
            playsinline
            :poster="settings.mainImage || ''"
          >
            <source :src="videoFileUrl" :type="videoFileType" />
          </video>
          <div v-else class="dsf-spotlight-hero__placeholder"></div>
        </template>

        <!-- Image -->
        <template v-else>
          <img
            v-if="settings.mainImage"
            :src="settings.mainImage"
            alt=""
            class="dsf-spotlight-hero__media"
          />
          <div v-else class="dsf-spotlight-hero__placeholder"></div>
        </template>

        <!-- Bottom gradient + content -->
        <div
          class="dsf-spotlight-hero__main-content"
          :class="`dsf-spotlight-hero__main-content--${mainContentAlign}`"
        >
          <InlineText
            v-if="isEditor || settings.mainTitle"
            v-model="settings.mainTitle"
            tagName="h2"
            class="dsf-spotlight-hero__main-title"
            :style="{ color: settings.titleColor || '#FFFFFF' }"
            :is-editor="isEditor"
            placeholder="Hero headline goes here"
          />
          <a
            v-if="showMainButton && (isEditor || settings.mainButtonText)"
            :href="mainButtonHref"
            class="dsf-spotlight-hero__btn"
            :style="buttonStyle"
            @click="handleLinkClick($event, mainButtonHref)"
          >
            <InlineText
              v-model="settings.mainButtonText"
              tagName="span"
              :is-editor="isEditor"
              placeholder="Start Here"
            />
          </a>
        </div>
      </div>

      <!-- Side column -->
      <div class="dsf-spotlight-hero__side" :style="sideStyle">
        <!-- Promo image tile -->
        <a
          :href="promoHref"
          class="dsf-spotlight-hero__promo"
          @click="handleLinkClick($event, promoHref)"
        >
          <img
            v-if="settings.promoImage"
            :src="settings.promoImage"
            alt=""
            class="dsf-spotlight-hero__media"
          />
          <div v-else class="dsf-spotlight-hero__placeholder"></div>

          <div
            v-if="showPromoCaption && (isEditor || settings.promoTitle)"
            class="dsf-spotlight-hero__promo-content"
          >
            <InlineText
              v-model="settings.promoTitle"
              tagName="span"
              class="dsf-spotlight-hero__promo-title"
              :style="{ color: settings.promoTextColor || '#FFFFFF' }"
              :is-editor="isEditor"
              placeholder="Promo caption"
            />
          </div>
        </a>

        <!-- Buttons: stacked full-width up to 3, then 2-column grid for 4+ -->
        <div
          v-if="showButtons && enabledButtons.length"
          class="dsf-spotlight-hero__buttons"
          :class="{ 'dsf-spotlight-hero__buttons--two-col': useTwoColumns }"
        >
          <a
            v-for="(button, index) in enabledButtons"
            :key="index"
            :href="buttonHref(button)"
            class="dsf-spotlight-hero__side-btn"
            :class="{ 'dsf-spotlight-hero__side-btn--full': isFullWidth(index) }"
            :style="buttonStyle"
            @click="handleLinkClick($event, buttonHref(button))"
          >
            <InlineText
              v-model="button.text"
              tagName="span"
              :is-editor="isEditor"
              placeholder="Button"
            />
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const heightValue = computed(() =>
  getResponsiveValue(props.settings || {}, props.previewMode, 'height') ?? 460
)
const gapValue = computed(() =>
  getResponsiveValue(props.settings || {}, props.previewMode, 'gap') ?? 16
)
const splitRatio = computed(() => {
  const raw = Number.parseInt(props.settings?.splitRatio, 10)
  if (!Number.isFinite(raw)) return 58
  return Math.min(70, Math.max(40, raw))
})

const isVideoMode = computed(() => props.settings?.mediaType === 'video')

const videoUrl = computed(() => (props.settings?.mainVideo || '').trim())

const videoFileUrl = computed(() => {
  const url = videoUrl.value.toLowerCase()
  return /\.(mp4|webm|ogg|ogv)(\?.*)?$/.test(url) ? videoUrl.value : ''
})

const videoFileType = computed(() => {
  const url = videoUrl.value.toLowerCase()
  if (url.includes('.webm')) return 'video/webm'
  if (url.includes('.ogg') || url.includes('.ogv')) return 'video/ogg'
  return 'video/mp4'
})

const videoEmbedUrl = computed(() => {
  const url = videoUrl.value
  if (!url || videoFileUrl.value) return ''
  if (url.includes('/embed/') || url.includes('player.vimeo.com')) return url

  const ytShort = url.match(/youtu\.be\/([^?&]+)/)
  if (ytShort) return `https://www.youtube.com/embed/${ytShort[1]}?autoplay=1&mute=1&loop=1&playlist=${ytShort[1]}&controls=0`
  const ytWatch = url.match(/[?&]v=([^&]+)/)
  if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[1]}?autoplay=1&mute=1&loop=1&playlist=${ytWatch[1]}&controls=0`
  const ytShorts = url.match(/shorts\/([^?&]+)/)
  if (ytShorts) return `https://www.youtube.com/embed/${ytShorts[1]}`

  const vimeo = url.match(/vimeo\.com\/(\d+)/)
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1&muted=1&loop=1&background=1`

  return ''
})

const gridStyle = computed(() => ({
  gap: `${gapValue.value}px`,
  minHeight: `${heightValue.value}px`,
}))
const mainStyle = computed(() => ({ flex: `${splitRatio.value} 1 0` }))
const sideStyle = computed(() => ({
  flex: `${100 - splitRatio.value} 1 0`,
  gap: `${gapValue.value}px`,
}))

const buttonStyle = computed(() => ({
  backgroundColor: props.settings?.buttonColor || '#1CA0DC',
  color: props.settings?.buttonTextColor || '#FFFFFF',
}))

function normalizeHref(raw) {
  const value = (raw || '').trim()
  return value || '#'
}

const mainButtonHref = computed(() => normalizeHref(props.settings?.mainButtonUrl))
const promoHref = computed(() => normalizeHref(props.settings?.promoUrl))

const showMainButton = computed(() => props.settings?.showMainButton !== false)
const showPromoCaption = computed(() => props.settings?.showPromoCaption !== false)

const mainContentAlign = computed(() => {
  const align = props.settings?.mainContentAlign
  return ['left', 'center', 'right'].includes(align) ? align : 'left'
})

const showButtons = computed(() => props.settings?.showButtons !== false)

const enabledButtons = computed(() => {
  const list = Array.isArray(props.settings?.sideButtons) ? props.settings.sideButtons : []
  return list.filter((button) => button && button.enabled !== false)
})

function buttonHref(button) {
  return normalizeHref(button?.url)
}

// 1–3 buttons stack full-width; 4+ switch to a 2-column grid.
const useTwoColumns = computed(() => enabledButtons.value.length >= 4)

// In the 2-column grid, an odd trailing button spans the full width.
function isFullWidth(index) {
  if (!useTwoColumns.value) return false
  const count = enabledButtons.value.length
  return count % 2 === 1 && index === count - 1
}

function handleLinkClick(event, href) {
  if (props.isEditor || !href || href === '#') {
    event.preventDefault()
  }
}
</script>

<style scoped>
.dsf-spotlight-hero {
  width: 100%;
  container-type: inline-size;
}

.dsf-spotlight-hero__grid {
  display: flex;
  align-items: stretch;
  width: 100%;
  min-height: 460px;
}

/* Main + promo shared media styling */
.dsf-spotlight-hero__main,
.dsf-spotlight-hero__promo {
  position: relative;
  overflow: hidden;
  border-radius: var(--dsf-radius-lg);
  background: #e5e7eb;
}

.dsf-spotlight-hero__main {
  display: flex;
}

.dsf-spotlight-hero__media {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border: none;
}

.dsf-spotlight-hero__placeholder {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
}

/* Main bottom gradient + content.
   Headline and button stack tightly together; the group can be aligned
   left / center / right. Horizontal padding keeps the content at least
   15px off the edge when left/right aligned. */
.dsf-spotlight-hero__main-content {
  position: relative;
  z-index: 1;
  margin-top: auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 2rem max(2rem, 15px);
  background: linear-gradient(to top, rgba(0, 0, 0, 0.75) 0%, rgba(0, 0, 0, 0.4) 55%, transparent 100%);
}

/* Alignment of the headline + button group */
.dsf-spotlight-hero__main-content--left {
  align-items: flex-start;
  text-align: left;
}
.dsf-spotlight-hero__main-content--center {
  align-items: center;
  text-align: center;
}
.dsf-spotlight-hero__main-content--right {
  align-items: flex-end;
  text-align: right;
}

.dsf-spotlight-hero__main-title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h1, 42px);
  font-weight: 700;
  line-height: 1.15;
  margin: 0;
  width: 100%;
  max-width: 100%;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* Side column */
.dsf-spotlight-hero__side {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.dsf-spotlight-hero__promo {
  display: block;
  flex: 1 1 auto;
  min-height: 0;
  text-decoration: none;
  transition: transform 0.2s, box-shadow 0.2s;
}

.dsf-spotlight-hero__promo:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.dsf-spotlight-hero__promo-content {
  position: absolute;
  inset: auto 0 0 0;
  z-index: 1;
  padding: 1.25rem;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
}

.dsf-spotlight-hero__promo-title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-text-xl, 20px);
  font-weight: 600;
  line-height: 1.25;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* Buttons: stacked full-width by default (1–3 buttons) */
.dsf-spotlight-hero__buttons {
  display: grid;
  grid-template-columns: 1fr;
  gap: inherit;
  flex-shrink: 0;
}

/* 4+ buttons switch to a 2-column grid */
.dsf-spotlight-hero__buttons--two-col {
  grid-template-columns: 1fr 1fr;
}

.dsf-spotlight-hero__side-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem 1.25rem;
  border-radius: var(--dsf-radius-lg);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-lg, 18px);
  font-weight: 600;
  text-align: center;
  text-decoration: none;
  line-height: 1.25;
  min-width: 0;
  transition: transform 0.2s, opacity 0.2s;
}

/* Odd trailing button stretches across both columns. */
.dsf-spotlight-hero__side-btn--full {
  grid-column: 1 / -1;
}

.dsf-spotlight-hero__side-btn:hover {
  transform: translateY(-1px);
  opacity: 0.92;
}

.dsf-spotlight-hero__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.875rem 2rem;
  border-radius: var(--dsf-radius-md);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-lg, 18px);
  font-weight: 600;
  text-decoration: none;
  line-height: 1.25;
  white-space: nowrap;
  flex-shrink: 0;
  transition: transform 0.2s, opacity 0.2s;
}

.dsf-spotlight-hero__btn:hover {
  transform: translateY(-1px);
  opacity: 0.92;
}

@container (max-width: 1024px) {
  .dsf-spotlight-hero__main-title {
    font-size: var(--dsf-theme-h2, 32px);
  }
}

/* Stack on narrow containers */
@container (max-width: 768px) {
  .dsf-spotlight-hero__grid {
    flex-direction: column;
    min-height: 0;
  }

  .dsf-spotlight-hero__main {
    flex: 1 1 auto !important;
    min-height: 320px;
  }

  .dsf-spotlight-hero__side {
    flex: 1 1 auto !important;
  }

  .dsf-spotlight-hero__promo {
    min-height: 200px;
  }

  .dsf-spotlight-hero__buttons {
    grid-template-columns: 1fr;
  }

  .dsf-spotlight-hero__side-btn--full {
    grid-column: auto;
  }
}
</style>
