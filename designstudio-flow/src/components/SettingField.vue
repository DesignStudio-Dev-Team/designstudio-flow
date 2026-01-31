<template>
  <div class="dsf-form-group">
    <label class="dsf-label">{{ config.label }}</label>
    
    <!-- Text Input -->
    <input 
      v-if="config.type === 'text'"
      type="text"
      class="dsf-input"
      :value="value"
      @input="$emit('update', $event.target.value)"
    />
    
    <!-- Textarea -->
    <textarea 
      v-else-if="config.type === 'textarea'"
      class="dsf-input"
      rows="3"
      style="height: auto;"
      :value="value"
      @input="$emit('update', $event.target.value)"
    ></textarea>
    
    <!-- Number -->
    <input 
      v-else-if="config.type === 'number'"
      type="number"
      class="dsf-input"
      :value="value"
      :min="config.min"
      :max="config.max"
      @input="$emit('update', parseInt($event.target.value))"
    />
    
    <!-- Color Picker -->
    <ColorPicker 
      v-else-if="config.type === 'color'" 
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    
    <!-- Slider -->
    <div v-else-if="config.type === 'slider'" class="dsf-slider-group">
      <div class="dsf-slider-value">{{ value }}{{ config.unit || 'px' }}</div>
      <input 
        type="range"
        class="dsf-slider"
        :value="value"
        :min="config.min || 0"
        :max="config.max || 200"
        @input="$emit('update', parseInt($event.target.value))"
      />
    </div>
    
    <!-- Toggle -->
    <div v-else-if="config.type === 'toggle'" class="dsf-flex dsf-items-center dsf-justify-between">
      <span class="dsf-text-sm" style="color: var(--dsf-gray-600);">{{ value ? 'Enabled' : 'Disabled' }}</span>
      <button 
        class="dsf-toggle"
        :class="{ 'dsf-toggle--active': value }"
        @click="$emit('update', !value)"
      >
        <span class="dsf-toggle__thumb"></span>
      </button>
    </div>
    
    <!-- Select -->
    <select 
      v-else-if="config.type === 'select'"
      class="dsf-input"
      :value="value"
      @change="$emit('update', $event.target.value)"
    >
      <template v-if="Array.isArray(config.options)">
        <option 
          v-for="opt in config.options" 
          :key="opt" 
          :value="opt"
        >
          {{ opt }}
        </option>
      </template>
      <template v-else>
        <option 
          v-for="(optValue, optLabel) in config.options" 
          :key="optValue" 
          :value="optValue"
        >
          {{ optLabel }}
        </option>
      </template>
    </select>
    
    <!-- Image Upload -->
    <div v-else-if="config.type === 'image'" class="dsf-image-upload">
      <!-- Image Preview (when set) -->
      <div v-if="value" class="dsf-image-upload__preview">
        <img :src="value" alt="" />
        <button class="dsf-image-upload__remove" @click="$emit('update', '')" title="Remove Image">
          <X :size="16" />
        </button>
      </div>
      
      <!-- URL Input Field (always visible) -->
      <div class="dsf-image-upload__url-group">
        <input 
          type="text"
          class="dsf-input"
          placeholder="Enter image URL..."
          :value="value"
          @input="$emit('update', $event.target.value)"
        />
      </div>
      
      <!-- Media Library Button (always visible) -->
      <button class="dsf-btn dsf-btn--secondary dsf-w-full dsf-mt-2" @click="openMediaLibrary">
        <ImagePlus :size="16" />
        {{ value ? 'Change from Media Library' : 'Select from Media Library' }}
      </button>
    </div>
    
    <!-- Category Selector -->
    <CategorySelector 
      v-else-if="config.type === 'category'"
      :value="value"
      @update="$emit('update', $event)"
    />
    
    <!-- Categories Multi-Select -->
    <CategoriesSelector 
      v-else-if="config.type === 'categories'"
      :value="value"
      @update="$emit('update', $event)"
    />
    
    <!-- Products Selector -->
    <ProductsSelector 
      v-else-if="config.type === 'products'"
      :value="value"
      :config="config"
      @update="$emit('update', $event)"
    />
    
    <!-- Source Selector (Dynamic options) -->
    <div v-else-if="config.type === 'source'" class="dsf-select-cards">
      <button 
        class="dsf-select-card"
        :class="{ 'dsf-select-card--active': value === sourceOptions[0].value }"
        @click="$emit('update', sourceOptions[0].value)"
      >
        <component :is="sourceOptions[0].icon" :size="24" />
        <span class="dsf-select-card__title">{{ sourceOptions[0].title }}</span>
        <span class="dsf-select-card__desc">{{ sourceOptions[0].desc }}</span>
      </button>
      <button 
        class="dsf-select-card"
        :class="{ 'dsf-select-card--active': value === sourceOptions[1].value }"
        @click="$emit('update', sourceOptions[1].value)"
      >
        <component :is="sourceOptions[1].icon" :size="24" />
        <span class="dsf-select-card__title">{{ sourceOptions[1].title }}</span>
        <span class="dsf-select-card__desc">{{ sourceOptions[1].desc }}</span>
      </button>
    </div>
    
    <!-- Repeater Field -->
    <BrandRepeaterField 
      v-else-if="config.type === 'repeater' && fieldKey === 'brands'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    <TestimonialsRepeaterField 
      v-else-if="config.type === 'repeater' && fieldKey === 'testimonials'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    <RepeaterField 
      v-else-if="config.type === 'repeater'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    
    <!-- Helper Text -->
    <p v-if="config.helper" class="dsf-helper-text">{{ config.helper }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { X, ImagePlus, Folder, Hand, ShoppingBag } from 'lucide-vue-next'
import CategorySelector from './selectors/CategorySelector.vue'
import CategoriesSelector from './selectors/CategoriesSelector.vue'
import ProductsSelector from './selectors/ProductsSelector.vue'
import ColorPicker from './common/ColorPicker.vue'
import RepeaterField from './common/RepeaterField.vue'
import BrandRepeaterField from './common/BrandRepeaterField.vue'
import TestimonialsRepeaterField from './common/TestimonialsRepeaterField.vue'

const props = defineProps({
  config: Object,
  fieldKey: String,
  value: [String, Number, Boolean, Array, Object],
})

const emit = defineEmits(['update'])

// Source options based on default value (determines context)
const sourceOptions = computed(() => {
  // Ecommerce Showcase context (categories vs products)
  if (props.config?.default === 'categories') {
    return [
      { value: 'categories', title: 'Categories', desc: 'Display product categories', icon: Folder },
      { value: 'products', title: 'Products', desc: 'Display products from a category', icon: ShoppingBag },
    ]
  }
  // Product Grid context (category vs manual)
  return [
    { value: 'category', title: 'Category', desc: 'Show all products from a category', icon: Folder },
    { value: 'manual', title: 'Manual', desc: 'Pick specific products', icon: Hand },
  ]
})

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
        emit('update', selection.url)
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
.dsf-image-upload__preview {
  position: relative;
  border-radius: var(--dsf-radius-md);
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.dsf-image-upload__preview img {
  width: 100%;
  height: 120px;
  object-fit: cover;
}

.dsf-image-upload__remove {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border-radius: var(--dsf-radius-md);
  box-shadow: var(--dsf-shadow-md);
  color: var(--dsf-danger-500);
  cursor: pointer;
  border: none;
  transition: transform 0.15s ease;
}

.dsf-image-upload__remove:hover {
  transform: scale(1.1);
}

.dsf-image-upload__url-group {
  margin-top: 0.5rem;
}

.dsf-image-upload__url-group .dsf-input {
  font-size: 0.75rem;
}
</style>
