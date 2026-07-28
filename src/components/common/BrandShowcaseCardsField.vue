<template>
  <div class="dsf-brand-showcase-cards-field">
    <draggable v-model="localItems" item-key="id" handle=".dsf-brand-showcase-cards-field__drag" ghost-class="dsf-brand-showcase-cards-field__item--ghost" @end="emitUpdate">
      <template #item="{ element, index }">
        <div class="dsf-brand-showcase-cards-field__item">
          <div class="dsf-brand-showcase-cards-field__header" @click="toggleItem(index)">
            <button class="dsf-brand-showcase-cards-field__drag" type="button" aria-label="Move card" @click.stop><GripVertical :size="14" /></button>
            <span class="dsf-brand-showcase-cards-field__title">{{ element.title || `Card ${index + 1}` }}</span>
            <div class="dsf-brand-showcase-cards-field__actions"><ChevronDown :size="16" :class="{ 'is-open': openItems.includes(index) }" /><button type="button" class="dsf-brand-showcase-cards-field__delete" :aria-label="`Remove card ${index + 1}`" @click.stop="removeItem(index)"><Trash2 :size="14" /></button></div>
          </div>
          <div v-show="openItems.includes(index)" class="dsf-brand-showcase-cards-field__body">
            <div class="dsf-form-group"><label class="dsf-label">Title</label><input class="dsf-input" type="text" maxlength="100" :value="element.title" @input="updateField(index, 'title', $event.target.value)"></div>
            <div class="dsf-form-group"><label class="dsf-label">Subtitle</label><input class="dsf-input" type="text" maxlength="160" :value="element.subtitle" @input="updateField(index, 'subtitle', $event.target.value)"></div>
            <div class="dsf-form-group"><label class="dsf-label">Image</label><MediaPicker :modelValue="element.image" @update:modelValue="updateField(index, 'image', $event)" /></div>
            <div class="dsf-form-group"><label class="dsf-label">Card Background</label><ColorPicker :modelValue="element.backgroundColor" @update:modelValue="updateField(index, 'backgroundColor', $event)" /></div>
            <div class="dsf-form-group"><label class="dsf-label">Text Color</label><ColorPicker :modelValue="element.textColor" @update:modelValue="updateField(index, 'textColor', $event)" /></div>
            <div class="dsf-form-group"><label class="dsf-label">Optional Link</label><input class="dsf-input" type="url" maxlength="2048" placeholder="https://example.com" :value="element.url" @input="updateField(index, 'url', $event.target.value)"></div>
          </div>
        </div>
      </template>
    </draggable>
    <button v-if="localItems.length < MAX" type="button" class="dsf-brand-showcase-cards-field__add" @click="addItem"><Plus :size="16" /> Add Card</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import MediaPicker from './MediaPicker.vue'
import ColorPicker from './ColorPicker.vue'

const MAX = 8
const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const localItems = ref([])
const openItems = ref([0])
const normalize = (item = {}, index = 0) => ({ title: typeof item.title === 'string' ? item.title : '', subtitle: typeof item.subtitle === 'string' ? item.subtitle : '', image: typeof item.image === 'string' ? item.image : '', backgroundColor: typeof item.backgroundColor === 'string' ? item.backgroundColor : '#F3F4F6', textColor: typeof item.textColor === 'string' ? item.textColor : '#111111', url: typeof item.url === 'string' ? item.url : '', id: item.id || `brand-showcase-card-${index}-${Date.now()}` })
const emitUpdate = () => emit('update:modelValue', localItems.value.map(({ id, ...item }) => item))
function toggleItem(index) { const position = openItems.value.indexOf(index); if (position >= 0) openItems.value.splice(position, 1); else openItems.value.push(index) }
function updateField(index, key, value) { localItems.value[index][key] = value; emitUpdate() }
function addItem() { if (localItems.value.length < MAX) { localItems.value.push(normalize({}, localItems.value.length)); openItems.value.push(localItems.value.length - 1); emitUpdate() } }
function removeItem(index) { localItems.value.splice(index, 1); openItems.value = openItems.value.filter((item) => item !== index).map((item) => item > index ? item - 1 : item); emitUpdate() }
watch(() => props.modelValue, (value) => { const priorIds = localItems.value.map((item) => item.id); localItems.value = (Array.isArray(value) ? value : []).slice(0, MAX).map((item, index) => normalize({ ...item, id: item.id || priorIds[index] }, index)) }, { immediate: true, deep: true })
</script>

<style scoped>
.dsf-brand-showcase-cards-field { display: flex; flex-direction: column; gap: .5rem; }
.dsf-brand-showcase-cards-field__item { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }
.dsf-brand-showcase-cards-field__item--ghost { opacity: .5; }
.dsf-brand-showcase-cards-field__header { display: flex; align-items: center; gap: .5rem; padding: .625rem .75rem; background: var(--dsf-gray-50); cursor: pointer; }
.dsf-brand-showcase-cards-field__drag, .dsf-brand-showcase-cards-field__delete { display: flex; align-items: center; justify-content: center; padding: .25rem; border: 0; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }
.dsf-brand-showcase-cards-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: .8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dsf-brand-showcase-cards-field__actions { display: flex; align-items: center; gap: .25rem; }.dsf-brand-showcase-cards-field__actions > svg { color: var(--dsf-gray-400); transition: transform .15s ease; }.dsf-brand-showcase-cards-field__actions > svg.is-open { transform: rotate(180deg); }
.dsf-brand-showcase-cards-field__body { display: flex; flex-direction: column; gap: .75rem; padding: .75rem; }
.dsf-brand-showcase-cards-field__add { display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; padding: .75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: .875rem; font-weight: 600; cursor: pointer; }
</style>
