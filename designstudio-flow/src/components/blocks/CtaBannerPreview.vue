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
      <button 
        class="dsf-cta-banner-preview__btn"
        :style="{ 
          backgroundColor: settings.buttonColor || '#FFFFFF', 
          color: settings.buttonTextColor || '#1E40AF' 
        }"
      >
        <InlineText 
          v-model="settings.buttonText" 
          tagName="span"
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

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 60}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#1E40AF',
}))
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
}
</style>
