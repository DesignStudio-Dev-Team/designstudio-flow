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
        
        <button 
          v-if="settings.showButton" 
          class="dsf-text-image-preview__btn"
          :style="{ backgroundColor: settings.buttonColor, color: settings.buttonTextColor }"
        >
          <InlineText 
            v-model="settings.buttonText" 
            tagName="span"
            :is-editor="isEditor"
            placeholder="Learn More"
          />
        </button>
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

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const containerStyle = computed(() => ({
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  paddingTop: `${props.settings?.padding || 60}px`,
  paddingBottom: `${props.settings?.padding || 60}px`,
  paddingLeft: `${props.settings?.paddingX || 20}px`,
  paddingRight: `${props.settings?.paddingX || 20}px`,
  marginTop: `${props.settings?.marginY || 0}px`,
  marginBottom: `${props.settings?.marginY || 0}px`,
}))

</script>

<style scoped>
.dsf-text-image-container {
  width: 100%;
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
  font-size: 42px; /* Updated font size */
  font-weight: 600;
  color: var(--dsf-gray-800);
  margin-bottom: 1rem;
}

.dsf-text-image-preview__text {
  color: var(--dsf-gray-600);
  line-height: 1.7;
  font-size: 24px; /* Updated font size */
}

.dsf-text-image-preview__btn {
  display: inline-flex;
  margin-top: 1.5rem;
  padding: 0.75rem 1.5rem;
  background: var(--dsf-primary-600);
  color: white;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-weight: 600;
  font-size: 24px; /* Updated font size */
  cursor: pointer;
  transition: background 0.2s;
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
</style>
