<template>
  <section class="dsf-starter-block" :style="blockStyle">
    <div class="dsf-starter-block__inner">
      <InlineText
        v-model="settings.title"
        tagName="h2"
        class="dsf-starter-block__title"
        :is-editor="isEditor"
        placeholder="Starter Block Title"
      />
      <InlineText
        v-model="settings.subtitle"
        tagName="p"
        class="dsf-starter-block__subtitle"
        :is-editor="isEditor"
        placeholder="Short description goes here."
      />
      <a
        class="dsf-starter-block__btn"
        :href="settings.buttonUrl || '#'"
        :style="{ backgroundColor: settings.buttonColor || '#2C5F5D' }"
        @click.prevent
      >
        <InlineText
          v-model="settings.buttonText"
          tagName="span"
          :is-editor="isEditor"
          placeholder="Get Started"
        />
      </a>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

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

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#F5F5F4',
    color: props.settings?.textColor || '#1F2937',
  }
})
</script>

<style scoped>
.dsf-starter-block {
  width: 100%;
  border-radius: 16px;
}

.dsf-starter-block__inner {
  max-width: 900px;
  margin: 0 auto;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.dsf-starter-block__title {
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0;
}

.dsf-starter-block__subtitle {
  font-size: 1.25rem;
  margin: 0;
  opacity: 0.85;
}

.dsf-starter-block__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: center;
  padding: 0.75rem 1.75rem;
  border-radius: 6px;
  color: #FFFFFF;
  text-decoration: none;
  font-weight: 600;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.dsf-starter-block__btn:hover {
  transform: translateY(-1px);
  opacity: 0.95;
}
</style>
