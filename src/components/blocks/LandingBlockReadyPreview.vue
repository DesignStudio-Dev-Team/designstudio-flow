<template>
  <section id="ready" ref="root" class="dsf-ready" :style="blockStyle">
    <div class="dsf-ready__inner">
      <!-- Copy column -->
      <div class="dsf-ready__copy">
        <div class="dsf-ready__heading" data-dsf-reveal>
          <span class="dsf-ready__eyebrow"><i></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
          <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" placeholder="Title" />
          <InlineText tagName="p" class="dsf-ready__lead" v-model="settings.description" :is-editor="isEditor" :multiline="true" placeholder="Description" />
        </div>

        <ol class="dsf-ready__steps">
          <li v-for="(step, index) in steps" :key="index" class="dsf-ready__step" data-dsf-reveal>
            <span class="dsf-ready__step-num">{{ String(index + 1).padStart(2, '0') }}</span>
            <div class="dsf-ready__step-body">
              <InlineText tagName="h3" v-model="settings[step.titleKey]" :is-editor="isEditor" :placeholder="step.titlePlaceholder" />
              <InlineText tagName="p" v-model="settings[step.textKey]" :is-editor="isEditor" :multiline="true" :placeholder="step.textPlaceholder" />
            </div>
          </li>
        </ol>

        <p class="dsf-ready__note" data-dsf-reveal>
          <Check :size="16" aria-hidden="true" />
          <InlineText tagName="span" v-model="settings.note" :is-editor="isEditor" placeholder="Supporting note" />
        </p>
      </div>

      <!-- Demo column: the actual editor, assembling a Countdown block + its settings -->
      <div class="dsf-ready__demo dsf-estudio" data-dsf-builder aria-hidden="true">
        <div class="dsf-estudio__body">
          <div class="dsf-estudio__canvas">
            <div class="dsf-estudio__canvas-label" data-dsf-builder-label><span>PAGE CANVAS</span><b>Countdown</b></div>

            <div class="dsf-estudio__ghost-block" data-dsf-builder-card><i></i><i></i></div>

            <div class="dsf-estudio__selected-block" data-dsf-builder-selected>
              <div class="dsf-estudio__block-tools" data-dsf-builder-toolbar>
                <span><GripVertical :size="11" /></span>
                <span><Settings :size="11" /></span>
                <span><ChevronUp :size="11" /></span>
                <span><ChevronDown :size="11" /></span>
                <span><Trash2 :size="11" /></span>
              </div>
              <div class="dsf-countdown-card">
                <div class="dsf-countdown-card__text">
                  <InlineText tagName="span" class="dsf-countdown-card__eyebrow" v-model="settings.demoEyebrow" :is-editor="isEditor" placeholder="Eyebrow" />
                  <InlineText tagName="h4" v-model="settings.demoTitle" :is-editor="isEditor" placeholder="Title" />
                  <InlineText tagName="p" v-model="settings.demoText" :is-editor="isEditor" :multiline="true" placeholder="Description" />
                  <span class="dsf-countdown-card__btn"><InlineText tagName="span" v-model="settings.demoButton" :is-editor="isEditor" placeholder="Button" /></span>
                </div>
                <div class="dsf-countdown-card__timer" data-dsf-builder-art>
                  <i v-for="unit in timerUnits" :key="unit.label"><b>{{ unit.value }}</b><span>{{ unit.label }}</span></i>
                </div>
              </div>
            </div>

            <div class="dsf-estudio__ghost-block" data-dsf-builder-card><i></i><i></i></div>

            <!-- New editor UI: no top bar — every action lives in a floating dock. -->
            <div class="dsf-estudio__dock" data-dsf-builder-dock>
              <span class="dsf-estudio__dock-mark"><img :src="logoUrl" alt="" /></span>
              <i class="dsf-estudio__dock-sep"></i>
              <span class="dsf-estudio__dock-btn"><Settings :size="11" /></span>
              <span class="dsf-estudio__dock-btn"><Palette :size="11" /></span>
              <span class="dsf-estudio__dock-btn"><LayoutTemplate :size="11" /></span>
              <span class="dsf-estudio__dock-btn"><ExternalLink :size="11" /></span>
              <span class="dsf-estudio__dock-btn dsf-estudio__dock-btn--primary"><Save :size="11" /></span>
              <i class="dsf-estudio__dock-sep"></i>
              <span class="dsf-estudio__dock-btn is-active"><Monitor :size="11" /></span>
              <span class="dsf-estudio__dock-btn"><Tablet :size="11" /></span>
              <span class="dsf-estudio__dock-btn"><Smartphone :size="11" /></span>
              <i class="dsf-estudio__dock-sep"></i>
              <span class="dsf-estudio__dock-btn dsf-estudio__dock-btn--accent"><Plus :size="11" /></span>
            </div>
          </div>

          <aside class="dsf-estudio__settings" data-dsf-builder-settings>
            <div class="dsf-estudio__settings-head"><span><strong>Customize Block</strong><small>Countdown</small></span><b>×</b></div>
            <div class="dsf-estudio__tabs"><span class="is-active"><FileText :size="9" />Content</span><span><Palette :size="9" />Style</span></div>
            <div class="dsf-estudio__expander">
              <div class="dsf-estudio__expander-title"><strong>Content</strong><ChevronDown :size="10" /></div>
              <label>Eyebrow <em>{{ settings.demoEyebrow }}</em></label>
              <label>Title <em>{{ settings.demoTitle }}</em></label>
              <label>Target date <em>Aug 31, 23:59</em></label>
              <label>Button text <em>{{ settings.demoButton }}</em></label>
              <label>Media position <span>Right</span></label>
            </div>
            <div class="dsf-estudio__expander is-closed"><strong>Style</strong><ChevronDown :size="10" /></div>
            <div class="dsf-estudio__expander is-closed"><strong>Spacing</strong><ChevronDown :size="10" /></div>
            <div class="dsf-estudio__range"><b></b></div>
            <small>Edit the content — the layout, spacing, and styling are already built in.</small>
          </aside>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Check, ChevronDown, ChevronUp, ExternalLink, FileText, GripVertical, LayoutTemplate, Monitor, Palette, Plus, Save, Settings, Smartphone, Tablet, Trash2 } from 'lucide-vue-next'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  blockId: { type: [String, Number], default: '' },
})

const root = ref(null)
const blockStyle = computed(() => landingBlockStyle(props.settings))
const logoUrl = computed(() => {
  const baseUrl = window.dsfEditorData?.pluginUrl || window.dsfFrontendData?.pluginUrl || ''
  return `${baseUrl}assets/images/dsflow-logo.png`
})

const steps = [
  { titleKey: 'step1Title', textKey: 'step1Text', titlePlaceholder: 'Step 1 title', textPlaceholder: 'Step 1 text' },
  { titleKey: 'step2Title', textKey: 'step2Text', titlePlaceholder: 'Step 2 title', textPlaceholder: 'Step 2 text' },
  { titleKey: 'step3Title', textKey: 'step3Text', titlePlaceholder: 'Step 3 title', textPlaceholder: 'Step 3 text' },
]

const timerUnits = [
  { value: '04', label: 'Days' },
  { value: '12', label: 'Hrs' },
  { value: '45', label: 'Min' },
  { value: '30', label: 'Sec' },
]

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-ready {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  --ink: var(--dsf-theme-text, #111827);
  position: relative;
  padding: clamp(76px, 9vw, 130px) 24px;
  color: var(--ink);
  overflow: hidden;
  background:
    radial-gradient(120% 90% at 12% 0%, rgba(12, 95, 168, 0.07), transparent 58%),
    var(--dsf-theme-background, #f7f4ed);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}

.dsf-ready__inner { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); align-items: center; width: min(1240px, 100%); margin: 0 auto; gap: clamp(40px, 5vw, 76px); }

/* Copy — eyebrow uses an orange line with blue text (shared across blocks). */
.dsf-ready__eyebrow { display: inline-flex; align-items: center; gap: 9px; color: var(--dsf-eyebrow-color, var(--blue)); font-size: var(--dsf-eyebrow-size, 14px); font-weight: 850; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-ready__eyebrow i { width: 22px; height: 2px; background: var(--dsf-eyebrow-line-color, var(--coral)); }
.dsf-ready__heading h2 { margin: 14px 0 16px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(34px, 4.4vw, 58px); line-height: 1.03; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-ready__lead { max-width: 520px; margin: 0; color: #5d6a76; font-size: clamp(16px, 1.45vw, 19px); line-height: 1.56; }

.dsf-ready__steps { margin: clamp(28px, 3.4vw, 44px) 0 0; padding: 0; list-style: none; display: grid; gap: 4px; counter-reset: ready; }
.dsf-ready__step { display: grid; grid-template-columns: auto 1fr; align-items: start; gap: 18px; padding: 16px 0; border-top: 1px solid rgba(12, 95, 168, 0.12); }
.dsf-ready__step:first-child { border-top: 0; }
.dsf-ready__step-num { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 11px; color: #fff; background: var(--blue); font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 13px; font-weight: 850; box-shadow: 0 10px 22px rgba(12, 95, 168, 0.22); }
.dsf-ready__step-body h3 { margin: 1px 0 4px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 19px; line-height: 1.2; }
.dsf-ready__step-body p { margin: 0; color: #65717b; font-size: 15px; line-height: 1.5; }

.dsf-ready__note { display: inline-flex; align-items: center; gap: 9px; margin: clamp(22px, 2.6vw, 30px) 0 0; color: #4d5a66; font-size: 14.5px; font-weight: 650; }
.dsf-ready__note svg { flex: 0 0 auto; color: var(--blue); }

/* Editor studio — a faithful, restrained mockup of the DesignStudio Flow editor. */
.dsf-estudio { position: relative; min-width: 0; overflow: hidden; border: 1px solid rgba(17, 24, 39, 0.12); border-radius: 18px; background: #fff; box-shadow: 0 35px 90px rgba(26, 45, 64, 0.18), 0 4px 12px rgba(26, 45, 64, 0.08); transform: rotate(-0.35deg); will-change: transform; }

.dsf-estudio__body { display: grid; grid-template-columns: minmax(300px, 1fr) 200px; min-height: 432px; }
.dsf-estudio__canvas { position: relative; display: flex; flex-direction: column; gap: 11px; padding: 20px 20px 66px; background: #eef1f3; }
.dsf-estudio__canvas-label { display: flex; justify-content: space-between; color: #86919a; font-size: 7px; font-weight: 800; letter-spacing: 0.11em; }
.dsf-estudio__canvas-label b { color: #65727d; letter-spacing: 0; }
.dsf-estudio__ghost-block { display: flex; flex-direction: column; gap: 6px; padding: 12px; border: 1px solid #e1e5e8; border-radius: 8px; background: #fff; }
.dsf-estudio__ghost-block i { height: 6px; border-radius: 3px; background: #e6eaed; }
.dsf-estudio__ghost-block i:first-child { width: 44%; }
.dsf-estudio__ghost-block i:last-child { width: 72%; }

.dsf-estudio__selected-block { position: relative; padding: 3px; border: 2px solid var(--blue); border-radius: 10px; box-shadow: 0 0 0 3px rgba(0, 145, 255, 0.1); }
.dsf-estudio__block-tools { position: absolute; top: -26px; right: 8px; z-index: 2; display: flex; overflow: hidden; border-radius: 5px; color: #fff; background: #071b2f; box-shadow: 0 5px 10px rgba(7, 27, 47, 0.18); }
.dsf-estudio__block-tools span { display: grid; place-items: center; width: 24px; height: 23px; border-right: 1px solid rgba(255,255,255,0.15); }

/* Countdown block being edited */
.dsf-countdown-card { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 16px; overflow: hidden; min-height: 168px; padding: 24px 26px; border-radius: 8px; color: #fff; background: linear-gradient(124deg, var(--blue) 0%, color-mix(in srgb, var(--blue) 58%, #071b2f) 100%); }
.dsf-countdown-card__text { min-width: 0; }
.dsf-countdown-card__eyebrow { display: inline-block; margin-bottom: 7px; color: #fff; font-size: 8px; font-weight: 850; letter-spacing: 0.14em; text-transform: uppercase; opacity: 0.85; }
.dsf-countdown-card h4 { margin: 0 0 6px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(16px, 1.7vw, 22px); line-height: 1.08; letter-spacing: -0.01em; }
.dsf-countdown-card__text > p { margin: 0 0 14px; color: rgba(255,255,255,0.78); font-size: 9.5px; line-height: 1.45; }
.dsf-countdown-card__btn { display: inline-block; padding: 8px 13px; border-radius: 6px; background: #fff; color: #071b2f; font-size: 9px; font-weight: 850; }
.dsf-countdown-card__timer { display: flex; gap: 6px; }
.dsf-countdown-card__timer i { display: grid; place-items: center; width: 40px; padding: 9px 4px; border: 1px solid rgba(255,255,255,0.2); border-radius: 7px; background: rgba(255,255,255,0.13); font-style: normal; }
.dsf-countdown-card__timer b { font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 18px; line-height: 1; }
.dsf-countdown-card__timer span { margin-top: 5px; color: rgba(255,255,255,0.78); font-size: 6.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }

/* New editor UI: floating action dock replacing the old top bar. */
.dsf-estudio__dock { position: absolute; bottom: 13px; left: 50%; z-index: 3; display: flex; align-items: center; gap: 3px; padding: 4px; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 999px; background: linear-gradient(180deg, #262b33, #14171c); box-shadow: 0 12px 26px rgba(7, 27, 47, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.06); transform: translateX(-50%); }
.dsf-estudio__dock-mark { display: grid; place-items: center; width: 24px; height: 24px; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 999px; background: rgba(255, 255, 255, 0.08); }
.dsf-estudio__dock-mark img { width: 15px; height: 15px; object-fit: contain; }
.dsf-estudio__dock-sep { width: 1px; height: 15px; margin: 0 2px; background: rgba(255, 255, 255, 0.14); }
.dsf-estudio__dock-btn { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 6px; color: rgba(255, 255, 255, 0.7); }
.dsf-estudio__dock-btn.is-active { color: #fff; background: rgba(255, 255, 255, 0.15); }
.dsf-estudio__dock-btn--primary { color: #fff; background: var(--blue); }
.dsf-estudio__dock-btn--accent { color: #fff; background: var(--coral); }

.dsf-estudio__settings { padding: 15px 13px; border-left: 1px solid #e4e8eb; background: #fff; }
.dsf-estudio__settings-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 11px; }
.dsf-estudio__settings-head > span { display: grid; }
.dsf-estudio__settings-head strong { color: #25313a; font-size: 10px; }
.dsf-estudio__settings-head small { margin: 2px 0 0; color: #89949c; font-size: 7px; }
.dsf-estudio__settings-head b { color: #4d5963; font-size: 13px; }
.dsf-estudio__tabs { display: grid; grid-template-columns: repeat(2, 1fr); margin-bottom: 10px; padding: 3px; border-radius: 6px; background: #f1f3f5; font-size: 8px; text-align: center; }
.dsf-estudio__tabs span { display: flex; align-items: center; justify-content: center; padding: 6px; border-radius: 4px; gap: 4px; color: #77818a; }
.dsf-estudio__tabs span.is-active { color: #071b2f; background: #fff; box-shadow: 0 2px 5px rgba(17,24,39,0.08); }
.dsf-estudio__expander { margin: 0 -3px 8px; padding: 8px 7px; border: 1px solid #e2e6e9; border-radius: 6px; background: #fbfcfc; }
.dsf-estudio__expander-title, .dsf-estudio__expander.is-closed { display: flex; align-items: center; justify-content: space-between; }
.dsf-estudio__expander-title strong, .dsf-estudio__expander.is-closed strong { color: #4d5963; font-size: 8px; }
.dsf-estudio__expander.is-closed { padding: 8px; }
.dsf-estudio__settings label { display: flex; align-items: center; justify-content: space-between; margin: 10px 0; color: #77818a; font-size: 8px; }
.dsf-estudio__settings label span { color: #35414a; font-weight: 700; }
.dsf-estudio__settings label em { max-width: 86px; overflow: hidden; padding: 5px; border: 1px solid #e0e5e8; border-radius: 4px; color: #4f5d68; font-size: 7px; font-style: normal; text-overflow: ellipsis; white-space: nowrap; }
.dsf-estudio__range { height: 3px; margin-top: 17px; border-radius: 4px; background: #e1e6e9; }
.dsf-estudio__range b { display: block; width: 64%; height: 100%; background: var(--blue); }
.dsf-estudio__settings > small { display: block; margin-top: 17px; padding: 8px; border-radius: 6px; color: #44617a; background: #edf7ff; font-size: 7px; line-height: 1.5; }

@media (max-width: 980px) {
  .dsf-ready__inner { grid-template-columns: 1fr; gap: 44px; }
  .dsf-ready__demo { max-width: 620px; }
  .dsf-ready__lead { max-width: none; }
}

@media (max-width: 640px) {
  .dsf-ready { padding-left: 18px; padding-right: 18px; }
  .dsf-ready__heading h2 { font-size: 32px; }
  .dsf-estudio__body { grid-template-columns: 1fr; min-height: 0; }
  .dsf-estudio__settings { display: none; }
  .dsf-estudio__dock { gap: 2px; padding: 3px; }
  .dsf-estudio__dock-btn { width: 20px; height: 20px; }
  .dsf-estudio__dock-sep { margin: 0 1px; }
  .dsf-countdown-card { grid-template-columns: 1fr; }
}
</style>
