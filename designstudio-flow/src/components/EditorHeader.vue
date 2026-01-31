<template>
  <header class="dsf-header">
    <!-- Left: Logo & Title -->
    <div class="dsf-header__left">
      <div class="dsf-header__brand">
        <h1 class="dsf-header__title">DesignStudio Flow</h1>
        <p class="dsf-header__subtitle">Build your WordPress page with drag-and-drop blocks</p>
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
        @click="$emit('open-theme')"
      >
        <Palette :size="16" />
        Theme
      </button>
      
      <button 
        class="dsf-btn dsf-btn--secondary dsf-header__btn"
        @click="$emit('preview')"
      >
        <Eye :size="16" />
        Preview
      </button>
      
      <button 
        class="dsf-btn dsf-btn--primary dsf-header__btn"
        :disabled="isSaving"
        @click="$emit('save')"
      >
        <Save :size="16" />
        {{ isSaving ? 'Saving...' : 'Save Page' }}
      </button>
    </div>
  </header>
</template>

<script setup>
import { Monitor, Tablet, Smartphone, Palette, Eye, Save } from 'lucide-vue-next'

defineProps({
  title: String,
  isSaving: Boolean,
  previewMode: String,
})

defineEmits(['update:title', 'preview', 'save', 'set-preview-mode', 'open-theme'])
</script>

<style scoped>
.dsf-header__left {
  display: flex;
  align-items: center;
  gap: 1rem;
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
