<template>
  <div class="dsf-icon-items-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-icon-items-field__drag"
      ghost-class="dsf-icon-items-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-icon-items-field__item">
          <div class="dsf-icon-items-field__header" @click="toggleItem(index)">
            <button class="dsf-icon-items-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-icon-items-field__title">{{ element.title || `Item ${index + 1}` }}</span>
            <div class="dsf-icon-items-field__actions">
              <ChevronDown :size="16" class="dsf-icon-items-field__chevron" :class="{ 'dsf-icon-items-field__chevron--open': openItems.includes(index) }" />
              <button class="dsf-icon-items-field__delete" type="button" title="Remove item" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-icon-items-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Icon</label>
              <select class="dsf-input" :value="element.icon || 'sparkles'" @change="updateField(index, 'icon', $event.target.value)">
                <option v-for="name in iconNames" :key="name" :value="name">{{ name }}</option>
              </select>
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Title</label>
              <input type="text" class="dsf-input" :value="element.title" @input="updateField(index, 'title', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Description</label>
              <textarea class="dsf-input dsf-textarea" rows="2" :value="element.description" @input="updateField(index, 'description', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Note / caption (optional)</label>
              <input type="text" class="dsf-input" :value="element.note" @input="updateField(index, 'note', $event.target.value)" />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-icon-items-field__add" type="button" :disabled="localItems.length >= 8" @click="addItem">
      <Plus :size="16" />
      {{ localItems.length >= 8 ? 'Maximum 8 Items' : 'Add Item' }}
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import { LANDING_ICON_NAMES } from '../../utils/landingIcons'

const MAX = 8
const iconNames = LANDING_ICON_NAMES

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (newValue) => {
  const previousIds = localItems.value.map((item) => item.id)
  localItems.value = (newValue || []).slice(0, MAX).map((item, index) => ({
    icon: 'sparkles',
    title: `Item ${index + 1}`,
    description: '',
    note: '',
    ...item,
    id: item.id || previousIds[index] || `icon-item-${index}-${Date.now()}`,
  }))
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
  emitUpdate()
}

function addItem() {
  if (localItems.value.length >= MAX) return
  localItems.value.push({ id: `icon-item-${Date.now()}`, icon: 'sparkles', title: `Item ${localItems.value.length + 1}`, description: '', note: '' })
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
.dsf-icon-items-field { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-icon-items-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-icon-items-field__item--ghost { opacity: 0.5; }
.dsf-icon-items-field__header { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 0.75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-icon-items-field__drag, .dsf-icon-items-field__delete { display: flex; align-items: center; justify-content: center; padding: 0.25rem; border: none; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-icon-items-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: 0.8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-icon-items-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-icon-items-field__chevron { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-icon-items-field__chevron--open { transform: rotate(180deg); }
.dsf-icon-items-field__body { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; }
.dsf-icon-items-field__add { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.dsf-icon-items-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
