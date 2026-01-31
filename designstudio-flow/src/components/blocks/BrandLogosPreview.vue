<template>
  <div 
    class="dsf-block-preview dsf-brand-carousel-preview"
    :style="previewStyle"
  >
    <div class="dsf-brand-carousel-preview__inner">
      <!-- Title -->
      <InlineText 
        v-if="settings.showTitle !== false"
        v-model="settings.title"
        tagName="h2"
        class="dsf-brand-carousel-preview__title"
        :style="titleStyle"
        :is-editor="isEditor"
        placeholder="Shop By Brand"
      />
      
      <!-- Logos Grid -->
      <div class="dsf-brand-carousel-preview__grid" :style="gridStyle">
        <template v-if="displayBrands.length > 0">
          <a 
            v-for="(brand, idx) in displayBrands" 
            :key="idx"
            :href="brand.url || '#'"
            class="dsf-brand-item-preview"
            :style="itemStyle"
            @click.prevent
          >
            <img 
              v-if="brand.logo" 
              :src="brand.logo" 
              :alt="brand.name"
            />
            <span v-else class="dsf-brand-item-preview__name">{{ brand.name || 'Brand' }}</span>
          </a>
        </template>
        <template v-else>
          <div 
            v-for="i in 7" 
            :key="i"
            class="dsf-brand-item-preview dsf-brand-item-preview--placeholder"
          >
            <Award :size="100" />
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Award } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 40}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
}))

const titleStyle = computed(() => ({
  color: props.settings?.titleColor || '#1F2937',
  fontSize: '42px',
}))

const gridStyle = computed(() => ({
  gap: `${props.settings?.logoGap || 24}px`,
}))

// Calculate item width to ensure exactly 4 per row: (100% - 3 gaps) / 4
const itemStyle = computed(() => {
  const gap = props.settings?.logoGap || 24
  return {
    width: `calc((100% - ${gap * 3}px) / 4)`,
  }
})

const displayBrands = computed(() => {
  return props.settings?.brands || []
})
</script>

<style scoped>
.dsf-brand-carousel-preview {
  width: 100%;
}

.dsf-brand-carousel-preview__inner {
  max-width: 1800px;
  margin: 0 auto;
}

.dsf-brand-carousel-preview__title {
  text-align: center;
  font-weight: 500;
  margin: 0 0 2rem 0;
  line-height: 1.3;
}

.dsf-brand-carousel-preview__grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
}

.dsf-brand-item-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: opacity 0.2s;
  box-sizing: border-box;
}

.dsf-brand-item-preview:hover {
  opacity: 0.7;
}

.dsf-brand-item-preview img {
  max-width: 100%;
  object-fit: contain;
}

.dsf-brand-item-preview__name {
  color: var(--dsf-gray-700);
  font-weight: 600;
  font-size: 1.125rem;
}

.dsf-brand-item-preview--placeholder {
  width: 140px;
  height: 70px;
  color: var(--dsf-gray-300);
  border: 2px dashed var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
}
</style>
