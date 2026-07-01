<template>
  <header class="dsf-header">
    <!-- Left: Logo & Title -->
    <div class="dsf-header__left">
      <a
        v-if="adminUrl"
        :href="adminUrl"
        class="dsf-header__back"
        title="Back to WordPress admin"
        aria-label="Back to WordPress admin"
      >
        <ArrowLeft :size="18" />
      </a>
      <div class="dsf-header__brand">
        <div class="dsf-header__logo" aria-hidden="true">
          <img :src="logoUrl" alt="" />
        </div>
        <h1 class="dsf-header__title">DesignStudio Flow</h1>
        <p class="dsf-header__subtitle">{{ subtitleText }}</p>
      </div>
    </div>
    
    <!-- Center: Preview Toggle -->
    <div class="dsf-header__center">
      <div class="dsf-preview-toggle">
        <button 
          class="dsf-preview-toggle__btn"
          :class="{ 'dsf-preview-toggle__btn--active': previewMode === 'desktop' }"
          @click="$emit('set-preview-mode', 'desktop')"
          title="Desktop"
        >
          <Monitor :size="18" />
        </button>
        <button 
          class="dsf-preview-toggle__btn"
          :class="{ 'dsf-preview-toggle__btn--active': previewMode === 'tablet' }"
          @click="$emit('set-preview-mode', 'tablet')"
          title="Tablet"
        >
          <Tablet :size="18" />
        </button>
        <button 
          class="dsf-preview-toggle__btn"
          :class="{ 'dsf-preview-toggle__btn--active': previewMode === 'mobile' }"
          @click="$emit('set-preview-mode', 'mobile')"
          title="Mobile"
        >
          <Smartphone :size="18" />
        </button>
      </div>
    </div>
    
    <!-- Right: Actions -->
    <div class="dsf-header__right">
      <button 
        class="dsf-btn dsf-btn--secondary dsf-header__btn"
        :disabled="postType === 'dsf_layout'"
        @click="$emit('open-settings')"
      >
        <Settings :size="16" />
        {{ postType === 'dsf_layout' ? 'No Settings' : 'Settings' }}
      </button>

      <button
        class="dsf-btn dsf-btn--secondary dsf-header__btn"
        @click="$emit('open-theme')"
      >
        <Palette :size="16" />
        Theme
      </button>

      <button
        v-if="postType !== 'dsf_layout'"
        class="dsf-btn dsf-btn--secondary dsf-header__btn"
        title="Save this page's blocks as a reusable template"
        @click="$emit('save-as-template')"
      >
        <LayoutTemplate :size="16" />
        Save as Template
      </button>

      <button
        class="dsf-btn dsf-btn--secondary dsf-header__btn"
        :disabled="postType === 'dsf_layout'"
        @click="$emit('view')"
      >
        <ExternalLink :size="16" />
        {{ postType === 'dsf_layout' ? 'No View' : 'View' }}
      </button>
      
      <button 
        class="dsf-btn dsf-btn--primary dsf-header__btn"
        :disabled="isSaving"
        @click="$emit('save')"
      >
        <Save :size="16" />
        {{ isSaving ? 'Saving...' : saveLabel }}
      </button>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { Monitor, Tablet, Smartphone, Palette, Settings, ExternalLink, Save, LayoutTemplate, ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  title: String,
  isSaving: Boolean,
  previewMode: String,
  postType: {
    type: String,
    default: 'page',
  },
  layoutType: {
    type: String,
    default: 'header',
  },
})

defineEmits(['update:title', 'view', 'save', 'set-preview-mode', 'open-theme', 'open-settings', 'save-as-template'])

const logoUrl = computed(() => {
  const baseUrl = window.dsfEditorData?.pluginUrl || ''
  return `${baseUrl}assets/images/dsflow-logo.png`
})

const adminUrl = computed(() => window.dsfEditorData?.adminUrl || '')

const subtitleText = computed(() => {
  if (props.postType === 'dsf_layout') {
    return props.layoutType === 'footer'
      ? 'Build reusable footer templates for your Flow-built pages'
      : 'Build reusable header templates for your Flow-built pages'
  }
  return 'Build your WordPress Page with Artisanal Content Blocks'
})

const saveLabel = computed(() => {
  if (props.postType === 'dsf_layout') {
    return props.layoutType === 'footer' ? 'Save Footer Template' : 'Save Header Template'
  }
  return 'Save Page'
})
</script>

<style scoped>
.dsf-header__left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.dsf-header__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--dsf-radius-md, 8px);
  color: var(--dsf-gray-500, #6b7280);
  background: var(--dsf-gray-100, #f3f4f6);
  text-decoration: none;
  transition: all 0.15s;
}

.dsf-header__back:hover {
  color: var(--dsf-gray-900, #111827);
  background: var(--dsf-gray-200, #e5e7eb);
}

.dsf-header__brand {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: 12px;
  row-gap: 2px;
  align-items: center;
}

.dsf-header__logo {
  grid-row: 1 / span 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.dsf-header__logo img {
  height: 44px;
  width: 44px;
  object-fit: contain;
  display: block;
  border:none;
}

.dsf-header__title,
.dsf-header__subtitle {
  margin: 0;
}

.dsf-header__center {
  display: flex;
  align-items: center;
}

.dsf-header__right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.dsf-header__btn {
  white-space: nowrap;
}
</style>
