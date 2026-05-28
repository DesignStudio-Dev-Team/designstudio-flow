<template>
  <div class="dsf-panel dsf-panel--float" :style="floatStyle">
    <!-- Header / Drag Handle -->
    <div class="dsf-panel__header dsf-panel__drag-handle" @mousedown="startDrag">
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
        <div
          v-for="section in contentSettingSections"
          :key="section.key"
          class="dsf-settings-expander"
          :data-section="section.key"
        >
          <button
            class="dsf-settings-expander__trigger"
            type="button"
            @click="toggleContentSection(section.key)"
          >
            <span class="dsf-settings-expander__title">{{ section.title }}</span>
            <ChevronDown
              :size="18"
              class="dsf-settings-expander__chevron"
              :class="{ 'dsf-settings-expander__chevron--open': isContentSectionExpanded(section.key) }"
            />
          </button>

          <div
            v-if="isContentSectionExpanded(section.key)"
            class="dsf-settings-expander__body"
          >
            <template v-for="fieldKey in section.fields" :key="fieldKey">
              <SettingField
                :config="contentSettings[fieldKey]"
                :field-key="fieldKey"
                :value="getSettingValue(fieldKey)"
                :all-settings="block.settings"
                @update="(val) => updateSetting(fieldKey, val)"
              />
            </template>
          </div>
        </div>
      </template>

      <!-- Mobile Tab -->
      <template v-if="activeTab === 'mobile' && hasMobileTab">
        <div
          v-for="section in mobileSettingSections"
          :key="section.key"
          class="dsf-settings-expander"
        >
          <button
            class="dsf-settings-expander__trigger"
            type="button"
            @click="toggleMobileSection(section.key)"
          >
            <span class="dsf-settings-expander__title">{{ section.title }}</span>
            <ChevronDown
              :size="18"
              class="dsf-settings-expander__chevron"
              :class="{ 'dsf-settings-expander__chevron--open': isMobileSectionExpanded(section.key) }"
            />
          </button>
          <div v-if="isMobileSectionExpanded(section.key)" class="dsf-settings-expander__body">
            <template v-for="fieldKey in section.fields" :key="fieldKey">
              <SettingField
                :config="mobileSettings[fieldKey]"
                :field-key="fieldKey"
                :value="getSettingValue(fieldKey)"
                :all-settings="block.settings"
                @update="(val) => updateSetting(fieldKey, val)"
              />
            </template>
          </div>
        </div>
      </template>

      <!-- Style Tab -->
      <template v-if="activeTab === 'style'">
        <div
          v-for="section in styleSettingSections"
          :key="section.key"
          class="dsf-settings-expander"
          :data-style-section="section.key"
        >
          <button
            class="dsf-settings-expander__trigger"
            type="button"
            @click="toggleStyleSection(section.key)"
          >
            <span class="dsf-settings-expander__title">{{ section.title }}</span>
            <ChevronDown
              :size="18"
              class="dsf-settings-expander__chevron"
              :class="{ 'dsf-settings-expander__chevron--open': isStyleSectionExpanded(section.key) }"
            />
          </button>

          <div
            v-if="isStyleSectionExpanded(section.key)"
            class="dsf-settings-expander__body"
          >
            <template v-if="section.key === 'spacing'">
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
                  :all-settings="block.settings"
                  @update="(val) => updateResponsiveField(key, val)"
                />
              </template>
            </template>

            <template v-else>
              <template v-for="fieldKey in section.fields" :key="fieldKey">
                <SettingField
                  :config="styleSettings[fieldKey]"
                  :field-key="fieldKey"
                  :value="getSettingValue(fieldKey)"
                  :all-settings="block.settings"
                  @update="(val) => updateSetting(fieldKey, val)"
                />
              </template>
            </template>
          </div>
        </div>
      </template>

      <!-- Data Tab (Products/Categories) -->
      <template v-if="activeTab === 'data' && hasDataTab">
        <template v-for="(config, key) in dataSettings" :key="key">
          <SettingField
            v-if="shouldShowField(key, block.settings)"
            :config="config"
            :field-key="key"
            :value="getSettingValue(key)"
            :all-settings="block.settings"
            @update="(val) => updateSetting(key, val)"
          />
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive, onMounted, onUnmounted } from 'vue'
import { X, FileText, Palette, ShoppingBag, Smartphone, ChevronDown } from 'lucide-vue-next'
import SettingField from './SettingField.vue'
import { getResponsiveValue, setResponsiveValue } from '../utils/responsiveSettings'

const props = defineProps({
  block: Object,
  blockDefinition: Object,
})

const emit = defineEmits(['close', 'update:settings'])

// ── Draggable float ──────────────────────────────────────────────────────────
const pos = reactive({ x: 0, y: 0 })
let dragStartX = 0, dragStartY = 0, dragStartPosX = 0, dragStartPosY = 0, isDragging = false

onMounted(() => {
  pos.x = Math.max(0, window.innerWidth - 420)
  pos.y = 80
})

onUnmounted(() => {
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
})

const floatStyle = computed(() => ({
  left: `${pos.x}px`,
  top: `${pos.y}px`,
  right: 'auto',
  bottom: 'auto',
  marginTop: '0',
}))

function startDrag(e) {
  if (e.target.closest('button')) return
  isDragging = true
  dragStartX = e.clientX
  dragStartY = e.clientY
  dragStartPosX = pos.x
  dragStartPosY = pos.y
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
  e.preventDefault()
}

function onDrag(e) {
  if (!isDragging) return
  pos.x = dragStartPosX + (e.clientX - dragStartX)
  pos.y = dragStartPosY + (e.clientY - dragStartY)
}

function stopDrag() {
  isDragging = false
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
}
// ────────────────────────────────────────────────────────────────────────────

const activeTab = ref('content')
const responsiveBreakpoint = ref('desktop')
const expandedContentSections = ref({})
const expandedStyleSections = ref({})
const expandedMobileSections = ref({})

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
    'contentPosition', 'imagePosition', 'padding', 'backgroundColor',
    'textColor', 'titleColor', 'cardColor', 'shopAllColor', 'buttonColor', 'buttonTextColor',
    'backgroundType', 'gradientDirection', 'backgroundImage', 'gradientStart', 'gradientEnd',
    'cardStyle'
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
    'contentPosition', 'imagePosition', 'padding', 'backgroundColor',
    'textColor', 'titleColor', 'cardColor', 'shopAllColor', 'buttonColor', 'buttonTextColor',
    'backgroundType', 'gradientDirection', 'backgroundImage', 'gradientStart', 'gradientEnd',
    'cardStyle'
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

function buildSections(settings, currentSettings, explicitSections) {
  if (explicitSections) {
    const assignedKeys = new Set(explicitSections.flatMap((s) => s.fields))
    const sections = explicitSections
      .map((section) => ({
        ...section,
        fields: section.fields.filter(
          (key) => settings[key] && shouldShowField(key, currentSettings)
        ),
      }))
      .filter((s) => s.fields.length > 0)

    // Remaining fields not assigned to any explicit section
    const remaining = Object.keys(settings).filter(
      (key) => !assignedKeys.has(key) && shouldShowField(key, currentSettings)
    )
    if (remaining.length > 0) {
      sections.push({ key: 'general', title: 'General', fields: remaining })
    }
    return sections
  }

  // Auto-group: bucket by `section` property on each field config, fallback to 'settings'
  const buckets = new Map()
  Object.entries(settings).forEach(([key, config]) => {
    if (!shouldShowField(key, currentSettings)) return
    const sectionKey = config.section || 'settings'
    const sectionTitle = config.sectionTitle || (sectionKey === 'settings' ? 'Settings' : sectionKey.charAt(0).toUpperCase() + sectionKey.slice(1))
    if (!buckets.has(sectionKey)) {
      buckets.set(sectionKey, { key: sectionKey, title: sectionTitle, fields: [] })
    }
    buckets.get(sectionKey).fields.push(key)
  })
  return Array.from(buckets.values()).filter((s) => s.fields.length > 0)
}

const bentoCotentSections = [
  {
    key: 'hero', title: 'Hero',
    fields: ['heroImage','heroTitle','heroType','searchPlaceholder','searchUrl','heroButtonText','heroButtonAction','heroButtonUrl','heroButtonModalLayout','heroButtonModalContentType','heroButtonModalContent','heroButtonModalHtml','heroButtonModalShortcode'],
  },
  { key: 'layout', title: 'Layout', fields: ['boxCount'] },
  { key: 'bars', title: 'Section Bars', fields: ['showTopBar','topBarText','showBottomBar','bottomBarText'] },
  { key: 'box1', title: 'Box 1', fields: ['box1Image','box1Title','box1ShowTitle','box1Url'] },
  { key: 'box2', title: 'Box 2', fields: ['box2Image','box2Title','box2ShowTitle','box2Url'] },
  { key: 'box3', title: 'Box 3', fields: ['box3Image','box3Title','box3ShowTitle','box3Url'] },
  { key: 'box4', title: 'Box 4', fields: ['box4Image','box4Title','box4ShowTitle','box4Url'] },
  { key: 'box5', title: 'Box 5', fields: ['box5Image','box5Title','box5ShowTitle','box5Url'] },
  {
    key: 'lastTile', title: 'Last Box',
    fields: ['ctaType','ctaText','ctaUrl','ctaAction','ctaModalLayout','ctaModalContentType','ctaModalContent','ctaModalHtml','ctaModalShortcode','box6CategoryId','box6Image','box6Title','box6ShowTitle','box6Url'],
  },
]

const bentoStyleSections = [
  { key: 'tiles', title: 'Tiles', fields: ['boxBackground','boxImageSize','titleColor'] },
  { key: 'bars', title: 'Section Bars', fields: ['sectionBarBackground','sectionBarTextColor','sectionBarHeight'] },
  { key: 'lastTile', title: 'Last Box', fields: ['ctaColor','ctaTextColor'] },
]

const contentSettingSections = computed(() => {
  const explicit = blockId.value === 'bento-hero' ? bentoCotentSections : null
  return buildSections(contentSettings.value, props.block?.settings || {}, explicit)
})

const mobileSettingSections = computed(() =>
  buildSections(mobileSettings.value, props.block?.settings || {}, null)
)

const styleSettingSections = computed(() => {
  const explicit = blockId.value === 'bento-hero' ? bentoStyleSections : null
  const sections = buildSections(styleSettings.value, props.block?.settings || {}, explicit)

  if (Object.keys(responsiveFieldConfigs.value).length > 0) {
    sections.push({ key: 'spacing', title: 'Responsive Spacing', fields: [] })
  }

  return sections
})

watch(blockId, () => {
  expandedContentSections.value = {}
  expandedStyleSections.value = {}
  expandedMobileSections.value = {}
})

function normalizeIdList(value) {
  if (Array.isArray(value)) {
    return [...new Set(value.map((item) => Number.parseInt(item, 10)).filter((item) => Number.isFinite(item) && item > 0))]
  }

  const parsed = Number.parseInt(value, 10)
  return Number.isFinite(parsed) && parsed > 0 ? [parsed] : []
}

function getSettingValue(key) {
  const settings = props.block?.settings || {}

  if (blockId.value === 'product-grid' && key === 'categoryIds') {
    const categoryIds = normalizeIdList(settings.categoryIds)
    if (categoryIds.length > 0) {
      return categoryIds
    }

    return normalizeIdList(settings.categoryId)
  }

  return settings[key]
}

function updateSetting(key, value) {
  if (value && typeof value === 'object' && value.__dsfBatch && value.updates) {
    emit('update:settings', value.updates)
    return
  }

  if (blockId.value === 'product-grid' && key === 'categoryIds') {
    const categoryIds = normalizeIdList(value)
    emit('update:settings', {
      categoryIds,
      categoryId: categoryIds[0] || 0,
    })
    return
  }

  emit('update:settings', { [key]: value })
}

function isContentSectionExpanded(key) {
  return expandedContentSections.value[key] === true
}

function toggleContentSection(key) {
  expandedContentSections.value = {
    ...expandedContentSections.value,
    [key]: !isContentSectionExpanded(key),
  }
}

function isStyleSectionExpanded(key) {
  return expandedStyleSections.value[key] === true
}

function toggleStyleSection(key) {
  expandedStyleSections.value = {
    ...expandedStyleSections.value,
    [key]: !isStyleSectionExpanded(key),
  }
}

function isMobileSectionExpanded(key) {
  return expandedMobileSections.value[key] === true
}

function toggleMobileSection(key) {
  expandedMobileSections.value = {
    ...expandedMobileSections.value,
    [key]: !isMobileSectionExpanded(key),
  }
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

  if (key === 'box6ShowTitle') {
    return settings.boxCount === '6' && settings.ctaType !== 'cta'
  }
  
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
    if (key === 'categoryIds' || key === 'categoryId') return settings.source === 'category'
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

<style scoped>
/* Floating window overrides */
.dsf-panel--float {
  top: auto !important;
  right: auto !important;
  bottom: auto !important;
  margin-top: 0 !important;
  border-left: none;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18), 0 4px 16px rgba(0, 0, 0, 0.08);
  max-height: 85vh;
}

.dsf-panel__drag-handle {
  cursor: grab;
  user-select: none;
}

.dsf-panel__drag-handle:active {
  cursor: grabbing;
}

.dsf-settings-expander {
  background: #ffffff;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
}

.dsf-settings-expander + .dsf-settings-expander {
  margin-top: 0.75rem;
}

.dsf-settings-expander__trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.95rem 1rem;
  border: 0;
  background: #ffffff;
  color: var(--dsf-gray-900);
  cursor: pointer;
  text-align: left;
}

.dsf-settings-expander__trigger:hover {
  background: var(--dsf-gray-50);
}

.dsf-settings-expander__title {
  font-size: 0.95rem;
  font-weight: 600;
}

.dsf-settings-expander__chevron {
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.dsf-settings-expander__chevron--open {
  transform: rotate(180deg);
}

.dsf-settings-expander__body {
  padding: 0.25rem 1rem 1rem;
  border-top: 1px solid var(--dsf-gray-100);
}
</style>
