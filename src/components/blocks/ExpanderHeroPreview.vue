<template>
  <section
    class="dsf-block-preview dsf-expander-hero"
    :class="`dsf-expander-hero--${layoutStyle}`"
    :style="sectionStyle"
  >
    <template v-if="layoutStyle === 'row'">
      <div class="dsf-expander-hero__row" :style="rowStyle">
        <component
          :is="cardTag(card)"
          v-for="(card, index) in displayCards"
          :key="index"
          :href="cardHref(card)"
          class="dsf-expander-hero__card dsf-expander-hero__card--row"
          :style="cardStyle"
          @click="handleCardClick(card, $event)"
        >
          <ExpanderCardContent :card="card" :text-color="settings.cardTextColor || '#FFFFFF'" />
        </component>
      </div>
    </template>

    <template v-else>
      <div
        class="dsf-expander-hero__grid"
        :class="`dsf-expander-hero__grid--bar-${barPosition}`"
        :style="gridStyle"
      >
        <div class="dsf-expander-hero__card-grid dsf-expander-hero__card-grid--top">
          <component
            :is="cardTag(card)"
            v-for="(card, index) in topCards"
            :key="`top-${index}`"
            :href="cardHref(card)"
            class="dsf-expander-hero__card dsf-expander-hero__card--expandable"
            :style="cardStyle"
            @click="handleCardClick(card, $event)"
          >
            <ExpanderCardContent :card="card" :text-color="settings.cardTextColor || '#FFFFFF'" />
          </component>
        </div>

        <div class="dsf-expander-hero__bar" :style="barStyle">
          <InlineText
            tagName="h2"
            class="dsf-expander-hero__bar-title"
            v-model="settings.barTitle"
            :is-editor="isEditor"
            placeholder="Test Title 1"
          />
          <a
            v-if="settings.showButton !== false"
            class="dsf-expander-hero__bar-btn"
            :href="buttonHref"
            :style="buttonStyle"
            @click="handleButtonClick"
          >
            <InlineText
              tagName="span"
              v-model="settings.buttonText"
              :is-editor="isEditor"
              placeholder="test"
            />
          </a>
        </div>

        <div
          class="dsf-expander-hero__card-grid dsf-expander-hero__card-grid--bottom"
          :class="{ 'dsf-expander-hero__card-grid--two': bottomCards.length === 2 }"
        >
          <component
            :is="cardTag(card)"
            v-for="(card, index) in bottomCards"
            :key="`bottom-${index}`"
            :href="cardHref(card)"
            class="dsf-expander-hero__card dsf-expander-hero__card--expandable"
            :style="cardStyle"
            @click="handleCardClick(card, $event)"
          >
            <ExpanderCardContent :card="card" :text-color="settings.cardTextColor || '#FFFFFF'" />
          </component>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, h } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
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

const layoutStyle = computed(() => props.settings?.layoutStyle === 'row' ? 'row' : 'split-bar')
const barPosition = computed(() => {
  const value = props.settings?.barPosition
  return ['top', 'bottom'].includes(value) ? value : 'middle'
})
const displayCards = computed(() => {
  const cards = Array.isArray(props.settings?.cards) ? props.settings.cards : []
  const normalized = cards.slice(0, 6).filter(Boolean)
  if (normalized.length > 0) return normalized

  return Array.from({ length: 6 }, (_, index) => ({
    title: `Card ${index + 1}`,
    image: '',
    url: '#',
  }))
})
const topCards = computed(() => displayCards.value.slice(0, 3))
const bottomCards = computed(() => displayCards.value.slice(3, 6))

const sectionStyle = computed(() => {
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 0
  return {
    paddingLeft: `${paddingX}px`,
    paddingRight: `${paddingX}px`,
  }
})

const gapValue = computed(() => getResponsiveValue(props.settings || {}, props.previewMode, 'gap') ?? 16)
const cardHeight = computed(() => getResponsiveValue(props.settings || {}, props.previewMode, 'cardHeight') ?? 280)
const barHeight = computed(() => getResponsiveValue(props.settings || {}, props.previewMode, 'barHeight') ?? 110)
const overallHeight = computed(() => getResponsiveValue(props.settings || {}, props.previewMode, 'height'))
const hasOverallHeight = computed(() => Number.isFinite(Number(overallHeight.value)))

const rowStyle = computed(() => ({
  gap: `${gapValue.value}px`,
  height: `${hasOverallHeight.value ? overallHeight.value : cardHeight.value}px`,
}))

const gridStyle = computed(() => ({
  gap: `${gapValue.value}px`,
  ...(hasOverallHeight.value ? { height: `${overallHeight.value}px` } : {}),
}))

const cardStyle = computed(() => ({
  height: hasOverallHeight.value ? '100%' : `${cardHeight.value}px`,
}))

const barStyle = computed(() => ({
  minHeight: `${barHeight.value}px`,
  backgroundColor: props.settings?.barColor || '#76A64B',
  color: props.settings?.barTextColor || '#FFFFFF',
}))

const buttonStyle = computed(() => ({
  backgroundColor: props.settings?.buttonColor || '#17212B',
  color: props.settings?.buttonTextColor || '#FFFFFF',
}))

const buttonHref = computed(() => safePublicUrl(props.settings?.buttonUrl))

function cardHref(card) {
  return safePublicUrl(card?.url)
}

function cardTag(card) {
  return cardHref(card) === '#' ? 'div' : 'a'
}

function handleCardClick(card, event) {
  if (props.isEditor || cardHref(card) === '#') {
    event.preventDefault()
  }
}

function handleButtonClick(event) {
  const href = buttonHref.value
  if (props.isEditor || href === '#') {
    event.preventDefault()
  }
}

const ExpanderCardContent = {
  props: {
    card: Object,
    textColor: String,
  },
  setup(componentProps) {
    return () => [
      safePublicUrl(componentProps.card?.image, '')
        ? h('img', {
            class: 'dsf-expander-hero__card-img',
            src: safePublicUrl(componentProps.card.image, ''),
            alt: '',
          })
        : h('div', { class: 'dsf-expander-hero__card-placeholder' }),
      h('div', { class: 'dsf-expander-hero__card-overlay' }),
      h('span', {
        class: 'dsf-expander-hero__card-title',
        style: { color: componentProps.textColor || '#FFFFFF' },
      }, componentProps.card?.title || 'Card'),
    ]
  },
}
</script>

<style scoped>
.dsf-expander-hero {
  width: 100%;
  container-type: inline-size;
}

.dsf-expander-hero__row {
  display: flex;
  width: 100%;
}

.dsf-expander-hero__card {
  position: relative;
  display: block;
  overflow: hidden;
  flex: 1 1 0;
  min-width: 0;
  background: #d1d5db;
  color: white;
  text-decoration: none;
}

.dsf-expander-hero__card--row {
  transition: flex 0.32s ease, filter 0.32s ease;
}

.dsf-expander-hero__row:hover .dsf-expander-hero__card--row {
  flex: 0.82 1 0;
}

.dsf-expander-hero__row .dsf-expander-hero__card--row:hover {
  flex: 2 1 0;
}

.dsf-expander-hero__card :deep(.dsf-expander-hero__card-img),
.dsf-expander-hero__card :deep(.dsf-expander-hero__card-placeholder) {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.dsf-expander-hero__card :deep(.dsf-expander-hero__card-img) {
  object-fit: cover;
  transition: transform 0.32s ease;
}

.dsf-expander-hero__card:hover :deep(.dsf-expander-hero__card-img) {
  transform: scale(1.035);
}

.dsf-expander-hero__card :deep(.dsf-expander-hero__card-placeholder) {
  background:
    linear-gradient(135deg, rgba(17, 24, 39, 0.12), rgba(17, 24, 39, 0)),
    linear-gradient(135deg, #d1d5db, #9ca3af);
}

.dsf-expander-hero__card :deep(.dsf-expander-hero__card-overlay) {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.58), rgba(0, 0, 0, 0.08) 58%, rgba(0, 0, 0, 0));
}

.dsf-expander-hero__card :deep(.dsf-expander-hero__card-title) {
  position: absolute;
  right: 1rem;
  bottom: 1rem;
  left: 1rem;
  z-index: 1;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.5rem);
  font-weight: 700;
  line-height: 1.15;
  text-align: center;
  text-shadow: 0 1px 5px rgba(0, 0, 0, 0.36);
}

.dsf-expander-hero__grid {
  display: grid;
  grid-template-rows: minmax(0, 1fr) auto minmax(0, 1fr);
}

.dsf-expander-hero__grid--bar-top {
  grid-template-rows: auto minmax(0, 1fr) minmax(0, 1fr);
}

.dsf-expander-hero__grid--bar-bottom {
  grid-template-rows: minmax(0, 1fr) minmax(0, 1fr) auto;
}

.dsf-expander-hero__grid--bar-top .dsf-expander-hero__bar {
  order: -1;
}

.dsf-expander-hero__grid--bar-bottom .dsf-expander-hero__bar {
  order: 3;
}

.dsf-expander-hero__card-grid {
  display: flex;
  min-height: 0;
  gap: inherit;
}

.dsf-expander-hero__card--expandable {
  transition: flex 0.32s ease, filter 0.32s ease;
}

.dsf-expander-hero__card-grid:hover .dsf-expander-hero__card--expandable {
  flex: 0.82 1 0;
}

.dsf-expander-hero__card-grid .dsf-expander-hero__card--expandable:hover {
  flex: 2 1 0;
}

.dsf-expander-hero__bar {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  gap: 1.5rem;
  padding: 1rem 1.5rem;
  text-align: center;
}

.dsf-expander-hero__bar-title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 2rem);
  font-weight: 700;
  line-height: 1.12;
}

.dsf-expander-hero__bar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-height: 48px;
  min-width: 148px;
  padding: 0.75rem 1.4rem;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 10px;
  box-shadow: 0 8px 20px rgba(17, 24, 39, 0.2);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.dsf-expander-hero__bar-btn:hover {
  filter: brightness(1.08);
  transform: translateY(-2px);
  box-shadow: 0 11px 26px rgba(17, 24, 39, 0.26);
}

.dsf-expander-hero__bar-btn:focus-visible {
  outline: 3px solid rgba(255, 255, 255, 0.7);
  outline-offset: 3px;
}

@container (max-width: 900px) {
  .dsf-expander-hero__grid {
    height: auto !important;
    grid-template-rows: auto;
  }

  .dsf-expander-hero__row,
  .dsf-expander-hero__card-grid {
    display: grid;
    grid-template-columns: 1fr;
  }

  .dsf-expander-hero__card-grid .dsf-expander-hero__card {
    height: auto !important;
    min-height: 160px;
  }

  .dsf-expander-hero__row:hover .dsf-expander-hero__card--row,
  .dsf-expander-hero__row .dsf-expander-hero__card--row:hover,
  .dsf-expander-hero__card-grid:hover .dsf-expander-hero__card--expandable,
  .dsf-expander-hero__card-grid .dsf-expander-hero__card--expandable:hover {
    flex: initial;
  }

  .dsf-expander-hero__bar {
    flex-direction: column;
  }
}
</style>
