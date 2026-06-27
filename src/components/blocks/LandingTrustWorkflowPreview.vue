<template>
  <section :id="panel.id" ref="root" class="dsf-trust" :class="`is-${panel.id}`" :style="blockStyle">
    <div class="dsf-trust__heading" data-dsf-reveal>
      <InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" />
      <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" placeholder="Title" />
      <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" placeholder="Description" />
    </div>

    <div v-if="layout === 'pipeline'" class="dsf-trust__seo-wrap">
      <div class="dsf-trust__seo">
        <template v-for="(item, index) in resolvedItems" :key="index">
          <article data-dsf-card><component :is="iconFor(item.icon)" :size="24" /><span>{{ item.title }}</span><small>{{ item.note }}</small></article>
          <ArrowRight v-if="index < resolvedItems.length - 1" :size="20" aria-hidden="true" />
        </template>
      </div>
      <svg class="dsf-trust__seo-flow" viewBox="0 0 1000 24" preserveAspectRatio="none" data-dsf-draw aria-hidden="true">
        <path d="M40 12 H960" data-dsf-draw-path />
        <circle v-for="(item, index) in resolvedItems" :key="index" :cx="nodeX(index)" cy="12" r="7" data-dsf-draw-node />
      </svg>
      <p v-if="settings.caption" class="dsf-trust__seo-caption" data-dsf-reveal>{{ settings.caption }}</p>
    </div>

    <div v-else-if="layout === 'grid-light'" class="dsf-trust__audience">
      <article v-for="(item, index) in resolvedItems" :key="index" data-dsf-card>
        <small>{{ item.note || String(index + 1).padStart(2, '0') }}</small>
        <component :is="iconFor(item.icon)" :size="30" />
        <h3>{{ item.title }}</h3>
        <p>{{ item.description }}</p>
      </article>
    </div>

    <ol v-else-if="layout === 'numbered'" class="dsf-trust__workflow">
      <li v-for="(item, index) in resolvedItems" :key="index" data-dsf-card>
        <span>{{ String(index + 1).padStart(2, '0') }}</span>
        <div><h3>{{ item.title }}</h3><p>{{ item.description }}</p></div>
      </li>
    </ol>

    <div v-else class="dsf-trust__security">
      <article v-for="(item, index) in resolvedItems" :key="index" data-dsf-card>
        <span><component :is="iconFor(item.icon)" :size="23" /></span>
        <h3>{{ item.title }}</h3>
        <p>{{ item.description }}</p>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ArrowRight } from 'lucide-vue-next'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import { iconFor } from '../../utils/landingIcons'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const root = ref(null)
const blockStyle = computed(() => landingBlockStyle(props.settings))

const panels = {
  seo: { id: 'seo' },
  security: { id: 'security' },
  audience: { id: 'audience' },
  workflow: { id: 'workflow' },
}
const panel = computed(() => panels[props.settings.variant] || panels.seo)

// Variant determines the section id/background; layout determines the renderer.
const VARIANT_LAYOUT = { seo: 'pipeline', security: 'grid-dark', audience: 'grid-light', workflow: 'numbered' }
const layout = computed(() => props.settings.layout || VARIANT_LAYOUT[panel.value.id] || 'grid-dark')

const defaultItems = {
  seo: [
    { icon: 'paintbrush', title: 'Visual editor', note: 'Your intent' },
    { icon: 'file-code', title: 'HTML snapshot', note: 'Saved markup' },
    { icon: 'globe', title: 'WordPress page', note: 'Native URL' },
    { icon: 'file-search', title: 'Search engine', note: 'Readable content' },
    { icon: 'monitor', title: 'Live experience', note: 'Interactive page' },
  ],
  security: [
    { icon: 'shield-check', title: 'Sanitized on save', description: 'Known settings pass through block-specific server contracts before they reach the database.' },
    { icon: 'code', title: 'Escaped on output', description: 'Text, links, media, and rich content use context-aware output rules instead of trust-by-default.' },
    { icon: 'fingerprint', title: 'WordPress permissions', description: 'Nonce and capability checks protect privileged editor and AJAX operations.' },
    { icon: 'lock', title: 'Bounded controls', description: 'Enumerated options and capped collections reduce ambiguity at every input boundary.' },
  ],
  audience: [
    { icon: 'layers', title: 'Design teams', description: 'Create expressive systems without handing every iteration back to development.' },
    { icon: 'briefcase', title: 'Agencies', description: 'Ship client-ready pages faster while preserving guardrails and repeatable quality.' },
    { icon: 'store', title: 'Store teams', description: 'Build product discovery and campaigns around real WooCommerce data.' },
    { icon: 'users', title: 'Site owners', description: 'Update pages confidently through a visual workflow that still feels like WordPress.' },
  ],
  workflow: [
    { title: 'Choose the page', description: 'Start from a normal WordPress page and open it in DesignStudio Flow.' },
    { title: 'Shape the system', description: 'Set your typography, colors, spacing, and reusable page-level defaults.' },
    { title: 'Build with blocks', description: 'Arrange purpose-built sections and customize only what the design should allow.' },
    { title: 'Publish with confidence', description: 'Save the interactive experience and its HTML snapshot to the same WordPress page.' },
  ],
}

const resolvedItems = computed(() => {
  const items = Array.isArray(props.settings.items)
    ? props.settings.items.filter((item) => item && (item.title || item.description))
    : []
  return items.length ? items : (defaultItems[panel.value.id] || defaultItems.security)
})

function nodeX(index) {
  const count = resolvedItems.value.length
  if (count <= 1) return 500
  return 40 + index * (920 / (count - 1))
}

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-trust {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  --ink: var(--dsf-theme-text, #111827);
  padding: clamp(76px, 9vw, 130px) 24px;
  color: var(--ink);
  background: var(--dsf-theme-background, #f7f4ed);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}
/* Security uses a white accent (instead of the orange coral) on its blue field. */
.dsf-trust.is-security { --coral: #fff; color: #fff; background: var(--dsf-theme-primary, #0091ff); }
.dsf-trust.is-audience { background: #f3f0e9; }
.dsf-trust.is-workflow { background: var(--dsf-theme-background, #f7f4ed); }
.dsf-trust__heading { width: min(820px, 100%); margin: 0 auto clamp(42px, 5vw, 70px); text-align: center; }
.dsf-trust__heading > span { color: var(--blue); font-size: 13px; font-weight: 850; letter-spacing: 0.12em; text-transform: uppercase; }
/* Security sits on a blue field, so its eyebrow stays white for contrast. */
.dsf-trust.is-security .dsf-trust__heading > span { color: #fff; }
.dsf-trust__heading h2 { margin: 13px 0 18px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(38px, 4.7vw, 63px); line-height: 1.04; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-trust__heading p { max-width: 690px; margin: 0 auto; color: #5d6a76; font-size: 20px; line-height: 1.58; }
.dsf-trust.is-security .dsf-trust__heading p { color: rgba(255,255,255,0.82); }

.dsf-trust__seo-wrap { width: min(1180px, 100%); margin: 0 auto; }
.dsf-trust__seo { display: flex; align-items: center; justify-content: center; width: 100%; }
.dsf-trust__seo-flow { display: block; width: 100%; height: 24px; margin-top: 18px; overflow: visible; }
.dsf-trust__seo-flow path { fill: none; stroke: var(--coral); stroke-width: 2.4; stroke-linecap: round; }
.dsf-trust__seo-flow circle { fill: var(--blue); stroke: var(--dsf-theme-background, #f7f4ed); stroke-width: 2.5; }
.dsf-trust__seo-caption { max-width: 620px; margin: 18px auto 0; color: #74808a; font-size: 15px; line-height: 1.5; text-align: center; }
.dsf-trust__seo > article { display: grid; place-items: center; flex: 1; min-width: 0; min-height: 165px; padding: 23px 10px; border: 1px solid rgba(12,95,168,0.12); border-radius: 16px; background: rgba(255,255,255,0.72); box-shadow: 0 12px 34px rgba(24,52,71,0.06); text-align: center; transition: transform 220ms ease, box-shadow 220ms ease; }
.dsf-trust__seo > article:hover { transform: translateY(-6px); box-shadow: 0 22px 48px rgba(24,52,71,0.13); }
.dsf-trust__seo article svg { color: var(--blue); }
.dsf-trust__seo article span { margin-top: 13px; font-weight: 800; }
.dsf-trust__seo article small { margin-top: 3px; color: #77838d; }
.dsf-trust__seo > svg { flex: 0 0 auto; margin: 0 8px; color: var(--coral); }

.dsf-trust__security, .dsf-trust__audience { display: grid; grid-template-columns: repeat(4, 1fr); width: min(1180px, 100%); margin: 0 auto; gap: 14px; }
.dsf-trust__security article { padding: 29px; border: 1px solid rgba(255,255,255,0.2); border-radius: 18px; background: rgba(7,27,47,0.14); box-shadow: 0 18px 45px rgba(4,46,80,0.13); transition: transform 220ms ease, background 220ms ease; }
.dsf-trust__security article:hover { background: rgba(7,27,47,0.22); transform: translateY(-6px); }
.dsf-trust__security article > span { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 12px; color: #071b2f; background: var(--coral); }
.dsf-trust__security h3, .dsf-trust__audience h3 { margin: 22px 0 8px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 20px; }
.dsf-trust__security p { margin: 0; color: rgba(255,255,255,0.8); font-size: 15px; line-height: 1.52; }

.dsf-trust__audience article { position: relative; min-height: 270px; padding: 30px; border: 1px solid rgba(12,95,168,0.11); border-radius: 18px; background: rgba(255,255,255,0.68); box-shadow: 0 12px 34px rgba(24,52,71,0.055); transition: transform 220ms ease, box-shadow 220ms ease; }
.dsf-trust__audience article:hover { transform: translateY(-6px); box-shadow: 0 24px 52px rgba(24,52,71,0.13); }
.dsf-trust__audience article > small { position: absolute; top: 22px; right: 22px; color: #b1aaa0; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-weight: 800; }
.dsf-trust__audience article > svg { color: var(--blue); }
.dsf-trust__audience p { margin: 0; color: #65717b; font-size: 16px; line-height: 1.52; }

.dsf-trust__workflow { display: grid; grid-template-columns: repeat(4, 1fr); width: min(1180px, 100%); margin: 0 auto; padding: 0; list-style: none; counter-reset: workflow; }
.dsf-trust__workflow li { position: relative; padding: 0 28px; border-left: 1px solid #d9dfe2; }
.dsf-trust__workflow li:last-child { border-right: 1px solid #d9dfe2; }
.dsf-trust__workflow li > span { display: grid; place-items: center; width: 46px; height: 46px; margin-bottom: 25px; border-radius: 50%; color: #fff; background: var(--blue); font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 13px; font-weight: 850; }
.dsf-trust__workflow h3 { margin: 0 0 9px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 20px; }
.dsf-trust__workflow p { margin: 0; color: #65727d; font-size: 16px; line-height: 1.5; }

@media (max-width: 950px) {
  .dsf-trust__seo { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }.dsf-trust__seo > svg { display: none; }
  .dsf-trust__security, .dsf-trust__audience, .dsf-trust__workflow { grid-template-columns: repeat(2, 1fr); }
  .dsf-trust__workflow { gap: 30px 0; }.dsf-trust__workflow li:nth-child(3) { border-left: 1px solid #d9dfe2; }
}
@media (max-width: 680px) {
  .dsf-trust { padding-right: 18px; padding-left: 18px; }
  .dsf-trust__heading h2 { font-size: 34px; }
  .dsf-trust__seo { grid-template-columns: 1fr; }.dsf-trust__seo > article { grid-template-columns: 36px 1fr; place-items: center start; min-height: auto; text-align: left; }.dsf-trust__seo article svg { grid-row: span 2; }.dsf-trust__seo article span { margin-top: 0; }
  .dsf-trust__security, .dsf-trust__audience, .dsf-trust__workflow { grid-template-columns: 1fr; }
  .dsf-trust__workflow li { display: grid; grid-template-columns: 54px 1fr; padding: 0 0 24px; border: 0; border-bottom: 1px solid #d9dfe2; gap: 14px; }.dsf-trust__workflow li:last-child { border-right: 0; }.dsf-trust__workflow li > span { margin: 0; }
}
</style>
