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
          tagName="div"
          class="dsf-text-image-preview__text"
          :style="{ color: settings.textColor }"
          :is-editor="isEditor"
          placeholder="Share your brand story here."
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
        <img v-if="settings.image" :src="settings.image" alt="" />
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

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const { openModal } = useFlowModal()

const buttonHref = computed(() =>
  (props.settings?.buttonAction || 'link') === 'link'
    ? (props.settings?.buttonUrl || '#')
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
  return {
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
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
}

.dsf-text-image-preview--reverse {
  direction: rtl;
}

.dsf-text-image-preview--reverse > * {
  direction: ltr;
}

.dsf-text-image-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: 42px;
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
  font-size: 24px;
  word-wrap: break-word;
  overflow-wrap: break-word;
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
  font-size: 24px;
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
  width: 100%;
  border-radius: var(--dsf-radius-lg);
}

.dsf-text-image-preview__placeholder {
  aspect-ratio: 4/3;
  background: var(--dsf-gray-100);
  border-radius: var(--dsf-radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
}

@container (max-width: 1024px) {
  .dsf-text-image-preview { gap: 2rem; }
  .dsf-text-image-preview__title { font-size: 34px; }
  .dsf-text-image-preview__text { font-size: 18px; }
  .dsf-text-image-preview__btn { font-size: 18px; }
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
