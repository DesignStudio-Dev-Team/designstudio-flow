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
        <h4 
          class="dsf-feature-card-preview__title"
          :style="{ color: settings.cardTitleColor || '#60A5FA' }"
        >
          {{ feature.title }}
        </h4>
        <p 
          class="dsf-feature-card-preview__desc"
          :style="{ color: settings.cardDescriptionColor || '#9CA3AF' }"
        >
          {{ feature.description }}
        </p>
        <a 
          v-if="feature.buttonText"
          :href="feature.buttonUrl || '#'"
          class="dsf-feature-card-preview__btn"
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

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 60}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
}))

const cardStyle = computed(() => ({
  backgroundColor: props.settings?.cardColor || '#1F2937',
}))

const displayFeatures = computed(() => {
  return props.settings?.features || [
    { title: 'Easy to Use', description: 'Intuitive drag-and-drop interface', buttonText: 'Learn More', buttonUrl: '#' },
    { title: 'Customizable', description: 'Full control over styling and layout', buttonText: 'Learn More', buttonUrl: '#' },
    { title: 'Responsive', description: 'Works perfectly on all devices', buttonText: 'Learn More', buttonUrl: '#' },
  ]
})
</script>

<style scoped>
.dsf-features-grid-preview__header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.dsf-features-grid-preview__title {
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.dsf-features-grid-preview__subtitle {
  font-size: 1rem;
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
}

.dsf-feature-card-preview__title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.dsf-feature-card-preview__desc {
  font-size: 0.875rem;
  flex: 1;
}

.dsf-feature-card-preview__btn {
  display: inline-block;
  margin-top: 0.75rem;
  padding: 0.5rem 1rem;
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border-radius: var(--dsf-radius-md);
  font-size: 0.8125rem;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s;
}

.dsf-feature-card-preview__btn:hover {
  background: rgba(255, 255, 255, 0.25);
}
</style>
