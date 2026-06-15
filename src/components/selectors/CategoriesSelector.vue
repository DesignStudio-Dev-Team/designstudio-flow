<template>
  <div class="dsf-categories-selector">
    <div class="dsf-settings-card">
      <div class="dsf-setting-card__header">
        <span class="dsf-setting-card__title">Select Categories</span>
        <span v-if="selectedCategories.length > 0" class="dsf-badge dsf-badge--primary">
          {{ selectedCategories.length }}
        </span>
      </div>
      <p class="dsf-setting-card__desc">
        Search to add categories. Drag selected chips to control their order.
      </p>

      <div
        ref="dropdownRef"
        class="dsf-category-combobox"
        :class="{ 'dsf-category-combobox--open': isOpen }"
        @click="openDropdown"
      >
        <div class="dsf-category-combobox__control">
          <draggable
            v-if="selectedCategories.length > 0"
            v-model="selectedCategories"
            item-key="id"
            handle=".dsf-category-chip__drag"
            class="dsf-category-combobox__chips"
            @end="updateOrder"
          >
            <template #item="{ element }">
              <span class="dsf-category-chip">
                <GripVertical :size="13" class="dsf-category-chip__drag" />
                <span class="dsf-category-chip__label">{{ element.name }}</span>
                <button
                  type="button"
                  class="dsf-category-chip__remove"
                  :aria-label="`Remove ${element.name}`"
                  @click.stop="removeCategory(element.id)"
                >
                  <X :size="13" />
                </button>
              </span>
            </template>
          </draggable>

          <div class="dsf-category-combobox__search">
            <Search :size="14" class="dsf-category-combobox__search-icon" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              class="dsf-category-combobox__input"
              :placeholder="selectedCategories.length > 0 ? 'Search more categories...' : 'Search categories...'"
              autocomplete="off"
              @focus="openDropdown"
              @keydown.enter.prevent="selectFirstAvailable"
              @keydown.escape.stop="closeDropdown"
              @keydown.backspace="removeLastWhenEmpty"
            />
          </div>

          <ChevronDown :size="16" class="dsf-category-combobox__chevron" />
        </div>

        <div v-if="isOpen" class="dsf-category-combobox__dropdown">
          <button
            v-for="cat in availableCategories"
            :key="cat.id"
            type="button"
            class="dsf-category-combobox__option"
            @mousedown.prevent="addCategory(cat.id)"
          >
            <img
              :src="cat.image || placeholderImage"
              :alt="cat.name"
              class="dsf-category-combobox__option-image"
            />
            <span class="dsf-category-combobox__option-content">
              <span class="dsf-category-combobox__option-name">{{ cat.name }}</span>
              <span class="dsf-category-combobox__option-meta">{{ getCategoryProductCount(cat) }} products</span>
            </span>
            <Plus :size="16" class="dsf-category-combobox__option-plus" />
          </button>

          <div v-if="availableCategories.length === 0" class="dsf-category-combobox__empty">
            {{ searchQuery.trim() ? 'No categories found' : 'All categories are selected' }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ChevronDown, GripVertical, Plus, Search, X } from 'lucide-vue-next'
import draggable from 'vuedraggable'

const props = defineProps({
  value: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update'])

const dropdownRef = ref(null)
const searchInputRef = ref(null)
const searchQuery = ref('')
const isOpen = ref(false)

const allCategories = computed(() => window.dsfEditorData?.categories || [])

const placeholderImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCBmaWxsPSIjZTVlN2ViIiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIvPjwvc3ZnPg=='

const selectedIds = computed(() => (Array.isArray(props.value) ? props.value : []))
const selectedIdKeys = computed(() => new Set(selectedIds.value.map(normalizeId)))

const selectedCategories = computed({
  get: () => selectedIds.value
    .map((id) => allCategories.value.find((category) => normalizeId(category.id) === normalizeId(id)))
    .filter(Boolean),
  set: (newVal) => {
    emit('update', newVal.map((category) => category.id))
  },
})

const availableCategories = computed(() => {
  const query = normalizeSearch(searchQuery.value)

  return allCategories.value.filter((category) => {
    if (selectedIdKeys.value.has(normalizeId(category.id))) return false
    if (!query) return true

    return [
      category.name,
      category.slug,
    ]
      .filter(Boolean)
      .some((value) => normalizeSearch(value).includes(query))
  })
})

function normalizeId(value) {
  return String(value ?? '')
}

function normalizeSearch(value) {
  return String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

function getCategoryProductCount(category) {
  const totalCount = Number.parseInt(category?.total_count, 10)
  if (Number.isFinite(totalCount)) return totalCount

  const directCount = Number.parseInt(category?.count, 10)
  return Number.isFinite(directCount) ? directCount : 0
}

function openDropdown() {
  isOpen.value = true
  nextTick(() => searchInputRef.value?.focus())
}

function closeDropdown() {
  isOpen.value = false
}

function addCategory(catId) {
  const current = selectedIds.value
  const idKey = normalizeId(catId)

  if (!current.some((id) => normalizeId(id) === idKey)) {
    emit('update', [...current, catId])
  }

  searchQuery.value = ''
  isOpen.value = true
  nextTick(() => searchInputRef.value?.focus())
}

function removeCategory(catId) {
  const idKey = normalizeId(catId)
  emit('update', selectedIds.value.filter((id) => normalizeId(id) !== idKey))
}

function removeLastWhenEmpty() {
  if (searchQuery.value !== '' || selectedIds.value.length === 0) return
  emit('update', selectedIds.value.slice(0, -1))
}

function selectFirstAvailable() {
  const firstCategory = availableCategories.value[0]
  if (firstCategory) {
    addCategory(firstCategory.id)
  }
}

function updateOrder() {
  emit('update', selectedCategories.value.map((category) => category.id))
}

function onDocumentClick(event) {
  if (!dropdownRef.value?.contains(event.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentClick)
})
</script>

<style scoped>
.dsf-category-combobox {
  position: relative;
  margin-top: 0.875rem;
}

.dsf-category-combobox__control {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 46px;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.dsf-category-combobox--open .dsf-category-combobox__control {
  border-color: var(--dsf-primary-300);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.dsf-category-combobox__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  min-width: 0;
}

.dsf-category-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 100%;
  padding: 0.25rem 0.375rem;
  border: 1px solid var(--dsf-primary-200, #c7d2fe);
  border-radius: 999px;
  background: var(--dsf-primary-50, #eef2ff);
  color: var(--dsf-primary-700, #3730a3);
  font-size: 0.8125rem;
  font-weight: 600;
}

.dsf-category-chip__drag {
  flex-shrink: 0;
  color: currentColor;
  cursor: grab;
  opacity: 0.55;
}

.dsf-category-chip__drag:active {
  cursor: grabbing;
}

.dsf-category-chip__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-category-chip__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: inherit;
  cursor: pointer;
  opacity: 0.7;
}

.dsf-category-chip__remove:hover {
  background: rgba(0, 0, 0, 0.08);
  opacity: 1;
}

.dsf-category-combobox__search {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 150px;
}

.dsf-category-combobox__search-icon {
  position: absolute;
  left: 0.5rem;
  color: var(--dsf-gray-400);
  pointer-events: none;
}

.dsf-category-combobox__input {
  width: 100%;
  min-height: 32px;
  padding: 0 0.25rem 0 1.8rem;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--dsf-gray-800);
  font-size: 0.875rem;
}

.dsf-category-combobox__input::placeholder {
  color: var(--dsf-gray-400);
}

.dsf-category-combobox__chevron {
  flex-shrink: 0;
  color: var(--dsf-gray-400);
  transition: transform 0.15s;
}

.dsf-category-combobox--open .dsf-category-combobox__chevron {
  transform: rotate(180deg);
}

.dsf-category-combobox__dropdown {
  position: absolute;
  z-index: 80;
  top: calc(100% + 0.375rem);
  left: 0;
  right: 0;
  max-height: 260px;
  overflow-y: auto;
  padding: 0.375rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.14);
}

.dsf-category-combobox__option {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 0.625rem;
  padding: 0.5rem;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}

.dsf-category-combobox__option:hover {
  background: var(--dsf-gray-50);
}

.dsf-category-combobox__option-image {
  flex-shrink: 0;
  width: 30px;
  height: 30px;
  border-radius: 7px;
  object-fit: cover;
  background: var(--dsf-gray-100);
}

.dsf-category-combobox__option-content {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.dsf-category-combobox__option-name {
  overflow: hidden;
  color: var(--dsf-gray-800);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-category-combobox__option-meta {
  color: var(--dsf-gray-500);
  font-size: 0.75rem;
}

.dsf-category-combobox__option-plus {
  flex-shrink: 0;
  color: var(--dsf-primary-500);
}

.dsf-category-combobox__empty {
  padding: 1rem;
  color: var(--dsf-gray-400);
  font-size: 0.875rem;
  text-align: center;
}
</style>
