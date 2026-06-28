<template>
  <section id="mail" ref="root" class="dsf-mail" :class="{ 'is-reversed': settings.reverseLayout }" :style="blockStyle" data-dsf-parallax-scope>
    <div class="dsf-mail__inner">
      <div class="dsf-mail__copy">
        <span class="dsf-mail__kicker" data-dsf-reveal><i></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
        <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" data-dsf-split placeholder="Title" />
        <InlineText tagName="p" v-model="settings.description" :is-editor="isEditor" :multiline="true" data-dsf-reveal placeholder="Description" />
        <ul data-dsf-reveal>
          <li v-for="field in featureFields" :key="field.key" v-show="isEditor || settings[field.key]">
            <Check :size="17" /> <InlineText tagName="span" v-model="settings[field.key]" :is-editor="isEditor" :placeholder="field.placeholder" />
          </li>
        </ul>
      </div>

      <div class="dsf-mail__visual" data-dsf-parallax="0.12">
        <div class="dsf-mail__panel" data-dsf-card>
          <div class="dsf-mail__panel-top"><Mail :size="15" /> <b>Mail / SMTP</b> <em>Connected</em></div>
          <div class="dsf-mail__mailers">
            <span v-for="mailer in mailers" :key="mailer.label" :class="{ 'is-active': mailer.active }">
              <component :is="mailer.icon" :size="14" /> {{ mailer.label }}
            </span>
          </div>
          <div class="dsf-mail__log-head">Email log <small>last 30 days</small></div>
          <div v-for="(entry, index) in log" :key="index" class="dsf-mail__log-row">
            <span class="dsf-mail__to">{{ entry.to }}</span>
            <span class="dsf-mail__subject">{{ entry.subject }}</span>
            <span class="dsf-mail__status" :class="entry.ok ? 'is-ok' : 'is-fail'">{{ entry.ok ? 'Sent' : 'Failed' }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Check, Globe, Mail, Send } from 'lucide-vue-next'
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
const mailers = [
  { label: 'PHP', icon: Send, active: false },
  { label: 'SendGrid', icon: Globe, active: false },
  { label: 'Gmail', icon: Mail, active: true },
  { label: 'Outlook', icon: Mail, active: false },
]
const log = [
  { to: 'jane@studio.com', subject: 'New form submission', ok: true },
  { to: 'team@brand.co', subject: 'Order confirmation #1182', ok: true },
  { to: 'lead@acme.io', subject: 'Welcome to DSFlow', ok: true },
  { to: 'old@invalid', subject: 'Password reset', ok: false },
]

useLandingMotion(root, props.isEditor)
</script>

<style scoped>
.dsf-mail {
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
.dsf-mail__inner { position: relative; display: grid; grid-template-columns: minmax(420px, 0.95fr) minmax(460px, 1.05fr); align-items: center; width: min(1180px, 100%); margin: 0 auto; gap: clamp(44px, 5.5vw, 70px); }
.dsf-mail.is-reversed .dsf-mail__copy { order: 2; }
.dsf-mail.is-reversed .dsf-mail__visual { order: 1; }
.dsf-mail__copy { max-width: 520px; }
.dsf-mail__kicker { display: inline-flex; align-items: center; gap: 9px; color: var(--dsf-eyebrow-color, var(--blue)); font-size: var(--dsf-eyebrow-size, 14px); font-weight: 850; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-mail__kicker i { width: 22px; height: 2px; background: var(--dsf-eyebrow-line-color, var(--coral)); }
.dsf-mail h2 { margin: 14px 0 22px; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: clamp(37px, 3.8vw, 54px); line-height: 1.05; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-mail__copy > p { margin: 0; color: #596775; font-size: 20px; line-height: 1.57; }
.dsf-mail ul { display: grid; margin: 28px 0 0; padding: 0; gap: 13px; list-style: none; }
.dsf-mail li { display: flex; align-items: center; gap: 10px; color: #34424e; font-size: 16px; font-weight: 650; }
.dsf-mail li svg { flex: 0 0 auto; color: var(--coral); }

.dsf-mail__visual { min-width: 0; }
.dsf-mail__panel { overflow: hidden; border: 1px solid rgba(17, 24, 39, 0.1); border-radius: 18px; background: #fff; box-shadow: 0 30px 70px rgba(29, 52, 68, 0.14); }
.dsf-mail__panel-top { display: flex; align-items: center; gap: 8px; height: 46px; padding: 0 15px; border-bottom: 1px solid #e6eaee; background: #f9fafb; font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif); font-size: 13px; }
.dsf-mail__panel-top svg { color: var(--blue); }
.dsf-mail__panel-top em { margin-left: auto; padding: 3px 9px; border-radius: 999px; color: #1a7f37; background: rgba(26, 127, 55, 0.12); font-size: 10px; font-style: normal; font-weight: 800; }
.dsf-mail__mailers { display: grid; grid-template-columns: repeat(4, 1fr); gap: 7px; padding: 14px 15px; border-bottom: 1px solid #eef1f3; }
.dsf-mail__mailers span { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 8px 6px; border: 1px solid #dde4e8; border-radius: 8px; color: #5a6772; font-size: 10.5px; font-weight: 700; }
.dsf-mail__mailers span svg { color: #9aa6af; }
.dsf-mail__mailers span.is-active { border-color: var(--blue); color: var(--blue); background: rgba(0, 145, 255, 0.07); }
.dsf-mail__mailers span.is-active svg { color: var(--blue); }
.dsf-mail__log-head { display: flex; align-items: baseline; gap: 8px; padding: 13px 15px 8px; color: #1f2c38; font-size: 12px; font-weight: 850; }
.dsf-mail__log-head small { color: #9aa6af; font-size: 10px; font-weight: 600; }
.dsf-mail__log-row { display: grid; grid-template-columns: 0.9fr 1.3fr auto; align-items: center; padding: 9px 15px; border-top: 1px solid #f0f3f5; font-size: 11.5px; gap: 8px; }
.dsf-mail__to { color: #1f2c38; font-weight: 650; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dsf-mail__subject { color: #6a7782; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dsf-mail__status { padding: 2px 8px; border-radius: 999px; font-size: 9.5px; font-weight: 800; }
.dsf-mail__status.is-ok { color: #1a7f37; background: rgba(26, 127, 55, 0.12); }
.dsf-mail__status.is-fail { color: #b32d2e; background: rgba(179, 45, 46, 0.12); }

@media (max-width: 1020px) {
  .dsf-mail__inner { grid-template-columns: 1fr; }
  .dsf-mail__copy { max-width: 700px; }
  .dsf-mail.is-reversed .dsf-mail__copy, .dsf-mail.is-reversed .dsf-mail__visual { order: initial; }
}
@media (max-width: 620px) {
  .dsf-mail { padding-right: 18px; padding-left: 18px; }
}
</style>
