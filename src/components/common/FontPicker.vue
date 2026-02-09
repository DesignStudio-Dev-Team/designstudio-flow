<template>
  <div class="dsf-font-picker">
    <div class="dsf-font-picker__trigger" @click="isOpen = !isOpen">
      <span 
        class="dsf-font-picker__preview"
        :style="{ fontFamily: selectedFontFamily }"
      >
        {{ displayValue }}
      </span>
      <ChevronDown :size="16" class="dsf-font-picker__icon" :class="{ 'dsf-font-picker__icon--open': isOpen }" />
    </div>
    
    <div v-if="isOpen" class="dsf-font-picker__dropdown">
      <!-- Source Tabs -->
      <div class="dsf-font-picker__tabs">
        <button 
          class="dsf-font-picker__tab"
          :class="{ 'dsf-font-picker__tab--active': activeTab === 'preset' }"
          @click="activeTab = 'preset'"
        >
          Popular Fonts
        </button>
        <button 
          v-if="themeFonts.length > 0"
          class="dsf-font-picker__tab"
          :class="{ 'dsf-font-picker__tab--active': activeTab === 'theme' }"
          @click="activeTab = 'theme'"
        >
          {{ themeFontTabLabel }}
        </button>
      </div>
      
      <!-- Font List -->
      <div class="dsf-font-picker__list">
        <!-- Preset Fonts -->
        <template v-if="activeTab === 'preset'">
          <div
            v-for="font in presetFonts"
            :key="font.value"
            class="dsf-font-picker__item"
            :class="{ 'dsf-font-picker__item--selected': modelValue === font.value }"
            @click="selectFont(font.value)"
          >
            <span :style="{ fontFamily: font.value }">{{ font.label }}</span>
            <Check v-if="modelValue === font.value" :size="14" />
          </div>
        </template>
        
        <!-- Theme Fonts -->
        <template v-else-if="activeTab === 'theme'">
          <div
            v-for="font in themeFonts"
            :key="font.value"
            class="dsf-font-picker__item"
            :class="{ 'dsf-font-picker__item--selected': modelValue === font.value }"
            @click="selectFont(font.value)"
          >
            <span :style="{ fontFamily: font.value }">
              {{ font.label }}
              <span class="dsf-font-picker__badge">{{ getBadgeText(font) }}</span>
            </span>
            <Check v-if="modelValue === font.value" :size="14" />
          </div>
        </template>
      </div>
    </div>
    
    <!-- Backdrop to close dropdown -->
    <div v-if="isOpen" class="dsf-font-picker__backdrop" @click="isOpen = false"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ChevronDown, Check } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const activeTab = ref('preset')
const loadedFonts = ref(new Set())

// Curated list of popular Google Fonts
const presetFonts = [
  { label: 'Inter', value: "'Inter', sans-serif" },
  { label: 'Roboto', value: "'Roboto', sans-serif" },
  { label: 'Open Sans', value: "'Open Sans', sans-serif" },
  { label: 'Lato', value: "'Lato', sans-serif" },
  { label: 'Montserrat', value: "'Montserrat', sans-serif" },
  { label: 'Poppins', value: "'Poppins', sans-serif" },
  { label: 'Outfit', value: "'Outfit', sans-serif" },
  { label: 'Source Sans 3', value: "'Source Sans 3', sans-serif" },
  { label: 'Nunito', value: "'Nunito', sans-serif" },
  { label: 'Raleway', value: "'Raleway', sans-serif" },
  { label: 'Playfair Display', value: "'Playfair Display', serif" },
  { label: 'Merriweather', value: "'Merriweather', serif" },
  { label: 'Lora', value: "'Lora', serif" },
  { label: 'DM Sans', value: "'DM Sans', sans-serif" },
  { label: 'Work Sans', value: "'Work Sans', sans-serif" },
  { label: 'Oswald', value: "'Oswald', sans-serif" },
  { label: 'Ubuntu', value: "'Ubuntu', sans-serif" },
  { label: 'Rubik', value: "'Rubik', sans-serif" },
  { label: 'Manrope', value: "'Manrope', sans-serif" },
  { label: 'Space Grotesk', value: "'Space Grotesk', sans-serif" },
]

// Theme fonts from WordPress
const themeFonts = computed(() => {
  const wpData = window.dsfEditorData || {}
  return wpData.themeFonts || []
})

// Determine tab label based on font sources
const themeFontTabLabel = computed(() => {
  if (themeFonts.value.length === 0) return 'Theme Fonts'
  // Check if all fonts are system fonts
  const hasOnlySystemFonts = themeFonts.value.every((f) => f.source === 'system')
  if (hasOnlySystemFonts) return 'System Fonts'
  return 'Theme Fonts'
})

// Get appropriate badge text based on font source
function getBadgeText(font) {
  if (font.source === 'system') return 'System'
  if (font.source === 'customizer') return 'Customizer'
  return 'Theme'
}

const displayValue = computed(() => {
  if (!props.modelValue) return 'Select Font'
  
  // Try to find in preset fonts
  const preset = presetFonts.find((f) => f.value === props.modelValue)
  if (preset) return preset.label
  
  // Try to find in theme fonts
  const theme = themeFonts.value.find((f) => f.value === props.modelValue)
  if (theme) {
    const suffix = theme.source === 'system' ? 'System' : 'Theme'
    return `${theme.label} (${suffix})`
  }
  
  // Extract font name from font-family value
  const match = props.modelValue.match(/'([^']+)'/)
  return match ? match[1] : props.modelValue
})

const selectedFontFamily = computed(() => props.modelValue || 'inherit')

function selectFont(value) {
  emit('update:modelValue', value)
  isOpen.value = false
}

// Load Google Fonts dynamically for preview
function loadGoogleFont(fontFamily) {
  // Extract just the font name from the font-family string
  const match = fontFamily.match(/'([^']+)'/)
  if (!match) return
  
  const fontName = match[1]
  if (loadedFonts.value.has(fontName)) return
  
  loadedFonts.value.add(fontName)
  
  // Create link element for Google Fonts
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/ /g, '+')}:wght@400;500;600;700&display=swap`
  document.head.appendChild(link)
}

// Load fonts when dropdown opens
watch(isOpen, (open) => {
  if (open && activeTab.value === 'preset') {
    presetFonts.forEach((font) => loadGoogleFont(font.value))
  }
})

// Load selected font on mount
onMounted(() => {
  if (props.modelValue) {
    loadGoogleFont(props.modelValue)
  }
})
</script>

<style scoped>
.dsf-font-picker {
  position: relative;
}

.dsf-font-picker__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-white);
  border: 1px solid var(--dsf-gray-300);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.15s ease;
  min-height: 42px;
}

.dsf-font-picker__trigger:hover {
  border-color: var(--dsf-gray-400);
  background: var(--dsf-gray-50);
}

.dsf-font-picker__preview {
  font-size: 0.875rem;
  color: var(--dsf-gray-800);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-font-picker__icon {
  color: var(--dsf-gray-400);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.dsf-font-picker__icon--open {
  transform: rotate(180deg);
}

.dsf-font-picker__backdrop {
  position: fixed;
  inset: 0;
  z-index: 98;
}

.dsf-font-picker__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  z-index: 99;
  overflow: hidden;
  max-height: 320px;
  display: flex;
  flex-direction: column;
}

.dsf-font-picker__tabs {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  flex-shrink: 0;
}

.dsf-font-picker__tab {
  flex: 1;
  padding: 0.625rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--dsf-gray-600);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.dsf-font-picker__tab:hover {
  color: var(--dsf-gray-800);
  background: var(--dsf-gray-100);
}

.dsf-font-picker__tab--active {
  color: var(--dsf-primary, #3B82F6);
  background: #ffffff;
  box-shadow: inset 0 -2px 0 var(--dsf-primary, #3B82F6);
}

.dsf-font-picker__list {
  overflow-y: auto;
  flex: 1;
  background: #ffffff;
}

.dsf-font-picker__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 0.75rem;
  font-size: 0.875rem;
  color: var(--dsf-gray-700);
  cursor: pointer;
  transition: all 0.15s ease;
}

.dsf-font-picker__item:hover {
  background: #f3f4f6;
}

.dsf-font-picker__item--selected {
  background: var(--dsf-primary-50, #ecfdf5);
  color: var(--dsf-primary);
}

.dsf-font-picker__item span {
  font-size: 1rem;
}

.dsf-font-picker__badge {
  display: inline-block;
  margin-left: 0.5rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: #2C5F5D;
  background: #d1fae5;
  border-radius: 4px;
  vertical-align: middle;
}
</style>
