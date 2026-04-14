<template>
  <div class="dsf-tags-field" @click="focusInput">
    <div class="dsf-tags-field__tags">
      <span
        v-for="tag in selectedTags"
        :key="tag"
        class="dsf-tags-field__tag"
      >
        <span class="dsf-tags-field__tag-label">{{ tag }}</span>
        <button
          type="button"
          class="dsf-tags-field__tag-remove"
          @click.stop="removeTag(tag)"
          aria-label="Remove"
        >×</button>
      </span>

      <div class="dsf-tags-field__input-wrap">
        <input
          ref="inputEl"
          v-model="inputValue"
          class="dsf-tags-field__input"
          :placeholder="selectedTags.length === 0 ? 'e.g. brand, color, size…' : ''"
          @keydown.enter.prevent="commitInput"
          @keydown.tab.prevent="commitInput"
          @keydown.backspace="onBackspace"
          @keydown.delete="onBackspace"
          @blur="commitInput"
          autocomplete="off"
          spellcheck="false"
        />
        <ul v-if="suggestions.length > 0" class="dsf-tags-field__suggestions">
          <li
            v-for="s in suggestions"
            :key="s"
            class="dsf-tags-field__suggestion"
            @mousedown.prevent="selectSuggestion(s)"
          >{{ s }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  value: {
    type: Array,
    default: () => [],
  },
  config: Object,
})

const emit = defineEmits(['update'])

const inputEl = ref(null)
const inputValue = ref('')

const selectedTags = computed(() => Array.isArray(props.value) ? props.value : [])

const KNOWN_ATTRIBUTES = [
  'brand', 'color', 'material', 'size', 'weight', 'style', 'finish',
  'gender', 'age_group', 'pattern', 'fabric', 'fit', 'length', 'scent',
  'flavor', 'rating', 'condition', 'origin',
]

const suggestions = computed(() => {
  const term = inputValue.value.trim().toLowerCase()
  if (!term) return []
  return KNOWN_ATTRIBUTES.filter(
    (s) => s.includes(term) && !selectedTags.value.includes(s)
  ).slice(0, 5)
})

function normalizeTag(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '_')
    .replace(/[^a-z0-9_]/g, '')
}

function commitInput() {
  const tag = normalizeTag(inputValue.value)
  if (tag && !selectedTags.value.includes(tag)) {
    emit('update', [...selectedTags.value, tag])
  }
  inputValue.value = ''
}

function selectSuggestion(s) {
  if (!selectedTags.value.includes(s)) {
    emit('update', [...selectedTags.value, s])
  }
  inputValue.value = ''
  inputEl.value?.focus()
}

function removeTag(tag) {
  emit('update', selectedTags.value.filter((t) => t !== tag))
}

function onBackspace() {
  if (inputValue.value === '' && selectedTags.value.length > 0) {
    emit('update', selectedTags.value.slice(0, -1))
  }
}

function focusInput() {
  inputEl.value?.focus()
}
</script>

<style scoped>
.dsf-tags-field {
  display: flex;
  flex-direction: column;
  min-height: 40px;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 10px;
  background: #fff;
  cursor: text;
  gap: 0.375rem;
}

.dsf-tags-field__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  align-items: center;
}

.dsf-tags-field__tag {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  background: var(--dsf-primary-100, #e0e7ff);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--dsf-primary-700, #3730a3);
  white-space: nowrap;
}

.dsf-tags-field__tag-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  height: 14px;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font-size: 0.875rem;
  line-height: 1;
  cursor: pointer;
  opacity: 0.7;
}

.dsf-tags-field__tag-remove:hover {
  opacity: 1;
}

.dsf-tags-field__input-wrap {
  position: relative;
  flex: 1;
  min-width: 120px;
}

.dsf-tags-field__input {
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.875rem;
  color: var(--dsf-gray-800);
  padding: 0.125rem 0;
}

.dsf-tags-field__input::placeholder {
  color: var(--dsf-gray-400);
}

.dsf-tags-field__suggestions {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 100;
  margin: 0;
  padding: 0.25rem 0;
  list-style: none;
  background: #fff;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
}

.dsf-tags-field__suggestion {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  color: var(--dsf-gray-700);
  cursor: pointer;
}

.dsf-tags-field__suggestion:hover {
  background: var(--dsf-gray-50);
  color: var(--dsf-gray-900);
}
</style>
