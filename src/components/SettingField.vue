<template>
  <div class="dsf-form-group">
    <label class="dsf-label">{{ config.label }}</label>
    
    <!-- Text Input -->
    <input 
      v-if="config.type === 'text'"
      type="text"
      class="dsf-input"
      :value="value"
      :maxlength="config.maxLength || undefined"
      @input="$emit('update', $event.target.value)"
    />
    
    <!-- Textarea -->
    <textarea 
      v-else-if="config.type === 'textarea'"
      class="dsf-input"
      rows="3"
      style="height: auto;"
      :value="value"
      :maxlength="config.maxLength || undefined"
      @input="$emit('update', $event.target.value)"
    ></textarea>

    <!-- Date and time -->
    <input
      v-else-if="config.type === 'datetime'"
      type="datetime-local"
      class="dsf-input"
      :value="value"
      :min="config.min"
      :max="config.max"
      :step="config.step || 60"
      @input="$emit('update', $event.target.value)"
    />

    <!-- WYSIWYG -->
    <WysiwygField
      v-else-if="config.type === 'wysiwyg'"
      :modelValue="value"
      :allow-raw-html="config.allowRawHtml === true"
      @update:modelValue="$emit('update', $event)"
    />

    <ShortcodeEmbedField
      v-else-if="config.type === 'shortcode_embed'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    
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
    
    <!-- Slider (drag the knob or type an exact value) -->
    <div v-else-if="config.type === 'slider'" class="dsf-slider-group">
      <div class="dsf-slider-header">
        <input
          type="number"
          class="dsf-slider-input"
          :value="value"
          :min="sliderMin"
          :max="sliderMax"
          :step="config.step || 1"
          @input="onSliderNumberInput"
          @blur="onSliderNumberBlur"
          @keydown.enter.prevent="onSliderNumberBlur"
        />
        <span class="dsf-slider-unit">{{ config.unit || 'px' }}</span>
      </div>
      <input
        type="range"
        class="dsf-slider"
        :value="value"
        :min="sliderMin"
        :max="sliderMax"
        :step="config.step || 1"
        @input="commitSlider($event.target.value)"
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
        <button class="dsf-image-upload__remove" @click="handleImageRemove" title="Remove Image">
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
          @input="handleImageInput"
        />
      </div>
      
      <!-- Media Library Button (always visible) -->
      <button class="dsf-btn dsf-btn--secondary dsf-w-full dsf-mt-2" @click="openMediaLibrary">
        <ImagePlus :size="16" />
        {{ value ? 'Change from Media Library' : 'Select from Media Library' }}
      </button>
    </div>
    
    <!-- Video Upload -->
    <div v-else-if="config.type === 'video'" class="dsf-image-upload">
      <div v-if="value" class="dsf-image-upload__preview dsf-image-upload__preview--video">
        <video :src="value" class="dsf-video-thumb" muted preload="metadata" />
        <button class="dsf-image-upload__remove" @click="handleImageRemove" title="Remove Video">
          <X :size="16" />
        </button>
      </div>
      <div class="dsf-image-upload__url-group">
        <input
          type="text"
          class="dsf-input"
          placeholder="Enter video URL..."
          :value="value"
          @input="handleImageInput"
        />
      </div>
      <button class="dsf-btn dsf-btn--secondary dsf-w-full dsf-mt-2" @click="openVideoLibrary">
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
      :all-settings="allSettings"
      @update="$emit('update', $event)"
    />

    <ProductAttributeFiltersField
      v-else-if="config.type === 'multiselect_tags'"
      :value="value"
      :config="config"
      @update="$emit('update', $event)"
    />

    <ProductTagsFilterField
      v-else-if="config.type === 'product_tags'"
      :value="value"
      :config="config"
      @update="$emit('update', $event)"
    />

    <FaqItemsField
      v-else-if="config.type === 'faq_items'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <ProductTabsField
      v-else-if="config.type === 'product_tabs'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <ExpanderCardsField
      v-else-if="config.type === 'expander_cards'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <GalleryItemsField
      v-else-if="config.type === 'gallery_items'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <IconItemsField
      v-else-if="config.type === 'icon_items'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <CardColumnItemsField
      v-else-if="config.type === 'card_column_items'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <PricingPlansField
      v-else-if="config.type === 'pricing_plans'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
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
    <SpotlightButtonsField
      v-else-if="config.type === 'repeater' && fieldKey === 'sideButtons'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />
    <RepeaterField 
      v-else-if="config.type === 'repeater'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Simple Links -->
    <SimpleLinksField
      v-else-if="config.type === 'simple_links'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Dock nav links (label + url + preset icon or media image) -->
    <DockNavLinksField
      v-else-if="config.type === 'dock_nav_links'"
      :modelValue="value"
      :max-items="config.maxItems"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Mega Menu -->
    <MegaMenuField
      v-else-if="config.type === 'mega_menu'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Mega Menu (pro): per-column layout, per-link icon, featured card -->
    <MegaMenuField
      v-else-if="config.type === 'mega_menu_pro'"
      :modelValue="value"
      pro
      @update:modelValue="$emit('update', $event)"
    />

    <ShowcaseHeaderNavigationField
      v-else-if="config.type === 'showcase_header_navigation'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Footer dealers -->
    <FooterDealersField
      v-else-if="config.type === 'footer_dealers'"
      :modelValue="value"
      @update:modelValue="$emit('update', $event)"
    />

    <!-- Mobile stores -->
    <MobileStoresField
      v-else-if="config.type === 'mobile_stores'"
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
import ProductAttributeFiltersField from './common/ProductAttributeFiltersField.vue'
import ProductTagsFilterField from './common/ProductTagsFilterField.vue'
import FaqItemsField from './common/FaqItemsField.vue'
import ProductTabsField from './common/ProductTabsField.vue'
import ExpanderCardsField from './common/ExpanderCardsField.vue'
import GalleryItemsField from './common/GalleryItemsField.vue'
import IconItemsField from './common/IconItemsField.vue'
import CardColumnItemsField from './common/CardColumnItemsField.vue'
import PricingPlansField from './common/PricingPlansField.vue'
import ColorPicker from './common/ColorPicker.vue'
import RepeaterField from './common/RepeaterField.vue'
import BrandRepeaterField from './common/BrandRepeaterField.vue'
import TestimonialsRepeaterField from './common/TestimonialsRepeaterField.vue'
import SpotlightButtonsField from './common/SpotlightButtonsField.vue'
import WysiwygField from './common/WysiwygField.vue'
import ShortcodeEmbedField from './common/ShortcodeEmbedField.vue'
import SimpleLinksField from './common/SimpleLinksField.vue'
import DockNavLinksField from './common/DockNavLinksField.vue'
import MegaMenuField from './common/MegaMenuField.vue'
import ShowcaseHeaderNavigationField from './common/ShowcaseHeaderNavigationField.vue'
import FooterDealersField from './common/FooterDealersField.vue'
import MobileStoresField from './common/MobileStoresField.vue'

const props = defineProps({
  config: Object,
  fieldKey: String,
  value: [String, Number, Boolean, Array, Object],
  allSettings: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update'])

// Slider bounds — both the range knob and the number input share them so you can
// drag OR type an exact value.
const sliderMin = computed(() => Number(props.config?.min ?? 0))
const sliderMax = computed(() => Number(props.config?.max ?? 200))

function clampSlider(n) {
  return Math.max(sliderMin.value, Math.min(sliderMax.value, n))
}

// Dragging the knob: always emit an in-range value.
function commitSlider(raw) {
  const n = Number(raw)
  if (Number.isNaN(n)) return
  emit('update', clampSlider(n))
}

// Typing in the number box: allow the field to be cleared/mid-edit, and don't
// snap up to the minimum on every keystroke (only clamp the upper bound live).
function onSliderNumberInput(event) {
  const raw = event.target.value
  if (raw === '' || raw === '-') return
  const n = Number(raw)
  if (Number.isNaN(n)) return
  emit('update', Math.min(sliderMax.value, n))
}

// On blur / Enter, fully clamp to [min, max] (empty falls back to the minimum).
function onSliderNumberBlur(event) {
  const n = Number(event.target.value)
  emit('update', Number.isNaN(n) ? sliderMin.value : clampSlider(n))
}

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

function openVideoLibrary() {
  if (typeof window.wp === 'undefined' || !window.wp.media) return
  const frame = window.wp.media({
    title: 'Select Video',
    button: { text: 'Use this video' },
    multiple: false,
  })
  frame.on('select', () => {
    try {
      const selection = frame.state().get('selection').first().toJSON()
      emitImageUpdate(selection.url)
    } catch (e) {
      console.error('Error selecting video:', e)
    }
  })
  frame.open()
}

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
        emitImageUpdate(selection.url, {
          alt: selection.alt || '',
        })
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

function emitImageUpdate(url, metadata = {}) {
  const metaFields = props.config?.mediaMetaFields || {}
  const hasMetaFields = Object.keys(metaFields).length > 0

  if (!hasMetaFields) {
    emit('update', url)
    return
  }

  const updates = {
    [props.fieldKey]: url,
  }

  if (metaFields.alt) {
    updates[metaFields.alt] = metadata.alt || ''
  }

  emit('update', {
    __dsfBatch: true,
    updates,
  })
}

function handleImageInput(event) {
  emitImageUpdate(event.target.value, { alt: '' })
}

function handleImageRemove() {
  emitImageUpdate('', { alt: '' })
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
  top: 0.25rem;
  right: 0.25rem;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  color: white;
  cursor: pointer;
  border: none;
  transition: background 0.15s;
}

.dsf-image-upload__remove:hover {
  background: rgba(220, 38, 38, 0.9);
}

.dsf-image-upload__url-group {
  margin-top: 0.5rem;
}

.dsf-image-upload__preview--video {
  background: #000;
}

.dsf-video-thumb {
  width: 100%;
  height: 120px;
  object-fit: contain;
  display: block;
}

.dsf-image-upload__url-group .dsf-input {
  font-size: 0.75rem;
}
</style>
