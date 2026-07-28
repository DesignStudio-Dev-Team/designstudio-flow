<template>
  <section class="dsf-steps-image" :class="`dsf-steps-image--${layout}`" :style="blockStyle">
    <div class="dsf-steps-image__inner">
      <div class="dsf-steps-image__copy">
        <span v-if="settings.showEyebrow !== false" class="dsf-steps-image__eyebrow"><i></i><InlineText tagName="span" v-model="settings.eyebrow" :is-editor="isEditor" placeholder="Eyebrow" /></span>
        <InlineText tagName="h2" v-model="settings.title" :is-editor="isEditor" placeholder="Title" />
        <InlineText tagName="p" class="dsf-steps-image__description" v-model="settings.description" :is-editor="isEditor" :multiline="true" placeholder="Description" />

        <ol v-if="showSteps" class="dsf-steps-image__steps">
          <li v-for="(step, index) in steps" :key="step.titleKey">
            <span class="dsf-steps-image__number">{{ String(index + 1).padStart(2, '0') }}</span>
            <div>
              <InlineText tagName="h3" v-model="settings[step.titleKey]" :is-editor="isEditor" :placeholder="step.titlePlaceholder" />
              <InlineText tagName="p" v-model="settings[step.textKey]" :is-editor="isEditor" :multiline="true" :placeholder="step.textPlaceholder" />
            </div>
          </li>
        </ol>

        <p v-if="showNote" class="dsf-steps-image__note"><Check :size="16" aria-hidden="true" /><InlineText tagName="span" v-model="settings.note" :is-editor="isEditor" placeholder="Supporting note" /></p>
      </div>

      <div class="dsf-steps-image__visual">
        <img v-if="imageUrl" :src="imageUrl" :alt="settings.title || ''" loading="lazy" />
        <div v-else class="dsf-steps-image__placeholder" aria-hidden="true">
          <div><ImageIcon :size="34" /><span>Supporting image</span></div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { Check, Image as ImageIcon } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { landingBlockStyle } from '../../utils/landingStyle'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: { type: Boolean, default: false } })
const blockStyle = computed(() => landingBlockStyle(props.settings))
const layout = computed(() => ['image-left', 'stacked'].includes(props.settings?.layout) ? props.settings.layout : 'image-right')
const showSteps = computed(() => props.settings?.showSteps !== false)
const showNote = computed(() => props.settings?.showNote !== false)
const imageUrl = computed(() => safePublicUrl(props.settings?.image, ''))
const steps = [
  { titleKey: 'step1Title', textKey: 'step1Text', titlePlaceholder: 'Step 1 title', textPlaceholder: 'Step 1 text' },
  { titleKey: 'step2Title', textKey: 'step2Text', titlePlaceholder: 'Step 2 title', textPlaceholder: 'Step 2 text' },
  { titleKey: 'step3Title', textKey: 'step3Text', titlePlaceholder: 'Step 3 title', textPlaceholder: 'Step 3 text' },
]
</script>

<style scoped>
.dsf-steps-image { --steps-accent: var(--dsf-theme-primary, #0091ff); --steps-text: var(--dsf-theme-text, #111827); position: relative; overflow: hidden; padding: clamp(64px, 8vw, 112px) 24px; color: var(--steps-text); background: var(--dsf-theme-background, #f7f4ed); font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif); }
.dsf-steps-image__inner { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); align-items: center; width: min(1180px, 100%); margin: 0 auto; gap: clamp(36px, 6vw, 76px); }
.dsf-steps-image__copy { max-width: 560px; }
.dsf-steps-image__eyebrow { display: inline-flex; align-items: center; gap: 9px; color: var(--dsf-eyebrow-color, var(--steps-accent)); font-size: var(--dsf-eyebrow-size, 12px); font-weight: 800; letter-spacing: 0.13em; text-transform: uppercase; }
.dsf-steps-image__eyebrow i { width: 22px; height: 2px; background: var(--dsf-eyebrow-line-color, var(--steps-accent)); }
.dsf-steps-image h2 { margin: 14px 0 18px; font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2.2rem, 4.2vw, 4rem); line-height: 1.04; letter-spacing: -0.045em; text-wrap: balance; }
.dsf-steps-image__description { max-width: 540px; margin: 0; color: color-mix(in srgb, var(--steps-text) 65%, transparent); font-size: clamp(1rem, 1.5vw, 1.2rem); line-height: 1.55; }
.dsf-steps-image__steps { display: grid; gap: 0; margin: 34px 0 0; padding: 0; list-style: none; }
.dsf-steps-image__steps li { display: grid; grid-template-columns: auto 1fr; gap: 16px; padding: 16px 0; border-top: 1px solid color-mix(in srgb, var(--steps-accent) 18%, transparent); }
.dsf-steps-image__number { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; color: #fff; background: var(--steps-accent); font-size: 12px; font-weight: 800; }
.dsf-steps-image__steps h3 { margin: 1px 0 4px; font-family: var(--dsf-theme-heading-font, inherit); font-size: 1.05rem; }
.dsf-steps-image__steps p { margin: 0; color: color-mix(in srgb, var(--steps-text) 62%, transparent); font-size: 0.95rem; line-height: 1.45; }
.dsf-steps-image__note { display: inline-flex; align-items: center; gap: 8px; margin: 24px 0 0; color: color-mix(in srgb, var(--steps-text) 72%, transparent); font-size: 0.9rem; font-weight: 650; }
.dsf-steps-image__note svg { flex: 0 0 auto; color: var(--steps-accent); }
.dsf-steps-image__visual { min-height: 420px; overflow: hidden; border-radius: 24px; background: color-mix(in srgb, var(--steps-accent) 12%, #fff); box-shadow: 0 24px 60px rgba(17, 24, 39, 0.14); }
.dsf-steps-image__visual img { display: block; width: 100%; height: 100%; min-height: 420px; object-fit: cover; }
.dsf-steps-image__placeholder { display: grid; place-items: center; width: 100%; height: 100%; min-height: 420px; color: color-mix(in srgb, var(--steps-text) 45%, transparent); background: linear-gradient(135deg, color-mix(in srgb, var(--steps-accent) 18%, #fff), color-mix(in srgb, var(--steps-accent) 5%, #fff)); }
.dsf-steps-image__placeholder div { display: grid; justify-items: center; gap: 10px; font-size: 0.9rem; font-weight: 700; }
.dsf-steps-image--image-left .dsf-steps-image__copy { order: 2; }
.dsf-steps-image--image-left .dsf-steps-image__visual { order: 1; }
.dsf-steps-image--stacked .dsf-steps-image__inner { grid-template-columns: 1fr; }
.dsf-steps-image--stacked .dsf-steps-image__copy { max-width: 760px; }
.dsf-steps-image--stacked .dsf-steps-image__visual { min-height: 360px; }
.dsf-steps-image--stacked .dsf-steps-image__visual img, .dsf-steps-image--stacked .dsf-steps-image__placeholder { min-height: 360px; }
@media (max-width: 820px) { .dsf-steps-image__inner { grid-template-columns: 1fr; gap: 40px; } .dsf-steps-image--image-left .dsf-steps-image__copy, .dsf-steps-image--image-left .dsf-steps-image__visual { order: initial; } .dsf-steps-image__copy { max-width: none; } .dsf-steps-image__visual, .dsf-steps-image__visual img, .dsf-steps-image__placeholder { min-height: 340px; } }
@media (max-width: 480px) { .dsf-steps-image { padding-right: 18px; padding-left: 18px; } .dsf-steps-image__visual, .dsf-steps-image__visual img, .dsf-steps-image__placeholder { min-height: 260px; border-radius: 18px; } }
</style>
