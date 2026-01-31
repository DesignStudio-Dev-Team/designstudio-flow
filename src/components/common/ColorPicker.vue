<template>
  <div class="dsf-color-picker-wrapper">
    <div class="dsf-color-input-row">
      <div class="dsf-color-swatch-wrapper">
        <input 
          type="color" 
          class="dsf-color-swatch" 
          :value="hexValue"
          @input="onHexInput"
        />
      </div>
      <input 
        type="text" 
        class="dsf-input dsf-color-text" 
        :value="modelValue"
        @change="onManualInput"
      />
    </div>
    
    <div class="dsf-opacity-control">
      <div class="dsf-flex dsf-items-center dsf-justify-between dsf-mb-1">
        <label class="dsf-label-xs">Opacity</label>
        <span class="dsf-label-xs">{{ opacityValue }}%</span>
      </div>
      <input 
        type="range" 
        class="dsf-slider dsf-slider-sm" 
        min="0" 
        max="100" 
        :value="opacityValue"
        @input="onOpacityInput"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '#000000'
  }
})

const emit = defineEmits(['update:modelValue'])

// Helper to check if string is rgba
const isRgba = (str) => String(str).trim().startsWith('rgba')

// Helper to convert hex to rgb object
const hexToRgb = (hex) => {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  return result ? {
    r: parseInt(result[1], 16),
    g: parseInt(result[2], 16),
    b: parseInt(result[3], 16)
  } : { r: 0, g: 0, b: 0 }
}

// Helper to parse rgba string
const parseRgba = (str) => {
  const match = str.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/)
  if (match) {
    return {
      r: parseInt(match[1]),
      g: parseInt(match[2]),
      b: parseInt(match[3]),
      a: match[4] !== undefined ? parseFloat(match[4]) : 1
    }
  }
  return { r: 0, g: 0, b: 0, a: 1 }
}

// Helper: Component to Hex
const componentToHex = (c) => {
  const hex = c.toString(16)
  return hex.length == 1 ? "0" + hex : hex
}

// Helper: RGB to Hex
const rgbToHex = (r, g, b) => {
  return "#" + componentToHex(r) + componentToHex(g) + componentToHex(b)
}

// Computed properties
const hexValue = computed(() => {
  if (isRgba(props.modelValue)) {
    const { r, g, b } = parseRgba(props.modelValue)
    return rgbToHex(r, g, b)
  }
  // Assume hex or simple color name, but input[type=color] needs hex
  if (props.modelValue.startsWith('#')) {
    // Ensure full 6 digits
    if (props.modelValue.length === 4) {
      return '#' + props.modelValue[1] + props.modelValue[1] + props.modelValue[2] + props.modelValue[2] + props.modelValue[3] + props.modelValue[3]
    }
    return props.modelValue
  }
  // Fallback for names or invalid
  return '#000000'
})

const opacityValue = computed(() => {
  if (isRgba(props.modelValue)) {
    const { a } = parseRgba(props.modelValue)
    return Math.round(a * 100)
  }
  return 100
})

// Handlers
function onHexInput(event) {
  const newHex = event.target.value
  const currentOpacity = opacityValue.value
  
  if (currentOpacity < 100) {
    const { r, g, b } = hexToRgb(newHex)
    emit('update:modelValue', `rgba(${r}, ${g}, ${b}, ${currentOpacity / 100})`)
  } else {
    emit('update:modelValue', newHex)
  }
}

function onOpacityInput(event) {
  const newOpacity = parseInt(event.target.value)
  const currentHex = hexValue.value
  const { r, g, b } = hexToRgb(currentHex)
  
  if (newOpacity === 100) {
    emit('update:modelValue', currentHex)
  } else {
    emit('update:modelValue', `rgba(${r}, ${g}, ${b}, ${newOpacity / 100})`)
  }
}

function onManualInput(event) {
  emit('update:modelValue', event.target.value)
}
</script>

<style scoped>
.dsf-color-picker-wrapper {
  background: var(--dsf-gray-50);
  padding: 0.75rem;
  border-radius: var(--dsf-radius-md);
  border: 1px solid var(--dsf-gray-200);
}

.dsf-color-input-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.dsf-color-swatch-wrapper {
  position: relative;
  width: 36px;
  height: 36px;
  border-radius: var(--dsf-radius-sm);
  overflow: hidden;
  border: 1px solid var(--dsf-gray-300);
  background-image: 
    linear-gradient(45deg, #ccc 25%, transparent 25%), 
    linear-gradient(-45deg, #ccc 25%, transparent 25%), 
    linear-gradient(45deg, transparent 75%, #ccc 75%), 
    linear-gradient(-45deg, transparent 75%, #ccc 75%);
  background-size: 10px 10px;
  background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
  background-color: white;
}

.dsf-color-swatch {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  cursor: pointer;
  border: none;
  padding: 0;
  margin: 0;
}

.dsf-color-text {
  flex: 1;
  font-family: var(--dsf-font-mono);
  font-size: 0.75rem;
}

.dsf-opacity-control {
  padding-top: 0.5rem;
  border-top: 1px solid var(--dsf-gray-200);
}

.dsf-label-xs {
  font-size: 0.75rem;
  color: var(--dsf-gray-500);
  font-weight: 500;
}

.dsf-slider-sm {
  height: 4px;
}

.dsf-slider-sm::-webkit-slider-thumb {
  width: 12px;
  height: 12px;
}
</style>
