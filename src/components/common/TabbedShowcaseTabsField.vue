<template>
  <div class="dsf-tabbed-showcase-field">
    <draggable v-model="localTabs" item-key="id" handle=".dsf-tabbed-showcase-field__drag" ghost-class="dsf-tabbed-showcase-field__tab--ghost" @end="emitUpdate">
      <template #item="{ element: tab, index: tabIndex }">
        <div class="dsf-tabbed-showcase-field__tab">
          <div class="dsf-tabbed-showcase-field__tab-header" @click="toggleTab(tabIndex)">
            <button class="dsf-tabbed-showcase-field__drag" type="button" aria-label="Move tab" @click.stop><GripVertical :size="14" /></button>
            <span class="dsf-tabbed-showcase-field__title">{{ tab.label || `Tab ${tabIndex + 1}` }}</span>
            <div class="dsf-tabbed-showcase-field__actions"><ChevronDown :size="16" :class="{ 'is-open': openTabs.includes(tabIndex) }" /><button v-if="localTabs.length > 1" type="button" class="dsf-tabbed-showcase-field__delete" title="Remove tab" @click.stop="removeTab(tabIndex)"><Trash2 :size="14" /></button></div>
          </div>
          <div v-show="openTabs.includes(tabIndex)" class="dsf-tabbed-showcase-field__body">
            <div class="dsf-form-group"><label class="dsf-label">Tab Label</label><input class="dsf-input" type="text" maxlength="80" :value="tab.label" @input="updateTab(tabIndex, 'label', $event.target.value)" /></div>
            <div class="dsf-form-group"><label class="dsf-label">Content Type</label><select class="dsf-input" :value="tab.source" @change="updateTab(tabIndex, 'source', $event.target.value)"><option value="products">Products</option><option value="images">Images</option></select></div>
            <ProductsSelector v-if="tab.source === 'products'" :value="tab.productIds" :config="productConfig" @update="updateTab(tabIndex, 'productIds', $event)" />
            <div v-else class="dsf-tabbed-showcase-field__images">
              <draggable v-model="tab.images" item-key="id" handle=".dsf-tabbed-showcase-field__image-drag" ghost-class="dsf-tabbed-showcase-field__image--ghost" @end="emitUpdate">
                <template #item="{ element: image, index: imageIndex }"><div class="dsf-tabbed-showcase-field__image"><div class="dsf-tabbed-showcase-field__image-header" @click="toggleImage(tabIndex, imageIndex)"><button class="dsf-tabbed-showcase-field__image-drag" type="button" aria-label="Move image card" @click.stop><GripVertical :size="14" /></button><span class="dsf-tabbed-showcase-field__title">{{ image.title || `Image ${imageIndex + 1}` }}</span><div class="dsf-tabbed-showcase-field__actions"><ChevronDown :size="16" :class="{ 'is-open': isImageOpen(tabIndex, imageIndex) }" /><button type="button" class="dsf-tabbed-showcase-field__delete" title="Remove image" @click.stop="removeImage(tabIndex, imageIndex)"><Trash2 :size="14" /></button></div></div><div v-show="isImageOpen(tabIndex, imageIndex)" class="dsf-tabbed-showcase-field__image-body"><div class="dsf-form-group"><label class="dsf-label">Image</label><MediaPicker :modelValue="image.image" @update:modelValue="updateImage(tabIndex, imageIndex, 'image', $event)" /></div><div class="dsf-form-group"><label class="dsf-label">Card title</label><input class="dsf-input" type="text" maxlength="120" :value="image.title" @input="updateImage(tabIndex, imageIndex, 'title', $event.target.value)" /></div><div class="dsf-form-group"><label class="dsf-label">Card subtitle</label><input class="dsf-input" type="text" maxlength="180" :value="image.subtitle" @input="updateImage(tabIndex, imageIndex, 'subtitle', $event.target.value)" /></div><div class="dsf-form-group"><label class="dsf-label">Second subtitle (optional)</label><input class="dsf-input" type="text" maxlength="180" :value="image.secondarySubtitle" @input="updateImage(tabIndex, imageIndex, 'secondarySubtitle', $event.target.value)" /></div><div class="dsf-form-group"><label class="dsf-label">Whole-card URL (optional)</label><input class="dsf-input" type="url" maxlength="2048" :value="image.url" @input="updateImage(tabIndex, imageIndex, 'url', $event.target.value)" /></div></div></div></template>
              </draggable>
              <button v-if="tab.images.length < 6" type="button" class="dsf-tabbed-showcase-field__add" @click="addImage(tabIndex)"><Plus :size="16" /> Add Image</button>
            </div>
          </div>
        </div>
      </template>
    </draggable>
    <button v-if="localTabs.length < 6" type="button" class="dsf-tabbed-showcase-field__add" @click="addTab"><Plus :size="16" /> Add Tab</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import MediaPicker from './MediaPicker.vue'
import ProductsSelector from '../selectors/ProductsSelector.vue'
const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const localTabs = ref([]); const openTabs = ref([0]); const openImages = ref({})
const productConfig = { hideSearchCardTitle: true, searchPlaceholder: 'Search products...' }
const normalizeImage = (image = {}, index = 0) => ({ image: typeof image.image === 'string' ? image.image : '', title: typeof image.title === 'string' ? image.title : '', subtitle: typeof image.subtitle === 'string' ? image.subtitle : '', secondarySubtitle: typeof image.secondarySubtitle === 'string' ? image.secondarySubtitle : '', url: typeof image.url === 'string' ? image.url : '', id: image.id || `tabbed-showcase-image-${index}-${Date.now()}` })
const normalizeTab = (tab = {}, index = 0) => ({ label: typeof tab.label === 'string' ? tab.label : 'New Tab', source: tab.source === 'images' ? 'images' : 'products', productIds: Array.isArray(tab.productIds) ? tab.productIds : [], images: Array.isArray(tab.images) ? tab.images.slice(0, 6).map(normalizeImage) : [], id: tab.id || `tabbed-showcase-tab-${index}-${Date.now()}` })
function emitUpdate() { emit('update:modelValue', localTabs.value.map(({ id, images, ...tab }) => ({ ...tab, images: images.map(({ id: imageId, ...image }) => image) }))) }
function toggleTab(index) { const position = openTabs.value.indexOf(index); if (position >= 0) openTabs.value.splice(position, 1); else openTabs.value.push(index) }
function imageKey(tabIndex, imageIndex) { return `${tabIndex}:${imageIndex}` }
function isImageOpen(tabIndex, imageIndex) { return Boolean(openImages.value[imageKey(tabIndex, imageIndex)]) }
function toggleImage(tabIndex, imageIndex) { const key = imageKey(tabIndex, imageIndex); openImages.value[key] = !openImages.value[key] }
function updateTab(index, key, value) { localTabs.value[index][key] = value; emitUpdate() }
function updateImage(tabIndex, imageIndex, key, value) { localTabs.value[tabIndex].images[imageIndex][key] = value; emitUpdate() }
function addTab() { if (localTabs.value.length < 6) { localTabs.value.push(normalizeTab({}, localTabs.value.length)); openTabs.value.push(localTabs.value.length - 1); emitUpdate() } }
function removeTab(index) { localTabs.value.splice(index, 1); openTabs.value = openTabs.value.filter((item) => item !== index).map((item) => item > index ? item - 1 : item); openImages.value = {}; emitUpdate() }
function addImage(tabIndex) { const images = localTabs.value[tabIndex].images; if (images.length < 6) { images.push(normalizeImage({ title: 'Product' }, images.length)); openImages.value[imageKey(tabIndex, images.length - 1)] = true; emitUpdate() } }
function removeImage(tabIndex, imageIndex) { localTabs.value[tabIndex].images.splice(imageIndex, 1); openImages.value = {}; emitUpdate() }
watch(() => props.modelValue, (value) => { const priorIds = localTabs.value.map((tab) => tab.id); localTabs.value = (Array.isArray(value) ? value : []).slice(0, 6).map((tab, index) => normalizeTab({ ...tab, id: tab.id || priorIds[index] }, index)) }, { immediate: true, deep: true })
</script>

<style scoped>
.dsf-tabbed-showcase-field { display: flex; flex-direction: column; gap: .5rem; }.dsf-tabbed-showcase-field__tab, .dsf-tabbed-showcase-field__image { overflow: hidden; background: white; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); }.dsf-tabbed-showcase-field__tab--ghost, .dsf-tabbed-showcase-field__image--ghost { opacity: .5; }.dsf-tabbed-showcase-field__tab-header, .dsf-tabbed-showcase-field__image-header { display: flex; align-items: center; gap: .5rem; padding: .625rem .75rem; background: var(--dsf-gray-50); cursor: pointer; }.dsf-tabbed-showcase-field__drag, .dsf-tabbed-showcase-field__image-drag, .dsf-tabbed-showcase-field__delete { display: flex; align-items: center; justify-content: center; padding: .25rem; border: 0; background: transparent; color: var(--dsf-gray-400); cursor: pointer; }.dsf-tabbed-showcase-field__title { min-width: 0; flex: 1; overflow: hidden; color: var(--dsf-gray-800); font-size: .8125rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }.dsf-tabbed-showcase-field__actions { display: flex; align-items: center; gap: .25rem; }.dsf-tabbed-showcase-field__actions > svg { color: var(--dsf-gray-400); transition: transform .15s ease; }.dsf-tabbed-showcase-field__actions > svg.is-open { transform: rotate(180deg); }.dsf-tabbed-showcase-field__body, .dsf-tabbed-showcase-field__image-body { display: flex; flex-direction: column; gap: .75rem; padding: .75rem; }.dsf-tabbed-showcase-field__images { display: flex; flex-direction: column; gap: .5rem; }.dsf-tabbed-showcase-field__add { display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; padding: .75rem; border: 1px dashed var(--dsf-gray-300); border-radius: var(--dsf-radius-md); background: white; color: var(--dsf-primary-600); font-size: .875rem; font-weight: 600; cursor: pointer; }
</style>
