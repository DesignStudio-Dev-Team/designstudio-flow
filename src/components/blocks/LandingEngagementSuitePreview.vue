<template>
  <section id="engagement" ref="root" class="dsf-engagement-suite" :style="blockStyle">
    <div class="dsf-engagement-suite__heading">
      <InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" data-dsf-reveal placeholder="Eyebrow" />
      <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" data-dsf-reveal placeholder="Section title" />
      <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Section description" />
    </div>

    <div class="dsf-engagement-suite__grid">
      <article class="dsf-engagement-card dsf-engagement-card--forms" data-dsf-card>
        <div class="dsf-engagement-card__copy">
          <span class="dsf-engagement-card__icon"><component :is="iconFor(settings.formsIcon || 'form-input')" :size="22" /></span>
          <small>01 / {{ settings.formsLabel || 'FORMS' }}</small>
          <InlineText tagName="h3" v-model="settings.formsTitle" :is-editor="isEditor" placeholder="Feature title" />
          <InlineText tagName="p" v-model="settings.formsDescription" :is-editor="isEditor" :multiline="true" placeholder="Feature description" />
          <ul v-if="formsBullets.length">
            <li v-for="bullet in formsBullets" :key="bullet"><Check :size="14" /> {{ bullet }}</li>
          </ul>
        </div>
        <BlockMedia class="dsf-engagement-card__media" :settings="settings" type-key="formsType" image-key="formsImage" video-key="formsVideo" :alt="settings.formsTitle || ''">
        <div class="dsf-form-builder" data-dsf-parallax aria-hidden="true">
          <div class="dsf-feature-window__bar">
            <span>Form Builder</span><b>Contact form</b><i>Save Form</i>
          </div>
          <div class="dsf-form-builder__body">
            <aside><small>FIELDS</small><span>Text</span><span>Email</span><span>Phone</span><span>Choice</span></aside>
            <main>
              <div class="dsf-form-builder__canvas-label"><span>FORM CANVAS</span><b>2 columns</b></div>
              <label><span>First name</span><i></i></label>
              <label><span>Last name</span><i></i></label>
              <label class="is-wide"><span>Email address</span><i></i></label>
              <label class="is-wide is-selected"><span>How can we help?</span><i></i><em>Required</em></label>
              <button type="button" tabindex="-1">Send message</button>
            </main>
            <section><small>FIELD SETTINGS</small><b>Message</b><span>Label</span><i></i><span>Width</span><em>Full</em><span>Required</span><strong></strong></section>
          </div>
        </div>
        </BlockMedia>
      </article>

      <article class="dsf-engagement-card dsf-engagement-card--popup" data-dsf-card>
        <div class="dsf-engagement-card__copy">
          <span class="dsf-engagement-card__icon"><component :is="iconFor(settings.popupIcon || 'panel-top')" :size="22" /></span>
          <small>02 / {{ settings.popupLabel || 'POPUPS' }}</small>
          <InlineText tagName="h3" v-model="settings.popupTitle" :is-editor="isEditor" placeholder="Feature title" />
          <InlineText tagName="p" v-model="settings.popupDescription" :is-editor="isEditor" :multiline="true" placeholder="Feature description" />
        </div>
        <BlockMedia class="dsf-engagement-card__media" :settings="settings" type-key="popupType" image-key="popupImage" video-key="popupVideo" :alt="settings.popupTitle || ''">
        <div class="dsf-popup-scene" aria-hidden="true">
          <div class="dsf-popup-scene__page"><i></i><i></i><i></i></div>
          <div class="dsf-popup-scene__modal" data-dsf-pulse>
            <X :size="12" />
            <small>JUST FOR YOU</small>
            <strong>A timely message.</strong>
            <span>Schedule by date, delay, and repeat visit.</span>
            <b>Learn more</b>
          </div>
          <div class="dsf-popup-scene__rules"><Clock3 :size="12" /><span>3 sec delay</span><i></i><span>24 hr cookie</span></div>
        </div>
        </BlockMedia>
      </article>

      <article class="dsf-engagement-card dsf-engagement-card--notification" data-dsf-card>
        <div class="dsf-engagement-card__copy">
          <span class="dsf-engagement-card__icon"><component :is="iconFor(settings.notificationIcon || 'bell')" :size="22" /></span>
          <small>03 / {{ settings.notificationLabel || 'NOTIFICATION BAR' }}</small>
          <InlineText tagName="h3" v-model="settings.notificationTitle" :is-editor="isEditor" placeholder="Feature title" />
          <InlineText tagName="p" v-model="settings.notificationDescription" :is-editor="isEditor" :multiline="true" placeholder="Feature description" />
        </div>
        <BlockMedia class="dsf-engagement-card__media" :settings="settings" type-key="notificationType" image-key="notificationImage" video-key="notificationVideo" :alt="settings.notificationTitle || ''">
        <div class="dsf-notification-scene" data-dsf-float aria-hidden="true">
          <div class="dsf-notification-scene__bar">
            <Sparkles :size="13" /><strong>Summer release is live</strong><span>Explore what is new</span><MousePointerClick :size="13" />
          </div>
          <div class="dsf-notification-scene__site">
            <div><i></i><span></span><span></span><span></span><b></b></div>
            <section><small>SITE-WIDE MESSAGE</small><strong>One announcement. Every page.</strong><p>Schedule it once and keep the experience consistent.</p></section>
          </div>
          <div class="dsf-notification-scene__status"><ShieldCheck :size="13" /><span>Published site wide</span><i></i><span>Ends Friday, 11:59 PM</span></div>
        </div>
        </BlockMedia>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Check, Clock3, MousePointerClick, ShieldCheck, Sparkles, X } from 'lucide-vue-next'
import { useLandingMotion } from '../../utils/useLandingMotion'
import { landingBlockStyle } from '../../utils/landingStyle'
import { iconFor } from '../../utils/landingIcons'
import InlineText from '../common/InlineText.vue'
import BlockMedia from '../common/BlockMedia.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const root = ref(null)
const blockStyle = computed(() => {
  const settings = { ...props.settings }
  const iconBackgroundColor = settings.accentColor
  delete settings.accentColor
  return landingBlockStyle({ ...settings, secondaryColor: iconBackgroundColor })
})
const defaultFormsBullets = ['Visual field builder', 'WordPress and Gravity Forms', 'Responsive, theme-aware styling']
const formsBullets = computed(() => {
  const raw = props.settings.formsBullets
  if (typeof raw === 'string' && raw.trim()) {
    return raw.split('\n').map((line) => line.trim()).filter(Boolean)
  }
  return Array.isArray(raw) && raw.length ? raw : defaultFormsBullets
})

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-engagement-suite {
  --blue: var(--dsf-theme-primary, #0091ff);
  --coral: var(--dsf-theme-secondary, #ff7100);
  position: relative;
  overflow: hidden;
  padding: clamp(82px, 9vw, 132px) 24px;
  color: #fff;
  background:
    radial-gradient(circle at 12% 10%, rgba(255,255,255,0.16), transparent 28%),
    linear-gradient(135deg, var(--blue), rgba(7, 27, 47, 0.72)),
    var(--blue);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}
.dsf-engagement-suite::after { position: absolute; right: -140px; bottom: -220px; width: 520px; height: 520px; border: 1px solid rgba(255,255,255,0.16); border-radius: 50%; content: ''; }
.dsf-engagement-suite__heading { position: relative; z-index: 1; width: min(1080px, 100%); margin: 0 auto clamp(46px, 6vw, 76px); text-align: center; }
.dsf-engagement-suite__heading > span { color: var(--dsf-eyebrow-color, #fff); font-size: var(--dsf-eyebrow-size, 14px); font-weight: 850; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-engagement-suite__heading h2 { margin: 14px 0 19px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(40px, 4.8vw, 62px); line-height: 1.02; letter-spacing: -0.05em; }
.dsf-engagement-suite__heading p { max-width: 730px; margin: 0 auto; color: rgba(255,255,255,0.82); font-size: 20px; line-height: 1.58; }
.dsf-engagement-suite__grid { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(12, 1fr); width: min(1220px, 100%); margin: 0 auto; gap: 16px; }
.dsf-engagement-card { overflow: hidden; min-width: 0; padding: clamp(24px, 3vw, 38px); border: 1px solid rgba(255,255,255,0.18); border-radius: 22px; color: #fff; background: rgba(7,27,47,0.18); box-shadow: 0 30px 70px rgba(4,46,80,0.2); backdrop-filter: blur(8px); transition: border-color 240ms ease, box-shadow 240ms ease, transform 240ms ease; }
.dsf-engagement-card:hover { border-color: rgba(255,255,255,0.34); box-shadow: 0 38px 90px rgba(4,46,80,0.3); transform: translateY(-5px); }
.dsf-engagement-card--forms { display: grid; grid-column: span 12; grid-template-columns: minmax(380px, 0.8fr) minmax(500px, 1.2fr); align-items: center; gap: clamp(28px, 3.5vw, 50px); }
.dsf-engagement-card--popup { grid-column: span 5; }
.dsf-engagement-card--notification { grid-column: span 7; }
.dsf-engagement-card__copy { position: relative; z-index: 2; }
.dsf-engagement-card__icon { display: grid; place-items: center; width: 45px; height: 45px; margin-bottom: 25px; border-radius: 13px; color: #fff; background: var(--coral); box-shadow: 0 12px 28px rgba(102,45,0,0.2); }
.dsf-engagement-card__copy small { color: rgba(255,255,255,0.66); font-size: 11px; font-weight: 850; letter-spacing: 0.13em; }
.dsf-engagement-card h3 { margin: 10px 0 12px; color: #fff; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(25px, 2.5vw, 36px); line-height: 1.08; letter-spacing: -0.035em; }
.dsf-engagement-card__copy p { margin: 0; color: rgba(255,255,255,0.79); font-size: 17px; line-height: 1.55; }
.dsf-engagement-card ul { display: grid; margin: 24px 0 0; padding: 0; list-style: none; gap: 9px; }
.dsf-engagement-card li { display: flex; align-items: center; gap: 8px; color: #fff; font-size: 14px; font-weight: 700; }
.dsf-engagement-card li svg { color: #ffc08e; }

.dsf-engagement-card__media { min-width: 0; width: 100%; }
.dsf-engagement-card__media.dsf-block-media--filled { min-height: 280px; border-radius: 14px; box-shadow: 0 24px 55px rgba(3,31,52,0.24); }
.dsf-engagement-card--popup .dsf-engagement-card__media.dsf-block-media--filled,
.dsf-engagement-card--notification .dsf-engagement-card__media.dsf-block-media--filled { margin-top: 28px; }
.dsf-form-builder, .dsf-popup-scene, .dsf-notification-scene { color: #111827; background: #fff; box-shadow: 0 24px 55px rgba(3,31,52,0.24); }
.dsf-form-builder { overflow: hidden; border-radius: 14px; will-change: transform; }
.dsf-feature-window__bar { display: flex; align-items: center; height: 40px; padding: 0 12px; border-bottom: 1px solid #e1e6e9; background: #f7f4ed; gap: 9px; font-size: 8px; }.dsf-feature-window__bar span { font-weight: 850; }.dsf-feature-window__bar b { margin-right: auto; color: #7a8790; font-weight: 650; }.dsf-feature-window__bar i { padding: 6px 9px; border-radius: 4px; color: #fff; background: var(--blue); font-style: normal; font-weight: 800; }
.dsf-form-builder__body { display: grid; grid-template-columns: 76px 1fr 104px; min-height: 310px; }
.dsf-form-builder__body > aside { display: flex; flex-direction: column; padding: 13px 9px; border-right: 1px solid #e3e7e9; background: #f4f6f7; gap: 7px; }.dsf-form-builder__body > aside small, .dsf-form-builder__body > section small { margin-bottom: 4px; color: #89949c; font-size: 6px; font-weight: 850; letter-spacing: 0.1em; }.dsf-form-builder__body > aside span { padding: 7px 6px; border: 1px solid #e0e5e8; border-radius: 4px; color: #5c6974; background: #fff; font-size: 7px; }
.dsf-form-builder__body > main { display: grid; grid-template-columns: repeat(2, 1fr); align-content: start; padding: 16px; background: #eef1f3; gap: 9px; }.dsf-form-builder__canvas-label { display: flex; grid-column: span 2; justify-content: space-between; color: #8a959e; font-size: 6px; font-weight: 850; letter-spacing: 0.1em; }.dsf-form-builder__canvas-label b { letter-spacing: 0; }.dsf-form-builder label { position: relative; display: grid; padding: 10px; border: 1px solid #dce2e6; border-radius: 6px; background: #fff; gap: 5px; }.dsf-form-builder label span { color: #52606b; font-size: 7px; font-weight: 700; }.dsf-form-builder label i { height: 18px; border: 1px solid #dce2e6; border-radius: 4px; }.dsf-form-builder label.is-wide { grid-column: span 2; }.dsf-form-builder label.is-selected { border: 2px solid var(--blue); box-shadow: 0 0 0 2px rgba(0,145,255,0.12); }.dsf-form-builder label em { position: absolute; top: 7px; right: 8px; color: var(--coral); font-size: 6px; font-style: normal; font-weight: 800; }.dsf-form-builder main button { grid-column: span 2; justify-self: start; padding: 7px 12px; border: 0; border-radius: 4px; color: #fff; background: var(--blue); font-size: 7px; font-weight: 850; }
.dsf-form-builder__body > section { display: flex; flex-direction: column; padding: 13px 10px; border-left: 1px solid #e3e7e9; gap: 7px; }.dsf-form-builder__body > section b { margin-bottom: 3px; font-size: 9px; }.dsf-form-builder__body > section span { color: #74808a; font-size: 7px; }.dsf-form-builder__body > section i { height: 20px; border: 1px solid #dce2e6; border-radius: 4px; }.dsf-form-builder__body > section em { padding: 5px; border: 1px solid #dce2e6; border-radius: 4px; color: #47545e; font-size: 7px; font-style: normal; }.dsf-form-builder__body > section strong { width: 23px; height: 13px; border-radius: 10px; background: var(--blue); }

.dsf-popup-scene { position: relative; overflow: hidden; min-height: 310px; margin-top: 28px; border-radius: 14px; }
.dsf-popup-scene__page { position: absolute; inset: 18px; display: grid; grid-template-columns: repeat(2, 1fr); opacity: 0.28; gap: 8px; }.dsf-popup-scene__page i { border-radius: 7px; background: #91a9b8; }.dsf-popup-scene__page i:first-child { grid-column: span 2; height: 72px; background: #34576c; }
.dsf-popup-scene__modal { position: absolute; top: 50%; left: 50%; width: min(260px, 78%); padding: 27px 24px; border-radius: 12px; background: #fff; box-shadow: 0 22px 60px rgba(17,31,42,0.27); text-align: center; transform: translate(-50%, -54%); }.dsf-popup-scene__modal > svg { position: absolute; top: 10px; right: 10px; color: #7d8991; }.dsf-popup-scene__modal small { color: var(--coral); font-size: 7px; font-weight: 850; letter-spacing: 0.11em; }.dsf-popup-scene__modal strong, .dsf-popup-scene__modal span { display: block; }.dsf-popup-scene__modal strong { margin: 8px 0 6px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 17px; }.dsf-popup-scene__modal span { color: #65727c; font-size: 9px; line-height: 1.4; }.dsf-popup-scene__modal b { display: inline-block; margin-top: 13px; padding: 7px 11px; border-radius: 4px; color: #fff; background: var(--blue); font-size: 7px; }
.dsf-popup-scene__rules { position: absolute; right: 10px; bottom: 9px; left: 10px; display: flex; align-items: center; padding: 7px 9px; border-radius: 6px; color: #65727c; background: rgba(247,244,237,0.94); font-size: 7px; gap: 6px; }.dsf-popup-scene__rules svg { color: var(--blue); }.dsf-popup-scene__rules i { width: 1px; height: 11px; margin: 0 2px; background: #ccd3d7; }

.dsf-notification-scene { overflow: hidden; min-height: 310px; margin-top: 28px; border-radius: 14px; will-change: transform; }
.dsf-notification-scene__bar { display: flex; align-items: center; min-height: 38px; padding: 0 14px; color: #fff; background: var(--blue); font-size: 8px; gap: 7px; }.dsf-notification-scene__bar strong { margin-right: auto; }.dsf-notification-scene__bar span { font-weight: 750; }
.dsf-notification-scene__site > div { display: flex; align-items: center; height: 42px; padding: 0 14px; border-bottom: 1px solid #e2e6e9; gap: 12px; }.dsf-notification-scene__site > div i { width: 22px; height: 22px; margin-right: auto; border-radius: 6px; background: linear-gradient(135deg, var(--coral) 50%, var(--blue) 50%); }.dsf-notification-scene__site > div span { width: 34px; height: 4px; border-radius: 4px; background: #aab4ba; }.dsf-notification-scene__site > div b { width: 48px; height: 17px; border-radius: 4px; background: var(--blue); }.dsf-notification-scene__site section { min-height: 173px; padding: 35px 30px; background: linear-gradient(130deg, #f7f4ed 0%, #f7f4ed 67%, #a4d2f6 67%); }.dsf-notification-scene__site small { color: var(--coral); font-size: 7px; font-weight: 850; letter-spacing: 0.11em; }.dsf-notification-scene__site strong, .dsf-notification-scene__site p { display: block; max-width: 270px; }.dsf-notification-scene__site strong { margin-top: 9px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 22px; line-height: 1.08; }.dsf-notification-scene__site p { margin: 8px 0 0; color: #65727c; font-size: 10px; line-height: 1.45; }
.dsf-notification-scene__status { display: flex; align-items: center; min-height: 38px; padding: 0 13px; color: #65727c; background: #fff; font-size: 7px; gap: 7px; }.dsf-notification-scene__status svg { color: var(--blue); }.dsf-notification-scene__status i { width: 1px; height: 12px; background: #d4dade; }

@media (max-width: 1020px) {
  .dsf-engagement-card--forms { grid-template-columns: 1fr; }
  .dsf-engagement-card--popup, .dsf-engagement-card--notification { grid-column: span 6; }
}
@media (max-width: 700px) {
  .dsf-engagement-suite { padding-right: 18px; padding-left: 18px; }
  .dsf-engagement-card--popup, .dsf-engagement-card--notification { grid-column: span 12; }
  .dsf-form-builder__body { grid-template-columns: 58px 1fr; }.dsf-form-builder__body > section { display: none; }
  .dsf-notification-scene__bar span, .dsf-notification-scene__bar > svg:last-child { display: none; }
}
</style>
