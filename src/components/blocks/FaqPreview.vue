<template>
  <section class="dsf-block-preview dsf-faq-preview" :style="sectionStyle">
    <div class="dsf-faq-preview__inner" :style="innerStyle">
      <h2 class="dsf-faq-preview__title" :style="{ color: settings.titleColor || '#111827' }">
        {{ settings.title || 'Frequently asked questions' }}
      </h2>

      <div class="dsf-faq-preview__items">
        <div
          v-for="(item, index) in faqItems"
          :key="index"
          class="dsf-faq-preview__item"
          :class="{ 'dsf-faq-preview__item--open': isOpen(index) }"
          :style="{ borderColor: settings.dividerColor || '#E5E7EB' }"
        >
          <button
            type="button"
            class="dsf-faq-preview__question"
            :aria-expanded="isOpen(index) ? 'true' : 'false'"
            @click="toggleItem(index)"
          >
            <span :style="{ color: settings.questionColor || '#111827' }">
              {{ item.question || `Question ${index + 1}` }}
            </span>
            <span class="dsf-faq-preview__icon" aria-hidden="true">
              {{ isOpen(index) ? '−' : '+' }}
            </span>
          </button>

          <div
            v-if="isOpen(index)"
            class="dsf-faq-preview__answer"
            :style="{ color: settings.answerColor || '#4B5563' }"
            v-html="item.answer || '<p>Answer goes here.</p>'"
          ></div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const openItems = ref([0])

const faqItems = computed(() => {
  if (Array.isArray(props.settings?.items) && props.settings.items.length > 0) {
    return props.settings.items
  }

  return [
    {
      question: 'What is DesignStudio Flow?',
      answer: '<p>DesignStudio Flow is a block-based page builder for creating polished WordPress pages with controlled, reusable layouts.</p>',
    },
  ]
})

watch(faqItems, (items) => {
  openItems.value = openItems.value.filter((index) => index < items.length)
  if (openItems.value.length === 0 && items.length > 0) {
    openItems.value = [0]
  }
})

const sectionStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 80
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const innerStyle = computed(() => ({
  maxWidth: `${props.settings?.maxWidth || 900}px`,
}))

function isOpen(index) {
  return openItems.value.includes(index)
}

function toggleItem(index) {
  if (isOpen(index)) {
    openItems.value = openItems.value.filter((itemIndex) => itemIndex !== index)
  } else {
    openItems.value = [...openItems.value, index]
  }
}
</script>

<style scoped>
.dsf-faq-preview {
  width: 100%;
  container-type: inline-size;
}

.dsf-faq-preview__inner {
  width: 100%;
  margin: 0 auto;
}

.dsf-faq-preview__title {
  margin: 0 0 3rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h1, 3rem);
  font-weight: 700;
  line-height: 1.12;
  letter-spacing: -0.04em;
}

.dsf-faq-preview__items {
  width: 100%;
}

.dsf-faq-preview__item {
  border-bottom: 1px solid;
}

.dsf-faq-preview__question {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  width: 100%;
  padding: 1.6rem 0;
  border: 0;
  background: transparent;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  font-weight: 700;
  line-height: 1.4;
  text-align: left;
  cursor: pointer;
}

.dsf-faq-preview__icon {
  flex: 0 0 auto;
  color: #111827;
  font-size: 1.35rem;
  font-weight: 400;
  line-height: 1;
}

.dsf-faq-preview__answer {
  max-width: 760px;
  padding: 0 3rem 1.6rem 0;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.7;
}

.dsf-faq-preview__answer :deep(p) {
  margin: 0 0 0.85rem;
}

.dsf-faq-preview__answer :deep(p:last-child) {
  margin-bottom: 0;
}

.dsf-faq-preview__answer :deep(a) {
  color: var(--dsf-primary-600, currentColor);
}

@container (max-width: 768px) {
  .dsf-faq-preview__title {
    margin-bottom: 2rem;
    font-size: var(--dsf-theme-h2, 2.2rem);
  }

  .dsf-faq-preview__question {
    padding: 1.25rem 0;
  }

  .dsf-faq-preview__answer {
    padding-right: 2rem;
  }
}
</style>
