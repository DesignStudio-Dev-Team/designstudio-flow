<template>
  <footer id="get-dsflow" ref="root" class="dsf-marketing-footer" :class="`is-${variant}`" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-marketing-footer__backdrop" aria-hidden="true">
      <span class="dsf-marketing-footer__grid" data-dsf-parallax="0.22"></span>
      <span class="dsf-marketing-footer__glow" data-dsf-parallax="0.4"></span>
    </div>
    <div v-if="variant !== 'columns'" class="dsf-marketing-footer__cta">
      <div>
        <InlineText tagName="span" class="dsf-marketing-footer__eyebrow" v-model="settings.eyebrow" :is-editor="isEditor" data-dsf-reveal placeholder="Eyebrow" />
        <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" data-dsf-split placeholder="Final call to action" />
        <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Description" />
      </div>
      <div v-if="isEditor || settings.primaryText || settings.secondaryText" class="dsf-marketing-footer__actions" data-dsf-reveal>
        <a v-if="isEditor || settings.primaryText" class="dsf-footer-button dsf-footer-button--light" :href="safePublicUrl(settings.primaryUrl)" @click="guardEditor">
          {{ settings.primaryText }} <ArrowUpRight :size="18" />
        </a>
        <a v-if="isEditor || settings.secondaryText" class="dsf-footer-button dsf-footer-button--outline" :href="safePublicUrl(settings.secondaryUrl)" @click="guardEditor">{{ settings.secondaryText }}</a>
      </div>
    </div>

    <div v-if="variant !== 'simple'" class="dsf-marketing-footer__main">
      <div class="dsf-marketing-footer__brand" data-dsf-reveal>
        <a :href="safePublicUrl(settings.homeUrl)" @click="guardEditor">
          <img class="dsf-footer-mark" :src="logoUrl" alt="" aria-hidden="true" />
          <span v-if="settings.brandText">{{ settings.brandText }}</span>
          <span v-else>DesignStudio <strong>Flow</strong></span>
        </a>
        <p>{{ settings.brandStatement }}</p>
      </div>
      <nav v-for="column in columns" :key="column.title" :aria-label="column.title" data-dsf-reveal>
        <strong>{{ column.title }}</strong>
        <a v-for="link in column.links" :key="link.label" :href="link.url" @click="guardEditor">{{ link.label }}</a>
      </nav>
    </div>

    <div class="dsf-marketing-footer__bottom">
      <span>© {{ year }} <InlineText tagName="span" v-model="copyrightModel" :is-editor="isEditor" placeholder="Copyright line" /></span>
      <InlineText tagName="span" v-model="taglineModel" :is-editor="isEditor" placeholder="Tagline" />
    </div>
  </footer>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ArrowUpRight } from 'lucide-vue-next'
import { safePublicUrl } from '../../utils/safeUrl'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const root = ref(null)
const year = new Date().getFullYear()
const variant = computed(() => props.settings.variant || 'bigcta')

const copyrightModel = computed({
  get: () => props.settings.copyright ?? 'DesignStudio Flow. Built for WordPress.',
  set: (value) => { props.settings.copyright = value },
})
const taglineModel = computed({
  get: () => props.settings.tagline ?? 'Build freely. Stay beautifully consistent.',
  set: (value) => { props.settings.tagline = value },
})
const hexColor = (value) => (typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : '')

// Relative luminance test so the CTA label is always readable on its background.
const isLight = (hex) => {
  const h = hex.replace('#', '')
  const r = parseInt(h.slice(0, 2), 16)
  const g = parseInt(h.slice(2, 4), 16)
  const b = parseInt(h.slice(4, 6), 16)
  return (0.299 * r + 0.587 * g + 0.114 * b) > 150
}

// Legacy grey link colors (old default + old CSS value) migrate to bright blue.
const LEGACY_LINK_GREY = ['#9fb0bd', '#99a8b5']

// The button and link colors are published as CSS variables on the footer root,
// then consumed by the scoped CSS with `!important`. This is the key fix: the
// colors apply to BOTH the live Vue render AND the saved HTML snapshot (which has
// no inline element styles), and they can never be overwritten by the footer's
// inherited text color. The CSS fallbacks keep older snapshots correct too.
const buttonBg = computed(() => hexColor(props.settings.buttonBgColor) || '#ffffff')
const buttonText = computed(() => {
  const bg = buttonBg.value
  let label = hexColor(props.settings.buttonLabelColor)
  if (!label || isLight(bg) === isLight(label)) {
    label = isLight(bg) ? '#101b26' : '#ffffff'
  }
  return label
})
const linkColor = computed(() => {
  let color = hexColor(props.settings.linksColor)
  if (!color || LEGACY_LINK_GREY.includes(color.toLowerCase())) {
    color = '#0091ff'
  }
  return color
})

const blockStyle = computed(() => ({
  ...landingBlockStyle(props.settings),
  '--dsf-fbtn-bg': buttonBg.value,
  '--dsf-fbtn-text': buttonText.value,
  '--dsf-flink': linkColor.value,
}))
const logoUrl = computed(() => {
  if (props.settings.logoImage) return props.settings.logoImage
  const baseUrl = window.dsfEditorData?.pluginUrl || window.dsfFrontendData?.pluginUrl || ''
  return `${baseUrl}assets/images/dsflow-logo.png`
})

const defaultColumns = [
  { title: 'Product', links: [{ label: 'Why DSFlow', url: '#why-dsflow' }, { label: 'Block library', url: '#blocks' }, { label: 'WooCommerce', url: '#woocommerce' }, { label: 'Forms & growth', url: '#engagement' }] },
  { title: 'Build', links: [{ label: 'Editor experience', url: '#editor' }, { label: 'Theme system', url: '#theme' }, { label: 'Layouts', url: '#layouts' }, { label: 'Workflow', url: '#workflow' }] },
  { title: 'Trust', links: [{ label: 'SEO rendering', url: '#seo' }, { label: 'Security', url: '#security' }, { label: 'For agencies', url: '#audience' }, { label: 'Documentation', url: '#workflow' }] },
]

const columns = computed(() => {
  const fromSettings = [1, 2, 3]
    .map((n) => ({
      title: props.settings[`col${n}Title`],
      links: Array.isArray(props.settings[`col${n}Links`]) ? props.settings[`col${n}Links`] : [],
    }))
    .filter((column) => column.title || column.links.length)

  const source = fromSettings.length ? fromSettings : defaultColumns
  return source.map((column) => ({
    title: column.title || '',
    links: (column.links || []).map((link) => ({ label: link.label || link.url, url: safePublicUrl(link.url || '#') })),
  }))
})

function guardEditor(event) {
  if (props.isEditor) event.preventDefault()
}

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-marketing-footer {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  position: relative;
  overflow: hidden;
  color: #fff;
  background: #101b26;
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}
.dsf-marketing-footer__backdrop { position: absolute; inset: -14% 0; z-index: 0; pointer-events: none; }
.dsf-marketing-footer__grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.045) 1px, transparent 1px); background-size: 58px 58px; -webkit-mask-image: radial-gradient(120% 80% at 30% 0%, #000 30%, transparent 72%); mask-image: radial-gradient(120% 80% at 30% 0%, #000 30%, transparent 72%); }
.dsf-marketing-footer__glow { position: absolute; top: -120px; right: 8%; width: 520px; height: 520px; border-radius: 50%; background: radial-gradient(circle, rgba(0,145,255,0.28), transparent 66%); }
.dsf-marketing-footer__cta { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(300px, 1fr) auto; align-items: end; width: min(1180px, calc(100% - 48px)); margin: 0 auto; padding: clamp(70px, 8vw, 112px) 0; border-bottom: 1px solid rgba(255,255,255,0.13); gap: 50px; }
.dsf-marketing-footer__cta > div:first-child { max-width: 860px; }
.dsf-marketing-footer__cta span { color: var(--dsf-eyebrow-color, var(--blue)); font-size: var(--dsf-eyebrow-size, 14px); font-weight: 850; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-marketing-footer h2 { margin: 13px 0 18px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(40px, 4.7vw, 62px); line-height: 1.02; letter-spacing: -0.05em; }
.dsf-marketing-footer__cta p { max-width: 670px; margin: 0; color: #aebbc6; font-size: 20px; line-height: 1.55; }
.dsf-marketing-footer__actions { display: grid; gap: 10px; }
.dsf-footer-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-width: 178px; min-height: 51px; padding: 0 19px; border: 1px solid transparent; border-radius: 9px; font-size: 15px; font-weight: 800; text-decoration: none; transition: transform 190ms ease, border-color 190ms ease, background 190ms ease, box-shadow 190ms ease; }
.dsf-footer-button:hover { transform: translateY(-3px); }
/* CTA colors come from CSS vars on the footer root so they apply to both the live
   render and the saved HTML snapshot, and `!important` keeps the label readable no
   matter what the footer text color is set to. */
.dsf-footer-button--light,
.dsf-footer-button--light:hover,
.dsf-footer-button--light:focus-visible { color: var(--dsf-fbtn-text, #101b26) !important; background: var(--dsf-fbtn-bg, #fff); box-shadow: 0 15px 34px rgba(0,0,0,0.22); }
.dsf-footer-button--outline { color: #fff; border-color: rgba(255,255,255,0.25); }
.dsf-marketing-footer__main { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(280px, 1.5fr) repeat(3, minmax(130px, 0.55fr)); width: min(1180px, calc(100% - 48px)); margin: 0 auto; padding: 65px 0 55px; gap: 45px; }
.dsf-marketing-footer__brand > a { display: flex; align-items: center; gap: 10px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 18px; font-weight: 750; text-decoration: none; }.dsf-marketing-footer__brand a strong { color: #6fb7e8; }
.dsf-footer-mark { display: block; width: 29px; height: 29px; object-fit: contain; }
.dsf-marketing-footer__brand p { max-width: 350px; margin: 19px 0 0; color: #9fadb9; font-size: 16px; line-height: 1.55; }
.dsf-marketing-footer nav { display: flex; flex-direction: column; align-items: flex-start; gap: 11px; }.dsf-marketing-footer nav strong { margin-bottom: 4px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 14px; }.dsf-marketing-footer nav a { color: var(--dsf-flink, #0091ff) !important; font-size: 14px; text-decoration: none; }.dsf-marketing-footer nav a:hover { color: #fff !important; }
.dsf-marketing-footer__bottom { position: relative; z-index: 1; display: flex; justify-content: space-between; width: min(1180px, calc(100% - 48px)); margin: 0 auto; padding: 22px 0 28px; border-top: 1px solid rgba(255,255,255,0.1); color: #7f909e; font-size: 13px; }

/* Variants */
.dsf-marketing-footer.is-centered .dsf-marketing-footer__cta { grid-template-columns: 1fr; justify-items: center; text-align: center; }
.dsf-marketing-footer.is-centered .dsf-marketing-footer__cta > div:first-child { margin: 0 auto; }
.dsf-marketing-footer.is-centered .dsf-marketing-footer__actions { justify-content: center; }
.dsf-marketing-footer.is-simple .dsf-marketing-footer__cta { border-bottom: 0; }
.dsf-marketing-footer.is-columns .dsf-marketing-footer__main { padding-top: clamp(70px, 8vw, 112px); }

@media (max-width: 820px) {
  .dsf-marketing-footer__cta { grid-template-columns: 1fr; align-items: start; }.dsf-marketing-footer__actions { display: flex; flex-wrap: wrap; }
  .dsf-marketing-footer__main { grid-template-columns: 1.3fr repeat(2, 1fr); }.dsf-marketing-footer__brand { grid-column: span 3; }
}
@media (max-width: 560px) {
  .dsf-marketing-footer__cta, .dsf-marketing-footer__main, .dsf-marketing-footer__bottom { width: calc(100% - 36px); }
  .dsf-marketing-footer__actions { display: grid; }.dsf-marketing-footer__main { grid-template-columns: repeat(2, 1fr); }.dsf-marketing-footer__brand { grid-column: span 2; }
  .dsf-marketing-footer__bottom { display: grid; gap: 8px; }
}
</style>
