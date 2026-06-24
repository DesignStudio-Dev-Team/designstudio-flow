<template>
  <div 
    class="dsf-text-image-container"
    :style="containerStyle"
  >
    <div 
      class="dsf-block-preview dsf-text-image-preview"
      :class="{ 'dsf-text-image-preview--reverse': settings.imagePosition === 'left' }"
    >
      <div class="dsf-text-image-preview__content">
        <InlineText 
          v-model="settings.title" 
          tagName="h2"
          class="dsf-text-image-preview__title"
          :style="{ color: settings.titleColor }"
          :is-editor="isEditor"
          placeholder="About Our Story"
        />
        <InlineText 
          v-model="settings.content" 
          tagName="p"
          class="dsf-text-image-preview__text"
          :class="{ 'dsf-text-image-preview__text--normal': descriptionSize === 'normal' }"
          :style="{ color: settings.textColor }"
          :is-editor="isEditor"
          placeholder="Share your brand story here."
          :multiline="true"
        />
        
        <a 
          v-if="settings.showButton" 
          class="dsf-text-image-preview__btn"
          :href="buttonHref"
          :style="{ backgroundColor: settings.buttonColor, color: settings.buttonTextColor }"
          @click="handleButtonClick"
        >
          <InlineText 
            v-model="settings.buttonText" 
            tagName="span"
            :is-editor="isEditor"
            placeholder="Learn More"
          />
        </a>
      </div>
      <div class="dsf-text-image-preview__image">
        <img v-if="imageSrc" :src="imageSrc" alt="" />
        <div v-else class="dsf-text-image-preview__placeholder">
          <ImageIcon :size="48" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Image as ImageIcon } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { useFlowModal } from '../common/useFlowModal'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const { openModal } = useFlowModal()

const descriptionSize = computed(() => (
  props.settings?.descriptionSize === 'normal' ? 'normal' : 'large'
))
const imageSrc = computed(() => safePublicUrl(props.settings?.image, ''))

const buttonHref = computed(() =>
  (props.settings?.buttonAction || 'link') === 'link'
    ? safePublicUrl(props.settings?.buttonUrl)
    : '#'
)

function getModalContent() {
  const type = props.settings?.buttonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.buttonModalHtml || ''
  if (type === 'shortcode') return props.settings?.buttonModalShortcode || ''
  return props.settings?.buttonModalContent || ''
}

function handleButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.buttonAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.buttonModalLayout || 'center',
      contentType: props.settings?.buttonModalContentType || 'wysiwyg',
      content: getModalContent(),
    })
  }
}

const containerStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 20
  const marginY = getResponsiveValue(props.settings || {}, props.previewMode, 'marginY') ?? 0
  const height = getResponsiveValue(props.settings || {}, props.previewMode, 'height') ?? 400
  return {
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
    '--dsf-text-image-height': `${Math.max(100, Math.min(800, Number(height) || 400))}px`,
    '--dsf-text-image-padding-y': `${Math.max(0, Number(paddingY) || 0)}px`,
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    paddingLeft: `${paddingX}px`,
    paddingRight: `${paddingX}px`,
    marginTop: `${marginY}px`,
    marginBottom: `${marginY}px`,
  }
})

</script>

<style scoped>
.dsf-text-image-container {
  width: 100%;
  container-type: inline-size;
}

.dsf-text-image-preview {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
  min-height: max(100px, calc(var(--dsf-text-image-height, 400px) - var(--dsf-text-image-padding-y, 60px) - var(--dsf-text-image-padding-y, 60px)));
}

.dsf-text-image-preview--reverse {
  direction: rtl;
}

.dsf-text-image-preview--reverse > * {
  direction: ltr;
}

.dsf-text-image-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h1, 42px);
  font-weight: 600;
  color: var(--dsf-gray-800);
  margin-bottom: 1rem;
  line-height: 1.15;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-text-image-preview__text {
  font-family: var(--dsf-theme-body-font, inherit);
  color: var(--dsf-gray-600);
  line-height: 1.7;
  font-size: var(--dsf-theme-text-2xl, 24px);
  word-wrap: break-word;
  overflow-wrap: break-word;
  margin: 0;
}

.dsf-text-image-preview__text.dsf-text-image-preview__text--normal {
  font-size: var(--dsf-theme-p-size, var(--dsf-theme-text-base, 16px));
  line-height: 1.6;
}

.dsf-text-image-preview__btn {
  display: inline-flex;
  margin-top: 1.5rem;
  padding: 0.75rem 1.5rem;
  background: var(--dsf-primary-600);
  color: white;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-family: var(--dsf-theme-body-font, inherit);
  font-weight: 600;
  font-size: var(--dsf-theme-p-size, var(--dsf-theme-text-base, 16px));
  cursor: pointer;
  transition: background 0.2s;
  text-decoration: none;
  line-height: 1.25;
  white-space: nowrap;
}

.dsf-text-image-preview__btn:hover {
  background: var(--dsf-primary-700);
}

.dsf-text-image-preview__image img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: var(--dsf-radius-lg);
}

.dsf-text-image-preview__image {
  height: max(100px, calc(var(--dsf-text-image-height, 400px) - var(--dsf-text-image-padding-y, 60px) - var(--dsf-text-image-padding-y, 60px)));
}

.dsf-text-image-preview__placeholder {
  width: 100%;
  height: 100%;
  background: var(--dsf-gray-100);
  border-radius: var(--dsf-radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
}

@container (max-width: 1024px) {
  .dsf-text-image-preview { gap: 2rem; }
  .dsf-text-image-preview__title { font-size: var(--dsf-theme-h2, 34px); }
  .dsf-text-image-preview__text { font-size: var(--dsf-theme-text-lg, 18px); }
  .dsf-text-image-preview__btn { font-size: var(--dsf-theme-p-size, var(--dsf-theme-text-base, 16px)); }
}

@container (max-width: 900px) {
  .dsf-text-image-preview { grid-template-columns: 1fr; }
}

@container (max-width: 768px) {
  .dsf-text-image-preview {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .dsf-text-image-preview__image {
    order: -1;
  }

  .dsf-text-image-preview__btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
