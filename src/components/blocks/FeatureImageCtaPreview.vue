<template>
  <section class="dsf-feature-image-cta" :class="{ 'dsf-feature-image-cta--image-left': imagePosition === 'left' }" :style="sectionStyle">
    <div class="dsf-feature-image-cta__copy">
      <InlineText
        v-model="settings.title"
        tagName="h2"
        class="dsf-feature-image-cta__title"
        :is-editor="isEditor"
        placeholder="Add your heading"
      />
      <InlineText
        v-model="settings.description"
        tagName="p"
        class="dsf-feature-image-cta__description"
        :is-editor="isEditor"
        :multiline="true"
        placeholder="Add a short description for this feature."
      />

      <ul v-if="features.length || isEditor" class="dsf-feature-image-cta__features" aria-label="Highlights">
        <li v-for="(feature, index) in features" :key="`${feature.title}-${index}`">
          <span class="dsf-feature-image-cta__icon" aria-hidden="true"><component :is="iconFor(feature.icon)" :size="31" stroke-width="1.8" /></span>
          <InlineText
            v-model="feature.title"
            tagName="span"
            class="dsf-feature-image-cta__feature-label"
            :is-editor="isEditor"
            placeholder="Add icon label"
          />
        </li>
      </ul>

      <a
        v-if="settings.showButton"
        class="dsf-feature-image-cta__button"
        :href="buttonHref"
        :style="buttonStyle"
        @click="handleButtonClick"
      ><InlineText v-model="settings.buttonText" tagName="span" :is-editor="isEditor" placeholder="Add CTA text" /></a>
    </div>

    <div class="dsf-feature-image-cta__image-wrap" :style="imageStyle">
      <img v-if="imageUrl" class="dsf-feature-image-cta__image" :src="imageUrl" :alt="settings.title || ''">
      <div v-else class="dsf-feature-image-cta__placeholder"><ImageIcon :size="48" aria-hidden="true" /><span>Choose an image</span></div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { Image as ImageIcon } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { iconFor } from '../../utils/landingIcons'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const clamp = (value, min, max, fallback) => {
  const number = Number(value)
  return Number.isFinite(number) ? Math.max(min, Math.min(max, number)) : fallback
}
const safeHex = (value, fallback) => /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback
const features = computed(() => Array.isArray(props.settings?.features) ? props.settings.features.slice(0, 8).filter((item) => item && typeof item === 'object') : [])
const imagePosition = computed(() => props.settings?.imagePosition === 'left' ? 'left' : 'right')
const imageUrl = computed(() => {
  const image = typeof props.settings?.image === 'string' ? props.settings.image.trim() : ''
  return image ? safePublicUrl(image) : ''
})
const buttonHref = computed(() => safePublicUrl(props.settings?.buttonUrl || '#'))
const sectionStyle = computed(() => ({
  '--dsf-feature-image-cta-padding-y': `${clamp(props.settings?.paddingY, 0, 160, 48)}px`,
  '--dsf-feature-image-cta-radius': `${clamp(props.settings?.borderRadius, 0, 48, 20)}px`,
  backgroundColor: safeHex(props.settings?.backgroundColor, '#f3f4f6'),
}))
const imageStyle = computed(() => ({ padding: `${clamp(props.settings?.imageInset, 0, 200, 0)}px` }))
const buttonStyle = computed(() => ({
  backgroundColor: safeHex(props.settings?.buttonColor, '#2473a6'),
  color: safeHex(props.settings?.buttonTextColor, '#ffffff'),
}))

function handleButtonClick(event) {
  if (props.isEditor) event.preventDefault()
}
</script>

<style scoped>
.dsf-feature-image-cta { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); align-items: center; overflow: hidden; width: min(100% - 32px, 1560px); min-height: 430px; margin: 24px auto; border-radius: var(--dsf-feature-image-cta-radius); color: var(--dsf-theme-text, #111827); font-family: var(--dsf-theme-body-font, inherit); container-type: inline-size; }
.dsf-feature-image-cta--image-left .dsf-feature-image-cta__copy { order: 2; }
.dsf-feature-image-cta--image-left .dsf-feature-image-cta__image-wrap { order: 1; }
.dsf-feature-image-cta__copy { min-width: 0; padding: var(--dsf-feature-image-cta-padding-y) clamp(30px, 5vw, 68px); }
.dsf-feature-image-cta__title { display: block; margin: 0; font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(36px, 4vw, 58px); font-weight: 600; line-height: 1.08; letter-spacing: -0.04em; overflow-wrap: anywhere; }
.dsf-feature-image-cta__description { display: block; max-width: 620px; margin: 18px 0 0; color: var(--dsf-theme-text, #1f2937); font-size: clamp(16px, 1.4vw, 19px); line-height: 1.45; overflow-wrap: anywhere; }
.dsf-feature-image-cta__features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin: 31px 0 0; padding: 0; list-style: none; }
.dsf-feature-image-cta__features li { display: grid; justify-items: center; gap: 9px; min-width: 0; text-align: center; }
.dsf-feature-image-cta__icon { display: grid; place-items: center; width: 58px; height: 58px; border-radius: 50%; color: #0c1720; background: rgba(198, 240, 255, 0.65); box-shadow: 0 0 24px rgba(112, 211, 245, 0.46); }
.dsf-feature-image-cta__feature-label { display: block; font-size: 15px; font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
.dsf-feature-image-cta__button { display: inline-flex; align-items: center; justify-content: center; min-height: 54px; margin-top: 30px; padding: 0 34px; border-radius: 10px; font-size: 16px; font-weight: 800; line-height: 1; text-align: center; text-decoration: none; transition: filter 160ms ease, transform 160ms ease; }
.dsf-feature-image-cta__button:hover { filter: brightness(0.92); transform: translateY(-1px); }
.dsf-feature-image-cta__button:focus-visible { outline: 3px solid var(--dsf-theme-primary, #2473a6); outline-offset: 3px; }
.dsf-feature-image-cta__image-wrap { align-self: stretch; min-width: 0; box-sizing: border-box; }
.dsf-feature-image-cta__image { display: block; width: 100%; height: 100%; min-height: 430px; object-fit: contain; object-position: center; }
.dsf-feature-image-cta__placeholder { display: grid; place-content: center; justify-items: center; gap: 10px; width: 100%; min-height: 430px; color: #64748b; background: linear-gradient(135deg, #e5e7eb, #f8fafc); }
.dsf-feature-image-cta__placeholder span { font-size: 14px; font-weight: 700; }
@container (max-width: 800px) { .dsf-feature-image-cta { grid-template-columns: 1fr; } .dsf-feature-image-cta--image-left .dsf-feature-image-cta__copy, .dsf-feature-image-cta--image-left .dsf-feature-image-cta__image-wrap { order: initial; } .dsf-feature-image-cta__image-wrap { order: -1; } .dsf-feature-image-cta__image, .dsf-feature-image-cta__placeholder { min-height: 260px; } .dsf-feature-image-cta__copy { padding: 38px 30px; } }
@container (max-width: 540px) { .dsf-feature-image-cta { width: min(100% - 20px, 1560px); margin: 12px auto; } .dsf-feature-image-cta__features { grid-template-columns: 1fr; justify-items: start; gap: 14px; } .dsf-feature-image-cta__features li { grid-template-columns: 50px 1fr; justify-items: start; align-items: center; text-align: left; } .dsf-feature-image-cta__icon { width: 46px; height: 46px; } .dsf-feature-image-cta__button { width: 100%; } }
</style>
