<template>
  <div class="dsf-image-logo-grid-field">
    <div v-for="(item, index) in localItems" :key="index" class="dsf-image-logo-grid-field__item">
      <div class="dsf-image-logo-grid-field__header">
        <strong>Card {{ index + 1 }}</strong>
        <button type="button" class="dsf-icon-btn" title="Remove card" @click="removeItem(index)">×</button>
      </div>
      <label class="dsf-label">Main Image</label>
      <MediaPicker :modelValue="item.image" @update:modelValue="updateField(index, 'image', $event)" />
      <label class="dsf-label">Logo</label>
      <MediaPicker :modelValue="item.logo" @update:modelValue="updateField(index, 'logo', $event)" />
      <label class="dsf-label">Optional Link</label>
      <input class="dsf-input" type="url" maxlength="2048" placeholder="https://example.com" :value="item.url" @input="updateField(index, 'url', $event.target.value)" />
    </div>
    <button v-if="localItems.length < MAX" type="button" class="dsf-btn dsf-btn--secondary dsf-w-full" @click="addItem">+ Add Card</button>
    <p v-else class="dsf-helper-text">Maximum of {{ MAX }} cards. Cards 1–4 display on the first row and cards 5–8 on the second row.</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import MediaPicker from './MediaPicker.vue'

const MAX = 8
const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const localItems = ref([])

function normalize(item) {
  return { image: typeof item?.image === 'string' ? item.image : '', logo: typeof item?.logo === 'string' ? item.logo : '', url: typeof item?.url === 'string' ? item.url : '' }
}
function emitUpdate() { emit('update:modelValue', localItems.value.map(normalize)) }
function updateField(index, key, value) { localItems.value[index][key] = value; emitUpdate() }
function addItem() { if (localItems.value.length >= MAX) return; localItems.value.push(normalize({})); emitUpdate() }
function removeItem(index) { localItems.value.splice(index, 1); emitUpdate() }
watch(() => props.modelValue, (value) => { localItems.value = (Array.isArray(value) ? value : []).slice(0, MAX).map(normalize) }, { immediate: true, deep: true })
</script>

<style scoped>
.dsf-image-logo-grid-field { display: flex; flex-direction: column; gap: 0.75rem; }
.dsf-image-logo-grid-field__item { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); background: var(--dsf-gray-50); }
.dsf-image-logo-grid-field__header { display: flex; align-items: center; justify-content: space-between; }
.dsf-icon-btn { border: 0; background: transparent; color: var(--dsf-gray-500); cursor: pointer; font-size: 1.25rem; }
</style>
