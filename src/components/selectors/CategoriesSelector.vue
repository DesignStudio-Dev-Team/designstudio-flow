<template>
  <div class="dsf-categories-selector">
    <!-- Selected Categories (Draggable) -->
    <div v-if="selectedCategories.length > 0" class="dsf-settings-card dsf-mb-4">
      <div class="dsf-setting-card__header">
        <span class="dsf-setting-card__title">Selected Categories</span>
        <span class="dsf-badge dsf-badge--primary">{{ selectedCategories.length }}</span>
      </div>
      <p class="dsf-setting-card__desc">Drag to reorder your categories</p>
      
      <div class="dsf-list-container dsf-mt-3">
        <draggable 
          v-model="selectedCategories" 
          item-key="id"
          handle=".dsf-drag-handle"
          @end="updateOrder"
          class="dsf-drag-list"
        >
          <template #item="{ element }">
             <div class="dsf-list-item dsf-selected-item">
              <div class="dsf-drag-handle-wrapper">
                 <GripVertical :size="16" class="dsf-drag-handle" />
              </div>
              <img 
                :src="element.image || placeholderImage" 
                :alt="element.name"
                class="dsf-list-item__image"
              />
              <div class="dsf-list-item__content">
                <div class="dsf-list-item__title">{{ element.name }}</div>
              </div>
              
              <div class="dsf-list-item__actions">
                <button 
                  class="dsf-text-btn dsf-text-remove"
                  @click.stop="toggleCategory(element.id)"
                  title="Remove category"
                >
                  <X :size="16" />
                </button>
              </div>
            </div>
          </template>
        </draggable>
      </div>
    </div>

    <!-- Available Categories -->
    <div class="dsf-settings-card">
      <div class="dsf-setting-card__header">
        <span class="dsf-setting-card__title">Available Categories</span>
      </div>
      
      <div class="dsf-list-container dsf-mt-3 dsf-max-h-60 dsf-overflow-y-auto">
        <div 
          v-for="cat in availableCategories" 
          :key="cat.id"
          class="dsf-list-item dsf-cursor-pointer"
          @click="toggleCategory(cat.id)"
        >
          <img 
            :src="cat.image || placeholderImage" 
            :alt="cat.name"
            class="dsf-list-item__image"
          />
          <div class="dsf-list-item__content">
            <div class="dsf-list-item__title">{{ cat.name }}</div>
            <div style="font-size: var(--dsf-text-xs); color: var(--dsf-gray-500);">
              {{ cat.count }} products
            </div>
          </div>
          <div class="dsf-list-item__action">
            <Plus :size="18" class="dsf-text-primary-500" />
          </div>
        </div>
        
        <div v-if="availableCategories.length === 0" class="dsf-empty-state">
          No more categories available
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Check, Plus, GripVertical, X } from 'lucide-vue-next'
import draggable from 'vuedraggable'

const props = defineProps({
  value: {
    type: Array,
    default: () => []
  },
})

const emit = defineEmits(['update'])

const allCategories = computed(() => window.dsfEditorData?.categories || [])

const placeholderImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCBmaWxsPSIjZTVlN2ViIiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIvPjwvc3ZnPg=='

// Helper to get full objects for selected IDs in order
// We use a ref for local state to handle drag updates smoothly
const selectedCategories = computed({
  get: () => {
    const ids = props.value || []
    return ids
      .map(id => allCategories.value.find(c => c.id === id))
      .filter(Boolean)
  },
  set: (newVal) => {
    emit('update', newVal.map(c => c.id))
  }
})

const availableCategories = computed(() => {
  const selectedIds = props.value || []
  return allCategories.value.filter(c => !selectedIds.includes(c.id))
})

function toggleCategory(catId) {
  const current = props.value || []
  if (current.includes(catId)) {
    emit('update', current.filter(id => id !== catId))
  } else {
    emit('update', [...current, catId])
  }
}

function updateOrder() {
  emit('update', selectedCategories.value.map(c => c.id))
}
</script>

<style scoped>
.dsf-list-container {
  margin-top: 0.75rem;
}

/* Ensure no list styles if draggable uses ul/li */
:deep(.dsf-drag-list) {
  list-style: none;
  padding: 0;
  margin: 0;
}

.dsf-list-item {
  display: flex;
  align-items: center;
  padding: 0.5rem; /* Compact padding */
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-sm); /* Smaller radius */
  margin-bottom: 0.5rem;
  transition: all 0.15s;
  height: 56px; /* Fixed height for consistency */
}

.dsf-list-item:hover {
  border-color: var(--dsf-primary-300);
}

.dsf-selected-item {
  background: white; /* Clean white bg */
  border-color: var(--dsf-gray-300);
}

.dsf-list-item__image {
  width: 32px;
  height: 32px; /* Smaller image */
  border-radius: 4px;
  object-fit: cover;
  margin-right: 0.75rem;
  background: var(--dsf-gray-100);
  flex-shrink: 0;
}

.dsf-list-item__content {
  flex: 1;
  min-width: 0; /* Truncate text properly */
}

.dsf-list-item__title {
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--dsf-gray-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dsf-text-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dsf-text-remove {
  color: var(--dsf-gray-400);
}

.dsf-text-remove:hover {
  color: #DC2626;
  background-color: #FEE2E2;
}

.dsf-drag-handle-wrapper {
  color: var(--dsf-gray-300);
  margin-right: 0.5rem;
  cursor: grab;
  display: flex;
  align-items: center;
  padding: 4px;
}

.dsf-drag-handle-wrapper:hover {
  color: var(--dsf-gray-500);
}

.dsf-drag-handle-wrapper:active {
  cursor: grabbing;
}

.dsf-text-primary-500 {
  color: var(--dsf-primary-500);
}

.dsf-empty-state {
  text-align: center;
  color: var(--dsf-gray-400);
  font-size: 0.875rem;
  padding: 1rem;
}
</style>
