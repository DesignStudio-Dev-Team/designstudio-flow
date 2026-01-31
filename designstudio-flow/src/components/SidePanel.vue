<template>
  <aside class="dsf-panel dsf-animate-slide-in-right">
    <!-- Header -->
    <div class="dsf-panel__header">
      <div>
        <h2 class="dsf-panel__title">Customize Block</h2>
        <p class="dsf-panel__subtitle">{{ blockDefinition?.name || 'Block' }}</p>
      </div>
      <button class="dsf-panel__close" @click="$emit('close')">
        <X :size="20" />
      </button>
    </div>
    
    <!-- Tabs -->
    <div class="dsf-panel__tabs">
      <div class="dsf-segmented-control">
        <button 
          class="dsf-segmented-btn"
          :class="{ 'dsf-segmented-btn--active': activeTab === 'content' }"
          @click="activeTab = 'content'"
        >
          <FileText :size="14" />
          <span>Content</span>
        </button>
        <button 
          class="dsf-segmented-btn"
          :class="{ 'dsf-segmented-btn--active': activeTab === 'style' }"
          @click="activeTab = 'style'"
        >
          <Palette :size="14" />
          <span>Style</span>
        </button>
        <button 
          v-if="hasDataTab"
          class="dsf-segmented-btn"
          :class="{ 'dsf-segmented-btn--active': activeTab === 'data' }"
          @click="activeTab = 'data'"
        >
          <ShoppingBag :size="14" />
          <span>{{ dataTabLabel }}</span>
        </button>
      </div>
    </div>
    
    <!-- Body -->
    <div class="dsf-panel__body dsf-bg-gray-50">
      <!-- Content Tab -->
      <template v-if="activeTab === 'content'">
        <div class="dsf-settings-card">
          <template v-for="(config, key) in contentSettings" :key="key">
            <SettingField 
              v-if="shouldShowField(key, block.settings)"
              :config="config"
              :field-key="key"
              :value="block.settings[key]"
              @update="(val) => updateSetting(key, val)"
            />
          </template>
        </div>
      </template>
      
      <!-- Style Tab -->
      <template v-if="activeTab === 'style'">
        <div class="dsf-settings-card">
          <template v-for="(config, key) in styleSettings" :key="key">
            <SettingField 
              v-if="shouldShowField(key, block.settings)"
              :config="config"
              :field-key="key"
              :value="block.settings[key]"
              @update="(val) => updateSetting(key, val)"
            />
          </template>
        </div>
      </template>
      
      <!-- Data Tab (Products/Categories) -->
      <template v-if="activeTab === 'data' && hasDataTab">
        <template v-for="(config, key) in dataSettings" :key="key">
          <SettingField 
            v-if="shouldShowField(key, block.settings)"
            :config="config"
            :field-key="key"
            :value="block.settings[key]"
            @update="(val) => updateSetting(key, val)"
          />
        </template>
      </template>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed } from 'vue'
import { X, FileText, Palette, ShoppingBag } from 'lucide-vue-next'
import SettingField from './SettingField.vue'

const props = defineProps({
  block: Object,
  blockDefinition: Object,
})

const emit = defineEmits(['close', 'update:settings'])

const activeTab = ref('content')

// Split settings into tabs
const contentSettings = computed(() => {
  const settings = props.blockDefinition?.settings || {}
  const styleTypes = ['color', 'slider']
  const styleKeys = [
    'contentPosition', 'imagePosition', 'columns', 'padding', 'backgroundColor', 
    'textColor', 'titleColor', 'cardColor', 'shopAllColor', 'buttonColor', 'buttonTextColor',
    'backgroundType', 'gradientDirection', 'backgroundImage', 'gradientStart', 'gradientEnd'
  ]

  return Object.fromEntries(
    Object.entries(settings).filter(([key, config]) => 
      !styleTypes.includes(config.type) && 
      !styleKeys.includes(key)
    )
  )
})

const styleSettings = computed(() => {
  const settings = props.blockDefinition?.settings || {}
  const styleTypes = ['color', 'slider']
  const styleKeys = [
    'contentPosition', 'imagePosition', 'columns', 'padding', 'backgroundColor', 
    'textColor', 'titleColor', 'cardColor', 'shopAllColor', 'buttonColor', 'buttonTextColor',
    'backgroundType', 'gradientDirection', 'backgroundImage', 'gradientStart', 'gradientEnd'
  ]

  return Object.fromEntries(
    Object.entries(settings).filter(([key, config]) => 
      styleTypes.includes(config.type) || 
      styleKeys.includes(key)
    )
  )
})

const hasDataTab = computed(() => false) // Disable Data tab
const dataTabLabel = computed(() => '') // Not used

function updateSetting(key, value) {
  emit('update:settings', { [key]: value })
}

function shouldShowField(key, settings) {
  // Get the config for this field
  const allSettings = props.blockDefinition?.settings || {}
  const config = allSettings[key]
  
  // Check showWhen condition if present
  if (config?.showWhen) {
    for (const [dependKey, dependValue] of Object.entries(config.showWhen)) {
      if (settings[dependKey] !== dependValue) {
        return false
      }
    }
  }
  
  // Legacy logic for Product Grid source
  if (settings.source) {
    if (key === 'categoryId') return settings.source === 'category'
    if (key === 'pinnedProductIds') return settings.source === 'category'
    if (key === 'productIds') return settings.source === 'manual'
  }
  
  // Ecommerce Showcase displayMode logic
  if (settings.displayMode !== undefined) {
    if (key === 'pinnedProductIds') return settings.displayMode === 'products'
  }
  
  return true
}
</script>
