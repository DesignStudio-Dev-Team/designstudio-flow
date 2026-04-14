<template>
  <div class="dsf-category-selector">
    <div class="dsf-setting-card">
      <div class="dsf-setting-card__title">Select Category</div>
      <p class="dsf-setting-card__desc">Products from this category will be displayed automatically</p>

      <div class="dsf-cat-select" ref="containerRef" :class="{ 'dsf-cat-select--open': isOpen }">
        <!-- Trigger -->
        <button
          type="button"
          class="dsf-cat-select__trigger"
          @click="toggleOpen"
        >
          <span class="dsf-cat-select__trigger-text">
            {{ selectedCategory ? selectedCategory.name : 'Select a category...' }}
          </span>
          <span v-if="selectedCategory" class="dsf-cat-select__trigger-count">
            {{ selectedCategory.total_count ?? selectedCategory.count }}
          </span>
          <ChevronDown :size="14" class="dsf-cat-select__chevron" />
        </button>

        <!-- Dropdown -->
        <div v-if="isOpen" class="dsf-cat-select__dropdown">
          <!-- Search -->
          <div class="dsf-cat-select__search-wrap">
            <Search :size="13" class="dsf-cat-select__search-icon" />
            <input
              ref="searchRef"
              v-model="query"
              type="text"
              placeholder="Search categories..."
              class="dsf-cat-select__search"
              @keydown.escape="close"
              @keydown.down.prevent="moveHighlight(1)"
              @keydown.up.prevent="moveHighlight(-1)"
              @keydown.enter.prevent="selectHighlighted"
            />
          </div>

          <!-- List -->
          <ul class="dsf-cat-select__list">
            <li
              class="dsf-cat-select__item dsf-cat-select__item--none"
              :class="{ 'dsf-cat-select__item--highlighted': highlightIndex === 0 }"
              @mouseenter="highlightIndex = 0"
              @click="selectItem(null)"
            >
              <span>All categories</span>
            </li>
            <li
              v-for="(cat, idx) in filteredCategories"
              :key="cat.id"
              class="dsf-cat-select__item"
              :class="{
                'dsf-cat-select__item--selected': cat.id === value,
                'dsf-cat-select__item--highlighted': highlightIndex === idx + 1,
                [`dsf-cat-select__item--depth-${Math.min(cat.depth, 3)}`]: true,
              }"
              @mouseenter="highlightIndex = idx + 1"
              @click="selectItem(cat)"
            >
              <span class="dsf-cat-select__item-name">{{ cat.name }}</span>
              <span class="dsf-cat-select__item-count">{{ cat.total_count ?? cat.count }}</span>
            </li>
            <li v-if="filteredCategories.length === 0" class="dsf-cat-select__empty">
              No categories found
            </li>
          </ul>
        </div>
      </div>

      <div v-if="selectedCategory" class="dsf-mt-3 dsf-flex dsf-items-center dsf-gap-2">
        <Check :size="16" style="color: var(--dsf-success-500);" />
        <span style="color: var(--dsf-gray-600); font-size: var(--dsf-text-sm);">
          Showing products from: <strong>{{ selectedCategory.name }}</strong>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Check, ChevronDown, Search } from 'lucide-vue-next'

const props = defineProps({
  value: Number,
})

const emit = defineEmits(['update'])

const isOpen = ref(false)
const query = ref('')
const highlightIndex = ref(-1)
const containerRef = ref(null)
const searchRef = ref(null)

// Raw flat category list from PHP
const rawCategories = computed(() => window.dsfEditorData?.categories || [])

// Build sorted flat list with depth calculated from parent chain
const categoriesWithDepth = computed(() => {
  const cats = rawCategories.value
  const idMap = {}
  cats.forEach(c => { idMap[c.id] = c })

  function getDepth(cat) {
    let depth = 0
    let current = cat
    while (current.parent && current.parent !== 0 && idMap[current.parent]) {
      depth++
      current = idMap[current.parent]
      if (depth > 10) break // safety
    }
    return depth
  }

  // Sort: parents before children, siblings alphabetically
  function buildSorted(parentId, acc) {
    const children = cats
      .filter(c => (c.parent || 0) === parentId)
      .sort((a, b) => a.name.localeCompare(b.name))
    for (const c of children) {
      acc.push({ ...c, depth: getDepth(c) })
      buildSorted(c.id, acc)
    }
    return acc
  }

  return buildSorted(0, [])
})

// Filtered by search query
const filteredCategories = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return categoriesWithDepth.value
  return rawCategories.value
    .filter(c => c.name.toLowerCase().includes(q))
    .map(c => ({ ...c, depth: 0 }))
})

const selectedCategory = computed(() => {
  if (!props.value) return null
  return rawCategories.value.find(c => c.id === props.value) || null
})

function toggleOpen() {
  if (isOpen.value) {
    close()
  } else {
    isOpen.value = true
    query.value = ''
    highlightIndex.value = -1
    nextTick(() => searchRef.value?.focus())
  }
}

function close() {
  isOpen.value = false
  query.value = ''
}

function selectItem(cat) {
  emit('update', cat ? cat.id : 0)
  close()
}

function moveHighlight(dir) {
  const max = filteredCategories.value.length // +1 for "none" item at index 0
  highlightIndex.value = Math.max(0, Math.min(max, highlightIndex.value + dir))
}

function selectHighlighted() {
  if (highlightIndex.value === 0) {
    selectItem(null)
  } else if (highlightIndex.value > 0) {
    selectItem(filteredCategories.value[highlightIndex.value - 1])
  }
}

// Reset highlight when filter changes
watch(query, () => { highlightIndex.value = -1 })

// Close on outside click
function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    close()
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<style scoped>
.dsf-cat-select {
  position: relative;
  margin-top: 0.75rem;
}

.dsf-cat-select__trigger {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  width: 100%;
  padding: 0.4375rem 0.625rem;
  background: var(--dsf-white, #fff);
  border: 1px solid var(--dsf-gray-300, #d1d5db);
  border-radius: var(--dsf-radius-md, 6px);
  font-size: var(--dsf-text-sm, 0.8125rem);
  color: var(--dsf-gray-700, #374151);
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s;
}

.dsf-cat-select--open .dsf-cat-select__trigger,
.dsf-cat-select__trigger:hover {
  border-color: var(--dsf-primary, #2c5f5d);
}

.dsf-cat-select__trigger-text {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-cat-select__trigger-count {
  font-size: 0.6875rem;
  color: var(--dsf-gray-500, #6b7280);
  background: var(--dsf-gray-100, #f3f4f6);
  border-radius: 999px;
  padding: 0 0.375rem;
  line-height: 1.5;
  flex-shrink: 0;
}

.dsf-cat-select__chevron {
  color: var(--dsf-gray-400, #9ca3af);
  flex-shrink: 0;
  transition: transform 0.15s;
}

.dsf-cat-select--open .dsf-cat-select__chevron {
  transform: rotate(180deg);
}

.dsf-cat-select__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--dsf-white, #fff);
  border: 1px solid var(--dsf-gray-200, #e5e7eb);
  border-radius: var(--dsf-radius-md, 6px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  z-index: 200;
  overflow: hidden;
}

.dsf-cat-select__search-wrap {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.625rem;
  border-bottom: 1px solid var(--dsf-gray-100, #f3f4f6);
}

.dsf-cat-select__search-icon {
  color: var(--dsf-gray-400, #9ca3af);
  flex-shrink: 0;
}

.dsf-cat-select__search {
  flex: 1;
  border: none;
  outline: none;
  font-size: var(--dsf-text-sm, 0.8125rem);
  color: var(--dsf-gray-700, #374151);
  background: transparent;
}

.dsf-cat-select__search::placeholder {
  color: var(--dsf-gray-400, #9ca3af);
}

.dsf-cat-select__list {
  list-style: none;
  margin: 0;
  padding: 0.25rem 0;
  max-height: 220px;
  overflow-y: auto;
}

.dsf-cat-select__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.375rem 0.625rem;
  font-size: var(--dsf-text-sm, 0.8125rem);
  color: var(--dsf-gray-700, #374151);
  cursor: pointer;
  transition: background 0.1s;
}

.dsf-cat-select__item--none {
  color: var(--dsf-gray-500, #6b7280);
  font-style: italic;
}

.dsf-cat-select__item--highlighted {
  background: var(--dsf-gray-50, #f9fafb);
}

.dsf-cat-select__item--selected {
  background: color-mix(in srgb, var(--dsf-primary, #2c5f5d) 8%, transparent);
  color: var(--dsf-primary, #2c5f5d);
  font-weight: 500;
}

.dsf-cat-select__item--depth-1 { padding-left: 1.25rem; }
.dsf-cat-select__item--depth-2 { padding-left: 2rem; }
.dsf-cat-select__item--depth-3 { padding-left: 2.75rem; }

.dsf-cat-select__item-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-cat-select__item-count {
  font-size: 0.6875rem;
  color: var(--dsf-gray-400, #9ca3af);
  flex-shrink: 0;
}

.dsf-cat-select__item--selected .dsf-cat-select__item-count {
  color: color-mix(in srgb, var(--dsf-primary, #2c5f5d) 70%, transparent);
}

.dsf-cat-select__empty {
  padding: 0.625rem;
  font-size: var(--dsf-text-sm, 0.8125rem);
  color: var(--dsf-gray-400, #9ca3af);
  text-align: center;
}
</style>
