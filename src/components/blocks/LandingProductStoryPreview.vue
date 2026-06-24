<template>
  <section :id="story.id" ref="root" class="dsf-product-story" :class="[`is-${story.id}`, { 'is-reversed': settings.reverseLayout }]" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-product-story__inner">
      <div class="dsf-product-story__copy">
        <span class="dsf-story-kicker" data-dsf-reveal><i class="dsf-story-kicker__tick"></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
        <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" data-dsf-split placeholder="Title" />
        <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Description" />
        <ul data-dsf-reveal>
          <li v-for="field in featureFields" :key="field.key" v-show="isEditor || settings[field.key]">
            <Check :size="17" /> <InlineText tagName="span" v-model="settings[field.key]" :is-editor="isEditor" :placeholder="field.placeholder" />
          </li>
        </ul>
      </div>

      <div class="dsf-product-story__visual" data-dsf-parallax="0.14">
       <div class="dsf-product-story__visual-inner" data-dsf-card>
        <BlockMedia class="dsf-product-story__media" :settings="settings" :alt="settings.title || ''">
        <div v-if="story.id === 'editor'" class="dsf-story-ui dsf-story-ui--editor">
          <div class="dsf-story-ui__top"><span></span><span></span><b>Publish</b></div>
          <div class="dsf-editor-scene">
            <aside><i></i><i></i><i></i><i></i></aside>
            <main><span>EDITING CANVAS</span><h3>Make the page feel like your brand.</h3><p>Direct manipulation without mystery.</p><b>Start building</b></main>
            <section><b>STYLE</b><label>Spacing <i></i></label><label>Alignment <i></i></label><label>Color <em></em></label></section>
          </div>
        </div>

        <div v-else-if="story.id === 'theme'" class="dsf-story-ui dsf-story-ui--theme">
          <span class="dsf-theme-label">GLOBAL THEME</span>
          <h3>One system. Every block.</h3>
          <div class="dsf-theme-palette" data-dsf-float><i></i><i></i><i></i><i></i></div>
          <div class="dsf-theme-type"><strong>Aa</strong><span><b>Manrope</b><small>Source Sans 3</small></span></div>
          <div class="dsf-theme-pages"><i></i><i></i><i></i></div>
        </div>

        <div v-else-if="story.id === 'woocommerce'" class="dsf-story-ui dsf-story-ui--commerce">
          <div class="dsf-commerce-head"><span>Shop the collection</span><div>Search products <Search :size="14" /></div></div>
          <div class="dsf-commerce-filters"><b>All products</b><span>Hot tubs</span><span>Saunas</span><span>Accessories</span></div>
          <div class="dsf-commerce-grid">
            <article v-for="(product, index) in commerceProducts" :key="product">
              <div :class="`tone-${index + 1}`"><Heart :size="13" /></div><span>{{ product }}</span><strong>Explore options</strong>
            </article>
          </div>
        </div>

        <div v-else-if="story.id === 'layouts'" class="dsf-story-ui dsf-story-ui--layouts">
          <div class="dsf-layout-window">
            <div class="dsf-layout-bar"><span class="dsf-mini-mark"></span><i></i><i></i><i></i><b>Get started</b></div>
            <div class="dsf-layout-mega"><section><small>EXPLORE</small><h3>A header with somewhere to go.</h3><b>View solutions</b></section><div><i></i><i></i><i></i><i></i></div></div>
          </div>
          <div class="dsf-layout-swap" data-dsf-drift><PanelTop :size="19" /><span>Header + footer templates</span><ArrowRight :size="18" /></div>
        </div>

        <div v-else class="dsf-story-ui dsf-story-ui--campaigns">
          <div class="dsf-campaign-page"><i></i><i></i><i></i></div>
          <div class="dsf-campaign-modal">
            <span aria-hidden="true">×</span><small>LIMITED RELEASE</small><h3>A campaign that knows when to arrive.</h3><p>Control timing, dates, and repeat visits.</p><b>See what’s new</b>
          </div>
          <div class="dsf-campaign-clock" data-dsf-float><Clock3 :size="17" /><span>Delay: 5 seconds</span></div>
        </div>
        </BlockMedia>
       </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ArrowRight, Check, Clock3, Heart, PanelTop, Search } from 'lucide-vue-next'
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
const stories = {
  editor: { id: 'editor' },
  theme: { id: 'theme' },
  commerce: { id: 'woocommerce' },
  layouts: { id: 'layouts' },
  campaigns: { id: 'campaigns' },
}
const story = computed(() => stories[props.settings.variant] || stories.editor)
const featureFields = [
  { key: 'featureOne', placeholder: 'Feature one' },
  { key: 'featureTwo', placeholder: 'Feature two' },
  { key: 'featureThree', placeholder: 'Feature three' },
]
const commerceProducts = ['The Retreat', 'The Reserve', 'The Essential']

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-product-story {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  --ink: var(--dsf-theme-text, #111827);
  position: relative;
  overflow: hidden;
  padding: clamp(76px, 9vw, 130px) 24px;
  color: var(--ink);
  background: var(--dsf-theme-background, #f7f4ed);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}
.dsf-product-story::before { position: absolute; top: 8%; right: -170px; width: 430px; height: 430px; border-radius: 50%; background: radial-gradient(circle, rgba(0,145,255,0.08), transparent 68%); content: ''; pointer-events: none; }
.dsf-product-story:nth-of-type(even), .dsf-product-story.is-theme, .dsf-product-story.is-layouts { background: #f3f0e9; }
.dsf-product-story__inner { position: relative; display: grid; grid-template-columns: minmax(420px, 0.95fr) minmax(460px, 1.05fr); align-items: center; width: min(1180px, 100%); margin: 0 auto; gap: clamp(44px, 5.5vw, 70px); }
.dsf-product-story.is-reversed .dsf-product-story__copy { order: 2; }
.dsf-product-story.is-reversed .dsf-product-story__visual { order: 1; }
.dsf-product-story__copy { max-width: 520px; }
.dsf-story-kicker { display: inline-flex; align-items: center; gap: 10px; color: var(--blue); font-size: 13px; font-weight: 850; letter-spacing: 0.12em; text-transform: uppercase; }
.dsf-story-kicker__tick { width: 18px; height: 2px; background: var(--coral); }
.dsf-product-story h2 { margin: 14px 0 22px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(37px, 3.8vw, 54px); line-height: 1.05; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-product-story__copy > p { margin: 0; color: #596775; font-size: 20px; line-height: 1.57; }
.dsf-product-story ul { display: grid; margin: 28px 0 0; padding: 0; gap: 13px; list-style: none; }
.dsf-product-story li { display: flex; align-items: center; gap: 10px; color: #34424e; font-size: 16px; font-weight: 650; }
.dsf-product-story li svg { flex: 0 0 auto; color: var(--coral); }
.dsf-product-story__visual { min-width: 0; }
.dsf-product-story__visual-inner { height: 100%; }
.dsf-product-story__media { height: 100%; }
.dsf-product-story__media.dsf-block-media--filled { min-height: 470px; border-radius: 22px; box-shadow: 0 30px 70px rgba(29, 52, 68, 0.14); }
.dsf-story-ui { position: relative; overflow: hidden; min-height: 470px; border: 1px solid rgba(17, 24, 39, 0.1); border-radius: 22px; background: #fff; box-shadow: 0 30px 70px rgba(29, 52, 68, 0.14); }

.dsf-story-ui__top { display: flex; align-items: center; gap: 6px; height: 45px; padding: 0 14px; border-bottom: 1px solid #e3e7e9; background: #f9fafb; }
.dsf-story-ui__top span { width: 8px; height: 8px; border-radius: 50%; background: #e7a276; }
.dsf-story-ui__top span:nth-child(2) { background: #84b8d9; }
.dsf-story-ui__top b { margin-left: auto; padding: 5px 9px; border-radius: 4px; color: #fff; background: var(--blue); font-size: 9px; }
.dsf-editor-scene { display: grid; grid-template-columns: 45px 1fr 150px; min-height: 425px; }
.dsf-editor-scene > aside { display: flex; flex-direction: column; align-items: center; gap: 12px; padding-top: 16px; border-right: 1px solid #e4e8eb; background: #f7f9fa; }
.dsf-editor-scene > aside i { width: 20px; height: 20px; border-radius: 5px; background: #d6dfe5; }
.dsf-editor-scene > aside i:first-child { background: var(--blue); }
.dsf-editor-scene > main { align-self: center; margin: 24px; padding: 55px 34px; border-radius: 12px; color: #fff; background: #0091ff; }
.dsf-editor-scene > main > span { color: #fff; font-size: 8px; font-weight: 850; letter-spacing: 0.12em; }
.dsf-editor-scene h3 { margin: 12px 0; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(21px, 2.2vw, 29px); line-height: 1.08; text-wrap: balance; }
.dsf-editor-scene p { color: rgba(255,255,255,0.8); font-size: 11px; }
.dsf-editor-scene main > b, .dsf-layout-mega section > b { display: inline-block; padding: 8px 11px; border: 0; border-radius: 5px; color: #071b2f; background: var(--coral); font-size: 9px; font-weight: 800; }
.dsf-editor-scene > section { padding: 17px 13px; border-left: 1px solid #e4e8eb; }
.dsf-editor-scene > section > b { font-size: 8px; letter-spacing: 0.12em; }
.dsf-editor-scene label { display: flex; align-items: center; justify-content: space-between; margin-top: 22px; color: #68747d; font-size: 9px; }
.dsf-editor-scene label i { width: 45px; height: 4px; border-radius: 3px; background: #dce3e7; }
.dsf-editor-scene label em { width: 18px; height: 18px; border-radius: 4px; background: #0091ff; }

.dsf-story-ui--theme { padding: 52px; background: #0091ff; color: #fff; }
.dsf-theme-label { color: #fff; font-size: 10px; font-weight: 850; letter-spacing: 0.14em; }
.dsf-story-ui--theme h3 { max-width: 420px; margin: 12px 0 30px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 36px; }
.dsf-theme-palette { display: flex; gap: 11px; }
.dsf-theme-palette i { width: 52px; height: 52px; border: 5px solid rgba(7,27,47,0.12); border-radius: 50%; background: var(--blue); }
.dsf-theme-palette i:nth-child(2) { background: var(--coral); }.dsf-theme-palette i:nth-child(3) { background: #f7f4ed; }.dsf-theme-palette i:nth-child(4) { background: #111827; }
.dsf-theme-type { display: flex; align-items: center; gap: 18px; margin-top: 34px; padding: 18px; border: 1px solid rgba(7,27,47,0.18); border-radius: 12px; background: rgba(255,255,255,0.2); }
.dsf-theme-type > strong { font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 42px; }
.dsf-theme-type span { display: grid; gap: 4px; }.dsf-theme-type small { color: rgba(255,255,255,0.78); }
.dsf-theme-pages { position: absolute; right: 35px; bottom: -26px; left: 35px; display: flex; gap: 10px; }
.dsf-theme-pages i { flex: 1; height: 95px; border-radius: 9px 9px 0 0; background: #fff; opacity: 0.92; }
.dsf-theme-pages i:nth-child(2) { background: #cde5f4; }.dsf-theme-pages i:nth-child(3) { background: #f8d8ce; }

.dsf-story-ui--commerce { padding: 25px; background: #f8fafb; }
.dsf-commerce-head { display: flex; align-items: center; justify-content: space-between; }
.dsf-commerce-head > span { font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 21px; font-weight: 800; }
.dsf-commerce-head > div { display: flex; gap: 25px; padding: 8px 10px; border: 1px solid #dce3e7; border-radius: 6px; color: #88939b; font-size: 9px; }
.dsf-commerce-filters { display: flex; gap: 8px; margin: 22px 0; }
.dsf-commerce-filters span, .dsf-commerce-filters b { padding: 7px 10px; border: 1px solid #dde4e8; border-radius: 999px; font-size: 8px; }.dsf-commerce-filters b { color: #fff; border-color: var(--blue); background: var(--blue); }
.dsf-commerce-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 11px; }
.dsf-commerce-grid article { padding: 8px 8px 15px; border: 1px solid #e1e5e7; border-radius: 10px; background: #fff; }
.dsf-commerce-grid article > div { display: flex; justify-content: flex-end; height: 220px; padding: 8px; border-radius: 7px; color: #071b2f; background: linear-gradient(145deg, #a4d2f6, #0091ff); }.dsf-commerce-grid article > div.tone-2 { background: linear-gradient(145deg, #ffc08e, #ff7100); }.dsf-commerce-grid article > div.tone-3 { background: linear-gradient(145deg, #a4d2f6, #4caeff); }
.dsf-commerce-grid article span { display: block; margin: 12px 3px 4px; font-size: 11px; font-weight: 800; }.dsf-commerce-grid article strong { margin-left: 3px; color: var(--blue); font-size: 8px; }

.dsf-story-ui--layouts { padding: 20px; background: #dfe8ec; }
.dsf-layout-window { overflow: hidden; height: 365px; border-radius: 12px; background: #fff; box-shadow: 0 15px 30px rgba(16, 38, 52, 0.12); }
.dsf-layout-bar { display: flex; align-items: center; height: 54px; padding: 0 18px; color: #fff; background: #111827; gap: 16px; }
.dsf-mini-mark { width: 23px; height: 23px; margin-right: auto; border-radius: 6px; background: linear-gradient(135deg, var(--coral) 50%, #258ad0 50%); }
.dsf-layout-bar i { width: 38px; height: 5px; background: rgba(255,255,255,0.75); }.dsf-layout-bar b { padding: 7px 10px; border-radius: 5px; color: #fff; background: var(--blue); font-size: 8px; }
.dsf-layout-mega { display: grid; grid-template-columns: 1fr 1fr; margin: 14px; padding: 24px; border-radius: 13px; background: #f7f4ed; box-shadow: 0 18px 40px rgba(19, 42, 58, 0.16); gap: 22px; }
.dsf-layout-mega small { color: var(--blue); font-weight: 800; }.dsf-layout-mega h3 { margin: 8px 0 20px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 19px; line-height: 1.12; }
.dsf-layout-mega > div { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }.dsf-layout-mega > div i { min-height: 76px; border: 1px solid #dde3e6; border-radius: 7px; background: #fff; }
.dsf-layout-swap { display: flex; align-items: center; gap: 10px; margin-top: 18px; padding: 13px 15px; border-radius: 9px; background: #fff; color: #3e4b56; font-size: 11px; font-weight: 750; }.dsf-layout-swap span { flex: 1; }

.dsf-story-ui--campaigns { display: grid; place-items: center; background: #ece7df; }
.dsf-campaign-page { position: absolute; inset: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; opacity: 0.34; }.dsf-campaign-page i { border-radius: 8px; background: #90aebc; }.dsf-campaign-page i:first-child { grid-column: span 3; height: 110px; background: #45697b; }
.dsf-campaign-modal { position: relative; z-index: 1; width: min(330px, 72%); padding: 38px; border-radius: 15px; background: #fff; box-shadow: 0 24px 70px rgba(25, 44, 57, 0.23); text-align: center; }.dsf-campaign-modal > span { position: absolute; top: 10px; right: 12px; }.dsf-campaign-modal small { color: var(--coral); font-weight: 850; letter-spacing: 0.1em; }.dsf-campaign-modal h3 { margin: 10px 0; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 25px; line-height: 1.1; }.dsf-campaign-modal p { color: #69757e; font-size: 12px; }.dsf-campaign-modal b { display: inline-block; margin-top: 10px; padding: 9px 13px; border-radius: 5px; color: #fff; background: var(--blue); font-size: 9px; }
.dsf-campaign-clock { position: absolute; right: 18px; bottom: 18px; z-index: 2; display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 7px; color: #fff; background: #0091ff; font-size: 10px; font-weight: 800; }

@media (max-width: 1020px) {
  .dsf-product-story__inner { grid-template-columns: 1fr; }
  .dsf-product-story__copy { max-width: 700px; }
  .dsf-product-story.is-reversed .dsf-product-story__copy, .dsf-product-story.is-reversed .dsf-product-story__visual { order: initial; }
}
@media (max-width: 620px) {
  .dsf-product-story { padding-right: 18px; padding-left: 18px; }
  .dsf-story-ui { min-height: 400px; }
  .dsf-editor-scene { grid-template-columns: 34px 1fr; }.dsf-editor-scene > section { display: none; }.dsf-editor-scene > main { margin: 12px; padding: 40px 20px; }
  .dsf-story-ui--theme { padding: 36px 24px; }
  .dsf-commerce-grid { gap: 6px; }.dsf-commerce-grid article > div { height: 150px; }.dsf-commerce-filters { overflow: hidden; }
  .dsf-layout-bar i { display: none; }.dsf-layout-mega { grid-template-columns: 1fr; }.dsf-layout-mega > div { display: none; }
}
</style>
