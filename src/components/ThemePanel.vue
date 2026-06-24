<template>
  <aside class="dsf-panel">
    <div class="dsf-panel__header">
      <div>
        <h2 class="dsf-panel__title">Theme Settings</h2>
        <p class="dsf-panel__subtitle">Customize colors and typography</p>
      </div>
      <div class="dsf-theme-panel__actions">
        <button
          class="dsf-theme-panel__undo"
          type="button"
          :disabled="!canUndo"
          title="Undo last theme change"
          @click="$emit('undo-theme')"
        >
          <Undo2 :size="16" />
          Undo
        </button>
        <button class="dsf-panel__close" type="button" @click="$emit('close')">
          <X :size="20" />
        </button>
      </div>
    </div>
    
    <div class="dsf-panel__body">
      <div class="dsf-theme-panel__site-defaults">
        <div>
          <strong>Site theme defaults</strong>
          <span>Matches DesignStudio Flow settings in WordPress.</span>
        </div>
        <button type="button" @click="$emit('restore-defaults')">Restore</button>
      </div>

      <!-- Primary Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Primary Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.primaryColor || defaultTheme.primaryColor"
          @update:modelValue="updateTheme('primaryColor', $event)" 
        />
        <p class="dsf-helper-text">Used for buttons and accents</p>
      </div>
      
      <!-- Secondary Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Secondary Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.secondaryColor || defaultTheme.secondaryColor"
          @update:modelValue="updateTheme('secondaryColor', $event)" 
        />
      </div>
      
      <!-- Text Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Text Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.textColor || defaultTheme.textColor"
          @update:modelValue="updateTheme('textColor', $event)" 
        />
      </div>
      
      <!-- Background Color -->
      <div class="dsf-form-group">
        <label class="dsf-label">Background Color</label>
        <ColorPicker 
          :modelValue="settings.theme?.backgroundColor || defaultTheme.backgroundColor"
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
          :modelValue="settings.theme?.headingFont || defaultTheme.headingFont || ''"
          @update:modelValue="updateTheme('headingFont', $event)"
        />
        <p class="dsf-helper-text">Used for titles and headings</p>
      </div>
      
      <div class="dsf-form-group">
        <label class="dsf-label">Body Font</label>
        <FontPicker
          :modelValue="settings.theme?.bodyFont || defaultTheme.bodyFont || ''"
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
import { X, Type, Undo2 } from 'lucide-vue-next'
import ColorPicker from './common/ColorPicker.vue'
import FontPicker from './common/FontPicker.vue'

const props = defineProps({
  settings: Object,
  defaultTheme: {
    type: Object,
    default: () => ({
      primaryColor: '#2C5F5D',
      secondaryColor: '#1E40AF',
      textColor: '#1F2937',
      backgroundColor: '#FFFFFF',
      headingFont: '',
      bodyFont: '',
    }),
  },
  canUndo: Boolean,
  postType: {
    type: String,
    default: 'page',
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

const emit = defineEmits(['close', 'update:settings', 'undo-theme', 'restore-defaults'])

const headerTemplates = computed(() => props.layoutTemplates?.headers || [])
const footerTemplates = computed(() => props.layoutTemplates?.footers || [])

function updateTheme(key, value) {
  emit('update:settings', {
    theme: {
      ...props.settings.theme,
      [key]: value,
    },
    _themeChangeKey: key,
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
.dsf-theme-panel__actions,
.dsf-theme-panel__undo {
  display: flex;
  align-items: center;
}

.dsf-theme-panel__actions {
  gap: 6px;
}

.dsf-theme-panel__undo {
  gap: 5px;
  min-height: 36px;
  padding: 0 10px;
  border: 1px solid var(--dsf-ui-border);
  border-radius: 9px;
  background: white;
  color: var(--dsf-brand-blue);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.dsf-theme-panel__undo:disabled {
  cursor: not-allowed;
  opacity: 0.42;
}

.dsf-theme-panel__site-defaults {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
  padding: 12px;
  border: 1px solid rgb(12 95 168 / 16%);
  border-radius: 11px;
  background: var(--dsf-brand-blue-soft);
}

.dsf-theme-panel__site-defaults div,
.dsf-theme-panel__site-defaults span {
  display: block;
}

.dsf-theme-panel__site-defaults strong {
  color: var(--dsf-ui-ink);
  font-size: 13px;
}

.dsf-theme-panel__site-defaults span {
  margin-top: 2px;
  color: var(--dsf-ui-muted);
  font-size: 11px;
}

.dsf-theme-panel__site-defaults button {
  padding: 6px 9px;
  border: 1px solid var(--dsf-brand-blue);
  border-radius: 7px;
  background: white;
  color: var(--dsf-brand-blue);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

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
