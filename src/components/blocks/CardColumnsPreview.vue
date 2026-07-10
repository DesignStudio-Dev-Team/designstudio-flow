<template>
  <section class="dsf-card-columns" :style="sectionStyle">
    <div
      class="dsf-card-columns__header"
      :class="{ 'dsf-card-columns__header--split': headerLayout === 'split' }"
    >
      <InlineText
        tagName="h2"
        class="dsf-card-columns__title"
        :style="{ color: settings.titleColor || '#111827' }"
        v-model="settings.title"
        :is-editor="isEditor"
        placeholder="Enter Section Title"
      />
      <InlineText
        tagName="p"
        class="dsf-card-columns__description"
        :style="{ color: settings.descriptionColor || '#4B5563' }"
        v-model="settings.description"
        :is-editor="isEditor"
        :multiline="true"
        placeholder="Enter a short introduction…"
      />
    </div>

    <div class="dsf-card-columns__grid" :style="gridStyle">
      <article
        v-for="(card, index) in displayCards"
        :key="index"
        class="dsf-card-columns__card"
        :class="{
          'dsf-card-columns__card--left': contentAlign === 'left',
          'dsf-card-columns__card--overlay': isOverlay,
        }"
        :style="cardStyle(card)"
      >
        <template v-if="isOverlay">
          <div v-if="cardImage(card)" class="dsf-card-columns__card-bg" aria-hidden="true">
            <img :src="cardImage(card)" alt="" loading="lazy" />
          </div>
          <div class="dsf-card-columns__card-scrim" :style="scrimStyle" aria-hidden="true"></div>
        </template>

        <div class="dsf-card-columns__card-head">
          <span
            v-if="cardIconType(card) === 'preset'"
            class="dsf-card-columns__card-icon"
            :style="{ color: isOverlay ? overlayTextColor : (settings.cardIconColor || '#111827') }"
            aria-hidden="true"
          >
            <component :is="iconFor(card.icon)" :size="28" />
          </span>
          <span
            v-else-if="cardIconType(card) === 'custom'"
            class="dsf-card-columns__card-icon"
            aria-hidden="true"
          >
            <img class="dsf-card-columns__card-icon-img" :src="customIconUrl(card)" alt="" loading="lazy" />
          </span>
          <InlineText
            v-if="isEditor || card.title"
            tagName="h3"
            class="dsf-card-columns__card-title"
            :style="{ color: isOverlay ? overlayTextColor : (settings.cardTitleColor || '#111827') }"
            v-model="card.title"
            :is-editor="isEditor"
            placeholder="Card title"
          />
        </div>
        <InlineText
          v-if="isEditor || card.description"
          tagName="p"
          class="dsf-card-columns__card-description"
          :style="{ color: isOverlay ? overlayTextColor : (settings.cardDescriptionColor || '#4B5563') }"
          v-model="card.description"
          :is-editor="isEditor"
          :multiline="true"
          placeholder="Add a short description…"
        />

        <a
          v-if="showsButton(card) && buttonStyle !== 'arrow'"
          class="dsf-card-columns__card-btn dsf-card-columns__card-btn--text"
          :href="buttonHref(card)"
          :style="buttonStyles"
          @click="handleButtonClick"
        >
          <span>{{ card.buttonText || 'Learn More' }}</span>
          <ArrowRight v-if="buttonStyle === 'text-arrow'" :size="16" aria-hidden="true" />
        </a>

        <div v-if="!isOverlay && cardImage(card)" class="dsf-card-columns__card-media" :style="mediaStyle">
          <img :src="cardImage(card)" alt="" loading="lazy" />
        </div>

        <a
          v-if="showsButton(card) && buttonStyle === 'arrow'"
          class="dsf-card-columns__card-btn dsf-card-columns__card-btn--arrow"
          :href="buttonHref(card)"
          :style="buttonStyles"
          :aria-label="card.buttonText || card.title || 'Learn more'"
          @click="handleButtonClick"
        >
          <ArrowRight :size="18" aria-hidden="true" />
        </a>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowRight } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { iconFor } from '../../utils/landingIcons'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const FALLBACK_CARDS = [
  { icon: 'sparkles', iconType: 'preset', customIcon: '', title: 'First Benefit', description: '', image: '', backgroundType: 'solid', backgroundColor: '#F3F4F6', showButton: false },
  { icon: 'heart', iconType: 'preset', customIcon: '', title: 'Second Benefit', description: '', image: '', backgroundType: 'solid', backgroundColor: '#F3F4F6', showButton: false },
  { icon: 'users', iconType: 'preset', customIcon: '', title: 'Third Benefit', description: '', image: '', backgroundType: 'solid', backgroundColor: '#F3F4F6', showButton: false },
]

const displayCards = computed(() => {
  const cards = props.settings?.cards
  // Clone the fallbacks so inline edits never mutate the shared constants.
  if (!Array.isArray(cards)) return FALLBACK_CARDS.map((card) => ({ ...card }))
  return cards.filter((card) => card && typeof card === 'object')
})

function cardIconType(card) {
  const type = card?.iconType
  if (type === 'none') return 'none'
  if (type === 'custom') return customIconUrl(card) ? 'custom' : 'none'
  // Legacy cards have no iconType; a non-empty icon means preset.
  return card?.icon ? 'preset' : 'none'
}

function customIconUrl(card) {
  const url = safePublicUrl(card?.customIcon, '')
  return url && url !== '#' ? url : ''
}

const headerLayout = computed(() => (props.settings?.headerLayout === 'split' ? 'split' : 'centered'))
const contentAlign = computed(() => (props.settings?.contentAlign === 'left' ? 'left' : 'center'))
const isOverlay = computed(() => props.settings?.cardLayout === 'overlay')
const overlayTextColor = computed(() => props.settings?.overlayTextColor || '#FFFFFF')

const scrimStyle = computed(() => {
  const strength = clampNumber(props.settings?.overlayStrength, 0, 100, 60) / 100
  const height = clampNumber(props.settings?.overlayHeight, 20, 100, 50)
  return {
    background: `linear-gradient(to top, rgba(0, 0, 0, ${strength}) 0%, rgba(0, 0, 0, 0) ${height}%)`,
  }
})
const buttonStyle = computed(() => {
  const style = props.settings?.buttonStyle
  return ['arrow', 'text', 'text-arrow'].includes(style) ? style : 'arrow'
})

function buildBackground(type, solid, start, end, direction) {
  if (type === 'transparent') return { background: 'transparent' }
  if (type === 'gradient') {
    const from = start || '#F3F4F6'
    const to = end || '#E5E7EB'
    if (direction === 'radial') return { background: `radial-gradient(circle at center, ${from}, ${to})` }
    const angle = direction === 'left-right' ? 'to right' : 'to bottom'
    return { background: `linear-gradient(${angle}, ${from}, ${to})` }
  }
  return { background: solid || 'transparent' }
}

const sectionStyle = computed(() => {
  const settings = props.settings || {}
  const paddingY = getResponsiveValue(settings, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(settings, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    ...buildBackground(
      settings.backgroundType === 'gradient' ? 'gradient' : 'solid',
      settings.backgroundColor || '#FFFFFF',
      settings.gradientStart,
      settings.gradientEnd,
      settings.gradientDirection
    ),
  }
})

const gridStyle = computed(() => {
  const gap = getResponsiveValue(props.settings || {}, props.previewMode, 'gap') ?? 24
  return {
    '--columns': props.settings?.columns || 3,
    gap: `${gap}px`,
  }
})

function cardStyle(card) {
  const settings = props.settings || {}
  const padding = clampNumber(settings.cardPadding, 8, 48, 24)
  return {
    minHeight: `${clampNumber(settings.cardMinHeight, 200, 720, 380)}px`,
    padding: `${padding}px`,
    '--card-padding': `${padding}px`,
    borderRadius: `${clampNumber(settings.cardRadius, 0, 40, 16)}px`,
    ...buildBackground(
      card.backgroundType || 'solid',
      card.backgroundColor || '#F3F4F6',
      card.gradientStart,
      card.gradientEnd,
      card.gradientDirection
    ),
  }
}

const mediaStyle = computed(() => {
  const settings = props.settings || {}
  return {
    height: `${clampNumber(settings.imageHeight, 80, 420, 220)}px`,
    '--image-fit': settings.imageFit === 'contain' ? 'contain' : 'cover',
  }
})

const buttonStyles = computed(() => ({
  backgroundColor: props.settings?.buttonColor || '#111827',
  color: props.settings?.buttonTextColor || '#FFFFFF',
}))

function clampNumber(value, min, max, fallback) {
  const number = Number(value)
  if (!Number.isFinite(number)) return fallback
  return Math.min(max, Math.max(min, number))
}

function cardImage(card) {
  const url = safePublicUrl(card?.image, '')
  return url && url !== '#' ? url : ''
}

function showsButton(card) {
  return !!card?.showButton
}

function buttonHref(card) {
  return safePublicUrl(card?.buttonUrl, '#')
}

function handleButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
  }
}
</script>

<style scoped>
/* Flex column so an explicit responsive Height stretches the card grid (and
   the cards' backgrounds), not just the section background. */
.dsf-card-columns {
  container-type: inline-size;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.dsf-card-columns__header {
  max-width: 1200px;
  margin: 0 auto 2.5rem;
  text-align: center;
}

.dsf-card-columns__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 2rem);
  font-weight: 600;
  margin: 0 0 0.75rem;
  line-height: 1.2;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-card-columns__description {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  margin: 0 auto;
  max-width: 640px;
  line-height: 1.6;
}

.dsf-card-columns__header--split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  align-items: start;
  text-align: left;
}

.dsf-card-columns__header--split .dsf-card-columns__title {
  margin-bottom: 0;
}

.dsf-card-columns__header--split .dsf-card-columns__description {
  margin: 0;
  max-width: none;
}

.dsf-card-columns__header {
  flex: 0 0 auto;
  width: 100%;
}

.dsf-card-columns__grid {
  display: grid;
  grid-template-columns: repeat(var(--columns, 3), minmax(0, 1fr));
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
  flex: 1 0 auto;
}

.dsf-card-columns__card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.625rem;
  overflow: hidden;
}

.dsf-card-columns__card--left {
  align-items: flex-start;
  text-align: left;
}

/* Image-background layout: full-bleed image, content pinned to the bottom
   over a dark gradient scrim. */
.dsf-card-columns__card--overlay {
  justify-content: flex-end;
}

.dsf-card-columns__card-bg,
.dsf-card-columns__card-scrim {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.dsf-card-columns__card-bg img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.dsf-card-columns__card-scrim {
  z-index: 1;
}

.dsf-card-columns__card--overlay .dsf-card-columns__card-head,
.dsf-card-columns__card--overlay .dsf-card-columns__card-description,
.dsf-card-columns__card--overlay .dsf-card-columns__card-btn--text {
  position: relative;
  z-index: 2;
}

.dsf-card-columns__card-head {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}

.dsf-card-columns__card--left .dsf-card-columns__card-head {
  justify-content: flex-start;
}

.dsf-card-columns__card-icon {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}

.dsf-card-columns__card-icon-img {
  width: 28px;
  height: 28px;
  object-fit: contain;
  display: block;
}

.dsf-card-columns__card-title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h4, 1.25rem);
  font-weight: 600;
  margin: 0;
  line-height: 1.25;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-card-columns__card-description {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.9375rem);
  margin: 0;
  line-height: 1.55;
}

/* Bleed to the card edges so bottom images sit flush, like the design. */
.dsf-card-columns__card-media {
  width: calc(100% + 2 * var(--card-padding, 24px));
  margin: auto calc(var(--card-padding, 24px) * -1) calc(var(--card-padding, 24px) * -1);
  align-self: center;
  flex-shrink: 0;
}

.dsf-card-columns__card-media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: var(--image-fit, cover);
  object-position: center bottom;
}

.dsf-card-columns__card-btn {
  font-family: var(--dsf-theme-body-font, inherit);
  text-decoration: none;
  transition: transform 0.15s ease, opacity 0.15s ease;
}

.dsf-card-columns__card-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.dsf-card-columns__card-btn--text {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1.125rem;
  border-radius: var(--dsf-radius-md, 8px);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  line-height: 1.25;
}

.dsf-card-columns__card-btn--arrow {
  position: absolute;
  right: 1rem;
  bottom: 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 9999px;
  z-index: 2;
}

@container (max-width: 1024px) {
  .dsf-card-columns__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@container (max-width: 700px) {
  .dsf-card-columns__grid {
    grid-template-columns: 1fr;
  }

  .dsf-card-columns__header--split {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
}
</style>
