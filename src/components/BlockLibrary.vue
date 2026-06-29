<template>
  <div class="dsf-library-overlay" @click.self="$emit('close')">
    <div class="dsf-library-panel">
      <!-- Header -->
      <div class="dsf-library-header">
        <div class="dsf-library-header__title">
          <h2>Block Library</h2>
          <p>Choose a block to add to your page</p>
        </div>
        <button class="dsf-library-close" @click="$emit('close')">
          <X :size="18" />
        </button>
      </div>
      
      <!-- Search -->
      <div class="dsf-library-search">
        <Search :size="16" class="dsf-library-search__icon" />
        <input 
          type="text" 
          v-model="searchQuery"
          placeholder="Search blocks..."
          class="dsf-library-search__input"
        />
      </div>
      
      <!-- Categories -->
      <div class="dsf-library-content">
        <!-- Templates (saved groups of blocks) -->
        <div v-if="filteredTemplates.length" class="dsf-library-category">
          <button class="dsf-library-category__header" @click="templatesOpen = !templatesOpen">
            <div class="dsf-library-category__left">
              <LayoutTemplate :size="16" />
              <span>Templates</span>
              <span class="dsf-library-category__count">{{ filteredTemplates.length }}</span>
            </div>
            <ChevronDown
              :size="16"
              class="dsf-library-category__chevron"
              :class="{ 'dsf-library-category__chevron--open': templatesOpen }"
            />
          </button>
          <div class="dsf-library-blocks" v-show="templatesOpen">
            <div v-for="tpl in filteredTemplates" :key="tpl.id" class="dsf-library-block dsf-library-block--saved">
              <button class="dsf-library-block__main" @click="$emit('insert-template', tpl)">
                <div class="dsf-library-block__preview">
                  <BlockSchematic :type="templatePreviewType(tpl)" icon="layout-template" />
                </div>
                <div class="dsf-library-block__info">
                  <LayoutTemplate :size="16" />
                  <div class="dsf-library-block__text">
                    <h4>{{ tpl.name }}</h4>
                    <span>{{ tpl.blockCount }} block{{ tpl.blockCount === 1 ? '' : 's' }}</span>
                  </div>
                </div>
              </button>
              <button
                class="dsf-library-block__delete"
                title="Delete template"
                aria-label="Delete template"
                @click.stop="$emit('delete-template', tpl)"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Presets (curated starter library) -->
        <div v-if="filteredPresets.length" class="dsf-library-category">
          <button class="dsf-library-category__header" @click="presetsOpen = !presetsOpen">
            <div class="dsf-library-category__left">
              <Sparkles :size="16" />
              <span>Presets</span>
              <span class="dsf-library-category__count">{{ filteredPresets.length }}</span>
            </div>
            <ChevronDown
              :size="16"
              class="dsf-library-category__chevron"
              :class="{ 'dsf-library-category__chevron--open': presetsOpen }"
            />
          </button>
          <div class="dsf-library-blocks" v-show="presetsOpen">
            <button
              v-for="preset in filteredPresets"
              :key="preset.key"
              class="dsf-library-block"
              @click="$emit('insert-preset', preset)"
            >
              <div class="dsf-library-block__preview">
                <BlockSchematic :type="preset.type" :icon="preset.icon" />
              </div>
              <div class="dsf-library-block__info">
                <component :is="getBlockIcon(preset.icon)" :size="16" />
                <div class="dsf-library-block__text">
                  <h4>{{ preset.name }}</h4>
                  <span>Click to add</span>
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Saved Blocks (reusable library) -->
        <div v-if="filteredSavedBlocks.length" class="dsf-library-category">
          <button class="dsf-library-category__header" @click="savedOpen = !savedOpen">
            <div class="dsf-library-category__left">
              <Bookmark :size="16" />
              <span>Saved Blocks</span>
              <span class="dsf-library-category__count">{{ filteredSavedBlocks.length }}</span>
            </div>
            <ChevronDown
              :size="16"
              class="dsf-library-category__chevron"
              :class="{ 'dsf-library-category__chevron--open': savedOpen }"
            />
          </button>
          <div class="dsf-library-blocks" v-show="savedOpen">
            <template v-for="group in groupedSavedBlocks" :key="group.folder || '__ungrouped'">
              <div v-if="group.folder" class="dsf-library-folder">{{ group.folder }}</div>
              <div v-for="saved in group.items" :key="saved.id" class="dsf-library-block dsf-library-block--saved">
                <button class="dsf-library-block__main" @click="$emit('insert-saved', saved)">
                  <div class="dsf-library-block__preview">
                    <BlockSchematic :type="saved.type" :icon="savedIcon(saved)" />
                  </div>
                  <div class="dsf-library-block__info">
                    <component :is="getBlockIcon(savedIcon(saved))" :size="16" />
                    <div class="dsf-library-block__text">
                      <h4>{{ saved.name }}</h4>
                      <span>{{ savedTypeLabel(saved) }}<template v-if="saved.author"> · {{ saved.author }}</template></span>
                    </div>
                  </div>
                </button>
                <button
                  class="dsf-library-block__star"
                  :class="{ 'is-featured': saved.featured }"
                  :title="saved.featured ? 'Remove from Presets' : 'Add to Presets'"
                  :aria-label="saved.featured ? 'Remove from Presets' : 'Add to Presets'"
                  @click.stop="$emit('toggle-feature', saved)"
                >
                  <Star :size="14" :fill="saved.featured ? 'currentColor' : 'none'" />
                </button>
                <button
                  class="dsf-library-block__delete"
                  title="Delete saved block"
                  aria-label="Delete saved block"
                  @click.stop="$emit('delete-saved', saved)"
                >
                  <Trash2 :size="14" />
                </button>
              </div>
            </template>
          </div>
        </div>

        <div
          v-for="(cat, key) in filteredCategories"
          :key="key"
          class="dsf-library-category"
        >
          <!-- Category Header (collapsible) -->
          <button 
            class="dsf-library-category__header"
            @click="toggleCategory(key)"
          >
            <div class="dsf-library-category__left">
              <component :is="getCategoryIcon(key)" :size="16" />
              <span>{{ cat.label }}</span>
              <span class="dsf-library-category__count">{{ cat.blocks.length }}</span>
            </div>
            <ChevronDown 
              :size="16" 
              class="dsf-library-category__chevron"
              :class="{ 'dsf-library-category__chevron--open': openCategories.includes(key) }"
            />
          </button>
          
          <!-- Blocks List -->
          <div 
            class="dsf-library-blocks"
            v-show="openCategories.includes(key)"
          >
            <button 
              v-for="block in cat.blocks"
              :key="block.id"
              class="dsf-library-block"
              @click="$emit('add', block)"
            >
              <!-- Block Preview Schematic -->
              <div class="dsf-library-block__preview">
                <BlockSchematic 
                  :type="block.id" 
                  :icon="block.icon"
                />
              </div>
              
              <!-- Block Info -->
              <div class="dsf-library-block__info">
                <component :is="getBlockIcon(block.icon)" :size="16" />
                <div class="dsf-library-block__text">
                  <h4>{{ block.name }}</h4>
                  <span>Click to add</span>
                </div>
              </div>
            </button>
          </div>
        </div>
        
        <!-- No Results -->
        <div v-if="noResults" class="dsf-library-empty">
          <SearchX :size="32" />
          <p>No blocks found for "{{ searchQuery }}"</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { 
  X, 
  Search,
  SearchX,
  ChevronDown,
  LayoutTemplate, 
  FileText, 
  ShoppingCart, 
  Target,
  Columns,
  Grid3x3,
  Layout,
  MessageCircle,
  ShoppingBag,
  Folder,
  Award,
  Megaphone,
  Mail,
  Image,
  ListChecks,
  Clock,
  BadgeDollarSign,
  Bookmark,
  Trash2,
  Sparkles,
  Star
} from 'lucide-vue-next'
import BlockSchematic from './common/BlockSchematic.vue'

const props = defineProps({
  categories: Object,
  savedBlocks: {
    type: Array,
    default: () => [],
  },
  presets: {
    type: Array,
    default: () => [],
  },
  templates: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['close', 'add', 'insert-saved', 'delete-saved', 'toggle-feature', 'insert-preset', 'insert-template', 'delete-template'])

const searchQuery = ref('')
const openCategories = ref([])
const savedOpen = ref(true)
const presetsOpen = ref(true)
const templatesOpen = ref(true)

const icons = {
  'layout-template': LayoutTemplate,
  'file-text': FileText,
  'shopping-cart': ShoppingCart,
  'target': Target,
  'columns': Columns,
  'grid-3x3': Grid3x3,
  'layout': Layout,
  'message-circle': MessageCircle,
  'shopping-bag': ShoppingBag,
  'folder': Folder,
  'award': Award,
  'megaphone': Megaphone,
  'mail': Mail,
  'image': Image,
  'list-checks': ListChecks,
  'clock': Clock,
  'badge-dollar-sign': BadgeDollarSign,
  'layout-columns': Columns,
  'bookmark': Bookmark,
}

const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) {
    return props.categories
  }
  
  const query = searchQuery.value.toLowerCase()
  const result = {}
  
  for (const [key, cat] of Object.entries(props.categories)) {
    const filteredBlocks = cat.blocks.filter(block => 
      block.name.toLowerCase().includes(query) ||
      block.description.toLowerCase().includes(query)
    )
    
    if (filteredBlocks.length > 0) {
      result[key] = { ...cat, blocks: filteredBlocks }
    }
  }
  
  return result
})

// Flatten every registered block definition into a lookup by id, so a saved
// block can show its source type's name and icon.
const blockDefById = computed(() => {
  const map = {}
  for (const cat of Object.values(props.categories || {})) {
    for (const block of cat.blocks || []) {
      map[block.id] = block
    }
  }
  return map
})

const filteredSavedBlocks = computed(() => {
  const list = Array.isArray(props.savedBlocks) ? props.savedBlocks : []
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return list
  return list.filter((saved) => (saved.name || '').toLowerCase().includes(query))
})

const filteredPresets = computed(() => {
  const list = Array.isArray(props.presets) ? props.presets : []
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return list
  return list.filter((preset) => (preset.name || '').toLowerCase().includes(query))
})

// Group saved blocks by folder; named folders first (A→Z), ungrouped last.
const groupedSavedBlocks = computed(() => {
  const groups = {}
  filteredSavedBlocks.value.forEach((block) => {
    const key = block.category || ''
    if (!groups[key]) groups[key] = []
    groups[key].push(block)
  })
  const named = Object.keys(groups).filter((k) => k).sort((a, b) => a.localeCompare(b))
  const result = named.map((folder) => ({ folder, items: groups[folder] }))
  if (groups['']) result.push({ folder: '', items: groups[''] })
  return result
})

const filteredTemplates = computed(() => {
  const list = Array.isArray(props.templates) ? props.templates : []
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return list
  return list.filter((tpl) => (tpl.name || '').toLowerCase().includes(query))
})

function templatePreviewType(tpl) {
  return (Array.isArray(tpl.blocks) && tpl.blocks[0]?.type) || 'content'
}

function savedIcon(saved) {
  return blockDefById.value[saved.type]?.icon || 'bookmark'
}

function savedTypeLabel(saved) {
  return blockDefById.value[saved.type]?.name || 'Reusable block'
}

const noResults = computed(() => {
  return (
    searchQuery.value.trim() &&
    Object.keys(filteredCategories.value).length === 0 &&
    filteredSavedBlocks.value.length === 0 &&
    filteredPresets.value.length === 0 &&
    filteredTemplates.value.length === 0
  )
})

function toggleCategory(key) {
  const idx = openCategories.value.indexOf(key)
  if (idx >= 0) {
    openCategories.value.splice(idx, 1)
  } else {
    openCategories.value.push(key)
  }
}

function getCategoryIcon(key) {
  const categoryIcons = {
    heroes: Layout,
    content: FileText,
    ecommerce: ShoppingCart,
    marketing: Target,
    footers: LayoutTemplate,
  }
  return categoryIcons[key] || LayoutTemplate
}

function getBlockIcon(iconName) {
  return icons[iconName] || LayoutTemplate
}
</script>

<style scoped>
.dsf-library-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 1000;
}

.dsf-library-panel {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 380px; /* Increased from 280px */
  background: white;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
}

.dsf-animate-slide-in {
  animation: slideInLeft 0.25s ease-out;
}

@keyframes slideInLeft {
  from {
    transform: translateX(-100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.dsf-library-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 1.25rem 1rem;
  border-bottom: 1px solid var(--dsf-gray-100);
}

.dsf-library-header__title h2 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--dsf-gray-900);
  margin: 0 0 0.25rem 0;
}

.dsf-library-header__title p {
  font-size: 0.75rem;
  color: var(--dsf-gray-500);
  margin: 0;
}

.dsf-library-close {
  padding: 0.375rem;
  background: transparent;
  border: none;
  border-radius: var(--dsf-radius-md);
  color: var(--dsf-gray-500);
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-library-close:hover {
  background: var(--dsf-gray-100);
  color: var(--dsf-gray-700);
}

.dsf-library-search {
  position: relative;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--dsf-gray-100);
}

.dsf-library-search__icon {
  position: absolute;
  left: 1.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--dsf-gray-400);
}

.dsf-library-search__input {
  width: 100%;
  padding: 0.625rem 0.75rem 0.625rem 2.25rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  font-size: 0.8125rem;
  background: var(--dsf-gray-50);
  transition: all 0.15s;
}

.dsf-library-search__input:focus {
  outline: none;
  border-color: var(--dsf-primary-500);
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.dsf-library-search__input::placeholder {
  color: var(--dsf-gray-400);
}

.dsf-library-content {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem 0;
}

.dsf-library-category {
  margin-bottom: 0.25rem;
}

.dsf-library-category__header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}

.dsf-library-category__header:hover {
  background: var(--dsf-gray-50);
}

.dsf-library-category__left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--dsf-gray-800);
}

.dsf-library-category__count {
  padding: 0.125rem 0.5rem;
  background: var(--dsf-gray-100);
  border-radius: 10px;
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--dsf-gray-600);
}

.dsf-library-category__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.2s;
}

.dsf-library-category__chevron--open {
  transform: rotate(180deg);
}

.dsf-library-blocks {
  padding: 0.25rem 0.75rem 0.75rem;
}

.dsf-library-block {
  width: 100%;
  display: flex;
  flex-direction: column;
  margin-bottom: 0.75rem;
  background: white;
  border: 2px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-lg);
  cursor: pointer;
  overflow: hidden;
  transition: all 0.2s;
}

.dsf-library-block:last-child {
  margin-bottom: 0;
}

.dsf-library-block:hover {
  border-color: var(--dsf-primary-500);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.dsf-library-block__preview {
  aspect-ratio: 16/9;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: var(--dsf-radius-md) var(--dsf-radius-md) 0 0;
}

.dsf-library-block__info {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.75rem;
  border-top: 1px solid var(--dsf-gray-100);
  color: var(--dsf-gray-500);
}

.dsf-library-block__text {
  flex: 1;
  text-align: left;
}

.dsf-library-block__text h4 {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--dsf-gray-800);
  margin: 0 0 0.125rem 0;
}

.dsf-library-block__text span {
  font-size: 0.6875rem;
  color: var(--dsf-gray-500);
}

.dsf-library-block:hover .dsf-library-block__text span {
  color: var(--dsf-primary-500);
}

/* Folder divider within the Saved Blocks list. */
.dsf-library-folder {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--dsf-gray-500);
  margin: 0.5rem 0 0.375rem;
  padding-left: 0.125rem;
}

/* Saved block cards: same card as a regular block, plus a delete overlay. */
.dsf-library-block--saved {
  position: relative;
  padding: 0;
}

.dsf-library-block__main {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
}

.dsf-library-block__delete {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  color: var(--dsf-gray-500);
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-library-block__delete:hover {
  border-color: #ef4444;
  color: #ef4444;
  background: #fef2f2;
}

.dsf-library-block__star {
  position: absolute;
  top: 8px;
  right: 44px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  color: var(--dsf-gray-500);
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-library-block__star:hover {
  border-color: #d97706;
  color: #d97706;
  background: #fffbeb;
}

.dsf-library-block__star.is-featured {
  color: #d97706;
  border-color: #fcd34d;
  background: #fffbeb;
}

.dsf-library-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: var(--dsf-gray-400);
  text-align: center;
}

.dsf-library-empty p {
  margin-top: 0.75rem;
  font-size: 0.8125rem;
  color: var(--dsf-gray-500);
}
</style>
