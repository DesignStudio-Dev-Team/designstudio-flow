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
          v-if="hasMobileTab"
          class="dsf-segmented-btn"
          :class="{ 'dsf-segmented-btn--active': activeTab === 'mobile' }"
          @click="activeTab = 'mobile'"
        >
          <Smartphone :size="14" />
          <span>Mobile</span>
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

      <!-- Mobile Tab -->
      <template v-if="activeTab === 'mobile' && hasMobileTab">
        <div class="dsf-settings-card">
          <template v-for="(config, key) in mobileSettings" :key="key">
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

        <div class="dsf-settings-card">
          <div class="dsf-settings-card__header">Responsive Spacing</div>
          <div class="dsf-segmented-control dsf-segmented-control--sm">
            <button
              class="dsf-segmented-btn"
              :class="{ 'dsf-segmented-btn--active': responsiveBreakpoint === 'desktop' }"
              @click="responsiveBreakpoint = 'desktop'"
            >
              Desktop
            </button>
            <button
              class="dsf-segmented-btn"
              :class="{ 'dsf-segmented-btn--active': responsiveBreakpoint === 'tablet' }"
              @click="responsiveBreakpoint = 'tablet'"
            >
              Tablet
            </button>
            <button
              class="dsf-segmented-btn"
              :class="{ 'dsf-segmented-btn--active': responsiveBreakpoint === 'mobile' }"
              @click="responsiveBreakpoint = 'mobile'"
            >
              Mobile
            </button>
          </div>
          <template v-for="(config, key) in responsiveFieldConfigs" :key="key">
            <SettingField
              :config="config"
              :field-key="key"
              :value="getResponsiveFieldValue(key)"
              @update="(val) => updateResponsiveField(key, val)"
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
import { X, FileText, Palette, ShoppingBag, Smartphone } from 'lucide-vue-next'
import SettingField from './SettingField.vue'
import { getResponsiveValue, setResponsiveValue } from '../utils/responsiveSettings'

const props = defineProps({
  block: Object,
  blockDefinition: Object,
})

const emit = defineEmits(['close', 'update:settings'])

const activeTab = ref('content')
const responsiveBreakpoint = ref('desktop')

const responsiveFieldOrder = ['height', 'gap', 'padding', 'paddingX', 'marginY']
const responsiveFieldDefaults = {
  height: { type: 'slider', label: 'Height', min: 200, max: 1000, default: 200 },
  gap: { type: 'slider', label: 'Gap', min: 0, max: 100, default: 12 },
  padding: { type: 'slider', label: 'Vertical Padding', min: 0, max: 200, default: 60 },
  paddingX: { type: 'slider', label: 'Horizontal Padding', min: 0, max: 200, default: 24 },
  marginY: { type: 'slider', label: 'Vertical Margin', min: 0, max: 200, default: 25 },
}

const blockId = computed(() => props.blockDefinition?.id || props.block?.type || '')
const responsiveFieldVisibility = {
  gap: new Set(['bento-hero']),
}

const hasMobileTab = computed(() => blockId.value === 'header-mega-menu')

const heightDefaultsByBlock = {
  'bento-hero': 400,
  'duo-hero': 500,
  'promo-banner': 280,
  'featured-promo-banner': 450,
  'featured-product-banner': 240,
}

function isResponsiveFieldEnabled(key) {
  const allowed = responsiveFieldVisibility[key]
  if (!allowed) return true
  return allowed.has(blockId.value)
}

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
      !styleKeys.includes(key) &&
      !(hasMobileTab.value && key.startsWith('mobile'))
    )
  )
})

const mobileSettings = computed(() => {
  if (!hasMobileTab.value) return {}
  const settings = props.blockDefinition?.settings || {}
  return Object.fromEntries(
    Object.entries(settings).filter(([key]) => key.startsWith('mobile'))
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
      (styleTypes.includes(config.type) || styleKeys.includes(key)) &&
      !responsiveFieldOrder.includes(key)
    )
  )
})

const responsiveFieldConfigs = computed(() => {
  const settings = props.blockDefinition?.settings || {}
  const configs = {}

  responsiveFieldOrder.forEach((key) => {
    if (!isResponsiveFieldEnabled(key)) return
    const baseConfig = settings[key] || {}
    const fallback = responsiveFieldDefaults[key] || {}
    const config = { ...fallback, ...baseConfig }
    if (key === 'height') {
      const blockDefault = heightDefaultsByBlock[blockId.value]
      if ((config.default === undefined || config.default === null) && blockDefault !== undefined) {
        config.default = blockDefault
      }
    }
    if (!config.label && fallback.label) config.label = fallback.label
    if (config.type !== 'slider') config.type = 'slider'
    configs[key] = config
  })

  return configs
})

const hasDataTab = computed(() => false) // Disable Data tab
const dataTabLabel = computed(() => '') // Not used

function updateSetting(key, value) {
  emit('update:settings', { [key]: value })
}

function getResponsiveFieldValue(key) {
  const value = getResponsiveValue(props.block?.settings || {}, responsiveBreakpoint.value, key)
  if (value === undefined || value === null) {
    return responsiveFieldConfigs.value[key]?.default ?? 0
  }
  return value
}

function updateResponsiveField(key, value) {
  const nextSettings = setResponsiveValue(props.block?.settings || {}, responsiveBreakpoint.value, key, value)
  emit('update:settings', nextSettings)
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
