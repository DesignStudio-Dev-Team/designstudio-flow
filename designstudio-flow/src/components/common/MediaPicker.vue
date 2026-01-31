<template>
  <div class="dsf-media-picker">
    <!-- Image Preview (when set) -->
    <div v-if="modelValue" class="dsf-media-picker__preview">
      <img :src="modelValue" alt="" />
      <button class="dsf-media-picker__remove" @click="$emit('update:modelValue', '')" title="Remove Image">
        <X :size="16" />
      </button>
    </div>
    
    <!-- URL Input Field -->
    <div class="dsf-media-picker__url-group">
      <input 
        type="text"
        class="dsf-input"
        placeholder="Enter image URL..."
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
      />
    </div>
    
    <!-- Media Library Button -->
    <button class="dsf-btn dsf-btn--secondary dsf-media-picker__btn" @click="openMediaLibrary">
      <ImagePlus :size="16" />
      {{ modelValue ? 'Change Image' : 'Select Image' }}
    </button>
  </div>
</template>

<script setup>
import { X, ImagePlus } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

function openMediaLibrary() {
  // Use WordPress media library
  if (typeof window.wp !== 'undefined' && window.wp.media) {
    const frame = window.wp.media({
      title: 'Select Image',
      button: {
        text: 'Use this image'
      },
      multiple: false,
      library: { type: 'image' },
    })
    
    frame.on('select', () => {
      try {
        const selection = frame.state().get('selection').first().toJSON()
        emit('update:modelValue', selection.url)
      } catch (e) {
        console.error('Error selecting image:', e)
      }
    })
    
    frame.open()
  } else {
    console.error('WordPress Media Library not found')
    alert('Media Library is not available. Please ensure you are logged into WordPress.')
  }
}
</script>

<style scoped>
.dsf-media-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-media-picker__preview {
  position: relative;
  border-radius: var(--dsf-radius-md);
  overflow: hidden;
  background: var(--dsf-gray-100);
}

.dsf-media-picker__preview img {
  display: block;
  width: 100%;
  max-height: 120px;
  object-fit: contain;
}

.dsf-media-picker__remove {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,0.6);
  border: none;
  border-radius: 50%;
  color: white;
  cursor: pointer;
  transition: background 0.15s;
}

.dsf-media-picker__remove:hover {
  background: rgba(220, 38, 38, 0.9);
}

.dsf-media-picker__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem;
  font-size: 0.75rem;
}
</style>
