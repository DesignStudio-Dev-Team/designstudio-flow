<template>
  <div class="dsf-modal" :class="layoutClass">
    <div class="dsf-modal__backdrop" @click="$emit('close')"></div>
    <div class="dsf-modal__panel" role="dialog" aria-modal="true">
      <button class="dsf-modal__close" type="button" @click="$emit('close')">×</button>
      <div class="dsf-modal__content">
        <div v-if="loading" class="dsf-modal__loading">Loading...</div>
        <div v-else v-html="content"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  layout: {
    type: String,
    default: 'center',
  },
  content: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['close'])

const layoutClass = computed(() =>
  props.layout === 'drawer' ? 'dsf-modal--drawer' : 'dsf-modal--center'
)
</script>
