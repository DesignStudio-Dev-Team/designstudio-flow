<template>
  <div class="dsf-block-preview dsf-generic-preview">
    <div class="dsf-generic-preview__inner">
      <LayoutTemplate :size="32" />
      <p>Block Preview</p>
    </div>
  </div>
</template>

<script setup>
import { LayoutTemplate } from 'lucide-vue-next'

defineProps({
  settings: Object,
  isEditor: Boolean,
})
</script>

<style scoped>
.dsf-generic-preview {
  padding: 60px 24px;
  background: var(--dsf-gray-50);
}

.dsf-generic-preview__inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: var(--dsf-gray-400);
  text-align: center;
}
</style>
