<template>
  <div 
    class="dsf-block-preview dsf-hero-centered-preview"
    :style="previewStyle"
  >
    <div 
      class="dsf-hero-centered-preview__content"
      :style="contentStyle"
    >
      <InlineText 
        tagName="h2" 
        class="dsf-hero-centered-preview__title"
        v-model="settings.title"
        :is-editor="isEditor"
        placeholder="Enter Hero Title"
      />
      <InlineText 
        tagName="p" 
        class="dsf-hero-centered-preview__subtitle"
        v-model="settings.subtitle"
        :is-editor="isEditor"
        placeholder="Enter subtitle description..."
        :multiline="true"
      />
      <button v-if="settings.showButton !== false" class="dsf-hero-centered-preview__btn">
        <InlineText 
          tagName="span"
          v-model="settings.buttonText"
          :is-editor="isEditor"
          placeholder="Shop Now"
        />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const previewStyle = computed(() => {
  const position = props.settings?.contentPosition || 'center-center'
  const [vertical, horizontal] = position.split('-')
  
  const alignItemsMap = {
    left: 'flex-start',
    center: 'center',
    right: 'flex-end'
  }
  
  const justifyContentMap = {
    top: 'flex-start',
    center: 'center',
    bottom: 'flex-end'
  }

  return {
    padding: `${props.settings?.padding || 80}px 24px`,
    backgroundColor: props.settings?.backgroundColor || '#3B82F6',
    color: props.settings?.textColor || '#FFFFFF',
    backgroundImage: props.settings?.backgroundImage 
      ? `url(${props.settings.backgroundImage})` 
      : 'none',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    display: 'flex',
    flexDirection: 'column',
    minHeight: '500px',
    alignItems: alignItemsMap[horizontal] || 'center',
    justifyContent: justifyContentMap[vertical] || 'center',
  }
})

const contentStyle = computed(() => {
  const position = props.settings?.contentPosition || 'center-center'
  const [_, horizontal] = position.split('-')
  
  const textAlignMap = {
    left: 'left',
    center: 'center',
    right: 'right'
  }

  const hasBg = props.settings?.contentBackgroundColor && props.settings.contentBackgroundColor !== 'rgba(0,0,0,0)' && props.settings.contentBackgroundColor !== 'transparent'

  return {
    textAlign: textAlignMap[horizontal] || 'center',
    backgroundColor: props.settings?.contentBackgroundColor || 'transparent',
    padding: hasBg ? '2rem' : '0',
    borderRadius: hasBg ? 'var(--dsf-radius-lg)' : '0',
    maxWidth: '800px',
    width: '100%',
  }
})
</script>

<style scoped>
.dsf-hero-centered-preview {
  position: relative;
  /* alignment handled by inline styles now */
}

.dsf-hero-centered-preview__content {
  position: relative;
  z-index: 1;
}

.dsf-hero-centered-preview__title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: inherit; /* Inherit from wrapper */
}

.dsf-hero-centered-preview__subtitle {
  font-size: 1.125rem;
  opacity: 0.9;
  margin-bottom: 2rem;
  color: inherit; /* Inherit from wrapper */
}

.dsf-hero-centered-preview__btn {
  display: inline-flex;
  padding: 0.875rem 2rem;
  background: white;
  color: var(--dsf-primary-600);
  border: none;
  border-radius: var(--dsf-radius-md);
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}
</style>
