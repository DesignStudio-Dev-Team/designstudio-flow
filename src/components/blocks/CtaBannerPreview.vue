<template>
  <div 
    class="dsf-block-preview dsf-cta-banner-preview"
    :style="previewStyle"
  >
    <div class="dsf-cta-banner-preview__inner">
      <div class="dsf-cta-banner-preview__text">
        <InlineText 
          v-model="settings.title" 
          tagName="h2"
          class="dsf-cta-banner-preview__title"
          :style="{ color: settings.titleColor || '#FFFFFF' }"
          :is-editor="isEditor"
          placeholder="Get 20% Off Your First Order"
        />
        <InlineText 
          v-model="settings.subtitle" 
          tagName="p"
          class="dsf-cta-banner-preview__subtitle"
          :style="{ color: settings.textColor || '#FFFFFF' }"
          :is-editor="isEditor"
          placeholder="Sign up for our newsletter"
        />
      </div>
      <a 
        class="dsf-cta-banner-preview__btn"
        :href="buttonHref"
        :style="{ 
          backgroundColor: settings.buttonColor || '#FFFFFF', 
          color: settings.buttonTextColor || '#1E40AF' 
        }"
        @click="handleButtonClick"
      >
        <InlineText 
          v-model="settings.buttonText" 
          tagName="span"
          :is-editor="isEditor"
          placeholder="Shop Now"
        />
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
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

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#1E40AF',
  }
})
</script>

<style scoped>
.dsf-cta-banner-preview__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 1000px;
  margin: 0 auto;
  gap: 2rem;
}

.dsf-cta-banner-preview {
  container-type: inline-size;
}

.dsf-cta-banner-preview__title {
  font-size: 42px; /* Updated font size */
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.dsf-cta-banner-preview__subtitle {
  opacity: 0.9;
  font-size: 24px; /* Updated font size */
}

.dsf-cta-banner-preview__btn {
  padding: 0.875rem 2rem;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-weight: 600;
  font-size: 24px; /* Updated font size */
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

@container (max-width: 1024px) {
  .dsf-cta-banner-preview__title { font-size: 32px; }
  .dsf-cta-banner-preview__subtitle { font-size: 18px; }
  .dsf-cta-banner-preview__btn { font-size: 18px; }
}

@container (max-width: 768px) {
  .dsf-cta-banner-preview__inner {
    flex-direction: column;
    align-items: flex-start;
  }

  .dsf-cta-banner-preview__btn {
    width: 100%;
    justify-content: center;
  }
}

@container (max-width: 520px) {
  .dsf-cta-banner-preview__inner {
    align-items: center;
    text-align: center;
  }

  .dsf-cta-banner-preview__text {
    width: 100%;
  }
}
</style>
