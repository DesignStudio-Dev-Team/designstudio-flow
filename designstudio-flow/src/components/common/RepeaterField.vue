<template>
  <div class="dsf-repeater">
    <!-- Items -->
    <draggable 
      v-model="localItems"
      item-key="id"
      handle=".dsf-repeater-item__drag"
      ghost-class="dsf-repeater-item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-repeater-item">
          <!-- Item Header -->
          <div class="dsf-repeater-item__header" @click="toggleItem(index)">
            <button class="dsf-repeater-item__drag" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-repeater-item__title">{{ element.title || `Item ${index + 1}` }}</span>
            <div class="dsf-repeater-item__actions">
              <ChevronDown 
                :size="16" 
                class="dsf-repeater-item__chevron"
                :class="{ 'dsf-repeater-item__chevron--open': openItems.includes(index) }"
              />
              <button 
                class="dsf-repeater-item__delete" 
                @click.stop="removeItem(index)"
                title="Remove item"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
          
          <!-- Item Fields (collapsible) -->
          <div v-show="openItems.includes(index)" class="dsf-repeater-item__body">
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
              <label class="dsf-label">Description</label>
              <textarea 
                class="dsf-input"
                rows="2"
                :value="element.description"
                @input="updateField(index, 'description', $event.target.value)"
              ></textarea>
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Button Text</label>
              <input 
                type="text" 
                class="dsf-input"
                placeholder="Learn More"
                :value="element.buttonText"
                @input="updateField(index, 'buttonText', $event.target.value)"
              />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Button URL</label>
              <input 
                type="text" 
                class="dsf-input"
                placeholder="https://..."
                :value="element.buttonUrl"
                @input="updateField(index, 'buttonUrl', $event.target.value)"
              />
            </div>
          </div>
        </div>
      </template>
    </draggable>
    
    <!-- Add Button -->
    <button class="dsf-repeater__add" @click="addItem">
      <Plus :size="16" />
      Add Item
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { Plus, Trash2, GripVertical, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  itemFields: {
    type: Array,
    default: () => ['title', 'description']
  }
})

const emit = defineEmits(['update:modelValue'])

// Local copy of items with unique IDs
const localItems = ref([])
const openItems = ref([0]) // First item open by default

// Initialize local items
watch(() => props.modelValue, (newVal) => {
  localItems.value = (newVal || []).map((item, idx) => ({
    ...item,
    id: item.id || `item-${idx}-${Date.now()}`
  }))
}, { immediate: true, deep: true })

function emitUpdate() {
  // Emit without the internal id field
  const cleanItems = localItems.value.map(({ id, ...rest }) => rest)
  emit('update:modelValue', cleanItems)
}

function toggleItem(index) {
  const idx = openItems.value.indexOf(index)
  if (idx > -1) {
    openItems.value.splice(idx, 1)
  } else {
    openItems.value.push(index)
  }
}

function addItem() {
  const newItem = {
    id: `item-${Date.now()}`,
    title: 'New Feature',
    description: 'Description here',
    buttonText: 'Learn More',
    buttonUrl: '#'
  }
  localItems.value.push(newItem)
  openItems.value.push(localItems.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  // Update open items indices
  openItems.value = openItems.value
    .filter(i => i !== index)
    .map(i => i > index ? i - 1 : i)
  emitUpdate()
}

function updateField(index, field, value) {
  localItems.value[index][field] = value
  emitUpdate()
}
</script>

<style scoped>
.dsf-repeater {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-repeater-item {
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  overflow: hidden;
}

.dsf-repeater-item--ghost {
  opacity: 0.5;
}

.dsf-repeater-item__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-gray-50);
  cursor: pointer;
  transition: background 0.15s;
}

.dsf-repeater-item__header:hover {
  background: var(--dsf-gray-100);
}

.dsf-repeater-item__drag {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
  cursor: grab;
  background: none;
  border: none;
  padding: 0;
}

.dsf-repeater-item__drag:active {
  cursor: grabbing;
}

.dsf-repeater-item__title {
  flex: 1;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--dsf-gray-700);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dsf-repeater-item__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.dsf-repeater-item__chevron {
  color: var(--dsf-gray-400);
  transition: transform 0.2s;
}

.dsf-repeater-item__chevron--open {
  transform: rotate(180deg);
}

.dsf-repeater-item__delete {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: none;
  border: none;
  border-radius: var(--dsf-radius-sm);
  color: var(--dsf-gray-400);
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-repeater-item__delete:hover {
  background: var(--dsf-danger-50);
  color: var(--dsf-danger-500);
}

.dsf-repeater-item__body {
  padding: 0.75rem;
  border-top: 1px solid var(--dsf-gray-100);
}

.dsf-repeater-item__body .dsf-form-group {
  margin-bottom: 0.75rem;
}

.dsf-repeater-item__body .dsf-form-group:last-child {
  margin-bottom: 0;
}

.dsf-repeater__add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem;
  background: var(--dsf-gray-50);
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  color: var(--dsf-gray-600);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-repeater__add:hover {
  background: var(--dsf-primary-50);
  border-color: var(--dsf-primary-300);
  color: var(--dsf-primary-600);
}
</style>
