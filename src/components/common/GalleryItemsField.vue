<template>
  <div class="dsf-gallery-items-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-gallery-items-field__drag"
      ghost-class="dsf-gallery-items-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-gallery-items-field__item">
          <div class="dsf-gallery-items-field__header" @click="toggleItem(index)">
            <button class="dsf-gallery-items-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-gallery-items-field__title">{{ element.title || `Item ${index + 1}` }}</span>
            <div class="dsf-gallery-items-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-gallery-items-field__chevron"
                :class="{ 'dsf-gallery-items-field__chevron--open': openItems.includes(index) }"
              />
              <button class="dsf-gallery-items-field__delete" type="button" title="Remove item" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-gallery-items-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Category (filter)</label>
              <input type="text" class="dsf-input" :value="element.category" @input="updateField(index, 'category', $event.target.value)" placeholder="e.g. Heroes" />
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
              <label class="dsf-label">Image (optional)</label>
              <MediaPicker :modelValue="element.image" @update:modelValue="updateField(index, 'image', $event)" />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Fallback visual</label>
              <select class="dsf-input" :value="element.kind || 'generic'" @change="updateField(index, 'kind', $event.target.value)">
                <option v-for="kind in replicaKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
              </select>
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Link (optional)</label>
              <input type="text" class="dsf-input" :value="element.url" @input="updateField(index, 'url', $event.target.value)" placeholder="#" />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-gallery-items-field__add" type="button" :disabled="localItems.length >= 24" @click="addItem">
      <Plus :size="16" />
      {{ localItems.length >= 24 ? 'Maximum 24 Items' : 'Add Item' }}
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import MediaPicker from './MediaPicker.vue'

const MAX = 24

const replicaKinds = [
  { value: 'hero', label: 'Hero' }, { value: 'bento', label: 'Bento' }, { value: 'spotlight', label: 'Spotlight' },
  { value: 'duo', label: 'Duo' }, { value: 'expander', label: 'Expander' }, { value: 'content', label: 'Content' },
  { value: 'faq', label: 'FAQ' }, { value: 'text-image', label: 'Text + Image' }, { value: 'features', label: 'Features' },
  { value: 'testimonials', label: 'Testimonials' }, { value: 'countdown', label: 'Countdown' }, { value: 'pricing', label: 'Pricing' },
  { value: 'product-grid', label: 'Product Grid' }, { value: 'featured-promo', label: 'Featured Promo' }, { value: 'cta-banner', label: 'CTA Banner' },
  { value: 'form', label: 'Form' }, { value: 'mega-menu', label: 'Mega Menu' }, { value: 'footer', label: 'Footer' }, { value: 'generic', label: 'Generic' },
]

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (newValue) => {
  const previousIds = localItems.value.map((item) => item.id)
  localItems.value = (newValue || []).slice(0, MAX).map((item, index) => ({
    category: 'Blocks',
    title: `Item ${index + 1}`,
    description: '',
    image: '',
    kind: 'generic',
    url: '#',
    ...item,
    id: item.id || previousIds[index] || `gallery-item-${index}-${Date.now()}`,
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
  localItems.value.push({
    id: `gallery-item-${Date.now()}`,
    category: 'Blocks',
    title: `Item ${localItems.value.length + 1}`,
    description: '',
    image: '',
    kind: 'generic',
    url: '#',
  })
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
.dsf-gallery-items-field { display: flex; flex-direction: column; gap: 0.5rem; }
.dsf-gallery-items-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-gallery-items-field__item--ghost { opacity: 0.5; }
.dsf-gallery-items-field__header { display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 0.75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-gallery-items-field__drag, .dsf-gallery-items-field__delete { display: flex; align-items: center; justify-content: center; padding: 0.25rem; border: none; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-gallery-items-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: 0.8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-gallery-items-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-gallery-items-field__chevron { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-gallery-items-field__chevron--open { transform: rotate(180deg); }
.dsf-gallery-items-field__body { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; }
.dsf-gallery-items-field__add { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.dsf-gallery-items-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
