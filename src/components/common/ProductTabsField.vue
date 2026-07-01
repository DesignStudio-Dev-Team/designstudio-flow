<template>
  <div class="dsf-product-tabs-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-product-tabs-field__drag"
      ghost-class="dsf-product-tabs-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-product-tabs-field__item">
          <div class="dsf-product-tabs-field__header" @click="toggleItem(index)">
            <button class="dsf-product-tabs-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-product-tabs-field__title">{{ element.label || `Tab ${index + 1}` }}</span>
            <div class="dsf-product-tabs-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-product-tabs-field__chevron"
                :class="{ 'dsf-product-tabs-field__chevron--open': openItems.includes(index) }"
              />
              <button
                class="dsf-product-tabs-field__delete"
                type="button"
                title="Remove tab"
                @click.stop="removeItem(index)"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-product-tabs-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Tab Label</label>
              <input
                type="text"
                class="dsf-input"
                :value="element.label"
                @input="updateField(index, 'label', $event.target.value)"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">Content Source</label>
              <select class="dsf-input" :value="element.source" @change="updateField(index, 'source', $event.target.value)">
                <option v-for="opt in SOURCES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

            <div v-if="element.source === 'custom'" class="dsf-form-group">
              <label class="dsf-label">Custom Content</label>
              <WysiwygField
                :modelValue="element.content"
                :allow-raw-html="false"
                @update:modelValue="updateField(index, 'content', $event)"
              />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-product-tabs-field__add" type="button" @click="addItem">
      <Plus :size="16" />
      Add Tab
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import WysiwygField from './WysiwygField.vue'

const SOURCES = [
  { value: 'description', label: 'Product Description' },
  { value: 'specs', label: 'Specifications' },
  { value: 'reviews', label: 'Reviews' },
  { value: 'custom', label: 'Custom Content' },
]
const ALLOWED = SOURCES.map((s) => s.value)

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

watch(
  () => props.modelValue,
  (newValue) => {
    const previousIds = localItems.value.map((item) => item.id)
    localItems.value = (newValue || []).map((item, index) => ({
      label: '',
      source: 'description',
      content: '',
      ...item,
      source: ALLOWED.includes(item.source) ? item.source : 'description',
      id: item.id || previousIds[index] || `tab-${index}-${Date.now()}`,
    }))
  },
  { immediate: true, deep: true }
)

function emitUpdate() {
  emit('update:modelValue', localItems.value.map(({ id, ...item }) => item))
}

function toggleItem(index) {
  const currentIndex = openItems.value.indexOf(index)
  if (currentIndex >= 0) {
    openItems.value.splice(currentIndex, 1)
  } else {
    openItems.value.push(index)
  }
}

function updateField(index, key, value) {
  localItems.value[index][key] = value
  emitUpdate()
}

function addItem() {
  localItems.value.push({
    id: `tab-${Date.now()}`,
    label: 'New Tab',
    source: 'custom',
    content: '<p>Tab content goes here.</p>',
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
.dsf-product-tabs-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-product-tabs-field__item {
  overflow: hidden;
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
}

.dsf-product-tabs-field__item--ghost {
  opacity: 0.5;
}

.dsf-product-tabs-field__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-gray-50);
  cursor: pointer;
}

.dsf-product-tabs-field__drag,
.dsf-product-tabs-field__delete {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border: none;
  background: transparent;
  color: var(--dsf-gray-400);
  cursor: pointer;
}

.dsf-product-tabs-field__title {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: var(--dsf-gray-800);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-product-tabs-field__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dsf-product-tabs-field__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.15s ease;
}

.dsf-product-tabs-field__chevron--open {
  transform: rotate(180deg);
}

.dsf-product-tabs-field__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
}

.dsf-product-tabs-field__add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem;
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  background: white;
  color: var(--dsf-primary-600);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}
</style>
