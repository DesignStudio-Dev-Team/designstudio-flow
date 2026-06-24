<template>
  <div class="dsf-faq-items-field">
    <draggable
      v-model="localItems"
      item-key="id"
      handle=".dsf-faq-items-field__drag"
      ghost-class="dsf-faq-items-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-faq-items-field__item">
          <div class="dsf-faq-items-field__header" @click="toggleItem(index)">
            <button class="dsf-faq-items-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-faq-items-field__title">{{ element.question || `Question ${index + 1}` }}</span>
            <div class="dsf-faq-items-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-faq-items-field__chevron"
                :class="{ 'dsf-faq-items-field__chevron--open': openItems.includes(index) }"
              />
              <button
                class="dsf-faq-items-field__delete"
                type="button"
                title="Remove question"
                @click.stop="removeItem(index)"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-faq-items-field__body">
            <div class="dsf-form-group">
              <label class="dsf-label">Question</label>
              <input
                type="text"
                class="dsf-input"
                :value="element.question"
                @input="updateField(index, 'question', $event.target.value)"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label">Answer</label>
              <WysiwygField
                :modelValue="element.answer"
                :allow-raw-html="true"
                @update:modelValue="updateField(index, 'answer', $event)"
              />
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button class="dsf-faq-items-field__add" type="button" @click="addItem">
      <Plus :size="16" />
      Add FAQ
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import WysiwygField from './WysiwygField.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (newValue) => {
  const previousIds = localItems.value.map((item) => item.id)
  localItems.value = (newValue || []).map((item, index) => ({
    question: '',
    answer: '',
    ...item,
    id: item.id || previousIds[index] || `faq-${index}-${Date.now()}`,
  }))
}, { immediate: true, deep: true })

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
    id: `faq-${Date.now()}`,
    question: 'New question',
    answer: '<p>Answer goes here.</p>',
  })
  openItems.value.push(localItems.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  openItems.value = openItems.value
    .filter((itemIndex) => itemIndex !== index)
    .map((itemIndex) => itemIndex > index ? itemIndex - 1 : itemIndex)
  emitUpdate()
}
</script>

<style scoped>
.dsf-faq-items-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-faq-items-field__item {
  overflow: hidden;
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
}

.dsf-faq-items-field__item--ghost {
  opacity: 0.5;
}

.dsf-faq-items-field__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-gray-50);
  cursor: pointer;
}

.dsf-faq-items-field__drag,
.dsf-faq-items-field__delete {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border: none;
  background: transparent;
  color: var(--dsf-gray-400);
  cursor: pointer;
}

.dsf-faq-items-field__title {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: var(--dsf-gray-800);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-faq-items-field__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dsf-faq-items-field__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.15s ease;
}

.dsf-faq-items-field__chevron--open {
  transform: rotate(180deg);
}

.dsf-faq-items-field__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
}

.dsf-faq-items-field__add {
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
