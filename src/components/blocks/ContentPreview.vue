<template>
  <section class="dsf-block-preview dsf-content-preview">
    <div
      class="dsf-content-preview__inner"
      :style="innerStyle"
      v-html="contentHtml"
    ></div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
})

const contentHtml = computed(() =>
  props.settings?.content || '<p>Add your content here.</p>'
)

const innerStyle = computed(() => ({
  maxWidth: `${props.settings?.maxWidth || 900}px`,
}))
</script>

<style scoped>
.dsf-content-preview {
  width: 100%;
}

.dsf-content-preview__inner {
  width: 100%;
  margin: 0 auto;
  color: inherit;
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-theme-p-size, 20px);
  line-height: 1.65;
}

.dsf-content-preview__inner :deep(*) {
  font-family: inherit;
}

.dsf-content-preview__inner :deep(p) {
  margin: 0 0 1rem;
  font-size: var(--dsf-theme-p-size, 20px);
  line-height: inherit;
}

.dsf-content-preview__inner :deep(p:last-child) {
  margin-bottom: 0;
}

.dsf-content-preview__inner :deep(h1),
.dsf-content-preview__inner :deep(h2),
.dsf-content-preview__inner :deep(h3),
.dsf-content-preview__inner :deep(h4),
.dsf-content-preview__inner :deep(h5),
.dsf-content-preview__inner :deep(h6) {
  font-family: var(--dsf-theme-heading-font, inherit);
  margin: 0 0 0.75rem;
  line-height: 1.2;
}

.dsf-content-preview__inner :deep(h1) {
  font-size: var(--dsf-theme-h1, 42px);
}

.dsf-content-preview__inner :deep(h2) {
  font-size: var(--dsf-theme-h2, 37px);
}

.dsf-content-preview__inner :deep(a) {
  color: var(--dsf-primary-600, currentColor);
}

.dsf-content-preview__inner :deep(ul),
.dsf-content-preview__inner :deep(ol) {
  margin: 0 0 1rem;
  padding-left: 1.4em;
}

.dsf-content-preview__inner :deep(ul) {
  list-style: disc outside;
}

.dsf-content-preview__inner :deep(ol) {
  list-style: decimal outside;
}

.dsf-content-preview__inner :deep(li) {
  display: list-item;
  margin: 0 0 0.35em;
}
</style>
