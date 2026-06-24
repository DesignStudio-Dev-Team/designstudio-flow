<template>
  <section class="dsf-block-preview dsf-countdown-preview" :style="sectionStyle">
    <div
      class="dsf-countdown-preview__inner"
      :class="{ 'dsf-countdown-preview__inner--media-left': mediaPosition === 'left' }"
      :style="innerStyle"
    >
      <div class="dsf-countdown-preview__content" :style="{ color: settings.textColor || '#111827' }">
        <InlineText
          tagName="p"
          class="dsf-countdown-preview__eyebrow"
          :style="{ color: settings.accentColor || '#B42318' }"
          v-model="settings.eyebrow"
          :is-editor="isEditor"
          placeholder="Default eyebrow text"
        />
        <InlineText
          tagName="h2"
          class="dsf-countdown-preview__title"
          v-model="settings.title"
          :is-editor="isEditor"
          placeholder="Default title here"
        />
        <InlineText
          tagName="p"
          class="dsf-countdown-preview__description"
          v-model="settings.description"
          :is-editor="isEditor"
          :multiline="true"
          placeholder="Default description text here."
        />

        <div class="dsf-countdown-preview__cta-row">
          <a
            v-if="settings.buttonText"
            class="dsf-countdown-preview__button"
            :href="buttonHref"
            :style="buttonStyle"
            @click="handleButtonClick"
          >
            <InlineText
              tagName="span"
              v-model="settings.buttonText"
              :is-editor="isEditor"
              placeholder="Default button text"
            />
          </a>

          <div
            v-if="isExpired"
            class="dsf-countdown-preview__expired"
            :style="{ color: settings.accentColor || '#B42318' }"
          >
            {{ settings.expiredMessage || 'Default expired message.' }}
          </div>
          <div
            v-else
            class="dsf-countdown-preview__timer"
            :style="{ color: settings.accentColor || '#B42318' }"
            aria-live="polite"
          >
            <div
              v-for="unit in countdownUnits"
              :key="unit.label"
              class="dsf-countdown-preview__unit"
            >
              <span class="dsf-countdown-preview__number">{{ unit.value }}</span>
              <span class="dsf-countdown-preview__label">{{ unit.label }}</span>
            </div>
          </div>
        </div>

        <div
          v-if="settings.noticeText"
          class="dsf-countdown-preview__notice"
          :style="noticeStyle"
        >
          <InlineText
            tagName="span"
            v-model="settings.noticeText"
            :is-editor="isEditor"
            placeholder="Default notice text"
          />
        </div>
      </div>

      <div class="dsf-countdown-preview__media">
        <template v-if="isVideoMode">
          <iframe
            v-if="videoEmbedUrl"
            :src="videoEmbedUrl"
            class="dsf-countdown-preview__media-el dsf-countdown-preview__media-el--embed"
            frameborder="0"
            allow="autoplay; fullscreen; picture-in-picture"
            allowfullscreen
          />
          <video
            v-else-if="videoFileUrl"
            class="dsf-countdown-preview__media-el"
            autoplay
            muted
            loop
            playsinline
            :poster="imageUrl"
          >
            <source :src="videoFileUrl" :type="videoFileType" />
          </video>
          <div v-else class="dsf-countdown-preview__placeholder"></div>
        </template>
        <template v-else>
          <img
            v-if="imageUrl"
            class="dsf-countdown-preview__media-el"
            :src="imageUrl"
            alt=""
          />
          <div v-else class="dsf-countdown-preview__placeholder"></div>
        </template>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useFlowModal } from '../common/useFlowModal'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const now = ref(Date.now())
let timerId = null
const { openModal } = useFlowModal()

onMounted(() => {
  timerId = window.setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onUnmounted(() => {
  if (timerId) {
    window.clearInterval(timerId)
  }
})

const mediaPosition = computed(() => props.settings?.mediaPosition || 'right')
const isVideoMode = computed(() => props.settings?.mediaType === 'video')
const buttonAction = computed(() => props.settings?.buttonAction === 'modal' ? 'modal' : 'link')
const buttonHref = computed(() => buttonAction.value === 'link' ? safePublicUrl(props.settings?.buttonUrl) : '#')

const targetTime = computed(() => {
  const raw = props.settings?.targetDate || ''
  const parsed = Date.parse(raw)
  return Number.isFinite(parsed) ? parsed : now.value
})

const remainingMs = computed(() => Math.max(0, targetTime.value - now.value))
const isExpired = computed(() => remainingMs.value <= 0)

const countdownUnits = computed(() => {
  let seconds = Math.floor(remainingMs.value / 1000)
  const days = Math.floor(seconds / 86400)
  seconds -= days * 86400
  const hours = Math.floor(seconds / 3600)
  seconds -= hours * 3600
  const minutes = Math.floor(seconds / 60)
  seconds -= minutes * 60

  return [
    { label: 'Days', value: pad(days) },
    { label: 'Hours', value: pad(hours) },
    { label: 'Minutes', value: pad(minutes) },
    { label: 'Seconds', value: pad(seconds) },
  ]
})

function pad(value) {
  return String(Math.max(0, value)).padStart(2, '0')
}

const sectionStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 64
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 40
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const innerStyle = computed(() => {
  const gap = getResponsiveValue(props.settings || {}, props.previewMode, 'gap') ?? 56
  return {
    gap: `${gap}px`,
  }
})

const buttonStyle = computed(() => ({
  backgroundColor: props.settings?.buttonColor || '#111111',
  color: props.settings?.buttonTextColor || '#FFFFFF',
}))

const noticeStyle = computed(() => ({
  backgroundColor: props.settings?.noticeColor || '#F8D7DA',
  color: props.settings?.accentColor || '#B42318',
  '--dsf-countdown-notice-bg': props.settings?.noticeColor || '#F8D7DA',
}))

function safeMediaUrl(value) {
  const url = safePublicUrl(value, '')
  return url.startsWith('/') || /^https?:\/\//i.test(url) ? url : ''
}

const imageUrl = computed(() => safeMediaUrl(props.settings?.image))
const videoUrl = computed(() => safeMediaUrl(props.settings?.video))
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

function handleButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if (buttonAction.value === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.buttonModalLayout === 'drawer' ? 'drawer' : 'center',
      contentType: ['wysiwyg', 'html', 'shortcode'].includes(props.settings?.buttonModalContentType)
        ? props.settings.buttonModalContentType
        : 'wysiwyg',
      content: getModalContent(),
    })
    return
  }
  if (buttonHref.value === '#') event.preventDefault()
}

function getModalContent() {
  const type = props.settings?.buttonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.buttonModalHtml || ''
  if (type === 'shortcode') return props.settings?.buttonModalShortcode || ''
  return props.settings?.buttonModalContent || ''
}
</script>

<style scoped>
.dsf-countdown-preview {
  width: 100%;
  container-type: inline-size;
}

.dsf-countdown-preview__inner {
  display: grid;
  grid-template-columns: minmax(0, 0.88fr) minmax(0, 1.12fr);
  align-items: center;
  max-width: 1600px;
  margin: 0 auto;
}

.dsf-countdown-preview__inner--media-left .dsf-countdown-preview__content {
  order: 2;
}

.dsf-countdown-preview__inner--media-left .dsf-countdown-preview__media {
  order: 1;
}

.dsf-countdown-preview__eyebrow {
  margin: 0 0 1.15rem;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 800;
  line-height: 1.25;
  text-transform: uppercase;
}

.dsf-countdown-preview__title {
  margin: 0 0 1.25rem;
  color: inherit;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 2rem);
  font-weight: 600;
  line-height: 1.15;
}

.dsf-countdown-preview__description {
  max-width: 680px;
  margin: 0 0 2rem;
  color: inherit;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.55;
}

.dsf-countdown-preview__cta-row {
  display: flex;
  align-items: center;
  gap: 2rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.dsf-countdown-preview__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 56px;
  padding: 0.9rem 2rem;
  border: 0;
  border-radius: 0;
  box-shadow: 0 6px 10px rgba(17, 24, 39, 0.12);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
}

.dsf-countdown-preview__timer {
  display: flex;
  align-items: flex-end;
  gap: 1.1rem;
}

.dsf-countdown-preview__unit {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 52px;
}

.dsf-countdown-preview__unit + .dsf-countdown-preview__unit::before {
  content: ':';
  position: absolute;
  left: -0.75rem;
  top: 0.05rem;
  font-size: var(--dsf-theme-h3, 1.5rem);
  font-weight: 800;
  line-height: 1;
}

.dsf-countdown-preview__number {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.5rem);
  font-weight: 800;
  line-height: 1;
}

.dsf-countdown-preview__label {
  margin-top: 0.35rem;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  line-height: 1;
}

.dsf-countdown-preview__expired {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 800;
  text-transform: uppercase;
}

.dsf-countdown-preview__notice {
  position: relative;
  width: min(100%, 680px);
  padding: 1.2rem 1.5rem;
  border-radius: 6px;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 800;
  line-height: 1.25;
  text-align: center;
  text-transform: uppercase;
}

.dsf-countdown-preview__notice::before {
  content: '';
  position: absolute;
  top: -12px;
  left: 75%;
  width: 0;
  height: 0;
  border-right: 12px solid transparent;
  border-bottom: 12px solid var(--dsf-countdown-notice-bg, #F8D7DA);
  border-left: 12px solid transparent;
}

.dsf-countdown-preview__media {
  position: relative;
  overflow: hidden;
  min-height: 420px;
  background: #e5e7eb;
}

.dsf-countdown-preview__media-el {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-countdown-preview__media-el--embed {
  width: 177.78%;
  left: 50%;
  transform: translateX(-50%);
  border: 0;
  pointer-events: none;
}

.dsf-countdown-preview__placeholder {
  width: 100%;
  height: 100%;
  min-height: 420px;
  background:
    linear-gradient(135deg, rgba(17, 24, 39, 0.1), rgba(17, 24, 39, 0)),
    linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
}

@container (max-width: 900px) {
  .dsf-countdown-preview__inner,
  .dsf-countdown-preview__inner--media-left {
    grid-template-columns: 1fr;
  }

  .dsf-countdown-preview__inner--media-left .dsf-countdown-preview__content,
  .dsf-countdown-preview__inner--media-left .dsf-countdown-preview__media {
    order: initial;
  }

  .dsf-countdown-preview__media {
    min-height: 320px;
  }
}
</style>
