<template>
  <div class="dsf-brand-showcase-cards-field">
    <div v-for="(item, index) in localItems" :key="index" class="dsf-brand-showcase-cards-field__item">
      <div class="dsf-brand-showcase-cards-field__header"><strong>Card {{ index + 1 }}</strong><button type="button" class="dsf-icon-btn" :aria-label="`Remove card ${index + 1}`" @click="removeItem(index)">×</button></div>
      <label class="dsf-label">Title</label><input class="dsf-input" type="text" maxlength="100" :value="item.title" @input="updateField(index, 'title', $event.target.value)">
      <label class="dsf-label">Subtitle</label><input class="dsf-input" type="text" maxlength="160" :value="item.subtitle" @input="updateField(index, 'subtitle', $event.target.value)">
      <label class="dsf-label">Image</label><MediaPicker :modelValue="item.image" @update:modelValue="updateField(index, 'image', $event)" />
      <label class="dsf-label">Card Background</label><ColorPicker :modelValue="item.backgroundColor" @update:modelValue="updateField(index, 'backgroundColor', $event)" />
      <label class="dsf-label">Text Color</label><ColorPicker :modelValue="item.textColor" @update:modelValue="updateField(index, 'textColor', $event)" />
      <label class="dsf-label">Optional Link</label><input class="dsf-input" type="url" maxlength="2048" placeholder="https://example.com" :value="item.url" @input="updateField(index, 'url', $event.target.value)">
    </div>
    <button v-if="localItems.length < MAX" type="button" class="dsf-btn dsf-btn--secondary dsf-w-full" @click="addItem">+ Add Card</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import MediaPicker from './MediaPicker.vue'
import ColorPicker from './ColorPicker.vue'

const MAX = 8
const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const localItems = ref([])
const normalize = (item = {}) => ({ title: typeof item.title === 'string' ? item.title : '', subtitle: typeof item.subtitle === 'string' ? item.subtitle : '', image: typeof item.image === 'string' ? item.image : '', backgroundColor: typeof item.backgroundColor === 'string' ? item.backgroundColor : '#F3F4F6', textColor: typeof item.textColor === 'string' ? item.textColor : '#111111', url: typeof item.url === 'string' ? item.url : '' })
const emitUpdate = () => emit('update:modelValue', localItems.value.map(normalize))
function updateField(index, key, value) { localItems.value[index][key] = value; emitUpdate() }
function addItem() { if (localItems.value.length < MAX) { localItems.value.push(normalize()); emitUpdate() } }
function removeItem(index) { localItems.value.splice(index, 1); emitUpdate() }
watch(() => props.modelValue, (value) => { localItems.value = (Array.isArray(value) ? value : []).slice(0, MAX).map(normalize) }, { immediate: true, deep: true })
</script>

<style scoped>
.dsf-brand-showcase-cards-field { display: flex; flex-direction: column; gap: .75rem; }
.dsf-brand-showcase-cards-field__item { display: flex; flex-direction: column; gap: .5rem; padding: .75rem; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); background: var(--dsf-gray-50); }
.dsf-brand-showcase-cards-field__header { display: flex; align-items: center; justify-content: space-between; }
.dsf-icon-btn { border: 0; background: transparent; color: var(--dsf-gray-500); cursor: pointer; font-size: 1.25rem; }
</style>
