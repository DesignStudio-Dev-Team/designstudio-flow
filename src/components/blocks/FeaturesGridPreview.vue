<template>
  <div 
    class="dsf-block-preview dsf-features-grid-preview"
    :style="previewStyle"
  >
    <div class="dsf-features-grid-preview__header">
      <InlineText 
        tagName="h2" 
        class="dsf-features-grid-preview__title"
        :style="{ color: settings.titleColor || '#1F2937' }"
        v-model="settings.title"
        :is-editor="isEditor"
        placeholder="Enter Section Title"
      />
      <InlineText 
        tagName="p" 
        class="dsf-features-grid-preview__subtitle"
        :style="{ color: settings.subtitleColor || '#6B7280' }"
        v-model="settings.subtitle"
        :is-editor="isEditor"
        placeholder="Enter subtitle..."
      />
    </div>
    
    <div 
      class="dsf-features-grid-preview__items"
      :style="{ '--columns': settings.columns || 3 }"
    >
      <div
        v-for="(feature, idx) in displayFeatures"
        :key="idx"
        class="dsf-feature-card-preview"
        :style="cardStyle"
      >
        <!-- Image above title -->
        <img
          v-if="feature.image && (feature.imagePosition || 'above') === 'above'"
          :src="feature.image"
          class="dsf-feature-card-preview__image"
          alt=""
        />
        <h4
          class="dsf-feature-card-preview__title"
          :style="{ color: settings.cardTitleColor || '#60A5FA' }"
        >
          {{ feature.title }}
        </h4>
        <!-- Image below title -->
        <img
          v-if="feature.image && (feature.imagePosition || 'above') === 'below'"
          :src="feature.image"
          class="dsf-feature-card-preview__image"
          alt=""
        />
        <p
          class="dsf-feature-card-preview__desc"
          :style="{ color: settings.cardDescriptionColor || '#9CA3AF' }"
          v-html="feature.description"
        />
        <a
          v-if="feature.buttonText"
          :href="getFeatureHref(feature)"
          class="dsf-feature-card-preview__btn"
          :style="buttonStyle"
          @click="handleFeatureClick($event, feature)"
        >
          {{ feature.buttonText }}
        </a>
      </div>
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

function getFeatureAction(feature) {
  return feature?.buttonAction || 'link'
}

function getFeatureHref(feature) {
  return getFeatureAction(feature) === 'link' ? (feature?.buttonUrl || '#') : '#'
}

function getFeatureModalContent(feature) {
  const type = feature?.buttonModalContentType || 'wysiwyg'
  if (type === 'html') return feature?.buttonModalHtml || ''
  if (type === 'shortcode') return feature?.buttonModalShortcode || ''
  return feature?.buttonModalContent || ''
}

function handleFeatureClick(event, feature) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if (getFeatureAction(feature) === 'modal') {
    event.preventDefault()
    openModal({
      layout: feature?.buttonModalLayout || 'center',
      contentType: feature?.buttonModalContentType || 'wysiwyg',
      content: getFeatureModalContent(feature),
    })
  }
}

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const cardStyle = computed(() => ({
  backgroundColor: props.settings?.cardColor || '#1F2937',
}))

const buttonStyle = computed(() => {
  const style = {}
  const bg = props.settings?.buttonColor
  const text = props.settings?.buttonTextColor
  if (bg) { style.backgroundColor = bg; style.borderColor = bg }
  if (text) { style.color = text }
  return style
})

const displayFeatures = computed(() => {
  return props.settings?.features || [
    {
      title: 'Easy to Use',
      description: 'Intuitive drag-and-drop interface',
      image: '',
      imagePosition: 'above',
      buttonText: 'Learn More',
      buttonUrl: '#',
      buttonAction: 'link',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '',
      buttonModalHtml: '',
      buttonModalShortcode: '',
    },
    {
      title: 'Customizable',
      description: 'Full control over styling and layout',
      image: '',
      imagePosition: 'above',
      buttonText: 'Learn More',
      buttonUrl: '#',
      buttonAction: 'link',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '',
      buttonModalHtml: '',
      buttonModalShortcode: '',
    },
    {
      title: 'Responsive',
      description: 'Works perfectly on all devices',
      image: '',
      imagePosition: 'above',
      buttonText: 'Learn More',
      buttonUrl: '#',
      buttonAction: 'link',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '',
      buttonModalHtml: '',
      buttonModalShortcode: '',
    },
  ]
})
</script>

<style scoped>
.dsf-features-grid-preview__header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.dsf-features-grid-preview {
  container-type: inline-size;
}

.dsf-features-grid-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2, 2rem);
  font-weight: 600;
  margin-bottom: 0.5rem;
  line-height: 1.2;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-features-grid-preview__subtitle {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.5;
}

.dsf-features-grid-preview__items {
  display: grid;
  grid-template-columns: repeat(var(--columns, 3), 1fr);
  gap: 1.5rem;
  max-width: 1000px;
  margin: 0 auto;
}

.dsf-feature-card-preview {
  padding: 1.5rem;
  border-radius: var(--dsf-radius-lg);
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  overflow: hidden;
}

.dsf-feature-card-preview__image {
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
  border-radius: var(--dsf-radius-md);
}

.dsf-feature-card-preview__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h4, 1.25rem);
  font-weight: 600;
  margin-bottom: 0.25rem;
  line-height: 1.2;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-feature-card-preview__desc {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  flex: 1;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 4;
  line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.dsf-feature-card-preview__btn {
  display: inline-block;
  margin-top: 0.75rem;
  padding: 0.5rem 1rem;
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border-radius: var(--dsf-radius-md);
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-sm, 0.8125rem);
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s;
  line-height: 1.25;
  white-space: nowrap;
}

.dsf-feature-card-preview__btn:hover {
  background: rgba(255, 255, 255, 0.25);
}

@container (max-width: 1024px) {
  .dsf-features-grid-preview__items {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@container (max-width: 768px) {
  .dsf-features-grid-preview__items {
    grid-template-columns: 1fr;
  }
}
</style>
