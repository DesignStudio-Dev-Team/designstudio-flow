<template>
  <section id="why-dsflow" ref="root" class="dsf-product-hero" :class="{ 'is-center': settings.align === 'center', 'is-media-left': settings.mediaPosition === 'left' }" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-product-hero__backdrop" aria-hidden="true">
      <div class="dsf-product-hero__grid" data-dsf-parallax="0.18"></div>
      <div class="dsf-product-hero__glow" data-dsf-parallax="0.42"></div>
      <div class="dsf-product-hero__scan"></div>
    </div>
    <div class="dsf-product-hero__inner">
      <div class="dsf-product-hero__copy">
        <span class="dsf-kicker" data-dsf-reveal><i class="dsf-kicker__dot"></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
        <InlineText tagName="h1" v-model="settings.title" :is-editor="isEditor" data-dsf-split placeholder="Headline" />
        <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Supporting description" />
        <div v-if="isEditor || settings.primaryText || settings.secondaryText" class="dsf-product-hero__actions" data-dsf-reveal>
          <a v-if="isEditor || settings.primaryText" class="dsf-hero-button dsf-hero-button--primary" :href="safePublicUrl(settings.primaryUrl)" @click="guardEditor">
            <InlineText tagName="span" v-model="settings.primaryText" :is-editor="isEditor" placeholder="Primary" /> <ArrowUpRight :size="18" />
          </a>
          <a v-if="isEditor || settings.secondaryText" class="dsf-hero-button dsf-hero-button--secondary" :href="safePublicUrl(settings.secondaryUrl)" @click="guardEditor">
            <Play :size="16" fill="currentColor" /> <InlineText tagName="span" v-model="settings.secondaryText" :is-editor="isEditor" placeholder="Secondary" />
          </a>
        </div>
        <p v-if="isEditor || settings.note" class="dsf-product-hero__note" data-dsf-reveal><ShieldCheck :size="17" /> <InlineText tagName="span" v-model="settings.note" :is-editor="isEditor" placeholder="Supporting note" /></p>
      </div>

      <BlockMedia class="dsf-product-hero__visual" :settings="settings" :alt="settings.title || ''">
      <div class="dsf-studio" data-dsf-builder aria-hidden="true">
        <div class="dsf-studio__topbar" data-dsf-builder-topbar>
          <div class="dsf-studio__brand">
            <img :src="logoUrl" alt="" />
            <span><strong>DesignStudio Flow</strong><small>Build your WordPress Page</small></span>
          </div>
          <div class="dsf-studio__devices">
            <span class="is-active"><Monitor :size="12" /></span>
            <span><Tablet :size="12" /></span>
            <span><Smartphone :size="12" /></span>
          </div>
          <div class="dsf-studio__tools">
            <span><Settings :size="11" /> Settings</span>
            <span><Palette :size="11" /> Theme</span>
            <span><ExternalLink :size="11" /> View</span>
            <b><Save :size="11" /> Save Page</b>
          </div>
        </div>
        <div class="dsf-studio__body">
          <div class="dsf-studio__canvas">
            <div class="dsf-studio__canvas-label" data-dsf-builder-label><span>PAGE CANVAS</span><b>1800 px</b></div>
            <div class="dsf-studio__selected-block" data-dsf-builder-selected>
              <div class="dsf-studio__block-tools" data-dsf-builder-toolbar>
                <span><GripVertical :size="11" /></span>
                <span><Settings :size="11" /></span>
                <span><ChevronUp :size="11" /></span>
                <span><ChevronDown :size="11" /></span>
                <span><Trash2 :size="11" /></span>
              </div>
              <div class="dsf-canvas-card dsf-canvas-card--hero">
                <span>NEW RELEASE</span>
                <h3>Pages that feel unmistakably yours.</h3>
                <p>Build visually. Publish natively.</p>
                <b>Explore the builder</b>
                <div class="dsf-canvas-card__art" data-dsf-builder-art data-dsf-float><i></i><i></i><i></i><i></i></div>
              </div>
            </div>
            <div class="dsf-canvas-row">
              <div class="dsf-canvas-card" data-dsf-builder-card><i></i><strong>Reusable blocks</strong></div>
              <div class="dsf-canvas-card" data-dsf-builder-card><i></i><strong>Theme controls</strong></div>
              <div class="dsf-canvas-card" data-dsf-builder-card><i></i><strong>Live editing</strong></div>
            </div>
            <div class="dsf-studio__add" data-dsf-builder-add><Plus :size="12" /> Add Block</div>
          </div>
          <aside class="dsf-studio__settings" data-dsf-builder-settings>
            <div class="dsf-studio__settings-head"><span><strong>Customize Block</strong><small>Product Hero</small></span><b>×</b></div>
            <div class="dsf-studio__tabs"><span class="is-active"><FileText :size="9" />Content</span><span><Palette :size="9" />Style</span></div>
            <div class="dsf-studio__expander">
              <div class="dsf-studio__expander-title"><strong>Content</strong><ChevronDown :size="10" /></div>
              <label>Eyebrow <em>THE VISUAL BUILDER...</em></label>
              <label>Title <em>Build freely. Stay...</em></label>
              <label>Layout <span>Split</span></label>
              <label>Alignment <span>Left</span></label>
              <label>Primary color <i></i></label>
            </div>
            <div class="dsf-studio__expander is-closed"><strong>Buttons</strong><ChevronDown :size="10" /></div>
            <div class="dsf-studio__expander is-closed"><strong>Spacing</strong><ChevronDown :size="10" /></div>
            <div class="dsf-studio__range"><b></b></div>
            <small>Theme-linked settings update every connected block.</small>
          </aside>
        </div>
      </div>
      </BlockMedia>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ArrowUpRight, ChevronDown, ChevronUp, ExternalLink, FileText, GripVertical, Monitor, Palette, Play, Plus, Save, Settings, ShieldCheck, Smartphone, Tablet, Trash2 } from 'lucide-vue-next'
import { safePublicUrl } from '../../utils/safeUrl'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import InlineText from '../common/InlineText.vue'
import BlockMedia from '../common/BlockMedia.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const root = ref(null)
const blockStyle = computed(() => landingBlockStyle(props.settings))
const logoUrl = computed(() => {
  const baseUrl = window.dsfEditorData?.pluginUrl || window.dsfFrontendData?.pluginUrl || ''
  return `${baseUrl}assets/images/dsflow-logo.png`
})

function guardEditor(event) {
  if (props.isEditor) event.preventDefault()
}

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-product-hero {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  --ink: var(--dsf-theme-text, #111827);
  position: relative;
  overflow: hidden;
  padding: clamp(72px, 10vw, 138px) 24px clamp(70px, 9vw, 124px);
  color: var(--ink);
  background: var(--dsf-theme-background, #f7f4ed);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}

.dsf-product-hero__backdrop { position: absolute; inset: -12% 0 -12%; z-index: 0; pointer-events: none; }
.dsf-product-hero__grid { position: absolute; inset: -10% -5%; background-image: linear-gradient(rgba(12, 95, 168, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(12, 95, 168, 0.05) 1px, transparent 1px); background-size: 54px 54px; -webkit-mask-image: radial-gradient(120% 90% at 60% 30%, #000 38%, transparent 78%); mask-image: radial-gradient(120% 90% at 60% 30%, #000 38%, transparent 78%); }
.dsf-product-hero__glow { position: absolute; top: -120px; left: 14%; width: 560px; height: 560px; border-radius: 50%; background: radial-gradient(circle at 50% 50%, rgba(12, 135, 221, 0.16), rgba(12, 135, 221, 0) 68%); }
.dsf-product-hero__scan { position: absolute; inset: 0; opacity: 0.4; background-image: repeating-linear-gradient(0deg, rgba(17, 24, 39, 0.022) 0, rgba(17, 24, 39, 0.022) 1px, transparent 1px, transparent 4px); }
.dsf-product-hero__inner { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(540px, 0.95fr) minmax(500px, 1.05fr); align-items: center; width: min(1320px, 100%); margin: 0 auto; gap: clamp(36px, 4vw, 56px); }
.dsf-product-hero.is-media-left .dsf-product-hero__inner { direction: rtl; }
.dsf-product-hero.is-media-left .dsf-product-hero__copy,
.dsf-product-hero.is-media-left .dsf-product-hero__visual { direction: ltr; }
.dsf-product-hero.is-center .dsf-product-hero__copy { max-width: 760px; margin: 0 auto; text-align: center; }
.dsf-product-hero.is-center .dsf-product-hero__copy > p { margin-left: auto; margin-right: auto; }
.dsf-product-hero.is-center .dsf-kicker,
.dsf-product-hero.is-center .dsf-product-hero__actions,
.dsf-product-hero.is-center .dsf-product-hero__note { justify-content: center; }
.dsf-product-hero__copy { max-width: 610px; }
.dsf-kicker { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 22px; padding: 7px 14px 7px 11px; border: 1px solid rgba(12, 95, 168, 0.16); border-radius: 999px; background: rgba(255, 255, 255, 0.55); color: var(--blue); font-size: 12px; font-weight: 850; letter-spacing: 0.14em; text-transform: uppercase; }
.dsf-kicker__dot { width: 7px; height: 7px; border-radius: 50%; background: var(--coral); box-shadow: 0 0 0 4px rgba(232, 106, 69, 0.18); }
.dsf-product-hero h1 { max-width: 720px; margin: 0; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(46px, 4.6vw, 66px); line-height: 0.99; letter-spacing: -0.055em; }
.dsf-product-hero__copy > p { max-width: 590px; margin: 28px 0 0; color: #526171; font-size: clamp(19px, 1.7vw, 23px); line-height: 1.55; }
.dsf-product-hero__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
.dsf-hero-button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 52px; padding: 0 22px; border: 1px solid transparent; border-radius: 10px; font-size: 16px; font-weight: 800; text-decoration: none; transition: transform 180ms ease, box-shadow 180ms ease; }
.dsf-hero-button:hover { transform: translateY(-2px); }
.dsf-hero-button--primary,
.dsf-hero-button--primary:hover,
.dsf-hero-button--primary:focus-visible { color: #fff !important; background: var(--blue); box-shadow: 0 14px 34px rgba(12, 95, 168, 0.22); }
.dsf-hero-button--secondary { color: var(--ink); border-color: rgba(17, 24, 39, 0.13); background: rgba(255, 255, 255, 0.76); }
.dsf-product-hero__copy .dsf-product-hero__note { display: flex; align-items: center; gap: 8px; margin-top: 20px; color: #607080; font-size: 14px; }

.dsf-product-hero__visual { min-width: 0; }
.dsf-product-hero__visual.dsf-block-media--filled { aspect-ratio: 4 / 3; border-radius: 18px; box-shadow: 0 35px 90px rgba(26, 45, 64, 0.18), 0 4px 12px rgba(26, 45, 64, 0.08); }
.dsf-studio { min-width: 0; overflow: hidden; border: 1px solid rgba(17, 24, 39, 0.12); border-radius: 18px; background: #fff; box-shadow: 0 35px 90px rgba(26, 45, 64, 0.18), 0 4px 12px rgba(26, 45, 64, 0.08); transform: rotate(0.35deg); will-change: transform; }
.dsf-studio__topbar { display: grid; grid-template-columns: minmax(150px, 1fr) auto minmax(210px, 1fr); align-items: center; min-height: 58px; padding: 0 14px; border-bottom: 1px solid #e7ebef; background: #f7f4ed; font-size: 10px; gap: 10px; }
.dsf-studio__brand { display: flex; align-items: center; min-width: 0; gap: 8px; }
.dsf-studio__brand img { width: 27px; height: 27px; object-fit: contain; }
.dsf-studio__brand > span { display: grid; min-width: 0; }
.dsf-studio__brand strong { overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.dsf-studio__brand small { color: #7c8790; font-size: 7px; }
.dsf-studio__devices { display: flex; padding: 3px; border: 1px solid #dfe5e9; border-radius: 6px; background: #fff; }
.dsf-studio__devices span { display: grid; place-items: center; width: 25px; height: 23px; border-radius: 4px; color: #7a8791; }
.dsf-studio__devices span.is-active { color: #071b2f; background: #a4d2f6; }
.dsf-studio__tools { display: flex; align-items: center; justify-content: flex-end; gap: 5px; }
.dsf-studio__tools span, .dsf-studio__tools b { display: inline-flex; align-items: center; gap: 4px; padding: 6px 7px; border: 1px solid #dfe4e7; border-radius: 5px; color: #4f5d68; background: #fff; font-size: 7px; font-weight: 750; white-space: nowrap; }
.dsf-studio__tools b { color: #fff; border-color: var(--blue); background: var(--blue); box-shadow: 0 5px 12px rgba(0, 145, 255, 0.2); }
.dsf-studio__body { display: grid; grid-template-columns: minmax(330px, 1fr) 205px; min-height: 448px; }
.dsf-studio__canvas { position: relative; display: flex; flex-direction: column; gap: 11px; padding: 18px 20px 48px; background: #eef1f3; }
.dsf-studio__canvas-label { display: flex; justify-content: space-between; color: #86919a; font-size: 7px; font-weight: 800; letter-spacing: 0.11em; }
.dsf-studio__canvas-label b { color: #65727d; letter-spacing: 0; }
.dsf-studio__selected-block { position: relative; padding: 3px; border: 2px solid var(--blue); border-radius: 10px; box-shadow: 0 0 0 3px rgba(0, 145, 255, 0.1); }
.dsf-studio__block-tools { position: absolute; top: -26px; right: 8px; z-index: 2; display: flex; overflow: hidden; border-radius: 5px; color: #fff; background: #071b2f; box-shadow: 0 5px 10px rgba(7, 27, 47, 0.18); }
.dsf-studio__block-tools span { display: grid; place-items: center; width: 24px; height: 23px; border-right: 1px solid rgba(255,255,255,0.15); }
.dsf-canvas-card { overflow: hidden; border: 1px solid #e1e5e8; border-radius: 8px; background: #fff; }
.dsf-canvas-card--hero { position: relative; min-height: 242px; padding: 36px 31px; color: #fff; background: linear-gradient(128deg, #0091ff 0%, #0091ff 65%, #a4d2f6 65%); }
.dsf-canvas-card--hero > span { color: #fff; font-size: 8px; font-weight: 850; letter-spacing: 0.13em; }
.dsf-canvas-card--hero h3 { position: relative; z-index: 1; max-width: 320px; margin: 12px 0 8px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(20px, 2vw, 31px); line-height: 1.06; }
.dsf-canvas-card--hero p { position: relative; z-index: 1; margin: 0 0 19px; color: rgba(255,255,255,0.8); font-size: 11px; }
.dsf-canvas-card--hero > b { position: relative; z-index: 1; display: inline-block; padding: 8px 11px; border-radius: 5px; color: #071b2f; background: var(--coral); font-size: 8px; }
.dsf-canvas-card__art { position: absolute; right: 24px; bottom: 18px; display: grid; grid-template-columns: repeat(2, 32px); gap: 5px; transform: rotate(-4deg); }
.dsf-canvas-card__art i { width: 32px; height: 32px; border-radius: 9px; background: #ff7100; box-shadow: 0 7px 15px rgba(7,27,47,0.14); }
.dsf-canvas-card__art i:nth-child(2) { background: #fff; }.dsf-canvas-card__art i:nth-child(3) { background: #ffc08e; }.dsf-canvas-card__art i:nth-child(4) { background: #071b2f; }
.dsf-canvas-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.dsf-canvas-row .dsf-canvas-card { min-height: 76px; padding: 11px; transform-origin: center; }
.dsf-canvas-row i { display: block; width: 24px; height: 24px; margin-bottom: 8px; border-radius: 7px; background: #a4d2f6; }
.dsf-canvas-row .dsf-canvas-card:nth-child(2) i { background: #ffc08e; }.dsf-canvas-row .dsf-canvas-card:nth-child(3) i { background: #0091ff; }
.dsf-canvas-row strong { font-size: 8px; }
.dsf-studio__add { position: absolute; bottom: 13px; left: 50%; display: flex; align-items: center; gap: 5px; padding: 7px 13px; border-radius: 999px; color: #071b2f; background: #fff; box-shadow: 0 8px 22px rgba(27, 49, 65, 0.16); font-size: 8px; font-weight: 800; transform: translateX(-50%); }
.dsf-studio__settings { padding: 15px 13px; border-left: 1px solid #e4e8eb; background: #fff; }
.dsf-studio__settings-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 11px; }.dsf-studio__settings-head > span { display: grid; }.dsf-studio__settings-head strong { color: #25313a; font-size: 10px; }.dsf-studio__settings-head small { margin: 2px 0 0; padding: 0; color: #89949c; background: transparent; font-size: 7px; }.dsf-studio__settings-head b { color: #4d5963; font-size: 13px; }
.dsf-studio__tabs { display: grid; grid-template-columns: repeat(2, 1fr); margin-bottom: 10px; padding: 3px; border-radius: 6px; background: #f1f3f5; font-size: 8px; text-align: center; }.dsf-studio__tabs span { display: flex; align-items: center; justify-content: center; padding: 6px; border-radius: 4px; gap: 4px; }.dsf-studio__tabs span.is-active { color: #071b2f; background: #fff; box-shadow: 0 2px 5px rgba(17,24,39,0.08); }
.dsf-studio__expander { margin: 0 -3px 8px; padding: 8px 7px; border: 1px solid #e2e6e9; border-radius: 6px; background: #fbfcfc; }.dsf-studio__expander-title, .dsf-studio__expander.is-closed { display: flex; align-items: center; justify-content: space-between; }.dsf-studio__expander-title strong, .dsf-studio__expander.is-closed strong { color: #4d5963; font-size: 8px; }.dsf-studio__expander.is-closed { padding: 8px; }
.dsf-studio__settings label { display: flex; align-items: center; justify-content: space-between; margin: 10px 0; color: #77818a; font-size: 8px; }
.dsf-studio__settings label span { color: #35414a; font-weight: 700; }.dsf-studio__settings label em { max-width: 78px; overflow: hidden; padding: 5px; border: 1px solid #e0e5e8; border-radius: 4px; color: #4f5d68; font-size: 7px; font-style: normal; text-overflow: ellipsis; white-space: nowrap; }
.dsf-studio__settings label i { width: 15px; height: 15px; border: 3px solid #a4d2f6; border-radius: 50%; background: #0091ff; }
.dsf-studio__range { height: 3px; margin-top: 17px; border-radius: 4px; background: #e1e6e9; }.dsf-studio__range b { display: block; width: 64%; height: 100%; background: var(--blue); }
.dsf-studio__settings > small { display: block; margin-top: 17px; padding: 8px; border-radius: 6px; color: #44617a; background: #edf7ff; font-size: 7px; line-height: 1.5; }

@media (max-width: 1120px) {
  .dsf-product-hero__inner { grid-template-columns: 1fr; }
  .dsf-product-hero__copy { max-width: 760px; text-align: center; margin: 0 auto; }
  .dsf-product-hero h1, .dsf-product-hero__copy > p { margin-right: auto; margin-left: auto; }
  .dsf-kicker, .dsf-product-hero__actions, .dsf-product-hero__note { justify-content: center; }
}

@media (max-width: 720px) {
  .dsf-product-hero { padding-right: 12px; padding-left: 12px; }
  .dsf-product-hero h1 { font-size: 38px; text-wrap: balance; }
  .dsf-studio__topbar { grid-template-columns: 1fr auto; }
  .dsf-studio__devices, .dsf-studio__tools span { display: none; }
  .dsf-studio__body { grid-template-columns: 1fr; min-height: 400px; }
  .dsf-studio__settings { display: none; }
  .dsf-studio__canvas { padding: 12px; }
  .dsf-canvas-card--hero { padding: 32px 22px; }
  .dsf-studio__add { display: none; }
  .dsf-product-hero__actions { display: grid; }
}

@media (prefers-reduced-motion: reduce) {
  .dsf-hero-button { transition: none; }
}
</style>
