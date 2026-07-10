<template>
  <div 
    class="dsf-block-preview dsf-hero-centered-preview"
    :class="{ 'dsf-hero-centered-preview--bottom-split': isBottomSplit }"
    :style="previewStyle"
  >
    <div
      v-if="showBottomGradient"
      class="dsf-hero-centered-preview__gradient"
      :style="gradientStyle"
    ></div>

    <div 
      class="dsf-hero-centered-preview__content"
      :style="contentStyle"
    >
      <div class="dsf-hero-centered-preview__text" :style="textStyle">
        <InlineText
          tagName="h1"
          class="dsf-hero-centered-preview__title"
          v-model="settings.title"
          :is-editor="isEditor"
          placeholder="Enter Hero Title"
        />
        <InlineText
          tagName="p"
          class="dsf-hero-centered-preview__subtitle"
          v-model="settings.subtitle"
          :is-editor="isEditor"
          placeholder="Enter subtitle description..."
          :multiline="true"
        />
      </div>
      <a
        v-if="settings.showButton !== false"
        class="dsf-hero-centered-preview__btn"
        :style="{ backgroundColor: settings.buttonColor || '#FFFFFF', color: settings.buttonTextColor || '#2563EB' }"
        :href="buttonHref"
        @click="handleButtonClick"
      >
        <InlineText 
          tagName="span"
          v-model="settings.buttonText"
          :is-editor="isEditor"
          placeholder="Shop Now"
        />
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useFlowModal } from '../common/useFlowModal'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const { openModal } = useFlowModal()

const buttonAction = computed(() => props.settings?.buttonAction || 'link')
const layoutStyle = computed(() => props.settings?.layoutStyle || 'centered')
const isBottomSplit = computed(() => layoutStyle.value === 'bottom-split')
const showBottomGradient = computed(() => props.settings?.gradientType === 'bottom-dark')
const buttonHref = computed(() =>
  buttonAction.value === 'link' ? (props.settings?.buttonUrl || '#') : '#'
)

// The Height slider (per breakpoint) drives the hero's own min-height so the
// background element grows with it and the content stays vertically centered.
const heightValue = computed(() => {
  const h = getResponsiveValue(props.settings || {}, props.previewMode, 'height')
  const n = Number(h)
  return Number.isFinite(n) && n > 0 ? n : 500
})

function getNumberSetting(key, fallback) {
  const value = Number.parseFloat(props.settings?.[key])
  return Number.isFinite(value) ? value : fallback
}

function getModalContent() {
  const type = props.settings?.buttonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.buttonModalHtml || ''
  if (type === 'shortcode') return props.settings?.buttonModalShortcode || ''
  return props.settings?.buttonModalContent || ''
}

function handleButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if (buttonAction.value === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.buttonModalLayout || 'center',
      contentType: props.settings?.buttonModalContentType || 'wysiwyg',
      content: getModalContent(),
    })
  }
}

const previewStyle = computed(() => {
  const position = props.settings?.contentPosition || 'center-center'
  const [vertical, horizontal] = position.split('-')
  
  const alignItemsMap = {
    left: 'flex-start',
    center: 'center',
    right: 'flex-end'
  }
  
  const justifyContentMap = {
    top: 'flex-start',
    center: 'center',
    bottom: 'flex-end'
  }

  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 80
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  const edgePadding = getNumberSetting('contentEdgePadding', 15)
  const bottomOffset = getNumberSetting('bottomOffset', 15)

  const horizontalPad = Math.max(paddingX, edgePadding)
  const verticalPad = Math.max(paddingY, edgePadding)
  const bottomPad = Math.max(bottomOffset, edgePadding)

  return {
    padding: isBottomSplit.value
      ? `${verticalPad}px ${horizontalPad}px ${bottomPad}px`
      : `${verticalPad}px ${horizontalPad}px`,
    backgroundColor: props.settings?.backgroundColor || '#3B82F6',
    color: props.settings?.textColor || '#FFFFFF',
    backgroundImage: props.settings?.backgroundImage 
      ? `url(${props.settings.backgroundImage})` 
      : 'none',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    display: 'flex',
    flexDirection: 'column',
    minHeight: `${heightValue.value}px`,
    alignItems: isBottomSplit.value ? 'stretch' : (alignItemsMap[horizontal] || 'center'),
    justifyContent: justifyContentMap[vertical] || 'center',
  }
})

const contentStyle = computed(() => {
  const position = props.settings?.contentPosition || 'center-center'
  const [_, horizontal] = position.split('-')
  
  const textAlignMap = {
    left: 'left',
    center: 'center',
    right: 'right'
  }

  const hasBg = props.settings?.contentBackgroundColor && props.settings.contentBackgroundColor !== 'rgba(0,0,0,0)' && props.settings.contentBackgroundColor !== 'transparent'

  if (isBottomSplit.value) {
    const justifyMap = {
      left: 'flex-start',
      center: 'center',
      right: 'flex-end',
    }
    const showBtn = props.settings?.showButton !== false

    return {
      textAlign: textAlignMap[horizontal] || 'center',
      backgroundColor: props.settings?.contentBackgroundColor || 'transparent',
      padding: hasBg ? '1.5rem' : '0',
      borderRadius: hasBg ? 'var(--dsf-radius-lg)' : '0',
      maxWidth: '100%',
      width: '100%',
      display: 'grid',
      // Columns sized to their content so the button hugs the text instead of
      // being pushed away by a fixed-width text column; the text width is
      // capped via textStyle (max-width).
      gridTemplateColumns: showBtn ? 'auto auto' : 'auto',
      justifyContent: justifyMap[horizontal] || 'center',
      justifyItems: justifyMap[horizontal] || 'center',
      gap: `${getNumberSetting('textButtonGap', 15)}px`,
      alignItems: 'center',
      '--hero-title-subtitle-gap': `${getNumberSetting('titleSubtitleGap', 12)}px`,
    }
  }

  return {
    textAlign: textAlignMap[horizontal] || 'center',
    backgroundColor: props.settings?.contentBackgroundColor || 'transparent',
    padding: hasBg ? '2rem' : '0',
    borderRadius: hasBg ? 'var(--dsf-radius-lg)' : '0',
    maxWidth: '800px',
    width: '100%',
    '--hero-title-subtitle-gap': `${getNumberSetting('titleSubtitleGap', 16)}px`,
  }
})

const gradientStyle = computed(() => ({
  height: `${getNumberSetting('gradientHeight', 75)}%`,
}))

// In bottom-split mode, cap the text block width so a long subtitle wraps
// rather than stretching the whole row and pushing the button off-screen.
const textStyle = computed(() => {
  if (!isBottomSplit.value) return {}
  return { maxWidth: `${getNumberSetting('textColumnWidth', 720)}px` }
})
</script>

<style scoped>
.dsf-hero-centered-preview {
  position: relative;
  /* alignment handled by inline styles now */
  container-type: inline-size;
  overflow: hidden;
}

.dsf-hero-centered-preview__gradient {
  position: absolute;
  inset: auto 0 0;
  z-index: 0;
  pointer-events: none;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.86) 0%, rgba(0, 0, 0, 0.62) 38%, rgba(0, 0, 0, 0) 100%);
}

.dsf-hero-centered-preview__content {
  position: relative;
  z-index: 1;
}

.dsf-hero-centered-preview__text {
  min-width: 0;
}

.dsf-hero-centered-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h1, 2.5rem);
  font-weight: 700;
  margin-top: 0;
  margin-bottom: var(--hero-title-subtitle-gap, 1rem);
  color: inherit;
  line-height: 1.15;
  word-wrap: break-word;
  overflow-wrap: break-word;
  max-width: 100%;
}

.dsf-hero-centered-preview__subtitle {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-lg, 1.125rem);
  opacity: 0.9;
  margin-top: 0;
  margin-bottom: 2rem;
  color: inherit;
  line-height: 1.5;
  word-wrap: break-word;
  overflow-wrap: break-word;
  max-width: 100%;
}

.dsf-hero-centered-preview--bottom-split .dsf-hero-centered-preview__subtitle {
  margin-bottom: 0;
}

.dsf-hero-centered-preview__btn {
  display: inline-flex;
  padding: 0.875rem 2rem;
  background: white;
  color: var(--dsf-primary-600);
  border: none;
  border-radius: var(--dsf-radius-md);
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 600;
  font-size: var(--dsf-theme-text-base, 1rem);
  cursor: pointer;
  text-decoration: none;
  line-height: 1.25;
  white-space: nowrap;
  align-self: center;
}

.dsf-hero-centered-preview--bottom-split .dsf-hero-centered-preview__btn {
  align-self: center;
}

@container (max-width: 1024px) {
  .dsf-hero-centered-preview__title { font-size: var(--dsf-theme-h2, 2.1rem); }
  .dsf-hero-centered-preview__subtitle { font-size: var(--dsf-theme-text-base, 1rem); }
}

@container (max-width: 768px) {
  .dsf-hero-centered-preview:not(.dsf-hero-centered-preview--bottom-split) {
    padding: 64px 20px !important;
    /* Height comes from the (per-breakpoint) Height setting so the hero grows
       with it; no fixed mobile floor that would override the chosen value. */
  }

  .dsf-hero-centered-preview__title { font-size: var(--dsf-theme-h3, 1.75rem); }
  .dsf-hero-centered-preview__subtitle { font-size: var(--dsf-theme-text-sm, 0.95rem); }

  .dsf-hero-centered-preview--bottom-split .dsf-hero-centered-preview__content {
    grid-template-columns: 1fr !important;
  }

  .dsf-hero-centered-preview--bottom-split .dsf-hero-centered-preview__btn {
    /* Follow the content alignment (justify-items) instead of forcing left. */
    justify-self: inherit;
  }
}
</style>
