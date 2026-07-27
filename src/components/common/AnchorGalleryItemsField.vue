<template>
  <div class="dsf-anchor-gallery-items-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-anchor-gallery-items-field__drag"
      ghost-class="dsf-anchor-gallery-items-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-anchor-gallery-items-field__item">
          <div class="dsf-anchor-gallery-items-field__header" @click="toggleItem(index)">
            <button class="dsf-anchor-gallery-items-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-anchor-gallery-items-field__title">{{ element.title || `Tile ${index + 1}` }}</span>
            <div class="dsf-anchor-gallery-items-field__actions">
              <ChevronDown :size="16" :class="{ 'is-open': openItems.includes(index) }" />
              <button class="dsf-anchor-gallery-items-field__delete" type="button" title="Remove tile" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-anchor-gallery-items-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Title</label>
              <input class="dsf-input" type="text" :value="element.title" @input="updateField(index, 'title', $event.target.value)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Media Type</label>
              <select class="dsf-input" :value="element.mediaType || 'image'" @change="updateField(index, 'mediaType', $event.target.value)">
                <option value="image">Image</option>
                <option value="video">Video</option>
              </select>
            </div>
            <div v-if="element.mediaType === 'video'" class="dsf-form-group">
              <label class="dsf-label">Video (MP4)</label>
              <VideoPicker :modelValue="element.video" @update:modelValue="updateField(index, 'video', $event)" />
            </div>
            <div v-else class="dsf-form-group">
              <label class="dsf-label">Image</label>
              <MediaPicker :modelValue="element.image" @update:modelValue="updateField(index, 'image', $event)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Link (optional)</label>
              <input class="dsf-input" type="text" placeholder="#" :value="element.url" @input="updateField(index, 'url', $event.target.value)" />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-anchor-gallery-items-field__add" type="button" :disabled="localItems.length >= maxItems" @click="addItem">
      <Plus :size="16" />
      {{ localItems.length >= maxItems ? `Maximum ${maxItems} Tiles` : 'Add Tile' }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import MediaPicker from './MediaPicker.vue'
import VideoPicker from './VideoPicker.vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  allSettings: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
const localItems = ref([])
const openItems = ref([0])
const maxItems = computed(() => props.allSettings?.layout === 'grid' ? 8 : 5)

function defaultItem(index) {
  return { title: `Featured Item ${index + 1}`, image: '', mediaType: 'image', video: '', url: '#' }
}

watch([() => props.modelValue, maxItems], ([value]) => {
  const previousIds = localItems.value.map((item) => item.id)
  localItems.value = (Array.isArray(value) ? value : []).slice(0, maxItems.value).map((item, index) => ({
    ...defaultItem(index),
    ...item,
    id: item.id || previousIds[index] || `anchor-gallery-item-${index}-${Date.now()}`,
  }))
}, { immediate: true, deep: true })

function emitUpdate() {
  emit('update:modelValue', localItems.value.map(({ id, ...item }) => item))
}

function toggleItem(index) {
  const position = openItems.value.indexOf(index)
  if (position >= 0) openItems.value.splice(position, 1)
  else openItems.value.push(index)
}

function updateField(index, key, value) {
  localItems.value[index][key] = value
  emitUpdate()
}

function addItem() {
  if (localItems.value.length >= maxItems.value) return
  localItems.value.push({ ...defaultItem(localItems.value.length), id: `anchor-gallery-item-${Date.now()}` })
  openItems.value.push(localItems.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  openItems.value = openItems.value.filter((item) => item !== index).map((item) => (item > index ? item - 1 : item))
  emitUpdate()
}
</script>

<style scoped>
.dsf-anchor-gallery-items-field { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-anchor-gallery-items-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-anchor-gallery-items-field__item--ghost { opacity: 0.5; }
.dsf-anchor-gallery-items-field__header { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 0.75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-anchor-gallery-items-field__drag, .dsf-anchor-gallery-items-field__delete { display: flex; align-items: center; justify-content: center; padding: 0.25rem; border: 0; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-anchor-gallery-items-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: 0.8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-anchor-gallery-items-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-anchor-gallery-items-field__actions > svg { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-anchor-gallery-items-field__actions > svg.is-open { transform: rotate(180deg); }
.dsf-anchor-gallery-items-field__body { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; }
.dsf-anchor-gallery-items-field__add { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.dsf-anchor-gallery-items-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
