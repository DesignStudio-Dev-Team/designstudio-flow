<template>
  <section id="blocks" ref="root" class="dsf-block-explorer" :class="{ 'is-static': isEditor }" :style="blockStyle" data-dsf-pin>
    <div class="dsf-block-explorer__stage" data-dsf-parallax-scope>
      <div class="dsf-block-explorer__topline">
        <div class="dsf-block-explorer__intro" data-dsf-reveal>
          <span class="dsf-section-kicker"><i></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
          <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" placeholder="Title" />
          <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" placeholder="Description" />
        </div>
        <div class="dsf-block-explorer__counter" data-dsf-reveal aria-hidden="true">
          <strong>{{ String(activeIndexLabel).padStart(2, '0') }}</strong>
          <span>/ {{ String(items.length).padStart(2, '0') }} items</span>
        </div>
      </div>

      <div class="dsf-block-explorer__toolbar" data-dsf-reveal>
        <div class="dsf-block-explorer__filters" role="group" aria-label="Filter block types">
          <button
            v-for="filter in filters"
            :key="filter.id"
            type="button"
            :class="{ 'is-active': activeFilter === filter.id }"
            :aria-pressed="activeFilter === filter.id"
            @click="selectFilter(filter.id)"
          >{{ filter.label }}</button>
        </div>
        <div class="dsf-block-explorer__controls" aria-label="Block carousel controls">
          <button
            type="button"
            aria-label="Show previous blocks"
            :aria-controls="railId"
            :disabled="railProgress <= 0.005"
            @click="scrollRail(-1)"
          ><ArrowLeft :size="19" aria-hidden="true" /></button>
          <button
            type="button"
            aria-label="Show next blocks"
            :aria-controls="railId"
            :disabled="railProgress >= 0.995"
            @click="scrollRail(1)"
          ><ArrowRight :size="19" aria-hidden="true" /></button>
        </div>
      </div>

      <div :id="railId" ref="rail" class="dsf-block-explorer__rail">
        <div ref="track" class="dsf-block-explorer__track" data-dsf-hscroll>
          <article
            v-for="(block, index) in items"
            :key="index"
            class="dsf-explorer-card"
            :class="{ 'is-dim': !isMatch(block), 'is-focus': isMatch(block) }"
            data-dsf-card
          >
            <div class="dsf-explorer-card__frame">
              <img v-if="block.image" :src="block.image" alt="" class="dsf-explorer-card__img" loading="lazy" />
              <BlockReplica v-else :kind="block.kind || 'generic'" />
              <span v-if="block.category" class="dsf-explorer-card__tag">{{ block.category }}</span>
            </div>
            <div class="dsf-explorer-card__body">
              <div class="dsf-explorer-card__meta">
                <h3>{{ block.title }}</h3>
                <ArrowUpRight :size="18" />
              </div>
              <p>{{ block.description }}</p>
            </div>
          </article>
        </div>
      </div>

      <div class="dsf-block-explorer__footline">
        <InlineText tagName="p" class="dsf-block-explorer__footnote" v-model="settings.footnote" :is-editor="isEditor" placeholder="Footnote" />
        <div class="dsf-block-explorer__progress" aria-hidden="true"><span :style="{ transform: `scaleX(${railProgress})` }"></span></div>
        <span class="dsf-block-explorer__hint" aria-hidden="true">Scroll to explore <ArrowRight :size="14" /></span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ArrowLeft, ArrowRight, ArrowUpRight } from 'lucide-vue-next'
import BlockReplica from './replicas/BlockReplica.vue'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  blockId: { type: [String, Number], default: '' },
})

const root = ref(null)
const rail = ref(null)
const track = ref(null)
const activeFilter = ref('all')
const railProgress = ref(0)
const blockStyle = computed(() => landingBlockStyle(props.settings))
const railId = computed(() => {
  const suffix = String(props.blockId || 'landing-block-explorer').replace(/[^a-zA-Z0-9_-]/g, '')
  return `dsf-block-explorer-rail-${suffix}`
})

const defaultItems = [
  { title: 'Hero', category: 'Heroes', kind: 'hero', description: 'Editorial headline, supporting copy, and CTAs with responsive media.' },
  { title: 'Bento Hero', category: 'Heroes', kind: 'bento', description: 'A modular first impression built from expressive, asymmetric tiles.' },
  { title: 'Spotlight Hero', category: 'Heroes', kind: 'spotlight', description: 'One cinematic image with overlaid copy and a single decisive action.' },
  { title: 'Duo Hero', category: 'Heroes', kind: 'duo', description: 'A balanced split of message and media for product launches.' },
  { title: 'Expander Hero', category: 'Heroes', kind: 'expander', description: 'Interactive panels that open to reveal layered storytelling.' },
  { title: 'Content', category: 'Content', kind: 'content', description: 'Long-form editorial with pull quotes and considered rhythm.' },
  { title: 'FAQ', category: 'Content', kind: 'faq', description: 'Accessible answers in a calm, expandable editorial layout.' },
  { title: 'Text + Image', category: 'Content', kind: 'text-image', description: 'Balanced storytelling for features, services, and brand moments.' },
  { title: 'Features Grid', category: 'Content', kind: 'features', description: 'Scannable capability cards with icons and short, clear copy.' },
  { title: 'Testimonials', category: 'Content', kind: 'testimonials', description: 'Customer voices in a quiet, credible slider with attribution.' },
  { title: 'Countdown', category: 'Marketing', kind: 'countdown', description: 'Time-aware launches with CTAs and expiration messaging.' },
  { title: 'Pricing', category: 'Marketing', kind: 'pricing', description: 'Plan comparisons with a billing toggle and a highlighted tier.' },
  { title: 'Featured Promo', category: 'Marketing', kind: 'featured-promo', description: 'A bold split banner with a curved divider and a focused offer.' },
  { title: 'CTA Banner', category: 'Marketing', kind: 'cta-banner', description: 'A centered conversion band that still feels native to the page.' },
  { title: 'Product Grid', category: 'Ecommerce', kind: 'product-grid', description: 'Searchable, filterable WooCommerce discovery with add-to-cart.' },
  { title: 'Form', category: 'Forms', kind: 'form', description: 'Native or Gravity Forms fields styled to match the page system.' },
  { title: 'Mega Menu Header', category: 'Headers', kind: 'mega-menu', description: 'Multi-column navigation with featured panels and mobile drawer.' },
  { title: 'Footer', category: 'Footers', kind: 'footer', description: 'A structured close with brand statement and link columns.' },
]

const items = computed(() => {
  const fromSettings = Array.isArray(props.settings.items)
    ? props.settings.items.filter((item) => item && item.title)
    : []
  return fromSettings.length ? fromSettings : defaultItems
})

const filters = computed(() => {
  const categories = []
  items.value.forEach((item) => {
    const category = item.category || 'Blocks'
    if (!categories.includes(category)) categories.push(category)
  })
  return [{ id: 'all', label: 'All' }, ...categories.map((category) => ({ id: category, label: category }))]
})

const isMatch = (block) => activeFilter.value === 'all' || (block.category || 'Blocks') === activeFilter.value
const activeIndexLabel = computed(() => items.value.filter(isMatch).length)

let scrollHandler = null
let rafId = 0

const readProgress = () => {
  const st = root.value?._dsfPinST
  if (st) {
    railProgress.value = st.progress
  } else if (rail.value) {
    const max = rail.value.scrollWidth - rail.value.clientWidth
    railProgress.value = max > 0 ? rail.value.scrollLeft / max : 0
  }
}

const queueProgress = () => {
  if (rafId) return
  rafId = window.requestAnimationFrame(() => {
    rafId = 0
    readProgress()
  })
}

function selectFilter(id) {
  activeFilter.value = id
  if (props.isEditor || id === 'all') return

  const firstMatch = items.value.find((block) => (block.category || 'Blocks') === id)
  if (!firstMatch || !track.value) return

  const cards = track.value.querySelectorAll('.dsf-explorer-card')
  const index = items.value.indexOf(firstMatch)
  const cardEl = cards[index]
  if (!cardEl) return

  const st = root.value?._dsfPinST
  if (st) {
    // Pinned (desktop): translate the page scroll so the matching card leads.
    const distance = track.value.scrollWidth - root.value.clientWidth
    const fraction = distance > 0 ? Math.min(1, cardEl.offsetLeft / distance) : 0
    const target = st.start + fraction * (st.end - st.start)
    window.scrollTo({ top: target, behavior: 'smooth' })
  } else {
    cardEl.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' })
  }
}

function scrollRail(direction) {
  if (props.isEditor || !rail.value || !track.value) return

  const firstCard = track.value.querySelector('.dsf-explorer-card')
  const styles = window.getComputedStyle(track.value)
  const gap = parseFloat(styles.columnGap || styles.gap) || 0
  const step = (firstCard?.offsetWidth || rail.value.clientWidth * 0.75) + gap
  const st = root.value?._dsfPinST

  if (st) {
    const target = Math.max(st.start, Math.min(st.end, window.scrollY + direction * step))
    window.scrollTo({ top: target, behavior: 'smooth' })
    return
  }

  rail.value.scrollBy({ left: direction * step, behavior: 'smooth' })
}

onMounted(() => {
  if (props.isEditor) return
  scrollHandler = queueProgress
  window.addEventListener('scroll', scrollHandler, { passive: true })
  rail.value?.addEventListener('scroll', scrollHandler, { passive: true })
  readProgress()
})

onUnmounted(() => {
  if (rafId) window.cancelAnimationFrame(rafId)
  if (scrollHandler) {
    window.removeEventListener('scroll', scrollHandler)
    rail.value?.removeEventListener('scroll', scrollHandler)
  }
})

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-block-explorer {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  --ink: var(--dsf-theme-text, #111827);
  position: relative;
  color: var(--ink);
  background:
    radial-gradient(120% 80% at 80% 0%, rgba(12, 95, 168, 0.06), transparent 60%),
    var(--dsf-theme-background, #f7f4ed);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}

.dsf-block-explorer__stage { display: flex; flex-direction: column; height: auto; min-height: 0; padding: clamp(78px, 8vw, 104px) 0 0; }
.is-static .dsf-block-explorer__stage { height: auto; min-height: 0; padding-bottom: 64px; }

.dsf-block-explorer__topline { display: flex; align-items: flex-end; justify-content: space-between; width: min(1320px, calc(100% - 48px)); margin: 0 auto; gap: 32px; }
.dsf-block-explorer__intro { max-width: 760px; }
.dsf-section-kicker { display: inline-flex; align-items: center; gap: 9px; color: var(--coral); font-size: 12px; font-weight: 850; letter-spacing: 0.14em; text-transform: uppercase; }
.dsf-section-kicker i { width: 22px; height: 2px; background: var(--coral); }
.dsf-block-explorer h2 { max-width: 820px; margin: 14px 0 12px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(34px, 4.2vw, 58px); line-height: 1.02; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-block-explorer__intro p { max-width: 580px; margin: 0; color: #5d6975; font-size: clamp(16px, 1.5vw, 19px); line-height: 1.5; }
.dsf-block-explorer__counter { display: grid; flex: 0 0 auto; text-align: right; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); }
.dsf-block-explorer__counter strong { font-size: clamp(38px, 5vw, 60px); line-height: 0.9; letter-spacing: -0.04em; color: var(--blue); }
.dsf-block-explorer__counter span { margin-top: 4px; color: #8a96a1; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; }

.dsf-block-explorer__toolbar { display: flex; align-items: center; width: min(1320px, calc(100% - 48px)); margin: clamp(20px, 2.5vw, 32px) auto 8px; gap: 16px; }
.dsf-block-explorer__filters { display: flex; flex: 1; flex-wrap: wrap; gap: 8px; }
.dsf-block-explorer__filters button { padding: 9px 15px; border: 1px solid #dfe5e9; border-radius: 999px; color: #4d5a66; background: rgba(255, 255, 255, 0.7); font-family: inherit; font-size: 13.5px; font-weight: 750; cursor: pointer; transition: transform 160ms ease, color 160ms ease, background 160ms ease, border-color 160ms ease; }
.dsf-block-explorer__filters button:hover { transform: translateY(-2px); border-color: rgba(12, 95, 168, 0.4); }
.dsf-block-explorer__filters button.is-active,
.dsf-block-explorer__filters button.is-active:hover,
.dsf-block-explorer__filters button.is-active:focus-visible { color: #fff !important; border-color: var(--blue); background: var(--blue); box-shadow: 0 8px 20px rgba(12, 95, 168, 0.22); }
.dsf-block-explorer__controls { display: flex; flex: 0 0 auto; gap: 8px; }
.dsf-block-explorer__controls button { display: inline-grid; width: 40px; height: 40px; padding: 0; place-items: center; border: 1px solid rgba(12, 95, 168, 0.2); border-radius: 50%; color: var(--blue); background: rgba(255, 255, 255, 0.82); cursor: pointer; transition: transform 160ms ease, color 160ms ease, background 160ms ease, opacity 160ms ease; }
.dsf-block-explorer__controls button:hover:not(:disabled) { transform: translateY(-2px); color: #fff; background: var(--blue); }
.dsf-block-explorer__controls button:focus-visible { outline: 3px solid rgba(12, 95, 168, 0.25); outline-offset: 2px; }
.dsf-block-explorer__controls button:disabled { opacity: 0.35; cursor: not-allowed; }

.dsf-block-explorer__rail { flex: 0 0 auto; min-height: 0; display: flex; align-items: flex-start; overflow: hidden; }
.dsf-block-explorer__track { display: flex; align-items: stretch; gap: clamp(16px, 1.6vw, 22px); padding-block: 6px 0; padding-left: max(24px, calc((100vw - 1320px) / 2 + 24px)); padding-right: clamp(64px, 12vw, 200px); }

.dsf-explorer-card { position: relative; display: flex; flex-direction: column; flex: 0 0 clamp(248px, 24vw, 300px); overflow: hidden; border: 1px solid rgba(12, 95, 168, 0.12); border-radius: 18px; background: rgba(255, 255, 255, 0.86); box-shadow: 0 14px 36px rgba(24, 52, 71, 0.08); backdrop-filter: blur(4px); transition: transform 280ms ease, border-color 280ms ease, box-shadow 280ms ease, opacity 280ms ease; }
.dsf-explorer-card:hover { transform: translateY(-8px); border-color: rgba(12, 95, 168, 0.32); box-shadow: 0 30px 60px rgba(24, 52, 71, 0.18); }
.dsf-explorer-card.is-dim { opacity: 0.32; filter: saturate(0.7); }
.dsf-explorer-card__frame { position: relative; aspect-ratio: 16 / 11; overflow: hidden; border-radius: 12px; margin: 10px 10px 0; box-shadow: inset 0 0 0 1px rgba(12, 95, 168, 0.08); }
.dsf-explorer-card__img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.dsf-explorer-card__tag { position: absolute; top: 9px; left: 9px; z-index: 2; padding: 4px 9px; border-radius: 999px; background: rgba(12, 28, 43, 0.82); color: #fff; font-size: 10px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
.dsf-explorer-card__body { padding: 14px 16px 18px; }
.dsf-explorer-card__meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: var(--blue); }
.dsf-explorer-card__meta h3 { margin: 0; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 20px; color: var(--ink); }
.dsf-explorer-card__body p { margin: 7px 0 0; color: #63707b; font-size: 14px; line-height: 1.45; }

.dsf-block-explorer__footline { display: flex; align-items: center; gap: 18px; width: min(1320px, calc(100% - 48px)); margin: 20px auto 0; }
.dsf-block-explorer__footnote { flex: 1; margin: 0; color: #74808a; font-size: 14px; }
.dsf-block-explorer__progress { position: relative; width: clamp(120px, 22vw, 260px); height: 3px; border-radius: 3px; background: rgba(12, 95, 168, 0.14); overflow: hidden; }
.dsf-block-explorer__progress span { position: absolute; inset: 0; border-radius: 3px; background: linear-gradient(90deg, var(--blue), var(--coral)); transform-origin: left center; transform: scaleX(0); will-change: transform; }
.dsf-block-explorer__hint { display: inline-flex; align-items: center; gap: 6px; color: #8a96a1; font-size: 12px; font-weight: 750; letter-spacing: 0.04em; text-transform: uppercase; }

/* Editor / reduced-motion / tablet & mobile: no pin — fall back to a readable grid. */
@media (max-width: 1023px) {
  .dsf-block-explorer__stage { height: auto; min-height: 0; padding-bottom: 0; }
  .dsf-block-explorer__rail { overflow-x: auto; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory; }
  .dsf-block-explorer__track { padding-right: 24px; }
  .dsf-explorer-card { scroll-snap-align: start; flex-basis: 78vw; }
  .dsf-block-explorer__hint { display: none; }
}

.is-static .dsf-block-explorer__rail { overflow: visible; }
.is-static .dsf-block-explorer__track { flex-wrap: wrap; padding: 14px 24px; }
.is-static .dsf-explorer-card { flex-basis: clamp(220px, 30%, 300px); }

@media (max-width: 600px) {
  .dsf-block-explorer__topline { flex-direction: column; align-items: flex-start; gap: 16px; }
  .dsf-block-explorer__counter { text-align: left; }
  .dsf-block-explorer__toolbar { align-items: flex-end; }
  .dsf-block-explorer__filters { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px; }
  .dsf-block-explorer__filters button { flex: 0 0 auto; }
  .dsf-explorer-card { flex-basis: 84vw; }
  .dsf-block-explorer__footline { flex-wrap: wrap; }
}
</style>
