<template>
  <div class="dsf-product-tags-field">
    <div class="dsf-product-tags-field__box" @click="focusInput">
      <span
        v-for="tag in selectedTags"
        :key="tag"
        class="dsf-product-tags-field__chip"
      >
        <span class="dsf-product-tags-field__chip-label">{{ tag }}</span>
        <button
          type="button"
          class="dsf-product-tags-field__chip-remove"
          :aria-label="`Remove ${tag}`"
          @click.stop="removeTag(tag)"
        >×</button>
      </span>

      <div class="dsf-product-tags-field__input-wrap">
        <input
          ref="inputEl"
          v-model="inputValue"
          class="dsf-product-tags-field__input"
          type="text"
          :placeholder="selectedTags.length === 0 ? 'Type a product tag…' : 'Add tag…'"
          autocomplete="off"
          spellcheck="false"
          @keydown.enter.prevent="commitInput"
          @keydown.tab.prevent="commitInput"
          @keydown.backspace="onBackspace"
          @blur="commitInput"
        />
        <ul v-if="suggestions.length > 0" class="dsf-product-tags-field__suggestions">
          <li
            v-for="tag in suggestions"
            :key="tag.name"
            class="dsf-product-tags-field__suggestion"
            @mousedown.prevent="selectTag(tag.name)"
          >
            <span>{{ tag.name }}</span>
            <span class="dsf-product-tags-field__suggestion-count">{{ tag.count }} products</span>
          </li>
        </ul>
      </div>
    </div>

    <p v-if="allProductTags.length === 0" class="dsf-product-tags-field__empty">
      No WooCommerce product tags were found yet. You can still type a tag name manually.
    </p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  value: {
    type: Array,
    default: null,
  },
  config: Object,
})

const emit = defineEmits(['update'])

const inputEl = ref(null)
const inputValue = ref('')

const allProductTags = computed(() => {
  const tags = window.dsfEditorData?.productTags || []
  if (!Array.isArray(tags)) return []

  return tags
    .map((tag) => ({
      name: String(tag?.name || '').trim(),
      slug: String(tag?.slug || '').trim(),
      count: Number.parseInt(tag?.count, 10) || 0,
    }))
    .filter((tag) => tag.name)
})

const allProductTagNames = computed(() => allProductTags.value.map((tag) => tag.name))

const selectedTags = computed(() => {
  if (Array.isArray(props.value)) {
    return uniqueTags(props.value)
  }

  return allProductTagNames.value
})

const suggestions = computed(() => {
  const term = normalizeForSearch(inputValue.value)
  if (!term) return []

  const selected = new Set(selectedTags.value.map(normalizeForSearch))
  return allProductTags.value
    .filter((tag) => {
      if (selected.has(normalizeForSearch(tag.name))) return false
      return normalizeForSearch(`${tag.name} ${tag.slug}`).includes(term)
    })
    .slice(0, 8)
})

function uniqueTags(values) {
  const seen = new Set()
  const result = []

  values.forEach((value) => {
    const tag = String(value || '').trim()
    const key = normalizeForSearch(tag)
    if (!tag || seen.has(key)) return
    seen.add(key)
    result.push(tag)
  })

  return result
}

function normalizeForSearch(value) {
  return String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

function getCanonicalTagName(value) {
  const term = normalizeForSearch(value)
  if (!term) return ''

  const knownTag = allProductTags.value.find((tag) =>
    normalizeForSearch(tag.name) === term || normalizeForSearch(tag.slug) === term
  )

  return knownTag?.name || String(value || '').trim()
}

function updateTags(nextTags) {
  emit('update', uniqueTags(nextTags))
}

function commitInput() {
  const tag = getCanonicalTagName(inputValue.value)
  if (tag) {
    selectTag(tag)
  }
  inputValue.value = ''
}

function selectTag(tag) {
  const key = normalizeForSearch(tag)
  const selected = selectedTags.value.map(normalizeForSearch)
  if (key && !selected.includes(key)) {
    updateTags([...selectedTags.value, tag])
  }
  inputValue.value = ''
  inputEl.value?.focus()
}

function removeTag(tag) {
  const key = normalizeForSearch(tag)
  updateTags(selectedTags.value.filter((value) => normalizeForSearch(value) !== key))
}

function onBackspace() {
  if (inputValue.value === '' && selectedTags.value.length > 0) {
    updateTags(selectedTags.value.slice(0, -1))
  }
}

function focusInput() {
  inputEl.value?.focus()
}
</script>

<style scoped>
.dsf-product-tags-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-product-tags-field__box {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  min-height: 44px;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 10px;
  background: #fff;
  cursor: text;
}

.dsf-product-tags-field__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 100%;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: var(--dsf-primary-100, #e0e7ff);
  color: var(--dsf-primary-700, #3730a3);
  font-size: 0.8125rem;
  font-weight: 600;
}

.dsf-product-tags-field__chip-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-product-tags-field__chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: inherit;
  font-size: 0.875rem;
  line-height: 1;
  cursor: pointer;
  opacity: 0.7;
}

.dsf-product-tags-field__chip-remove:hover {
  opacity: 1;
  background: rgba(0, 0, 0, 0.08);
}

.dsf-product-tags-field__input-wrap {
  position: relative;
  flex: 1;
  min-width: 120px;
}

.dsf-product-tags-field__input {
  width: 100%;
  padding: 0.2rem 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--dsf-gray-800);
  font-size: 0.875rem;
}

.dsf-product-tags-field__input::placeholder {
  color: var(--dsf-gray-400);
}

.dsf-product-tags-field__suggestions {
  position: absolute;
  z-index: 100;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  margin: 0;
  padding: 0.25rem 0;
  list-style: none;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.dsf-product-tags-field__suggestion {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.45rem 0.75rem;
  color: var(--dsf-gray-700);
  font-size: 0.8125rem;
  cursor: pointer;
}

.dsf-product-tags-field__suggestion:hover {
  background: var(--dsf-gray-50);
  color: var(--dsf-gray-900);
}

.dsf-product-tags-field__suggestion-count {
  flex-shrink: 0;
  color: var(--dsf-gray-400);
  font-size: 0.75rem;
}

.dsf-product-tags-field__empty {
  margin: 0;
  color: var(--dsf-gray-500);
  font-size: 0.8125rem;
  line-height: 1.4;
}
</style>
