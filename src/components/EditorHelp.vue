<template>
  <section
    v-if="visible"
    class="dsf-editor-help"
    :class="{ 'dsf-editor-help--tour': isTour, 'dsf-editor-help--keep-target-readable': keepTargetReadable }"
    :aria-live="isTour ? 'polite' : 'off'"
  >
    <div v-if="isTour" class="dsf-editor-help__spotlight" aria-hidden="true"></div>
    <div v-if="targetRect" class="dsf-editor-help__target" :style="targetStyle" aria-hidden="true"></div>

    <!-- The arrow is decorative: every step also names its control in words. -->
    <svg
      v-if="arrow"
      class="dsf-editor-help__arrow"
      :viewBox="`0 0 ${viewport.width} ${viewport.height}`"
      aria-hidden="true"
      focusable="false"
    >
      <!-- White underlay first, so the arrow stays legible over dark headers,
           images, or the dimmed canvas. -->
      <path class="dsf-editor-help__arrow-halo" :d="arrow.shaft" />
      <polygon class="dsf-editor-help__arrow-halo-head" :points="arrow.head" />
      <path class="dsf-editor-help__arrow-shaft" :d="arrow.shaft" />
      <polygon class="dsf-editor-help__arrow-head" :points="arrow.head" />
    </svg>

    <div
      ref="cardEl"
      class="dsf-editor-help__card"
      :class="[`dsf-editor-help__card--${step}`, placement ? `is-anchored is-anchor-${placement}` : '']"
      :style="cardStyle"
    >
      <button type="button" class="dsf-editor-help__close" aria-label="Close help" @click="emit('close')">×</button>

      <template v-if="isTour">
        <p class="dsf-editor-help__eyebrow">Quick tour · {{ stepNumber }} of {{ stepCount }}</p>
        <h2>{{ current.title }}</h2>
        <p>{{ current.description }}</p>
        <ul v-if="currentItems.length" class="dsf-editor-help__list">
          <li v-for="item in currentItems" :key="item.term">
            <strong>{{ item.term }}</strong> — {{ item.detail }}
          </li>
        </ul>
        <div class="dsf-editor-help__actions">
          <button v-if="current.next" type="button" class="dsf-editor-help__button" @click="emit('next')">{{ current.next }}</button>
          <button v-else-if="step === 'background'" type="button" class="dsf-editor-help__button" @click="emit('finish')">Finish tour</button>
          <span v-else class="dsf-editor-help__waiting">{{ waitingMessage }}</span>
          <button type="button" class="dsf-editor-help__link" @click="emit('finish')">Skip tour</button>
        </div>
      </template>

      <template v-else>
        <p class="dsf-editor-help__eyebrow">Help</p>
        <h2>{{ context.title }}</h2>
        <p>{{ context.tip }}</p>
        <button type="button" class="dsf-editor-help__button" @click="emit('start-tour')">Restart quick tour</button>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  step: { type: String, default: '' },
  avoidPanel: Boolean,
  mobileActionsOpen: Boolean,
  context: {
    type: Object,
    default: () => ({ title: 'Need a hand?', tip: 'Select a block to customize it, or use Add block to start building.' }),
  },
})

const emit = defineEmits(['close', 'next', 'finish', 'start-tour'])

const CARD_WIDTH = 350
const CARD_GAP = 58
const CARD_GAP_NO_ARROW = 24
const EDGE = 16

const isTour = computed(() => Boolean(props.step))
const keepTargetReadable = computed(() => ['theme-detail', 'settings', 'background'].includes(props.step))

const isMobileViewport = ref(false)
const viewport = ref({ width: 1280, height: 800 })

function syncViewport() {
  if (typeof window === 'undefined') return
  isMobileViewport.value = window.innerWidth <= 620
  viewport.value = { width: window.innerWidth, height: window.innerHeight }
}

/**
 * One definition per step: what it points at, what it says, and how it advances.
 *
 * `next` is a button label for steps the reader just reads; steps without one
 * wait for the reader to operate the highlighted control themselves.
 */
const STEPS = {
  dock: {
    target: () =>
      isMobileViewport.value && !props.mobileActionsOpen
        ? '[aria-label="Open editor actions"]'
        : '[data-dsf-help="dock-settings"]',
    title: 'Settings — everything about this page',
    description:
      'The dock runs along the bottom of the screen and holds every editor action. Settings is the first icon: it opens one panel with two tabs.',
    items: [
      { term: 'Page', detail: 'the WordPress title, URL, parent page, publish status, SEO fields, and popup.' },
      { term: 'Theme', detail: 'colours, fonts, and layout widths shared by every block on the page.' },
    ],
    waiting: () => `${isMobileViewport.value ? 'Tap' : 'Click'} the highlighted Settings icon to open it.`,
  },
  'page-settings-detail': {
    arrow: false,
    target: '[data-dsf-help="page-settings-panel"]',
    // The whole form is the subject here, so the ring frames it and no arrow is
    // drawn — there is no single control to aim at.
    title: 'The Page tab',
    description:
      'Everything WordPress needs to publish this page. Change the title here rather than in the block canvas — this is the title used in menus, search results, and the browser tab.',
    items: [
      { term: 'Slug', detail: 'the last part of the URL. Leave it blank and WordPress builds one from the title.' },
      { term: 'Status', detail: 'Draft keeps the page private; Published makes it live.' },
      { term: 'SEO', detail: 'the title and description search engines show, with a live preview of the result.' },
    ],
    next: 'Next: the Theme tab',
  },
  theme: {
    target: '[data-dsf-help="settings-tab-theme"]',
    title: 'Switch to the Theme tab',
    description:
      'Theme sets the visual foundation for the whole page. Settle it before styling individual blocks, because every block starts from these values.',
    waiting: 'Click the highlighted Theme tab to continue.',
  },
  'theme-detail': {
    arrow: false,
    target: '[data-dsf-help="theme-panel"]',
    title: 'Colours, fonts, and width',
    description:
      'Change something here and every block follows immediately. Undo at the top of the panel steps back through theme changes only, so you can experiment without touching your content.',
    items: [
      { term: 'Primary colour', detail: 'buttons, links, and accents across the page.' },
      { term: 'Heading and body fonts', detail: 'applied to every block that does not override them.' },
      { term: 'Container width', detail: 'how wide content runs before it stops growing.' },
    ],
    next: 'Next: responsive preview',
  },
  preview: {
    target: '[data-dsf-help="dock-preview"]',
    title: 'Check every screen size',
    description:
      'This control shows which size you are editing at. Open it and pick Desktop, Tablet, or Mobile — the canvas resizes so you can fix layout problems before anyone else sees them.',
    items: [
      { term: 'Per-device settings', detail: 'many block controls remember a separate value for each size.' },
    ],
    next: 'Next: saving',
  },
  save: {
    target: '[data-dsf-help="dock-save"]',
    title: 'Saving your work',
    description:
      'The green Save button opens its options rather than saving straight away, so the two ways of saving sit together.',
    items: [
      { term: 'Save page', detail: 'stores your changes on this page.' },
      { term: 'Save as template', detail: 'keeps the whole design so you can start another page from it.' },
      { term: 'Structure', detail: 'sits just before Save — a list of every block, for jumping around long pages.' },
    ],
    next: 'Next: quick actions',
  },
  organize: {
    target: '[data-dsf-help="dock-help"]',
    title: 'Quick actions',
    description: 'These sit together on the right of the dock and stay available while you build.',
    items: () =>
      [
        { term: 'Help', detail: 'tips for whatever you are doing, and restarts this tour.' },
        multilingualActive.value
          ? { term: 'Language', detail: 'every language this page exists in, and creates the missing ones.' }
          : null,
        { term: 'History', detail: 'restores an earlier saved version of the page.' },
        { term: 'View page', detail: 'opens the live page in a new tab.' },
      ].filter(Boolean),
    next: 'Show me how to add a block',
  },
  add: {
    target: '[data-dsf-help="dock-add-block"]',
    title: 'Add your first block',
    description:
      'Add block sits at the centre of the dock, the largest target because it is the one you reach for most. It opens a library of ready-made sections — heroes, galleries, pricing tables, forms.',
    waiting: 'Click the highlighted Add block icon, then choose any block.',
  },
  settings: {
    arrow: false,
    target: '[data-dsf-help="customize-block"]',
    title: 'The Content tab',
    description:
      'Selecting a block opens its controls beside the canvas. Content holds the words and media: headings, body copy, links, buttons, and images. Edits appear on the canvas as you type.',
    next: 'Next: the Style tab',
  },
  background: {
    target: '[data-dsf-help="style-tab"]',
    title: 'The Style tab',
    description:
      'Style covers how the block looks: background and text colours, spacing above and below, and alignment. These start from your Theme values, so a block stays consistent until you deliberately change it.',
    items: [
      { term: 'Leave a control untouched', detail: 'and it keeps following the Theme, including later theme changes.' },
    ],
  },
}

const TOUR_STEPS = Object.keys(STEPS)
const stepCount = TOUR_STEPS.length
const stepNumber = computed(() => Math.max(1, TOUR_STEPS.indexOf(props.step) + 1))
const current = computed(() => STEPS[props.step] || {})

const multilingualActive = computed(
  () => typeof window !== 'undefined' && Boolean(window.dsfEditorData?.translation?.active),
)

const currentItems = computed(() => {
  const items = current.value.items
  const resolved = typeof items === 'function' ? items() : items
  return Array.isArray(resolved) ? resolved : []
})

const waitingMessage = computed(() => {
  const waiting = current.value.waiting
  const resolved = typeof waiting === 'function' ? waiting() : waiting
  return resolved || 'Follow the highlighted control to continue.'
})

const targetSelector = computed(() => {
  const target = current.value.target
  return (typeof target === 'function' ? target() : target) || ''
})

const targetRect = ref(null)
const cardEl = ref(null)
const cardRect = ref(null)
let measureTimer = null

function measureTarget() {
  if (typeof document === 'undefined' || !props.visible || !targetSelector.value) {
    targetRect.value = null
    return
  }
  const target = document.querySelector(targetSelector.value)
  if (!target) {
    targetRect.value = null
    return
  }
  const rect = target.getBoundingClientRect()
  targetRect.value = { left: rect.left - 6, top: rect.top - 6, width: rect.width + 12, height: rect.height + 12 }
  measureCard()
}

/** The arrow needs the card's real box, which is only known after it renders. */
function measureCard() {
  nextTick(() => {
    if (!cardEl.value || typeof cardEl.value.getBoundingClientRect !== 'function') {
      cardRect.value = null
      return
    }
    const rect = cardEl.value.getBoundingClientRect()
    cardRect.value = { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
  })
}

function queueMeasure() {
  if (typeof window === 'undefined') return
  window.clearTimeout(measureTimer)
  measureTimer = window.setTimeout(measureTarget, 30)
}

const targetStyle = computed(() =>
  targetRect.value
    ? {
        left: `${targetRect.value.left}px`,
        top: `${targetRect.value.top}px`,
        width: `${targetRect.value.width}px`,
        height: `${targetRect.value.height}px`,
      }
    : {},
)

/**
 * Which side of the target the card sits on.
 *
 * The dock runs along the bottom and the settings panel down one side, so the
 * card takes the opposite edge and the arrow spans the gap.
 */
const placement = computed(() => {
  const rect = targetRect.value
  if (!rect || !isTour.value || isMobileViewport.value) return ''

  const { width: vw, height: vh } = viewport.value
  if (rect.height > vh * 0.5) return rect.left > vw * 0.5 ? 'right' : 'left'
  return rect.top > vh * 0.55 ? 'bottom' : 'top'
})

const cardStyle = computed(() => {
  const rect = targetRect.value
  if (isMobileViewport.value) {
    const dockStep = ['dock', 'theme', 'preview', 'save', 'organize', 'add'].includes(props.step)
    return dockStep
      ? { left: '14px', right: '14px', top: '16px', bottom: 'auto', width: 'auto' }
      : { left: '14px', right: '14px', top: 'auto', bottom: '16px', width: 'auto' }
  }

  const side = placement.value
  if (!side || !rect) {
    if (!isTour.value && props.avoidPanel) return { left: '28px', right: 'auto', top: '90px', bottom: 'auto' }
    return {}
  }

  const { width: vw, height: vh } = viewport.value
  const gap = current.value.arrow === false ? CARD_GAP_NO_ARROW : CARD_GAP
  const centreX = rect.left + rect.width / 2
  const left = Math.min(Math.max(centreX - CARD_WIDTH / 2, EDGE), Math.max(EDGE, vw - CARD_WIDTH - EDGE))

  if (side === 'bottom') {
    return { left: `${Math.round(left)}px`, right: 'auto', top: 'auto', bottom: `${Math.round(vh - rect.top + gap)}px` }
  }
  if (side === 'top') {
    return { left: `${Math.round(left)}px`, right: 'auto', top: `${Math.round(rect.top + rect.height + gap)}px`, bottom: 'auto' }
  }

  const top = Math.min(Math.max(rect.top, EDGE), Math.max(EDGE, vh - 300))
  if (side === 'right') {
    return { right: `${Math.round(vw - rect.left + gap)}px`, left: 'auto', top: `${Math.round(top)}px`, bottom: 'auto' }
  }
  return { left: `${Math.round(rect.left + rect.width + gap)}px`, right: 'auto', top: `${Math.round(top)}px`, bottom: 'auto' }
})

/**
 * A straight arrow from the card to the control it describes.
 *
 * Drawn rather than composed from a marker so the head is a fixed, obvious size
 * instead of scaling with the stroke, and so the shaft can stop short of the
 * head without poking through it.
 */
const HEAD_LENGTH = 20
const HEAD_HALF_WIDTH = 10
const MIN_ARROW_LENGTH = 46

const arrow = computed(() => {
  const target = targetRect.value
  const card = cardRect.value
  const side = placement.value
  if (!target || !card || !side || isMobileViewport.value) return null
  if (current.value.arrow === false) return null

  const centre = { x: target.left + target.width / 2, y: target.top + target.height / 2 }
  let from
  let to

  if (side === 'bottom') {
    from = { x: clamp(centre.x, card.left + 28, card.left + card.width - 28), y: card.top + card.height + 2 }
    to = { x: centre.x, y: target.top - 6 }
  } else if (side === 'top') {
    from = { x: clamp(centre.x, card.left + 28, card.left + card.width - 28), y: card.top - 2 }
    to = { x: centre.x, y: target.top + target.height + 6 }
  } else if (side === 'right') {
    from = { x: card.left + card.width + 2, y: clamp(centre.y, card.top + 28, card.top + card.height - 28) }
    to = { x: target.left - 6, y: centre.y }
  } else {
    from = { x: card.left - 2, y: clamp(centre.y, card.top + 28, card.top + card.height - 28) }
    to = { x: target.left + target.width + 6, y: centre.y }
  }

  const dx = to.x - from.x
  const dy = to.y - from.y
  const length = Math.hypot(dx, dy)
  // Too short to read as an arrow: the ring alone is clearer than a stub.
  if (length < MIN_ARROW_LENGTH) return null

  const unit = { x: dx / length, y: dy / length }
  const perp = { x: -unit.y, y: unit.x }
  const base = { x: to.x - unit.x * HEAD_LENGTH, y: to.y - unit.y * HEAD_LENGTH }
  // Stop the shaft inside the head so the two read as one solid arrow.
  const shaftEnd = { x: to.x - unit.x * (HEAD_LENGTH * 0.72), y: to.y - unit.y * (HEAD_LENGTH * 0.72) }

  const head = [
    `${round(to.x)},${round(to.y)}`,
    `${round(base.x + perp.x * HEAD_HALF_WIDTH)},${round(base.y + perp.y * HEAD_HALF_WIDTH)}`,
    `${round(base.x - perp.x * HEAD_HALF_WIDTH)},${round(base.y - perp.y * HEAD_HALF_WIDTH)}`,
  ].join(' ')

  return {
    shaft: `M ${round(from.x)} ${round(from.y)} L ${round(shaftEnd.x)} ${round(shaftEnd.y)}`,
    head,
  }
})

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), Math.max(min, max))
}

function round(value) {
  return Math.round(value * 10) / 10
}

watch([() => props.visible, () => props.step, () => props.mobileActionsOpen, isMobileViewport], queueMeasure, {
  immediate: true,
})

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', queueMeasure)
  window.addEventListener('resize', syncViewport)
  window.addEventListener('scroll', queueMeasure, true)
  queueMeasure()
})

onBeforeUnmount(() => {
  window.clearTimeout(measureTimer)
  window.removeEventListener('resize', queueMeasure)
  window.removeEventListener('resize', syncViewport)
  window.removeEventListener('scroll', queueMeasure, true)
})
</script>

<style scoped>
.dsf-editor-help { position: fixed; inset: 0; z-index: 100001; pointer-events: none; }
.dsf-editor-help__spotlight { position: absolute; inset: 0; background: rgb(7 18 30 / 20%); }
.dsf-editor-help--keep-target-readable .dsf-editor-help__spotlight { background: transparent; }
.dsf-editor-help__target { position: fixed; z-index: 1; border: 2px solid #0c5fa8; border-radius: 12px; box-shadow: 0 0 0 4px rgb(12 95 168 / 18%); animation: dsf-help-glow 1.9s ease-in-out infinite; pointer-events: none; }

.dsf-editor-help__arrow { position: fixed; inset: 0; z-index: 2; width: 100vw; height: 100vh; pointer-events: none; overflow: visible; animation: dsf-help-arrow-in .28s ease-out both; }
.dsf-editor-help__arrow-shaft { fill: none; stroke: #0c5fa8; stroke-width: 4.5; stroke-linecap: round; }
.dsf-editor-help__arrow-head { fill: #0c5fa8; }
.dsf-editor-help__arrow-halo { fill: none; stroke: #fff; stroke-width: 9; stroke-linecap: round; }
.dsf-editor-help__arrow-halo-head { fill: none; stroke: #fff; stroke-width: 6; stroke-linejoin: round; }

.dsf-editor-help__card { position: fixed; right: 28px; bottom: 96px; width: min(350px, calc(100vw - 28px)); max-height: calc(100dvh - 100px); overflow: auto; padding: 20px; border: 1px solid rgb(12 95 168 / 20%); border-radius: 16px; background: #fff; color: #17202a; box-shadow: 0 22px 70px rgb(15 23 42 / 28%); pointer-events: auto; z-index: 3; }
.dsf-editor-help__card.is-anchored { right: auto; bottom: auto; }
.dsf-editor-help__card--settings, .dsf-editor-help__card--background { top: 90px; bottom: auto; }
.dsf-editor-help h2 { margin: 0 28px 8px 0; font-size: 19px; line-height: 1.2; }
.dsf-editor-help p { margin: 0; color: #53606e; font-size: 14px; line-height: 1.48; }
.dsf-editor-help__eyebrow { margin-bottom: 6px !important; color: #0c5fa8 !important; font-size: 11px !important; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
.dsf-editor-help__close { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: #64748b; cursor: pointer; font-size: 23px; line-height: 1; }
.dsf-editor-help__close:hover { background: #f1f5f9; color: #17202a; }
.dsf-editor-help__list { display: grid; gap: 9px; margin: 13px 0 0; padding-left: 18px; color: #53606e; font-size: 13px; line-height: 1.4; }
.dsf-editor-help__list strong { color: #17202a; font-weight: 700; }
.dsf-editor-help__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 17px; }
.dsf-editor-help__button { border: 0; border-radius: 8px; background: #0c5fa8; color: #fff; padding: 9px 12px; font-size: 13px; font-weight: 700; cursor: pointer; }
.dsf-editor-help__button:hover { background: #084d89; }
.dsf-editor-help__link { border: 0; background: transparent; color: #53606e; padding: 6px 2px; font-size: 13px; cursor: pointer; }
.dsf-editor-help__waiting { color: #53606e; font-size: 13px; font-weight: 600; }

@keyframes dsf-help-glow { 50% { box-shadow: 0 0 0 8px rgb(12 95 168 / 10%); } }
@keyframes dsf-help-arrow-in { from { opacity: 0; } to { opacity: 1; } }

@media (prefers-reduced-motion: reduce) {
  .dsf-editor-help__target { animation: none; }
  .dsf-editor-help__arrow { animation: none; }
}

@media (max-width: 620px) {
  .dsf-editor-help__target { border-radius: 11px; }
  .dsf-editor-help__arrow { display: none; }
  .dsf-editor-help__card, .dsf-editor-help__card--settings, .dsf-editor-help__card--background { max-height: calc(100dvh - 32px); padding: 16px; }
  .dsf-editor-help h2 { font-size: 18px; }
}
</style>
