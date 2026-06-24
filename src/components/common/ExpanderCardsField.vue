<template>
  <div class="dsf-expander-cards-field">
    <draggable
      v-model="localCards"
      item-key="id"
      handle=".dsf-expander-cards-field__drag"
      ghost-class="dsf-expander-cards-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-expander-cards-field__item">
          <div class="dsf-expander-cards-field__header" @click="toggleItem(index)">
            <button class="dsf-expander-cards-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-expander-cards-field__title">{{ element.title || `Card ${index + 1}` }}</span>
            <div class="dsf-expander-cards-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-expander-cards-field__chevron"
                :class="{ 'dsf-expander-cards-field__chevron--open': openItems.includes(index) }"
              />
              <button
                class="dsf-expander-cards-field__delete"
                type="button"
                title="Remove card"
                @click.stop="removeItem(index)"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-expander-cards-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Image</label>
              <MediaPicker
                :modelValue="element.image"
                @update:modelValue="updateField(index, 'image', $event)"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">Title</label>
              <input
                type="text"
                class="dsf-input"
                :value="element.title"
                @input="updateField(index, 'title', $event.target.value)"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">URL</label>
              <input
                type="text"
                class="dsf-input"
                :value="element.url"
                @input="updateField(index, 'url', $event.target.value)"
              />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button
      class="dsf-expander-cards-field__add"
      type="button"
      :disabled="localCards.length >= 6"
      @click="addItem"
    >
      <Plus :size="16" />
      {{ localCards.length >= 6 ? 'Maximum 6 Cards' : 'Add Card' }}
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import MediaPicker from './MediaPicker.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localCards = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (newValue) => {
  const previousIds = localCards.value.map((item) => item.id)
  localCards.value = (newValue || []).slice(0, 6).map((item, index) => ({
    title: `Card ${index + 1}`,
    image: '',
    url: '#',
    ...item,
    id: item.id || previousIds[index] || `expander-card-${index}-${Date.now()}`,
  }))
}, { immediate: true, deep: true })

function emitUpdate() {
  emit('update:modelValue', localCards.value.map(({ id, ...item }) => item))
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
  localCards.value[index][key] = value
  emitUpdate()
}

function addItem() {
  if (localCards.value.length >= 6) return
  const nextIndex = localCards.value.length + 1
  localCards.value.push({
    id: `expander-card-${Date.now()}`,
    title: `Card ${nextIndex}`,
    image: '',
    url: '#',
  })
  openItems.value.push(localCards.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localCards.value.splice(index, 1)
  openItems.value = openItems.value
    .filter((itemIndex) => itemIndex !== index)
    .map((itemIndex) => itemIndex > index ? itemIndex - 1 : itemIndex)
  emitUpdate()
}
</script>

<style scoped>
.dsf-expander-cards-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-expander-cards-field__item {
  overflow: hidden;
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
}

.dsf-expander-cards-field__item--ghost {
  opacity: 0.5;
}

.dsf-expander-cards-field__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-gray-50);
  cursor: pointer;
}

.dsf-expander-cards-field__drag,
.dsf-expander-cards-field__delete {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border: none;
  background: transparent;
  color: var(--dsf-gray-400);
  cursor: pointer;
}

.dsf-expander-cards-field__title {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: var(--dsf-gray-800);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-expander-cards-field__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dsf-expander-cards-field__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.15s ease;
}

.dsf-expander-cards-field__chevron--open {
  transform: rotate(180deg);
}

.dsf-expander-cards-field__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
}

.dsf-expander-cards-field__add {
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

.dsf-expander-cards-field__add:disabled {
  color: var(--dsf-gray-400);
  cursor: not-allowed;
}
</style>
