<template>
  <div class="dsf-simple-links">
    <div
      v-for="(item, index) in localItems"
      :key="item.__id"
      class="dsf-simple-links__item"
    >
      <div class="dsf-simple-links__row">
        <div class="dsf-form-group">
          <label class="dsf-label">Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="item.label"
            @input="updateItem(index, 'label', $event.target.value)"
          />
        </div>
        <div class="dsf-form-group">
          <label class="dsf-label">URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="item.url"
            @input="updateItem(index, 'url', $event.target.value)"
          />
        </div>
      </div>

      <button class="dsf-simple-links__remove" @click="removeItem(index)">Remove</button>
    </div>

    <button class="dsf-simple-links__add" @click="addItem">Add Link</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

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
    localItems.value = source.map((item, index) => ({
      __id: item.__id || `simple-link-${index}-${Date.now()}`,
      label: item?.label || '',
      url: item?.url || '#',
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
    __id: `simple-link-${Date.now()}`,
    label: 'New Link',
    url: '#',
  })
  emitUpdate()
}

function removeItem(index) {
  localItems.value.splice(index, 1)
  emitUpdate()
}
</script>

<style scoped>
.dsf-simple-links {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-simple-links__item {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: white;
  padding: 0.75rem;
}

.dsf-simple-links__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.dsf-simple-links__remove,
.dsf-simple-links__add {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: var(--dsf-gray-50);
  color: var(--dsf-gray-700);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
}

.dsf-simple-links__remove:hover,
.dsf-simple-links__add:hover {
  background: var(--dsf-gray-100);
}

.dsf-simple-links__remove {
  margin-top: 0.25rem;
}
</style>
