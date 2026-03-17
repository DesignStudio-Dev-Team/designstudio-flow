<template>
  <aside class="dsf-panel dsf-animate-slide-in-right">
    <div class="dsf-panel__header">
      <div>
        <h2 class="dsf-panel__title">Theme Settings</h2>
        <p class="dsf-panel__subtitle">Customize colors and typography</p>
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
      
      <!-- Typography Section -->
      <div class="dsf-form-group">
        <label class="dsf-label">
          <Type :size="14" style="display: inline-block; vertical-align: middle; margin-right: 0.375rem;" />
          Heading Font
        </label>
        <FontPicker
          :modelValue="settings.theme?.headingFont || ''"
          @update:modelValue="updateTheme('headingFont', $event)"
        />
        <p class="dsf-helper-text">Used for titles and headings</p>
      </div>
      
      <div class="dsf-form-group">
        <label class="dsf-label">Body Font</label>
        <FontPicker
          :modelValue="settings.theme?.bodyFont || ''"
          @update:modelValue="updateTheme('bodyFont', $event)"
        />
        <p class="dsf-helper-text">Used for paragraphs and text</p>
      </div>
      
      <hr style="margin: 1.5rem 0; border-color: var(--dsf-gray-200);">
      
      <!-- Container Width -->
      <div class="dsf-form-group">
        <label class="dsf-label">Container Width</label>
        <div class="dsf-slider-group">
          <div class="dsf-slider-value">{{ settings.layout?.containerWidth || 1800 }}px</div>
          <input 
            type="range"
            class="dsf-slider"
            :value="settings.layout?.containerWidth || 1800"
            min="1000"
            max="1800"
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

      <hr style="margin: 1.5rem 0; border-color: var(--dsf-gray-200);">

      <!-- Template Width -->
      <div class="dsf-form-group">
        <label class="dsf-label">Template Width</label>
        <select
          class="dsf-input"
          :value="settings.layout?.template || 'default'"
          @change="updateLayout('template', $event.target.value)"
        >
          <option value="default">Default (Theme Container)</option>
          <option value="fullwidth">Full Width</option>
        </select>
      </div>

      <!-- Theme Header/Footer -->
      <div class="dsf-form-group">
        <label class="dsf-label">Show Header</label>
        <div class="dsf-flex dsf-items-center dsf-justify-between">
          <span class="dsf-text-sm" style="color: var(--dsf-gray-600);">
            {{ settings.layout?.showHeader === false ? 'Hidden' : 'Visible' }}
          </span>
          <button 
            class="dsf-toggle"
            :class="{ 'dsf-toggle--active': settings.layout?.showHeader !== false }"
            @click="updateLayout('showHeader', !(settings.layout?.showHeader !== false))"
          >
            <span class="dsf-toggle__thumb"></span>
          </button>
        </div>
      </div>

      <div v-if="postType !== 'dsf_layout'" class="dsf-form-group">
        <label class="dsf-label">Header Template</label>
        <select
          class="dsf-input"
          :value="settings.layout?.headerTemplateId || 0"
          @change="updateLayout('headerTemplateId', parseTemplateId($event.target.value))"
        >
          <option :value="0">Theme Default Header</option>
          <option
            v-for="template in headerTemplates"
            :key="template.id"
            :value="template.id"
          >
            {{ formatTemplateOption(template) }}
          </option>
        </select>
        <p class="dsf-helper-text">Select a custom header for this page.</p>
        <a
          v-if="layoutCreateUrls?.header"
          class="dsf-theme-panel-link"
          :href="layoutCreateUrls.header"
          target="_blank"
          rel="noopener noreferrer"
        >
          Create Header Template
        </a>
      </div>

      <div class="dsf-form-group">
        <label class="dsf-label">Show Footer</label>
        <div class="dsf-flex dsf-items-center dsf-justify-between">
          <span class="dsf-text-sm" style="color: var(--dsf-gray-600);">
            {{ settings.layout?.showFooter === false ? 'Hidden' : 'Visible' }}
          </span>
          <button 
            class="dsf-toggle"
            :class="{ 'dsf-toggle--active': settings.layout?.showFooter !== false }"
            @click="updateLayout('showFooter', !(settings.layout?.showFooter !== false))"
          >
            <span class="dsf-toggle__thumb"></span>
          </button>
        </div>
      </div>

      <div v-if="postType !== 'dsf_layout'" class="dsf-form-group">
        <label class="dsf-label">Footer Template</label>
        <select
          class="dsf-input"
          :value="settings.layout?.footerTemplateId || 0"
          @change="updateLayout('footerTemplateId', parseTemplateId($event.target.value))"
        >
          <option :value="0">Theme Default Footer</option>
          <option
            v-for="template in footerTemplates"
            :key="template.id"
            :value="template.id"
          >
            {{ formatTemplateOption(template) }}
          </option>
        </select>
        <p class="dsf-helper-text">Select a custom footer for this page.</p>
        <a
          v-if="layoutCreateUrls?.footer"
          class="dsf-theme-panel-link"
          :href="layoutCreateUrls.footer"
          target="_blank"
          rel="noopener noreferrer"
        >
          Create Footer Template
        </a>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { X, Type } from 'lucide-vue-next'
import ColorPicker from './common/ColorPicker.vue'
import FontPicker from './common/FontPicker.vue'

const props = defineProps({
  settings: Object,
  postType: {
    type: String,
    default: 'dsf_page',
  },
  layoutTemplates: {
    type: Object,
    default: () => ({ headers: [], footers: [] }),
  },
  layoutCreateUrls: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['close', 'update:settings'])

const headerTemplates = computed(() => props.layoutTemplates?.headers || [])
const footerTemplates = computed(() => props.layoutTemplates?.footers || [])

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

function parseTemplateId(value) {
  const parsed = parseInt(value, 10)
  return Number.isNaN(parsed) ? 0 : parsed
}

function formatTemplateOption(template) {
  if (!template?.status || template.status === 'publish') {
    return template?.title || 'Untitled template'
  }
  return `${template?.title || 'Untitled template'} (${template.status})`
}
</script>

<style scoped>
.dsf-theme-panel-link {
  display: inline-flex;
  margin-top: 0.5rem;
  font-size: 0.75rem;
  color: var(--dsf-primary-600);
  text-decoration: none;
}

.dsf-theme-panel-link:hover {
  text-decoration: underline;
}
</style>
