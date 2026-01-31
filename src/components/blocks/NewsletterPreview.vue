<template>
  <div 
    class="dsf-block-preview dsf-newsletter-preview"
    :style="previewStyle"
  >
    <h2 class="dsf-newsletter-preview__title">{{ settings.title || 'Subscribe to Our Newsletter' }}</h2>
    <p class="dsf-newsletter-preview__subtitle">{{ settings.subtitle || 'Get the latest updates and offers' }}</p>
    
    <div class="dsf-newsletter-preview__form">
      <input 
        type="email" 
        :placeholder="settings.placeholder || 'Enter your email'"
        class="dsf-newsletter-preview__input"
      />
      <button class="dsf-newsletter-preview__btn">
        {{ settings.buttonText || 'Subscribe' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 60}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#F8FAFC',
}))
</script>

<style scoped>
.dsf-newsletter-preview {
  text-align: center;
  max-width: 600px;
  margin: 0 auto;
}

.dsf-newsletter-preview__title {
  font-size: 1.875rem;
  font-weight: 600;
  color: var(--dsf-gray-800);
  margin-bottom: 0.5rem;
}

.dsf-newsletter-preview__subtitle {
  color: var(--dsf-gray-500);
  margin-bottom: 1.5rem;
}

.dsf-newsletter-preview__form {
  display: flex;
  gap: 0.75rem;
  max-width: 500px;
  margin: 0 auto;
}

.dsf-newsletter-preview__input {
  flex: 1;
  padding: 0.875rem 1rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  font-size: 1rem;
}

.dsf-newsletter-preview__btn {
  padding: 0.875rem 1.5rem;
  background: var(--dsf-primary-500);
  color: white;
  border: none;
  border-radius: var(--dsf-radius-md);
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}
</style>
