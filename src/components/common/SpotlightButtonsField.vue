<template>
  <div class="dsf-spotlight-buttons-field">
    <draggable
      v-model="localItems"
      item-key="__id"
      handle=".dsf-sbf__drag"
      ghost-class="dsf-sbf__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-sbf__item" :class="{ 'dsf-sbf__item--off': element.enabled === false }">
          <button class="dsf-sbf__drag" type="button" @click.stop>
            <GripVertical :size="14" />
          </button>

          <div class="dsf-sbf__fields">
            <div class="dsf-sbf__row">
              <input
                type="text"
                class="dsf-input"
                placeholder="Button label"
                :value="element.text"
                @input="updateItem(index, 'text', $event.target.value)"
              />
              <input
                type="text"
                class="dsf-input"
                placeholder="https://..."
                :value="element.url"
                @input="updateItem(index, 'url', $event.target.value)"
              />
            </div>
          </div>

          <label class="dsf-sbf__toggle" :title="element.enabled === false ? 'Hidden' : 'Visible'">
            <input
              type="checkbox"
              :checked="element.enabled !== false"
              @change="updateItem(index, 'enabled', $event.target.checked)"
            />
            <span class="dsf-sbf__toggle-track"><span class="dsf-sbf__toggle-thumb"></span></span>
          </label>

          <button class="dsf-sbf__remove" type="button" title="Remove button" @click="removeItem(index)">
            <Trash2 :size="14" />
          </button>
        </div>
      </template>
    </draggable>

    <button class="dsf-sbf__add" type="button" @click="addItem">
      <Plus :size="16" />
      Add Button
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { Plus, Trash2, GripVertical } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])

watch(
  () => props.modelValue,
  (value) => {
    const source = Array.isArray(value) ? value : []
    const previousIds = localItems.value.map((item) => item.__id)
    localItems.value = source.map((item, index) => ({
      __id: item.__id || previousIds[index] || `sbf-${index}-${Date.now()}`,
      text: item?.text || '',
      url: item?.url || '#',
      enabled: item?.enabled !== false,
    }))
  },
  { immediate: true, deep: true }
)

function emitUpdate() {
  emit(
    'update:modelValue',
    localItems.value.map(({ __id, ...item }) => item)
  )
}

function updateItem(index, key, value) {
  localItems.value[index][key] = value
  emitUpdate()
}

function addItem() {
  localItems.value.push({
    __id: `sbf-${Date.now()}`,
    text: 'New Button',
    url: '#',
    enabled: true,
  })
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  emitUpdate()
}
</script>

<style scoped>
.dsf-spotlight-buttons-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-sbf__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: white;
  padding: 0.5rem;
}

.dsf-sbf__item--off {
  opacity: 0.55;
}

.dsf-sbf__item--ghost {
  opacity: 0.5;
}

.dsf-sbf__drag {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--dsf-gray-400);
  cursor: grab;
  background: none;
  border: none;
  padding: 0;
  flex-shrink: 0;
}

.dsf-sbf__drag:active {
  cursor: grabbing;
}

.dsf-sbf__fields {
  flex: 1;
  min-width: 0;
}

.dsf-sbf__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.375rem;
}

.dsf-sbf__toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  cursor: pointer;
}

.dsf-sbf__toggle input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.dsf-sbf__toggle-track {
  width: 30px;
  height: 18px;
  border-radius: 999px;
  background: var(--dsf-gray-300);
  transition: background 0.15s;
  display: inline-flex;
  align-items: center;
  padding: 2px;
}

.dsf-sbf__toggle input:checked + .dsf-sbf__toggle-track {
  background: var(--dsf-primary-500);
}

.dsf-sbf__toggle-thumb {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: white;
  transition: transform 0.15s;
}

.dsf-sbf__toggle input:checked + .dsf-sbf__toggle-track .dsf-sbf__toggle-thumb {
  transform: translateX(12px);
}

.dsf-sbf__remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: none;
  border: none;
  border-radius: var(--dsf-radius-sm);
  color: var(--dsf-gray-400);
  cursor: pointer;
  transition: all 0.15s;
  flex-shrink: 0;
}

.dsf-sbf__remove:hover {
  background: var(--dsf-danger-50, #fef2f2);
  color: var(--dsf-danger-500, #ef4444);
}

.dsf-sbf__add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.5rem;
  background: var(--dsf-gray-50);
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  color: var(--dsf-gray-600);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.dsf-sbf__add:hover {
  background: var(--dsf-primary-50);
  border-color: var(--dsf-primary-300);
  color: var(--dsf-primary-600);
}
</style>
