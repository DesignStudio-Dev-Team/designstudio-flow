<template>
  <section id="redirects" ref="root" class="dsf-redir" :class="{ 'is-reversed': settings.reverseLayout }" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-redir__inner">
      <div class="dsf-redir__copy">
        <span class="dsf-redir__kicker" data-dsf-reveal><i></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
        <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" data-dsf-split placeholder="Title" />
        <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Description" />
        <ul data-dsf-reveal>
          <li v-for="field in featureFields" :key="field.key" v-show="isEditor || settings[field.key]">
            <Check :size="17" /> <InlineText tagName="span" v-model="settings[field.key]" :is-editor="isEditor" :placeholder="field.placeholder" />
          </li>
        </ul>
      </div>

      <div class="dsf-redir__visual" data-dsf-parallax="0.12">
        <div class="dsf-redir__panel" data-dsf-card>
          <div class="dsf-redir__panel-top"><span></span><span></span><b>Redirects</b><em>{{ rows.length }} active</em></div>
          <div class="dsf-redir__row dsf-redir__row--head"><span>Source</span><span>Target</span><span>Type</span><span>Hits</span></div>
          <div v-for="(row, index) in rows" :key="index" class="dsf-redir__row">
            <span><code>{{ row.from }}</code></span>
            <span class="dsf-redir__to"><ArrowRight :size="12" /> {{ row.to }}</span>
            <span><b :class="`is-${row.type}`">{{ row.type }}</b></span>
            <span>{{ row.hits }}</span>
          </div>
          <div class="dsf-redir__bar"><Upload :size="14" /> Import CSV<i></i><Download :size="14" /> Export CSV</div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ArrowRight, Check, Download, Upload } from 'lucide-vue-next'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const root = ref(null)
const blockStyle = computed(() => landingBlockStyle(props.settings))
const featureFields = [
  { key: 'featureOne', placeholder: 'Feature one' },
  { key: 'featureTwo', placeholder: 'Feature two' },
  { key: 'featureThree', placeholder: 'Feature three' },
]
const rows = [
  { from: '/old-pricing', to: '/pricing', type: 301, hits: 482 },
  { from: '/blog/launch', to: '/whats-new', type: 301, hits: 211 },
  { from: '/promo', to: '/black-friday', type: 302, hits: 1290 },
  { from: '/docs/v1', to: '/docs', type: 301, hits: 96 },
]

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-redir {
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
.dsf-redir__inner { position: relative; display: grid; grid-template-columns: minmax(420px, 0.95fr) minmax(460px, 1.05fr); align-items: center; width: min(1180px, 100%); margin: 0 auto; gap: clamp(44px, 5.5vw, 70px); }
.dsf-redir.is-reversed .dsf-redir__copy { order: 2; }
.dsf-redir.is-reversed .dsf-redir__visual { order: 1; }
.dsf-redir__copy { max-width: 520px; }
.dsf-redir__kicker { display: inline-flex; align-items: center; gap: 9px; color: var(--dsf-eyebrow-color, var(--blue)); font-size: var(--dsf-eyebrow-size, 14px); font-weight: 850; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-redir__kicker i { width: 22px; height: 2px; background: var(--dsf-eyebrow-line-color, var(--coral)); }
.dsf-redir h2 { margin: 14px 0 22px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(37px, 3.8vw, 54px); line-height: 1.05; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-redir__copy > p { margin: 0; color: #596775; font-size: 20px; line-height: 1.57; }
.dsf-redir ul { display: grid; margin: 28px 0 0; padding: 0; gap: 13px; list-style: none; }
.dsf-redir li { display: flex; align-items: center; gap: 10px; color: #34424e; font-size: 16px; font-weight: 650; }
.dsf-redir li svg { flex: 0 0 auto; color: var(--coral); }

.dsf-redir__visual { min-width: 0; }
.dsf-redir__panel { overflow: hidden; border: 1px solid rgba(17, 24, 39, 0.1); border-radius: 18px; background: #fff; box-shadow: 0 30px 70px rgba(29, 52, 68, 0.14); }
.dsf-redir__panel-top { display: flex; align-items: center; gap: 6px; height: 46px; padding: 0 15px; border-bottom: 1px solid #e6eaee; background: #f9fafb; }
.dsf-redir__panel-top span { width: 9px; height: 9px; border-radius: 50%; background: #e7a276; }
.dsf-redir__panel-top span:nth-child(2) { background: #84b8d9; }
.dsf-redir__panel-top b { margin-left: 8px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 13px; }
.dsf-redir__panel-top em { margin-left: auto; color: var(--blue); font-size: 11px; font-style: normal; font-weight: 800; }
.dsf-redir__row { display: grid; grid-template-columns: 1.1fr 1.3fr 0.5fr 0.4fr; align-items: center; padding: 12px 15px; border-bottom: 1px solid #eef1f3; font-size: 12.5px; gap: 8px; }
.dsf-redir__row--head { color: #8a949d; font-size: 9px; font-weight: 850; letter-spacing: 0.1em; text-transform: uppercase; }
.dsf-redir__row code { padding: 2px 6px; border-radius: 5px; background: #f1f4f6; color: #1f2c38; font-size: 11.5px; }
.dsf-redir__to { display: inline-flex; align-items: center; gap: 5px; color: #3e4b56; }
.dsf-redir__to svg { color: var(--blue); }
.dsf-redir__row b { padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 800; }
.dsf-redir__row b.is-301 { color: #1a7f37; background: rgba(26, 127, 55, 0.12); }
.dsf-redir__row b.is-302 { color: #9a6700; background: rgba(154, 103, 0, 0.13); }
.dsf-redir__bar { display: flex; align-items: center; gap: 8px; padding: 13px 15px; color: #51606b; font-size: 12px; font-weight: 700; }
.dsf-redir__bar svg { color: var(--blue); }
.dsf-redir__bar i { width: 1px; height: 16px; margin: 0 6px; background: #dfe4e8; }

@media (max-width: 1020px) {
  .dsf-redir__inner { grid-template-columns: 1fr; }
  .dsf-redir__copy { max-width: 700px; }
  .dsf-redir.is-reversed .dsf-redir__copy, .dsf-redir.is-reversed .dsf-redir__visual { order: initial; }
}
@media (max-width: 620px) {
  .dsf-redir { padding-right: 18px; padding-left: 18px; }
  .dsf-redir__row { grid-template-columns: 1fr 1fr 0.5fr 0.4fr; }
}
</style>
