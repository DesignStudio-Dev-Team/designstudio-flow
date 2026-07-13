<template>
  <section id="why-dsflow" ref="root" class="dsf-showcase-hero" :class="{ 'dsf-showcase-hero--editor': isEditor }" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-showcase-hero__backdrop" aria-hidden="true">
      <div class="dsf-showcase-hero__grid"></div>
      <div class="dsf-showcase-hero__glow dsf-showcase-hero__glow--one"></div>
      <div class="dsf-showcase-hero__glow dsf-showcase-hero__glow--two"></div>
    </div>

    <div class="dsf-showcase-hero__inner">
      <!-- Copy: a few words + one rotating word that shows the breadth. -->
      <div class="dsf-showcase-hero__copy">
        <span class="dsf-kicker" data-dsf-reveal>
          <i class="dsf-kicker__dot"></i>
          <InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" />
        </span>

        <h1 class="dsf-showcase-hero__title" data-dsf-reveal>
          <span v-if="!isEditor" class="dsf-showcase-hero__sr-only">{{ accessibleHeadline }}</span>
          <span class="dsf-showcase-hero__visual-title" :aria-hidden="isEditor ? undefined : 'true'">
            <InlineText tagName="span" class="dsf-showcase-hero__lead" v-model="settings.title" :is-editor="isEditor" placeholder="Design your" />
            <span class="dsf-showcase-hero__rotating">
              <span :key="`${activeIndex}-${activeWord}`" class="dsf-showcase-hero__word">
                <span v-for="(line, index) in activeWordLines" :key="`${index}-${line}`" class="dsf-showcase-hero__word-line">
                  {{ line }}<span v-if="index === activeWordLines.length - 1" class="dsf-showcase-hero__word-dot">.</span>
                </span>
              </span>
            </span>
          </span>
        </h1>

        <div v-if="showCycleDots" class="dsf-showcase-hero__cycle" data-dsf-reveal aria-hidden="true">
          <span class="dsf-showcase-hero__cycle-dots" aria-hidden="true">
            <i v-for="(_, index) in words" :key="index" :class="{ 'is-active': index === activeIndex }"></i>
          </span>
        </div>

        <InlineText tagName="p" class="dsf-showcase-hero__tagline" v-model="settings.tagline" :is-editor="isEditor" data-dsf-reveal placeholder="One-line tagline" />

        <div class="dsf-showcase-hero__actions" data-dsf-reveal>
          <a v-if="isEditor || settings.primaryText" class="dsf-hero-button dsf-hero-button--primary" :href="safePublicUrl(settings.primaryUrl)" @click="guardEditor">
            <InlineText tagName="span" v-model="settings.primaryText" :is-editor="isEditor" placeholder="Primary" /> <ArrowUpRight :size="18" />
          </a>
          <a v-if="isEditor || settings.secondaryText" class="dsf-hero-button dsf-hero-button--secondary" :href="safePublicUrl(settings.secondaryUrl)" @click="guardEditor">
            <InlineText tagName="span" v-model="settings.secondaryText" :is-editor="isEditor" placeholder="Secondary" />
          </a>
        </div>

        <ul v-if="chips.length" class="dsf-showcase-hero__chips" data-dsf-reveal>
          <li v-for="chip in chips" :key="chip" class="dsf-showcase-hero__chip">
            <Check :size="13" /> {{ chip }}
          </li>
        </ul>
      </div>

      <!-- The visual site map: tiles preview each section; the active one lights
           up in sync with the rotating word. -->
      <nav class="dsf-showcase-hero__mosaic" aria-label="Page sections">
        <a
          v-for="(tile, index) in tiles"
          :key="index"
          class="dsf-showcase-tile"
          :class="[`dsf-showcase-tile--${sceneFor(tile)}`, { 'is-active': index === activeTile }]"
          :href="safePublicUrl(tile.url)"
          data-dsf-card
          @click="guardEditor"
        >
          <span v-if="tile.iconImage" class="dsf-showcase-tile__image">
            <img :src="tile.iconImage" alt="" loading="lazy" decoding="async" />
          </span>

          <span v-else class="dsf-showcase-tile__scene" aria-hidden="true">
            <template v-if="sceneFor(tile) === 'design'">
              <span class="dsf-scene-design__topline"><small>Global styles</small><component :is="iconFor('settings')" :size="10" /></span>
              <span class="dsf-scene-design__swatches"><i></i><i></i><i></i><i></i></span>
              <span class="dsf-scene-design__type"><b>Ag</b><span class="dsf-scene-design__font"><strong>Heading 1</strong><small>Manrope</small></span></span>
              <span class="dsf-scene-design__spacing"><small>Spacing</small><i><b></b></i><em>24</em></span>
            </template>

            <template v-else-if="sceneFor(tile) === 'builder'">
              <span class="dsf-scene-builder__chrome"><i></i><i></i><i></i><small><component :is="iconFor('monitor')" :size="9" /> Desktop</small></span>
              <span class="dsf-scene-builder__canvas">
                <span class="dsf-scene-builder__toolbar"><component :is="iconFor('mouse-pointer')" :size="9" /><i></i><i></i></span>
                <span class="dsf-scene-builder__block"><b>Create<br />without limits</b><i></i><i></i><i></i><i></i></span>
                <span class="dsf-scene-builder__control"><small>Spacing</small><b>24</b><em>px</em></span>
              </span>
              <span class="dsf-scene-builder__dock"><i></i><i></i><i class="is-active"></i><i></i><i></i></span>
            </template>

            <template v-else-if="sceneFor(tile) === 'commerce'">
              <span class="dsf-scene-commerce__product">
                <span class="dsf-scene-commerce__img"><component :is="iconFor('store')" :size="20" /></span>
                <span class="dsf-scene-commerce__info"><small>Modern chair</small><b>$49.00</b><span><i>−</i><em>1</em><i>+</i></span></span>
              </span>
              <span class="dsf-scene-commerce__row"><span class="dsf-scene-commerce__success"><Check :size="9" /> Added</span><span class="dsf-scene-commerce__buy">Add to cart</span></span>
            </template>

            <template v-else-if="sceneFor(tile) === 'forms'">
              <span class="dsf-scene-forms__row"><span><small>Name</small><i></i></span><span><small>Email</small><i></i></span></span>
              <span class="dsf-scene-forms__message"><small>Message</small><i></i></span>
              <span class="dsf-scene-forms__actions"><span class="dsf-scene-forms__send"><component :is="iconFor('mail')" :size="10" /> Send</span><span class="dsf-scene-forms__success"><Check :size="9" /> Message sent</span></span>
            </template>

            <template v-else-if="sceneFor(tile) === 'security'">
              <span class="dsf-scene-security__shield"><component :is="iconFor('shield-check')" :size="25" /></span>
              <span class="dsf-scene-security__checks">
                <span><i><Check :size="8" /></i> Sanitized output</span>
                <span><i><Check :size="8" /></i> Secure by default</span>
                <span><i><Check :size="8" /></i> Regular updates</span>
              </span>
            </template>

            <template v-else-if="sceneFor(tile) === 'agency'">
              <span class="dsf-scene-agency__pages">
                <span><component :is="iconFor('file-text')" :size="9" /><b>Home</b><small>Hero</small></span>
                <span><component :is="iconFor('file-text')" :size="9" /><b>Products</b><small>Grid</small></span>
                <span><component :is="iconFor('file-text')" :size="9" /><b>About</b><small>Team</small></span>
              </span>
              <span class="dsf-scene-agency__connections"><i></i><i></i><i></i></span>
              <span class="dsf-scene-agency__reusable"><small>Reusable</small><component :is="iconFor('layout')" :size="14" /></span>
            </template>

            <template v-else>
              <span class="dsf-showcase-tile__glyph"><component :is="iconFor(tile.icon)" :size="24" /></span>
            </template>
          </span>

          <span class="dsf-showcase-tile__label">
            <span><small>{{ wordForIndex(index) }}</small>{{ tile.label }}</span>
            <ArrowUpRight :size="14" />
          </span>
        </a>
      </nav>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, inject, onMounted, onBeforeUnmount, watch } from 'vue'
import { ArrowUpRight, Check } from 'lucide-vue-next'
import { safePublicUrl } from '../../utils/safeUrl'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import { iconFor } from '../../utils/landingIcons'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const root = ref(null)
const renderMode = inject('dsfRenderMode', null)
useLandingMotion(root, props.isEditor || renderMode === 'snapshot')

// A tile renders a mini scene chosen from its preset icon so the page reads as a
// visual map out of the box; any other icon (or a custom image) still works.
const SCENES = {
  wand: 'design',
  palette: 'design',
  paintbrush: 'design',
  'mouse-pointer': 'builder',
  boxes: 'builder',
  layout: 'builder',
  store: 'commerce',
  mail: 'forms',
  'form-input': 'forms',
  'shield-check': 'security',
  lock: 'security',
  briefcase: 'agency',
  users: 'agency',
}

const SCENE_WORDS = {
  design: 'WordPress site',
  builder: 'page visually',
  commerce: 'online store',
  forms: 'next campaign',
  security: 'site securely',
  agency: 'client site',
  glyph: 'whole site',
}

function sceneFor(tile) {
  return SCENES[tile.icon] || 'glyph'
}

const tiles = computed(() => {
  const raw = Array.isArray(props.settings?.tiles) ? props.settings.tiles : []
  return raw
    .filter((t) => t && typeof t === 'object' && (t.label || t.icon || t.iconImage))
    .slice(0, 6)
    .map((t) => ({
      label: typeof t.label === 'string' ? t.label : '',
      url: typeof t.url === 'string' ? t.url : '',
      icon: typeof t.icon === 'string' ? t.icon : '',
      iconImage: safeImageUrl(t.iconImage),
    }))
})

function normalizeTwoWordPhrase(value) {
  if (typeof value !== 'string') return ''
  const parts = value.trim().replace(/\s+/g, ' ').slice(0, 64).split(' ').filter(Boolean)
  return parts.length === 2 ? parts.join(' ') : ''
}

const configuredWords = computed(() => {
  const raw = typeof props.settings?.rotatingWords === 'string' ? props.settings.rotatingWords : ''
  const seen = new Set()
  return raw
    .split(',')
    .map(normalizeTwoWordPhrase)
    .filter((word) => {
      const key = word.toLocaleLowerCase()
      if (!word || seen.has(key)) return false
      seen.add(key)
      return true
    })
    .slice(0, 6)
})

function fallbackWordsForTiles(items) {
  const seen = new Set()
  return items.map((tile, index) => {
    const sceneWord = normalizeTwoWordPhrase(SCENE_WORDS[sceneFor(tile)] || '')
    const labelWord = normalizeTwoWordPhrase(tile.label)
    const candidates = [sceneWord, labelWord, `section ${index + 1}`]
    const word = candidates.find((candidate) => candidate && !seen.has(candidate.toLocaleLowerCase())) || `section ${index + 1}`
    seen.add(word.toLocaleLowerCase())
    return word
  })
}

// The tile count is the source of truth. Legacy pages stored five phrases for
// six tiles; a complete unique list is used only when it maps one-to-one.
const words = computed(() => {
  if (!tiles.value.length) return configuredWords.value.length ? configuredWords.value : ['whole site']
  if (configuredWords.value.length === tiles.value.length) return configuredWords.value
  return fallbackWordsForTiles(tiles.value)
})

const chips = computed(() =>
  [props.settings?.chip1, props.settings?.chip2, props.settings?.chip3].filter(
    (c) => typeof c === 'string' && c.trim() !== ''
  )
)

// The switching engine: one ticking index drives both the rotating word and the
// highlighted tile. Frontend only — the editor and snapshots stay on the first
// word so editing is stable and no timers leak into a static render.
const tick = ref(0)
const reducedMotion = ref(true)
let timer = null
let motionQuery = null

const activeIndex = computed(() => tick.value % words.value.length)
const activeWord = computed(() => words.value[activeIndex.value])
const activeWordLines = computed(() => {
  const [first = '', ...rest] = activeWord.value.trim().split(/\s+/)
  return rest.length ? [first, rest.join(' ')] : [first]
})
const activeTile = computed(() => (tiles.value.length ? activeIndex.value : -1))
const showCycleDots = computed(() =>
  !props.isEditor && renderMode !== 'snapshot' && !reducedMotion.value && words.value.length > 1
)
const accessibleHeadline = computed(() => {
  const lead = typeof props.settings?.title === 'string' && props.settings.title.trim() ? props.settings.title.trim() : 'Design your'
  const [first, ...rest] = words.value
  return `${lead} ${first}.${rest.length ? ` DSFlow also helps you design your ${rest.join(', ')}.` : ''}`
})

function wordForIndex(index) {
  return words.value[index] || SCENE_WORDS[sceneFor(tiles.value[index] || {})] || ''
}

function safeImageUrl(value) {
  const safe = safePublicUrl(value, '')
  if (!safe) return ''
  if (/^https?:\/\//i.test(safe)) return safe
  if ((safe.startsWith('/') && !safe.startsWith('//')) || safe.startsWith('./') || safe.startsWith('../')) return safe
  return ''
}

function stopTimer() {
  if (timer !== null) {
    window.clearInterval(timer)
    timer = null
  }
}

function startTimer() {
  if (
    timer !== null ||
    props.isEditor ||
    renderMode === 'snapshot' ||
    reducedMotion.value ||
    words.value.length < 2 ||
    typeof window === 'undefined'
  ) return

  timer = window.setInterval(() => {
    tick.value = (tick.value + 1) % words.value.length
  }, 2800)
}

function handleMotionPreference(event) {
  reducedMotion.value = event.matches
  if (event.matches) {
    tick.value = 0
    stopTimer()
  } else {
    startTimer()
  }
}

onMounted(() => {
  if (typeof window === 'undefined') return
  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  reducedMotion.value = motionQuery.matches
  if (typeof motionQuery.addEventListener === 'function') motionQuery.addEventListener('change', handleMotionPreference)
  else motionQuery.addListener?.(handleMotionPreference)
  startTimer()
})

onBeforeUnmount(() => {
  stopTimer()
  if (typeof motionQuery?.removeEventListener === 'function') motionQuery.removeEventListener('change', handleMotionPreference)
  else motionQuery?.removeListener?.(handleMotionPreference)
})

// Keep the index in range if the word/tile lists change while mounted.
watch([words, tiles], () => {
  tick.value = 0
  stopTimer()
  startTimer()
})

function guardEditor(event) {
  if (props.isEditor) event.preventDefault()
}

const blockStyle = computed(() => landingBlockStyle(props.settings))
</script>

<style scoped>
.dsf-showcase-hero {
  position: relative;
  display: flex;
  align-items: center;
  overflow: hidden;
  isolation: isolate;
  min-height: 100svh;
  padding: clamp(1rem, 2.5vh, 2rem) clamp(1.25rem, 4vw, 3.75rem) clamp(6rem, 10vh, 7.5rem);
  background: var(--dsf-landing-background, #f7f4ed);
  color: var(--dsf-landing-text, #111827);
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-showcase-hero--editor {
  min-height: 680px;
  padding-bottom: 5.25rem;
}

.dsf-showcase-hero__backdrop {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.dsf-showcase-hero__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(color-mix(in srgb, var(--dsf-landing-text, #111827) 4.5%, transparent) 1px, transparent 1px),
    linear-gradient(90deg, color-mix(in srgb, var(--dsf-landing-text, #111827) 4.5%, transparent) 1px, transparent 1px);
  background-size: 52px 52px;
  mask-image: radial-gradient(86% 78% at 62% 34%, #000 18%, transparent 100%);
}

.dsf-showcase-hero__glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(18px);
  opacity: 0.85;
}

.dsf-showcase-hero__glow--one {
  top: -22%;
  right: -4%;
  width: 52%;
  aspect-ratio: 1;
  background: radial-gradient(circle, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 22%, transparent), transparent 64%);
}

.dsf-showcase-hero__glow--two {
  bottom: -30%;
  left: -10%;
  width: 44%;
  aspect-ratio: 1;
  background: radial-gradient(circle, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 12%, transparent), transparent 66%);
}

@media (prefers-reduced-motion: no-preference) {
  .dsf-showcase-hero__glow--one { animation: dsf-glow-drift 14s ease-in-out infinite alternate; }
  .dsf-showcase-hero__glow--two { animation: dsf-glow-drift 18s ease-in-out infinite alternate-reverse; }
  .dsf-showcase-hero__word { animation: dsf-word-in 0.46s cubic-bezier(0.22, 1, 0.36, 1) both; }
  .dsf-showcase-tile.is-active .dsf-showcase-tile__scene { animation: dsf-scene-focus 0.55s cubic-bezier(0.22, 1, 0.36, 1) both; }
  .dsf-showcase-tile.is-active .dsf-scene-design__swatches i { animation: dsf-pop-in 0.42s cubic-bezier(0.2, 1.6, 0.4, 1) both; }
  .dsf-showcase-tile.is-active .dsf-scene-design__swatches i:nth-child(2) { animation-delay: 0.05s; }
  .dsf-showcase-tile.is-active .dsf-scene-design__swatches i:nth-child(3) { animation-delay: 0.1s; }
  .dsf-showcase-tile.is-active .dsf-scene-design__swatches i:nth-child(4) { animation-delay: 0.15s; }
  .dsf-showcase-tile.is-active .dsf-scene-builder__block i { animation: dsf-handle-pulse 0.8s ease both; }
  .dsf-showcase-tile.is-active .dsf-scene-commerce__buy { animation: dsf-button-tap 0.7s ease both; }
  .dsf-showcase-tile.is-active .dsf-scene-forms__success { animation: dsf-message-in 0.55s 0.12s cubic-bezier(0.2, 1.4, 0.4, 1) both; }
  .dsf-showcase-tile.is-active .dsf-scene-security__checks span { animation: dsf-check-in 0.42s cubic-bezier(0.2, 1.5, 0.4, 1) both; }
  .dsf-showcase-tile.is-active .dsf-scene-security__checks span:nth-child(2) { animation-delay: 0.08s; }
  .dsf-showcase-tile.is-active .dsf-scene-security__checks span:nth-child(3) { animation-delay: 0.16s; }
  .dsf-showcase-tile.is-active .dsf-scene-agency__connections i { animation: dsf-connect-in 0.48s ease both; }
  .dsf-showcase-tile.is-active .dsf-scene-agency__connections i:nth-child(2) { animation-delay: 0.08s; }
  .dsf-showcase-tile.is-active .dsf-scene-agency__connections i:nth-child(3) { animation-delay: 0.16s; }
}

@keyframes dsf-glow-drift {
  from { transform: translate3d(0, 0, 0) scale(1); }
  to { transform: translate3d(-6%, 5%, 0) scale(1.12); }
}

@keyframes dsf-word-in {
  from { opacity: 0; filter: blur(5px); transform: translate3d(0, 0.28em, 0); }
  to { opacity: 1; filter: blur(0); transform: translate3d(0, 0, 0); }
}

@keyframes dsf-scene-focus {
  from { transform: scale(0.985); }
  to { transform: scale(1); }
}

@keyframes dsf-pop-in {
  from { opacity: 0; transform: translateY(5px) scale(0.72); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes dsf-handle-pulse {
  0% { transform: scale(0.3); opacity: 0; }
  55% { transform: scale(1.5); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}

@keyframes dsf-button-tap {
  0%, 100% { transform: scale(1); }
  42% { transform: scale(0.91); }
}

@keyframes dsf-message-in {
  from { opacity: 0; transform: translateX(-7px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes dsf-check-in {
  from { opacity: 0; transform: translateX(-8px); }
  to { opacity: 1; transform: translateX(0); }
}

@keyframes dsf-connect-in {
  from { transform: scaleX(0); }
  to { transform: scaleX(1); }
}

.dsf-showcase-hero__inner {
  position: relative;
  width: 100%;
  max-width: 1240px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 0.88fr) minmax(540px, 1.12fr);
  align-items: center;
  gap: clamp(2.25rem, 4.5vw, 4.75rem);
}

/* ---- Copy ---- */
.dsf-showcase-hero__copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.92rem;
  min-width: 0;
}

.dsf-kicker {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--dsf-eyebrow-color, #0091ff);
  font-size: 0.74rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.dsf-kicker__dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--dsf-eyebrow-line-color, var(--dsf-theme-primary, #0091ff));
}

.dsf-showcase-hero__title {
  margin: 0;
  width: 100%;
  max-width: 10.5ch;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(3rem, 5.2vw, 4.75rem);
  font-weight: 820;
  line-height: 0.98;
  letter-spacing: -0.048em;
}

.dsf-showcase-hero__visual-title,
.dsf-showcase-hero__lead,
.dsf-showcase-hero__lead :deep(*) {
  display: block;
}

.dsf-showcase-hero__rotating {
  display: block;
  overflow: visible;
  margin: 0.02em -0.08em -0.08em;
  padding: 0.02em 0.08em 0.2em;
  line-height: 1.13;
  font-size: 0.84em;
}

.dsf-showcase-hero__word {
  display: inline-flex;
  align-items: baseline;
  gap: 0.28em;
  max-width: 100%;
  min-height: 1.12em;
  padding: 0 0.025em 0.075em;
  line-height: 1.12;
  background: linear-gradient(105deg, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 88%, #005fcc), color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 62%, #68c8ff));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-box-decoration-break: clone;
  box-decoration-break: clone;
  -webkit-text-fill-color: transparent;
  color: transparent;
  white-space: nowrap;
  overflow-wrap: normal;
  will-change: opacity, transform, filter;
}

.dsf-showcase-hero__word-line {
  display: inline;
  min-width: 0;
}

.dsf-showcase-hero__word-dot {
  -webkit-text-fill-color: var(--dsf-theme-primary, #0091ff);
  color: var(--dsf-theme-primary, #0091ff);
}

.dsf-showcase-hero__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.dsf-showcase-hero__cycle {
  display: inline-flex;
  align-items: center;
  min-height: 10px;
}

.dsf-showcase-hero__cycle-dots {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.dsf-showcase-hero__cycle-dots i {
  width: 5px;
  height: 5px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--dsf-landing-text, #111827) 18%, transparent);
  transition: width 0.25s ease, background-color 0.25s ease;
}

.dsf-showcase-hero__cycle-dots i.is-active {
  width: 22px;
  background: var(--dsf-theme-primary, #0091ff);
}

.dsf-showcase-hero__tagline {
  margin: 0;
  max-width: 46ch;
  font-size: clamp(1rem, 1.35vw, 1.16rem);
  line-height: 1.55;
  opacity: 0.76;
}

.dsf-showcase-hero__actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.7rem;
  margin-top: 0.42rem;
}

.dsf-hero-button {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  min-height: 48px;
  padding: 0.78rem 1.5rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.98rem;
  text-decoration: none;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}

.dsf-hero-button--primary {
  background: var(--dsf-button-bg, var(--dsf-theme-primary, #0091ff));
  color: #fff !important;
  box-shadow: 0 17px 38px -14px color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 72%, transparent);
}

.dsf-hero-button--primary:hover {
  transform: translateY(-2px);
}

.dsf-hero-button--secondary {
  border: 1.5px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 18%, transparent);
  color: inherit;
}

.dsf-hero-button:focus-visible,
.dsf-showcase-tile:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 36%, transparent);
  outline-offset: 3px;
}

.dsf-hero-button--secondary:hover {
  border-color: var(--dsf-theme-primary, #0091ff);
  color: var(--dsf-theme-primary, #0091ff);
}

.dsf-showcase-hero__chips {
  display: flex;
  max-width: 100%;
  flex-wrap: wrap;
  gap: 0.4rem 1rem;
  margin: 0.28rem 0 0;
  padding: 0;
  list-style: none;
}

.dsf-showcase-hero__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  font-weight: 600;
  opacity: 0.72;
}

.dsf-showcase-hero__chip svg {
  color: var(--dsf-theme-primary, #0091ff);
}

/* ---- Mosaic ---- */
.dsf-showcase-hero__mosaic {
  display: grid;
  width: 100%;
  min-width: 0;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  grid-template-rows: repeat(3, minmax(0, 1fr));
  gap: 12px;
  height: clamp(510px, 65svh, 650px);
  min-height: 0;
}

.dsf-showcase-tile {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 0.65rem;
  min-height: 0;
  min-width: 0;
  padding: 0.78rem;
  border-radius: 18px;
  border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 10%, transparent);
  background: color-mix(in srgb, #ffffff 76%, transparent);
  backdrop-filter: blur(10px);
  color: inherit;
  text-decoration: none;
  overflow: hidden;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
}

.dsf-showcase-tile:hover,
.dsf-showcase-tile.is-active {
  transform: translateY(-3px);
  border-color: color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 68%, transparent);
  box-shadow: 0 24px 50px -27px color-mix(in srgb, var(--dsf-landing-text, #111827) 55%, transparent);
}

.dsf-showcase-tile.is-active {
  background: color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 5%, #ffffff);
  box-shadow:
    0 24px 52px -28px color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 66%, transparent),
    inset 0 0 0 1px color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 16%, transparent);
}

.dsf-showcase-tile__label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-weight: 750;
  font-size: 0.88rem;
  letter-spacing: -0.01em;
  min-width: 0;
}

.dsf-showcase-tile__label > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.1rem;
}

.dsf-showcase-tile__label small {
  color: var(--dsf-theme-primary, #0091ff);
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  line-height: 1.15;
  text-transform: uppercase;
  opacity: 0.8;
}

.dsf-showcase-tile__label svg {
  color: var(--dsf-theme-primary, #0091ff);
  transition: transform 0.18s ease;
}

.dsf-showcase-tile:hover .dsf-showcase-tile__label svg,
.dsf-showcase-tile.is-active .dsf-showcase-tile__label svg {
  transform: translate(2px, -2px);
}

.dsf-showcase-tile__image {
  flex: 1;
  min-height: 0;
  border-radius: 11px;
  overflow: hidden;
  background: color-mix(in srgb, var(--dsf-landing-text, #111827) 5%, transparent);
}

.dsf-showcase-tile__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-showcase-tile__scene {
  flex: 1;
  position: relative;
  min-height: 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 5%, transparent);
  border-radius: 11px;
  padding: 10px;
  background:
    radial-gradient(120% 130% at 100% 0%, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 10%, transparent), transparent 62%),
    linear-gradient(135deg, color-mix(in srgb, #ffffff 76%, transparent), color-mix(in srgb, var(--dsf-landing-text, #111827) 3.5%, transparent));
  overflow: hidden;
}

.dsf-showcase-tile__glyph {
  align-self: center;
  display: inline-flex;
  padding: 12px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 14%, transparent);
  color: var(--dsf-theme-primary, #0091ff);
}

/* Scene: design */
.dsf-scene-design__topline { display: flex; align-items: center; justify-content: space-between; color: color-mix(in srgb, var(--dsf-landing-text, #111827) 58%, transparent); }
.dsf-scene-design__topline small { font-size: 0.57rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.dsf-scene-design__swatches { display: flex; gap: 5px; }
.dsf-scene-design__swatches i { width: 15px; height: 15px; border-radius: 4px; box-shadow: inset 0 0 0 1px rgba(17, 24, 39, 0.06); }
.dsf-scene-design__swatches i:nth-child(1) { background: var(--dsf-theme-primary, #0091ff); }
.dsf-scene-design__swatches i:nth-child(2) { background: #111827; }
.dsf-scene-design__swatches i:nth-child(3) { background: #ff7a18; }
.dsf-scene-design__swatches i:nth-child(4) { background: #fff; border: 1px solid rgba(0, 0, 0, 0.12); }
.dsf-scene-design__type { display: flex; align-items: center; gap: 8px; }
.dsf-scene-design__type > b { font-family: var(--dsf-theme-heading-font, inherit); font-size: 1.45rem; line-height: 1; }
.dsf-scene-design__font { display: flex; flex: 1; flex-direction: column; gap: 1px; padding: 4px 7px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 11%, transparent); border-radius: 6px; background: rgba(255, 255, 255, 0.62); }
.dsf-scene-design__font strong { font-size: 0.58rem; line-height: 1.15; }
.dsf-scene-design__font small { font-size: 0.5rem; opacity: 0.58; }
.dsf-scene-design__spacing { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 6px; }
.dsf-scene-design__spacing small { font-size: 0.5rem; font-weight: 700; }
.dsf-scene-design__spacing > i { position: relative; height: 2px; border-radius: 2px; background: color-mix(in srgb, var(--dsf-landing-text, #111827) 13%, transparent); }
.dsf-scene-design__spacing > i b { position: absolute; top: 50%; left: 58%; width: 7px; height: 7px; border: 2px solid var(--dsf-theme-primary, #0091ff); border-radius: 999px; background: #fff; transform: translate(-50%, -50%); }
.dsf-scene-design__spacing em { padding: 3px 5px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 10%, transparent); border-radius: 5px; background: rgba(255,255,255,0.68); font-size: 0.52rem; font-style: normal; }

/* Scene: builder */
.dsf-scene-builder__chrome { display: flex; align-items: center; gap: 3px; }
.dsf-scene-builder__chrome > i { width: 4px; height: 4px; border-radius: 999px; background: color-mix(in srgb, var(--dsf-landing-text, #111827) 22%, transparent); }
.dsf-scene-builder__chrome small { display: inline-flex; align-items: center; gap: 3px; margin-left: auto; padding: 2px 5px; border-radius: 4px; background: color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 10%, transparent); color: var(--dsf-theme-primary, #0091ff); font-size: 0.48rem; font-weight: 700; }
.dsf-scene-builder__canvas { display: grid; grid-template-columns: 1fr 45px; align-items: stretch; gap: 5px; min-height: 58px; }
.dsf-scene-builder__toolbar { position: absolute; z-index: 2; top: 26px; left: 14px; display: inline-flex; align-items: center; gap: 3px; padding: 3px 4px; border-radius: 4px; background: #087df1; color: #fff; box-shadow: 0 4px 10px rgba(0, 91, 190, 0.22); }
.dsf-scene-builder__toolbar i { width: 5px; height: 2px; border-radius: 2px; background: rgba(255,255,255,0.72); }
.dsf-scene-builder__block { position: relative; display: flex; align-items: center; padding: 8px; border: 1.5px dashed var(--dsf-theme-primary, #0091ff); border-radius: 5px; background: rgba(255,255,255,0.6); }
.dsf-scene-builder__block b { font-size: 0.62rem; line-height: 1.05; letter-spacing: -0.03em; text-transform: capitalize; }
.dsf-scene-builder__block > i { position: absolute; width: 5px; height: 5px; border-radius: 1px; background: var(--dsf-theme-primary, #0091ff); }
.dsf-scene-builder__block > i:nth-of-type(1) { top: -3px; left: -3px; }
.dsf-scene-builder__block > i:nth-of-type(2) { top: -3px; right: -3px; }
.dsf-scene-builder__block > i:nth-of-type(3) { bottom: -3px; right: -3px; }
.dsf-scene-builder__block > i:nth-of-type(4) { bottom: -3px; left: -3px; }
.dsf-scene-builder__control { display: flex; flex-wrap: wrap; align-content: center; gap: 2px; padding: 5px; border-left: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 8%, transparent); }
.dsf-scene-builder__control small { width: 100%; font-size: 0.43rem; font-weight: 700; }
.dsf-scene-builder__control b { padding: 3px 4px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 11%, transparent); border-radius: 4px; background: #fff; font-size: 0.48rem; }
.dsf-scene-builder__control em { align-self: center; font-size: 0.42rem; font-style: normal; opacity: 0.55; }
.dsf-scene-builder__dock { align-self: center; display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 999px; background: #202a35; box-shadow: 0 5px 12px rgba(10, 20, 30, 0.24); }
.dsf-scene-builder__dock i { width: 5px; height: 5px; border: 1px solid rgba(255,255,255,0.58); border-radius: 2px; }
.dsf-scene-builder__dock i.is-active { width: 8px; height: 8px; border: 0; background: var(--dsf-theme-primary, #0091ff); box-shadow: 0 0 0 3px rgba(0,145,255,0.2); }

/* Scene: commerce */
.dsf-scene-commerce__product { display: grid; grid-template-columns: 42% 1fr; gap: 8px; flex: 1; }
.dsf-scene-commerce__img { display: inline-flex; align-items: center; justify-content: center; min-height: 60px; border-radius: 8px; background: linear-gradient(145deg, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 18%, #eaf5ff), #f8fbff); color: var(--dsf-theme-primary, #0091ff); }
.dsf-scene-commerce__info { display: flex; flex-direction: column; justify-content: center; gap: 3px; }
.dsf-scene-commerce__info > small { font-size: 0.53rem; font-weight: 750; text-transform: capitalize; }
.dsf-scene-commerce__info > b { font-size: 0.72rem; }
.dsf-scene-commerce__info > span { display: inline-flex; align-self: flex-start; overflow: hidden; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 11%, transparent); border-radius: 4px; background: #fff; }
.dsf-scene-commerce__info > span i,
.dsf-scene-commerce__info > span em { min-width: 16px; padding: 2px 4px; text-align: center; font-size: 0.5rem; font-style: normal; }
.dsf-scene-commerce__info > span em { border-inline: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 8%, transparent); }
.dsf-scene-commerce__row { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
.dsf-scene-commerce__success { display: inline-flex; align-items: center; gap: 3px; color: #15803d; font-size: 0.48rem; font-weight: 650; }
.dsf-scene-commerce__buy { padding: 4px 8px; border-radius: 5px; background: var(--dsf-theme-primary, #0091ff); color: #fff; font-size: 0.5rem; font-weight: 750; box-shadow: 0 4px 10px color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 26%, transparent); }

/* Scene: forms */
.dsf-scene-forms__row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.dsf-scene-forms__row > span,
.dsf-scene-forms__message { display: flex; flex-direction: column; gap: 3px; }
.dsf-scene-forms__row small,
.dsf-scene-forms__message small { font-size: 0.47rem; font-weight: 700; }
.dsf-scene-forms__row i { height: 15px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 14%, transparent); border-radius: 4px; background: rgba(255,255,255,0.72); }
.dsf-scene-forms__message i { height: 25px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 14%, transparent); border-radius: 4px; background: rgba(255,255,255,0.72); }
.dsf-scene-forms__actions { display: flex; align-items: center; gap: 7px; }
.dsf-scene-forms__send { display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; border-radius: 5px; background: var(--dsf-theme-primary, #0091ff); color: #fff; font-size: 0.5rem; font-weight: 750; }
.dsf-scene-forms__success { display: inline-flex; align-items: center; gap: 3px; color: #15803d; font-size: 0.47rem; font-weight: 650; }
.dsf-scene-forms__success svg { padding: 1px; border-radius: 999px; background: #16a34a; color: #fff; }

/* Scene: security */
.dsf-showcase-tile--security .dsf-showcase-tile__scene { flex-direction: row; align-items: center; gap: 12px; }
.dsf-scene-security__shield { display: inline-flex; flex: 0 0 auto; padding: 12px; border: 1px solid color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 28%, transparent); border-radius: 15px; background: linear-gradient(145deg, color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 16%, #fff), color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 5%, #fff)); color: var(--dsf-theme-primary, #0091ff); box-shadow: 0 9px 22px -12px color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 58%, transparent); }
.dsf-scene-security__checks { display: flex; flex: 1; flex-direction: column; gap: 5px; }
.dsf-scene-security__checks span { display: flex; align-items: center; gap: 5px; white-space: nowrap; font-size: 0.49rem; font-weight: 650; }
.dsf-scene-security__checks i { display: inline-flex; align-items: center; justify-content: center; width: 13px; height: 13px; border-radius: 999px; background: #16a34a; color: #fff; }

/* Scene: agency / reusable page structure */
.dsf-showcase-tile--agency .dsf-showcase-tile__scene { flex-direction: row; align-items: center; gap: 0; }
.dsf-scene-agency__pages { display: flex; flex: 1; flex-direction: column; gap: 4px; }
.dsf-scene-agency__pages > span { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 2px 5px; padding: 4px 5px; border: 1px solid color-mix(in srgb, var(--dsf-landing-text, #111827) 8%, transparent); border-radius: 5px; background: rgba(255,255,255,0.66); }
.dsf-scene-agency__pages svg { grid-row: span 2; color: color-mix(in srgb, var(--dsf-landing-text, #111827) 52%, transparent); }
.dsf-scene-agency__pages b { font-size: 0.47rem; line-height: 1; }
.dsf-scene-agency__pages small { font-size: 0.4rem; line-height: 1; opacity: 0.52; }
.dsf-scene-agency__connections { display: flex; width: 24px; flex-direction: column; gap: 15px; }
.dsf-scene-agency__connections i { width: 100%; height: 1px; background: var(--dsf-theme-primary, #0091ff); transform-origin: left center; }
.dsf-scene-agency__reusable { display: flex; width: 42%; min-height: 62px; flex-direction: column; align-items: center; justify-content: center; gap: 5px; border: 1.5px dashed color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 58%, transparent); border-radius: 7px; background: color-mix(in srgb, var(--dsf-theme-primary, #0091ff) 5%, transparent); color: var(--dsf-theme-primary, #0091ff); }
.dsf-scene-agency__reusable small { font-size: 0.45rem; font-weight: 750; }

/* ---- Responsive ---- */
@media (max-width: 1080px) {
  .dsf-showcase-hero__inner {
    grid-template-columns: minmax(0, 0.82fr) minmax(500px, 1.18fr);
    gap: 2rem;
  }

  .dsf-showcase-hero__title {
    font-size: clamp(2.8rem, 5.6vw, 4.1rem);
  }
}

@media (max-width: 900px) {
  .dsf-showcase-hero {
    min-height: auto;
    padding-top: clamp(2rem, 7vw, 3.5rem);
    padding-bottom: 7rem;
  }

  .dsf-showcase-hero__inner {
    max-width: 100%;
    grid-template-columns: minmax(0, 1fr);
    gap: 2.4rem;
  }

  .dsf-showcase-hero__copy {
    width: 100%;
    max-width: 680px;
  }

  .dsf-showcase-hero__title {
    max-width: 11ch;
    font-size: clamp(3.25rem, 10vw, 5.2rem);
  }

  .dsf-showcase-hero__mosaic {
    height: auto;
    grid-auto-rows: minmax(180px, auto);
  }

  .dsf-showcase-tile {
    min-height: 180px;
  }
}

@media (max-width: 560px) {
  .dsf-showcase-hero {
    padding-inline: 1rem;
  }

  .dsf-showcase-hero__title {
    font-size: clamp(2.7rem, 11.5vw, 3.75rem);
  }

  .dsf-showcase-hero__actions {
    width: 100%;
  }

  .dsf-hero-button {
    justify-content: center;
  }

  .dsf-showcase-hero__mosaic {
    gap: 9px;
    grid-auto-rows: minmax(168px, auto);
  }

  .dsf-showcase-tile {
    min-height: 168px;
    padding: 0.58rem;
    border-radius: 15px;
  }

  .dsf-showcase-tile__scene {
    padding: 7px;
  }

  .dsf-showcase-tile__label {
    font-size: 0.78rem;
  }

  .dsf-scene-security__checks span:nth-child(3),
  .dsf-scene-agency__pages > span:nth-child(3) {
    display: none;
  }

  .dsf-scene-security__shield {
    padding: 8px;
  }

  .dsf-scene-builder__canvas {
    grid-template-columns: 1fr 36px;
  }
}

@media (max-width: 340px) {
  .dsf-showcase-hero__mosaic {
    grid-template-columns: 1fr;
  }

  .dsf-showcase-tile {
    min-height: 176px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dsf-showcase-hero__glow,
  .dsf-showcase-hero__word,
  .dsf-showcase-tile,
  .dsf-showcase-tile__scene,
  .dsf-showcase-tile__label svg,
  .dsf-showcase-hero__cycle-dots i {
    animation: none !important;
    transition-duration: 0.01ms !important;
  }

  .dsf-showcase-tile:hover,
  .dsf-showcase-tile.is-active {
    transform: none;
  }
}
</style>
