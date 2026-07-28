<template>
  <div class="dsf-video-picker">
    <video v-if="modelValue" :src="modelValue" muted preload="metadata" class="dsf-video-picker__preview" />
    <input
      type="text"
      class="dsf-input"
      placeholder="Enter direct MP4 URL..."
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <button type="button" class="dsf-btn dsf-btn--secondary" @click="openMediaLibrary">
      Select Video from Media Library
    </button>
  </div>
</template>

<script setup>
defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])

function openMediaLibrary() {
  if (typeof window.wp === 'undefined' || !window.wp.media) return
  const frame = window.wp.media({
    title: 'Select Video',
    button: { text: 'Use this video' },
    multiple: false,
    library: { type: 'video' },
  })
  frame.on('select', () => {
    const selection = frame.state().get('selection').first().toJSON()
    emit('update:modelValue', selection.url || '')
  })
  frame.open()
}
</script>

<style scoped>
.dsf-video-picker { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-video-picker__preview { display: block; width: 100%; max-height: 120px; object-fit: contain; background: var(--dsf-gray-100); }
</style>
