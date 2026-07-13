<template>
  <div class="dsf-docknav-field">
    <draggable
      v-model="localItems"
      item-key="__id"
      handle=".dsf-docknav-field__drag"
      ghost-class="dsf-docknav-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-docknav-field__item">
          <div class="dsf-docknav-field__header" @click="toggleItem(index)">
            <button class="dsf-docknav-field__drag" type="button" title="Drag to reorder" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-docknav-field__preview">
              <img v-if="element.iconImage" :src="element.iconImage" alt="" />
              <component :is="dockIconFor(element.icon)" v-else :size="18" />
            </span>
            <span class="dsf-docknav-field__title">{{ element.label || `Link ${index + 1}` }}</span>
            <div class="dsf-docknav-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-docknav-field__chevron"
                :class="{ 'dsf-docknav-field__chevron--open': openItems.includes(index) }"
              />
              <button class="dsf-docknav-field__delete" type="button" title="Remove link" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-docknav-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Label</label>
              <input type="text" class="dsf-input" :value="element.label" @input="updateField(index, 'label', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Link (section anchor or URL)</label>
              <input type="text" class="dsf-input" :value="element.url" placeholder="#section" @input="updateField(index, 'url', $event.target.value)" />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">Icon</label>
              <div class="dsf-docknav-field__icon-row">
                <span class="dsf-docknav-field__icon-preview">
                  <img v-if="element.iconImage" :src="element.iconImage" alt="" />
                  <component :is="dockIconFor(element.icon)" v-else :size="18" />
                </span>
                <select
                  class="dsf-input"
                  :value="element.icon || 'sparkles'"
                  :disabled="!!element.iconImage"
                  @change="updateField(index, 'icon', $event.target.value)"
                >
                  <option v-for="name in iconNames" :key="name" :value="name">{{ name }}</option>
                </select>
              </div>
              <p v-if="element.iconImage" class="dsf-docknav-field__hint">Using a custom image — remove it to use a preset icon.</p>
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">Or use a custom image</label>
              <MediaPicker :model-value="element.iconImage || ''" @update:model-value="updateField(index, 'iconImage', $event)" />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-docknav-field__add" type="button" :disabled="localItems.length >= MAX" @click="addItem">
      <Plus :size="16" />
      {{ localItems.length >= MAX ? `Maximum ${MAX} links` : 'Add link' }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import { LANDING_ICON_NAMES } from '../../utils/landingIcons'
import { DSFLOW_DOCK_ICON_NAMES, dockIconFor } from '../../utils/dsflowDockIcons'
import MediaPicker from './MediaPicker.vue'

const iconNames = [...DSFLOW_DOCK_ICON_NAMES, ...LANDING_ICON_NAMES]

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  maxItems: { type: Number, default: 16 },
})

const MAX = computed(() => Math.max(1, Math.min(16, Number(props.maxItems) || 16)))

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (value) => {
  const previousIds = localItems.value.map((item) => item.__id)
  localItems.value = (Array.isArray(value) ? value : []).slice(0, MAX.value).map((item, index) => ({
    label: '',
    url: '#',
    icon: 'sparkles',
    iconImage: '',
    ...item,
    __id: item.__id || previousIds[index] || `docknav-${index}-${Date.now()}`,
  }))
}, { immediate: true, deep: true })

function emitUpdate() {
  emit('update:modelValue', localItems.value.map(({ __id, ...item }) => item))
}

function toggleItem(index) {
  const current = openItems.value.indexOf(index)
  if (current >= 0) openItems.value.splice(current, 1)
  else openItems.value.push(index)
}

function updateField(index, key, value) {
  localItems.value[index][key] = value
  emitUpdate()
}

function addItem() {
  if (localItems.value.length >= MAX.value) return
  localItems.value.push({ __id: `docknav-${Date.now()}`, label: 'New link', url: '#', icon: 'sparkles', iconImage: '' })
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
.dsf-docknav-field { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-docknav-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-docknav-field__item--ghost { opacity: 0.5; }
.dsf-docknav-field__header { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 0.75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-docknav-field__drag, .dsf-docknav-field__delete { display: flex; align-items: center; justify-content: center; padding: 0.25rem; border: none; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-docknav-field__preview { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; flex: 0 0 auto; border-radius: 6px; background: var(--dsf-gray-100); color: var(--dsf-gray-700); }
.dsf-docknav-field__preview img { width: 15px; height: 15px; object-fit: contain; }
.dsf-docknav-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: 0.8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-docknav-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-docknav-field__chevron { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-docknav-field__chevron--open { transform: rotate(180deg); }
.dsf-docknav-field__body { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; }
.dsf-docknav-field__icon-row { display: flex; align-items: center; gap: 0.5rem; }
.dsf-docknav-field__icon-preview { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 auto; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); background: var(--dsf-gray-50); color: var(--dsf-gray-800); }
.dsf-docknav-field__icon-preview img { width: 20px; height: 20px; object-fit: contain; }
.dsf-docknav-field__hint { margin: 0; color: var(--dsf-gray-500); font-size: 0.6875rem; }
.dsf-docknav-field__add { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.dsf-docknav-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
