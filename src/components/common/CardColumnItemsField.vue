<template>
  <div class="dsf-card-column-items-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-card-column-items-field__drag"
      ghost-class="dsf-card-column-items-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-card-column-items-field__item">
          <div class="dsf-card-column-items-field__header" @click="toggleItem(index)">
            <button class="dsf-card-column-items-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-card-column-items-field__title">{{ element.title || `Card ${index + 1}` }}</span>
            <div class="dsf-card-column-items-field__actions">
              <ChevronDown :size="16" class="dsf-card-column-items-field__chevron" :class="{ 'dsf-card-column-items-field__chevron--open': openItems.includes(index) }" />
              <button class="dsf-card-column-items-field__delete" type="button" title="Remove card" aria-label="Remove card" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-card-column-items-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Icon</label>
              <select class="dsf-input" :value="element.iconType || 'none'" @change="updateField(index, 'iconType', $event.target.value)">
                <option value="none">No icon</option>
                <option value="preset">Preset icon</option>
                <option value="custom">Custom image</option>
              </select>
            </div>
            <div v-if="element.iconType === 'preset'" class="dsf-form-group">
              <label class="dsf-label">Preset Icon</label>
              <select class="dsf-input" :value="element.icon || 'sparkles'" @change="updateField(index, 'icon', $event.target.value)">
                <option v-for="name in iconNames" :key="name" :value="name">{{ name }}</option>
              </select>
            </div>
            <div v-if="element.iconType === 'custom'" class="dsf-form-group">
              <label class="dsf-label">Custom Icon Image</label>
              <MediaPicker
                :modelValue="element.customIcon"
                @update:modelValue="updateField(index, 'customIcon', $event)"
              />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Title</label>
              <input type="text" class="dsf-input" :value="element.title" @input="updateField(index, 'title', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Description (optional)</label>
              <textarea class="dsf-input dsf-textarea" rows="2" :value="element.description" @input="updateField(index, 'description', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Image (bottom of card)</label>
              <MediaPicker
                :modelValue="element.image"
                @update:modelValue="updateField(index, 'image', $event)"
              />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Card Background</label>
              <select class="dsf-input" :value="element.backgroundType || 'solid'" @change="updateField(index, 'backgroundType', $event.target.value)">
                <option value="transparent">Transparent</option>
                <option value="solid">Solid Color</option>
                <option value="gradient">Gradient</option>
              </select>
            </div>
            <div v-if="(element.backgroundType || 'solid') === 'solid'" class="dsf-form-group">
              <label class="dsf-label">Background Color</label>
              <ColorPicker
                :modelValue="element.backgroundColor || '#F3F4F6'"
                @update:modelValue="updateField(index, 'backgroundColor', $event)"
              />
            </div>
            <template v-if="element.backgroundType === 'gradient'">
              <div class="dsf-form-group">
                <label class="dsf-label">Gradient Start</label>
                <ColorPicker
                  :modelValue="element.gradientStart || '#F3F4F6'"
                  @update:modelValue="updateField(index, 'gradientStart', $event)"
                />
              </div>
              <div class="dsf-form-group">
                <label class="dsf-label">Gradient End</label>
                <ColorPicker
                  :modelValue="element.gradientEnd || '#E5E7EB'"
                  @update:modelValue="updateField(index, 'gradientEnd', $event)"
                />
              </div>
              <div class="dsf-form-group">
                <label class="dsf-label">Gradient Direction</label>
                <select class="dsf-input" :value="element.gradientDirection || 'top-bottom'" @change="updateField(index, 'gradientDirection', $event.target.value)">
                  <option value="top-bottom">Top to Bottom</option>
                  <option value="left-right">Left to Right</option>
                  <option value="radial">Radial (Center Out)</option>
                </select>
              </div>
            </template>
            <div class="dsf-form-group">
              <label class="dsf-label dsf-card-column-items-field__toggle">
                <input
                  type="checkbox"
                  :checked="!!element.showButton"
                  @change="updateField(index, 'showButton', $event.target.checked)"
                />
                Show button
              </label>
            </div>
            <template v-if="element.showButton">
              <div class="dsf-form-group">
                <label class="dsf-label">Button Text</label>
                <input type="text" class="dsf-input" placeholder="Learn More" :value="element.buttonText" @input="updateField(index, 'buttonText', $event.target.value)" />
              </div>
              <div class="dsf-form-group">
                <label class="dsf-label">Button URL</label>
                <input type="text" class="dsf-input" placeholder="https://…" :value="element.buttonUrl" @input="updateField(index, 'buttonUrl', $event.target.value)" />
              </div>
            </template>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-card-column-items-field__add" type="button" :disabled="localItems.length >= MAX" @click="addItem">
      <Plus :size="16" />
      {{ localItems.length >= MAX ? `Maximum ${MAX} Cards` : 'Add Card' }}
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import { LANDING_ICON_NAMES } from '../../utils/landingIcons'
import MediaPicker from './MediaPicker.vue'
import ColorPicker from './ColorPicker.vue'

const MAX = 8
const iconNames = LANDING_ICON_NAMES

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

function defaultCard(index) {
  return {
    icon: '',
    iconType: 'none',
    customIcon: '',
    title: `Card ${index + 1}`,
    description: '',
    image: '',
    backgroundType: 'solid',
    backgroundColor: '#F3F4F6',
    gradientStart: '#F3F4F6',
    gradientEnd: '#E5E7EB',
    gradientDirection: 'top-bottom',
    showButton: false,
    buttonText: '',
    buttonUrl: '',
  }
}

watch(() => props.modelValue, (newValue) => {
  const previousIds = localItems.value.map((item) => item.id)
  localItems.value = (Array.isArray(newValue) ? newValue : []).slice(0, MAX).map((item, index) => {
    const merged = {
      ...defaultCard(index),
      ...item,
      // Preserve a stable key so inputs do not lose focus while typing.
      id: item.id || previousIds[index] || `card-item-${index}-${Date.now()}`,
    }
    // Legacy cards have no iconType; a non-empty icon means preset.
    if (!item.iconType) merged.iconType = merged.icon ? 'preset' : 'none'
    return merged
  })
}, { immediate: true, deep: true })

function emitUpdate() {
  emit('update:modelValue', localItems.value.map(({ id, ...item }) => item))
}

function toggleItem(index) {
  const currentIndex = openItems.value.indexOf(index)
  if (currentIndex >= 0) openItems.value.splice(currentIndex, 1)
  else openItems.value.push(index)
}

function updateField(index, key, value) {
  localItems.value[index][key] = value
  if (key === 'iconType' && value === 'preset' && !localItems.value[index].icon) {
    localItems.value[index].icon = 'sparkles'
  }
  emitUpdate()
}

function addItem() {
  if (localItems.value.length >= MAX) return
  localItems.value.push({ ...defaultCard(localItems.value.length), id: `card-item-${Date.now()}` })
  openItems.value.push(localItems.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  openItems.value = openItems.value
    .filter((itemIndex) => itemIndex !== index)
    .map((itemIndex) => (itemIndex > index ? itemIndex - 1 : itemIndex))
  emitUpdate()
}
</script>

<style scoped>
.dsf-card-column-items-field { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-card-column-items-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-card-column-items-field__item--ghost { opacity: 0.5; }
.dsf-card-column-items-field__header { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 0.75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-card-column-items-field__drag, .dsf-card-column-items-field__delete { display: flex; align-items: center; justify-content: center; padding: 0.25rem; border: none; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-card-column-items-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: 0.8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-card-column-items-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-card-column-items-field__chevron { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-card-column-items-field__chevron--open { transform: rotate(180deg); }
.dsf-card-column-items-field__body { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; }
.dsf-card-column-items-field__toggle { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.dsf-card-column-items-field__add { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.dsf-card-column-items-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
