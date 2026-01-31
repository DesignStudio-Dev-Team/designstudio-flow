<template>
  <aside class="dsf-panel dsf-animate-slide-in-right">
    <div class="dsf-panel__header">
      <div>
        <h2 class="dsf-panel__title">Theme Settings</h2>
        <p class="dsf-panel__subtitle">Customize your page colors</p>
      </div>
      <button class="dsf-panel__close" @click="$emit('close')">
        <X :size="20" />
      </button>
    </div>
    
    <div class="dsf-panel__body">
      <!-- Primary Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Primary Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.primaryColor || '#3B82F6'" 
          @update:modelValue="updateTheme('primaryColor', $event)" 
        />
        <p class="dsf-helper-text">Used for buttons and accents</p>
      </div>
      
      <!-- Secondary Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Secondary Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.secondaryColor || '#1E40AF'" 
          @update:modelValue="updateTheme('secondaryColor', $event)" 
        />
      </div>
      
      <!-- Text Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Text Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.textColor || '#1F2937'" 
          @update:modelValue="updateTheme('textColor', $event)" 
        />
      </div>
      
      <!-- Background Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Background Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.backgroundColor || '#FFFFFF'" 
          @update:modelValue="updateTheme('backgroundColor', $event)" 
        />
      </div>
      
      <hr style="margin: 1.5rem 0; border-color: var(--dsf-gray-200);">
      
      <!-- Container Width -->
      <div class="dsf-form-group">
        <label class="dsf-label">Container Width</label>
        <div class="dsf-slider-group">
          <div class="dsf-slider-value">{{ settings.layout?.containerWidth || 1200 }}px</div>
          <input 
            type="range"
            class="dsf-slider"
            :value="settings.layout?.containerWidth || 1200"
            min="800"
            max="1600"
            @input="updateLayout('containerWidth', parseInt($event.target.value))"
          />
        </div>
      </div>
      
      <!-- Content Padding -->
      <div class="dsf-form-group">
        <label class="dsf-label">Content Padding</label>
        <div class="dsf-slider-group">
          <div class="dsf-slider-value">{{ settings.layout?.contentPadding || 24 }}px</div>
          <input 
            type="range"
            class="dsf-slider"
            :value="settings.layout?.contentPadding || 24"
            min="0"
            max="64"
            @input="updateLayout('contentPadding', parseInt($event.target.value))"
          />
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { X } from 'lucide-vue-next'
import ColorPicker from './common/ColorPicker.vue'

const props = defineProps({
  settings: Object,
})

const emit = defineEmits(['close', 'update:settings'])

function updateTheme(key, value) {
  emit('update:settings', {
    theme: {
      ...props.settings.theme,
      [key]: value,
    },
  })
}

function updateLayout(key, value) {
  emit('update:settings', {
    layout: {
      ...props.settings.layout,
      [key]: value,
    },
  })
}
</script>
