<template>
  <div class="dsf-mobile-stores-field">
    <div
      v-for="(store, index) in localStores"
      :key="store.__id"
      class="dsf-mobile-stores-field__card"
    >
      <div class="dsf-mobile-stores-field__head">
        <strong>Store {{ index + 1 }}</strong>
        <button class="dsf-mobile-stores-field__danger" @click="removeStore(index)">Remove</button>
      </div>

      <div class="dsf-mobile-stores-field__grid">
        <div class="dsf-form-group">
          <label class="dsf-label">Store Name</label>
          <input
            type="text"
            class="dsf-input"
            :value="store.title"
            @input="updateStore(index, 'title', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Address</label>
          <textarea
            class="dsf-input"
            rows="3"
            :value="store.address"
            @input="updateStore(index, 'address', $event.target.value)"
          ></textarea>
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Maps Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="store.mapsLabel"
            @input="updateStore(index, 'mapsLabel', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Maps URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="store.mapsUrl"
            @input="updateStore(index, 'mapsUrl', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Button Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="store.buttonLabel"
            @input="updateStore(index, 'buttonLabel', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Button URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="store.buttonUrl"
            @input="updateStore(index, 'buttonUrl', $event.target.value)"
          />
        </div>
      </div>
    </div>

    <button class="dsf-mobile-stores-field__add" @click="addStore">Add Store</button>
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

const localStores = ref([])

function makeId() {
  return `store-${Math.random().toString(36).slice(2, 10)}`
}

function createStore() {
  return {
    __id: makeId(),
    title: 'Store Name',
    address: '123 Main Street\nCity, State 12345',
    mapsLabel: 'Open in Google Maps',
    mapsUrl: '#',
    buttonLabel: 'Set as Default',
    buttonUrl: '#',
  }
}

function normalizeStore(store) {
  const fallback = createStore()
  return {
    __id: store?.__id || makeId(),
    title: store?.title || fallback.title,
    address: store?.address || fallback.address,
    mapsLabel: store?.mapsLabel || fallback.mapsLabel,
    mapsUrl: store?.mapsUrl || '#',
    buttonLabel: store?.buttonLabel || fallback.buttonLabel,
    buttonUrl: store?.buttonUrl || '#',
  }
}

watch(
  () => props.modelValue,
  (value) => {
    const source = Array.isArray(value) ? value : []
    localStores.value = (source.length ? source : [createStore(), createStore()]).map(normalizeStore)
  },
  { immediate: true, deep: true }
)

function cleanStores() {
  return localStores.value.map(({ __id, ...store }) => store)
}

function emitUpdate() {
  emit('update:modelValue', cleanStores())
}

function updateStore(index, key, value) {
  localStores.value[index][key] = value
  emitUpdate()
}

function addStore() {
  localStores.value.push(createStore())
  emitUpdate()
}

function removeStore(index) {
  localStores.value.splice(index, 1)
  if (!localStores.value.length) {
    localStores.value.push(createStore())
  }
  emitUpdate()
}
</script>

<style scoped>
.dsf-mobile-stores-field {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-mobile-stores-field__card {
  border: 1px solid var(--dsf-gray-200);
  background: #fff;
  border-radius: var(--dsf-radius-md);
  padding: 0.75rem;
}

.dsf-mobile-stores-field__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.dsf-mobile-stores-field__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.dsf-mobile-stores-field__add,
.dsf-mobile-stores-field__danger {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: #fff;
  color: var(--dsf-gray-700);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.45rem 0.7rem;
  cursor: pointer;
}

.dsf-mobile-stores-field__add:hover {
  background: var(--dsf-gray-100);
}

.dsf-mobile-stores-field__danger {
  color: #b42318;
  border-color: #fecdca;
  background: #fff5f3;
}

.dsf-mobile-stores-field__danger:hover {
  background: #ffe4df;
}

@media (max-width: 900px) {
  .dsf-mobile-stores-field__grid {
    grid-template-columns: 1fr;
  }
}
</style>
