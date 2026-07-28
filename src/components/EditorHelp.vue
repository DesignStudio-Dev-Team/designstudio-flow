<template>
  <section
    v-if="visible"
    class="dsf-editor-help"
    :class="{ 'dsf-editor-help--tour': isTour, 'dsf-editor-help--keep-target-readable': keepTargetReadable }"
    :aria-live="isTour ? 'polite' : 'off'"
  >
    <div v-if="isTour" class="dsf-editor-help__spotlight" aria-hidden="true"></div>
    <div v-if="targetRect" class="dsf-editor-help__target" :style="targetStyle" aria-hidden="true"></div>
    <div
      v-if="isTour && requiresTargetClick && targetRect"
      class="dsf-editor-help__pointer"
      :style="pointerStyle"
      aria-hidden="true"
    >
      <span>CLICK HERE</span>
      <b>↓</b>
    </div>
    <div class="dsf-editor-help__card" :class="`dsf-editor-help__card--${step}`" :style="cardStyle">
      <button type="button" class="dsf-editor-help__close" aria-label="Close help" @click="emit('close')">×</button>

      <template v-if="isTour">
        <p class="dsf-editor-help__eyebrow">Quick tour · {{ stepNumber }} of 10</p>
        <h2>{{ tour.title }}</h2>
        <p>{{ tour.description }}</p>
        <ul v-if="tour.items?.length" class="dsf-editor-help__list">
          <li v-for="item in tour.items" :key="item">{{ item }}</li>
        </ul>
        <div class="dsf-editor-help__actions">
          <button v-if="['page-settings-detail', 'theme-detail', 'preview', 'history', 'structure'].includes(step)" type="button" class="dsf-editor-help__button" @click="emit('next')">{{ nextLabel }}</button>
          <button v-else-if="step === 'settings'" type="button" class="dsf-editor-help__button" @click="emit('next')">Show Style controls</button>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  step: { type: String, default: '' },
  avoidPanel: Boolean,
  mobileActionsOpen: Boolean,
  blockLibraryOpen: Boolean,
  historyOpen: Boolean,
  structureOpen: Boolean,
  context: { type: Object, default: () => ({ title: 'Need a hand?', tip: 'Select a block to customize it, or use Add block to start building.' }) },
})

const emit = defineEmits(['close', 'next', 'finish', 'start-tour'])
const isTour = computed(() => Boolean(props.step))
// A flashing cue is deliberately kept on every action-oriented stop in the
// second half of the tour, too. New users should never have to infer which
// editor control the copy is referring to.
const requiresTargetClick = computed(() => ['dock', 'theme', 'preview', 'history', 'structure', 'add', 'settings', 'background'].includes(props.step))
const keepTargetReadable = computed(() => (
  props.blockLibraryOpen || props.historyOpen || props.structureOpen ||
  ['page-settings-detail', 'theme-detail', 'settings', 'background'].includes(props.step)
))
const isMobileViewport = ref(false)
const isCompactViewport = ref(false)
const viewportWidth = () => window.visualViewport?.width > 0 ? window.visualViewport.width : window.innerWidth
const viewportHeight = () => window.visualViewport?.height > 0 ? window.visualViewport.height : window.innerHeight
function syncViewport() {
  if (typeof window === 'undefined') return
  const width = viewportWidth()
  isMobileViewport.value = width <= 620
  // Chrome's side panel and vertical tabs reduce the page viewport without
  // making it a phone. Switch the tour to its compact layout before the card
  // can compete with the dock for horizontal space.
  isCompactViewport.value = width <= 840
}
const stepNumber = computed(() => ({ dock: 1, 'page-settings-detail': 2, theme: 3, 'theme-detail': 4, preview: 5, history: 6, structure: 7, add: 8, settings: 9, background: 10 }[props.step] || 1))
const targetSelector = computed(() => ({
  dock: isMobileViewport.value && !props.mobileActionsOpen
    ? '[aria-label="Open editor actions"]'
    : '[data-dsf-help="dock-page-settings"]',
  'page-settings-detail': '[data-dsf-help="page-settings-panel"]',
  theme: '[data-dsf-help="dock-theme"]',
  'theme-detail': '[data-dsf-help="theme-panel"]',
  preview: '[data-dsf-help="dock-preview"]',
  history: '[data-dsf-help="dock-history"]',
  structure: '[data-dsf-help="dock-structure"]',
  add: '[data-dsf-help="dock-add-block"]',
  settings: '[data-dsf-help="customize-block"]',
  background: '[data-dsf-help="background-color"]',
}[props.step] || ''))
const targetRect = ref(null)
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
}

function queueMeasure() {
  if (typeof window === 'undefined') return
  window.clearTimeout(measureTimer)
  measureTimer = window.setTimeout(measureTarget, 30)
}

const targetStyle = computed(() => targetRect.value ? {
  left: `${targetRect.value.left}px`, top: `${targetRect.value.top}px`,
  width: `${targetRect.value.width}px`, height: `${targetRect.value.height}px`,
} : {})

const pointerStyle = computed(() => {
  const rect = targetRect.value
  if (!rect) return {}
  const width = viewportWidth()
  return {
    left: `${Math.round(Math.max(62, Math.min(width - 62, rect.left + (rect.width / 2))))}px`,
    top: `${Math.max(12, Math.round(rect.top - 58))}px`,
  }
})

const cardStyle = computed(() => {
  const rect = targetRect.value
  if (isCompactViewport.value) {
    const dockStep = ['dock', 'theme', 'preview', 'history', 'structure', 'add'].includes(props.step)
    return dockStep
      ? { left: '14px', right: '14px', top: '16px', bottom: 'auto', width: 'auto' }
      : { left: '14px', right: '14px', top: 'auto', bottom: '16px', width: 'auto' }
  }
  if (isTour.value && rect && props.step === 'page-settings-detail') {
    const gap = 16
    const minWidth = 250
    // Keep a real viewport gutter as well as a gap from the settings window.
    // The card uses border-box sizing, so this is its complete visible width.
    const viewportGutter = 16
    const rightSpace = viewportWidth() - (rect.left + rect.width) - gap - viewportGutter
    const leftSpace = rect.left - gap - viewportGutter
    const side = rightSpace >= minWidth ? 'right' : (leftSpace >= minWidth ? 'left' : '')

    if (side) {
      const availableWidth = side === 'right' ? rightSpace : leftSpace
      const width = Math.min(350, Math.floor(availableWidth))
      return {
        left: side === 'right' ? `${Math.round(rect.left + rect.width + gap)}px` : `${Math.round(rect.left - gap - width)}px`,
        right: 'auto',
        top: `${Math.max(16, Math.round(rect.top))}px`,
        bottom: 'auto',
        width: `${width}px`,
      }
    }
  }
  if (isTour.value && props.step === 'dock' && rect) {
    const width = 350
    const left = Math.max(16, Math.min(viewportWidth() - width - 16, rect.left + (rect.width / 2) - (width / 2)))
    return {
      left: `${Math.round(left)}px`,
      right: 'auto',
      top: 'auto',
      bottom: `${Math.max(96, Math.round(viewportHeight() - rect.top + 92))}px`,
    }
  }
  if (isTour.value && rect && ['settings', 'background', 'theme-detail'].includes(props.step) && rect.left > 390) {
    return { right: `calc(100vw - ${Math.round(rect.left)}px + 24px)`, left: 'auto', top: `${Math.max(24, Math.round(rect.top))}px`, bottom: 'auto' }
  }
  if (!isTour.value && props.avoidPanel) return { left: '28px', right: 'auto', top: '90px', bottom: 'auto' }
  return {}
})
const nextLabel = computed(() => ({
  'page-settings-detail': 'Next: Theme',
  'theme-detail': 'Next: responsive preview',
  preview: 'Next: history',
  history: 'Next: structure',
  structure: 'Show me how to add a block',
}[props.step] || 'Continue'))
const waitingMessage = computed(() => ({
  dock: isMobileViewport.value && !props.mobileActionsOpen
    ? 'Tap the flashing CLICK HERE arrow, then open the editor actions.'
    : `${isMobileViewport.value ? 'Tap' : 'Click'} the flashing CLICK HERE arrow, then Page Settings.`,
  theme: 'Click the glowing Theme icon to continue.',
  add: 'Click the glowing Add block icon to continue.',
}[props.step] || 'Follow the glowing control to continue.'))
const tour = computed(() => ({
  dock: {
    title: 'Click here first',
    description: 'Start with the highlighted Page Settings button below. It opens the page title, URL, status, and optional page features.',
  },
  'page-settings-detail': {
    title: 'Set up this page',
    description: 'General contains the page title, URL, parent, and publication status. Other tabs expose relevant page features, such as SEO and popups. Save your changes in this window when you are ready.',
  },
  theme: {
    title: 'Now open Theme',
    description: 'Click Theme to set the page-wide visual foundation before refining individual blocks.',
  },
  'theme-detail': {
    title: 'Keep the page visually consistent',
    description: 'Theme controls the shared palette, typography, and layout defaults. Individual block Style controls build on these choices, so the page remains coherent.',
  },
  preview: {
    title: 'Preview every screen size',
    description: 'Open the Preview button, then choose Desktop, Tablet, or Mobile to check how your page adapts before publishing.',
  },
  history: {
    title: 'Restore an earlier version',
    description: 'History lets you review and restore an earlier saved version whenever you need to undo a bigger change.',
  },
  structure: {
    title: 'Organize your page',
    description: 'Structure gives you a clear outline of the page, so you can jump to, rename, and reorder blocks without hunting through the canvas.',
  },
  add: {
    title: 'Add your first block',
    description: 'Choose Add block in the dock, then pick any block from the library. We will open its controls next.',
  },
  settings: {
    title: 'Fill in your block content',
    description: 'Use Content to add the information you want in this block—headings, copy, links, images, buttons, and other block-specific details.',
  },
  background: {
    title: 'Refine the block’s style',
    description: 'Use Style for background and font colors, margins, padding, and other visual details. The controls build on your website theme, so the block stays visually consistent.',
  },
}[props.step] || {}))

watch([() => props.visible, () => props.step, () => props.mobileActionsOpen, isMobileViewport, isCompactViewport], queueMeasure, { immediate: true })
onMounted(() => {
  syncViewport()
  window.addEventListener('resize', queueMeasure)
  window.addEventListener('resize', syncViewport)
  window.addEventListener('scroll', queueMeasure, true)
  window.visualViewport?.addEventListener?.('resize', syncViewport)
  window.visualViewport?.addEventListener?.('resize', queueMeasure)
  queueMeasure()
})
onBeforeUnmount(() => {
  window.clearTimeout(measureTimer)
  window.removeEventListener('resize', queueMeasure)
  window.removeEventListener('resize', syncViewport)
  window.removeEventListener('scroll', queueMeasure, true)
  window.visualViewport?.removeEventListener?.('resize', syncViewport)
  window.visualViewport?.removeEventListener?.('resize', queueMeasure)
})
</script>

<style scoped>
.dsf-editor-help { position: fixed; inset: 0; z-index: 100001; pointer-events: none; }
.dsf-editor-help__spotlight { position: absolute; z-index: 0; inset: 0; background: rgb(7 18 30 / 20%); }
.dsf-editor-help--keep-target-readable .dsf-editor-help__spotlight { background: transparent; }
.dsf-editor-help__target { position: fixed; z-index: 1; border: 2px solid #55b5ff; border-radius: 14px; box-shadow: 0 0 0 5px rgb(12 95 168 / 24%), 0 0 28px 8px rgb(55 181 255 / 48%); animation: dsf-help-glow 1.45s ease-in-out infinite; pointer-events: none; }
.dsf-editor-help__pointer { position: fixed; z-index: 2; display: grid; justify-items: center; gap: 0; color: #fff; font-size: 12px; font-weight: 900; letter-spacing: .08em; text-shadow: 0 2px 5px rgb(7 18 30 / 42%); transform: translateX(-50%); animation: dsf-help-pointer 780ms ease-in-out infinite; pointer-events: none; }
.dsf-editor-help__pointer span { padding: 6px 9px; border-radius: 999px; background: #e74931; box-shadow: 0 5px 16px rgb(231 73 49 / 46%); white-space: nowrap; }
.dsf-editor-help__pointer b { color: #e74931; font-size: 34px; line-height: .7; -webkit-text-stroke: 1px #fff; }
.dsf-editor-help__card { position: fixed; z-index: 3; box-sizing: border-box; right: 28px; bottom: 96px; width: min(350px, calc(100vw - 32px)); max-height: calc(100dvh - 100px); overflow: auto; padding: 20px; border: 1px solid rgb(12 95 168 / 20%); border-radius: 16px; background: #fff; color: #17202a; box-shadow: 0 22px 70px rgb(15 23 42 / 28%); pointer-events: auto; }
.dsf-editor-help__card--dock { bottom: 102px; }
.dsf-editor-help__card--add { right: 28px; bottom: 102px; }
.dsf-editor-help__card--page-settings-detail { top: 24px; bottom: auto; }
.dsf-editor-help__card--settings, .dsf-editor-help__card--background { top: 90px; bottom: auto; }
.dsf-editor-help h2 { margin: 0 28px 8px 0; font-size: 19px; line-height: 1.2; }
.dsf-editor-help p { margin: 0; color: #53606e; font-size: 14px; line-height: 1.48; }
.dsf-editor-help__eyebrow { margin-bottom: 6px !important; color: #0c5fa8 !important; font-size: 11px !important; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
.dsf-editor-help__close { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: #64748b; cursor: pointer; font-size: 23px; line-height: 1; }
.dsf-editor-help__close:hover { background: #f1f5f9; color: #17202a; }
.dsf-editor-help__list { display: grid; gap: 7px; margin: 13px 0 0; padding-left: 19px; color: #53606e; font-size: 13px; line-height: 1.35; }
.dsf-editor-help__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
.dsf-editor-help__button { border: 0; border-radius: 8px; background: #0c5fa8; color: #fff; padding: 9px 12px; font-size: 13px; font-weight: 700; cursor: pointer; }
.dsf-editor-help__card > .dsf-editor-help__button { margin-top: 24px; }
.dsf-editor-help__button:hover { background: #084d89; }
.dsf-editor-help__link { border: 0; background: transparent; color: #53606e; padding: 6px 2px; font-size: 13px; cursor: pointer; }
.dsf-editor-help__waiting { color: #53606e; font-size: 13px; font-weight: 600; }
@keyframes dsf-help-glow { 50% { box-shadow: 0 0 0 9px rgb(12 95 168 / 12%), 0 0 40px 12px rgb(55 181 255 / 56%); } }
@keyframes dsf-help-pointer { 50% { transform: translate(-50%, 11px) scale(1.07); } }
@media (max-width: 840px) {
  .dsf-editor-help__target { border-radius: 11px; }
  .dsf-editor-help__card, .dsf-editor-help__card--settings, .dsf-editor-help__card--background { max-height: calc(100dvh - 32px); padding: 16px; }
  .dsf-editor-help h2 { font-size: 18px; }
}
</style>
