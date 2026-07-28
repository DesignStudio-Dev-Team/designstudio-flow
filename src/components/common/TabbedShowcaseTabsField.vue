<template>
  <div class="dsf-tabbed-showcase-field">
    <div v-for="(tab, tabIndex) in localTabs" :key="tabIndex" class="dsf-tabbed-showcase-field__tab">
      <div class="dsf-tabbed-showcase-field__tab-header">
        <strong>Tab {{ tabIndex + 1 }}</strong>
        <button v-if="localTabs.length > 1" type="button" class="dsf-icon-btn" title="Remove tab" @click="removeTab(tabIndex)">×</button>
      </div>

      <label class="dsf-label">Tab Label</label>
      <input class="dsf-input" type="text" maxlength="80" :value="tab.label" @input="updateTab(tabIndex, 'label', $event.target.value)" />

      <label class="dsf-label">Content Type</label>
      <select class="dsf-input" :value="tab.source" @change="updateTab(tabIndex, 'source', $event.target.value)">
        <option value="products">Products</option>
        <option value="images">Images</option>
      </select>
      <label class="dsf-label">Optional Product Supporting Text</label>
      <input class="dsf-input" type="text" maxlength="180" placeholder="Add another line below product details" :value="tab.supportingText" @input="updateTab(tabIndex, 'supportingText', $event.target.value)" />

      <ProductsSelector
        v-if="tab.source === 'products'"
        :value="tab.productIds"
        :config="productConfig"
        @update="updateTab(tabIndex, 'productIds', $event)"
      />

      <div v-else class="dsf-tabbed-showcase-field__images">
        <div v-for="(image, imageIndex) in tab.images" :key="imageIndex" class="dsf-tabbed-showcase-field__image">
          <div class="dsf-tabbed-showcase-field__image-header">
            <strong>Image {{ imageIndex + 1 }}</strong>
            <button type="button" class="dsf-icon-btn" title="Remove image" @click="removeImage(tabIndex, imageIndex)">×</button>
          </div>
          <MediaPicker :modelValue="image.image" @update:modelValue="updateImage(tabIndex, imageIndex, 'image', $event)" />
          <input class="dsf-input" type="text" maxlength="120" placeholder="Card title" :value="image.title" @input="updateImage(tabIndex, imageIndex, 'title', $event.target.value)" />
          <input class="dsf-input" type="text" maxlength="180" placeholder="Card subtitle" :value="image.subtitle" @input="updateImage(tabIndex, imageIndex, 'subtitle', $event.target.value)" />
          <input class="dsf-input" type="url" maxlength="2048" placeholder="Optional link URL" :value="image.url" @input="updateImage(tabIndex, imageIndex, 'url', $event.target.value)" />
        </div>
        <button v-if="tab.images.length < 6" type="button" class="dsf-btn dsf-btn--secondary dsf-w-full" @click="addImage(tabIndex)">+ Add Image</button>
      </div>
    </div>

    <button v-if="localTabs.length < 6" type="button" class="dsf-btn dsf-btn--secondary dsf-w-full" @click="addTab">+ Add Tab</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import MediaPicker from './MediaPicker.vue'
import ProductsSelector from '../selectors/ProductsSelector.vue'

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const localTabs = ref([])
const productConfig = { hideSearchCardTitle: true, searchPlaceholder: 'Search products...' }

function normalizeTab(tab) {
  return {
    label: typeof tab?.label === 'string' ? tab.label : 'New Tab',
    source: tab?.source === 'images' ? 'images' : 'products',
    supportingText: typeof tab?.supportingText === 'string' ? tab.supportingText : '',
    productIds: Array.isArray(tab?.productIds) ? tab.productIds : [],
    images: Array.isArray(tab?.images) ? tab.images.slice(0, 6).map(normalizeImage) : [],
  }
}

function normalizeImage(image) {
  return {
    image: typeof image?.image === 'string' ? image.image : '',
    title: typeof image?.title === 'string' ? image.title : '',
    subtitle: typeof image?.subtitle === 'string' ? image.subtitle : '',
    url: typeof image?.url === 'string' ? image.url : '',
  }
}

function emitUpdate() {
  emit('update:modelValue', localTabs.value.map((tab) => ({
    label: tab.label,
    source: tab.source,
    supportingText: tab.supportingText,
    productIds: tab.productIds,
    images: tab.images,
  })))
}

function updateTab(index, key, value) {
  localTabs.value[index][key] = value
  emitUpdate()
}

function updateImage(tabIndex, imageIndex, key, value) {
  localTabs.value[tabIndex].images[imageIndex][key] = value
  emitUpdate()
}

function addTab() {
  localTabs.value.push({ label: 'New Tab', source: 'products', supportingText: '', productIds: [], images: [] })
  emitUpdate()
}

function removeTab(index) {
  localTabs.value.splice(index, 1)
  emitUpdate()
}

function addImage(tabIndex) {
  localTabs.value[tabIndex].images.push({ image: '', title: 'Product', subtitle: '', url: '' })
  emitUpdate()
}

function removeImage(tabIndex, imageIndex) {
  localTabs.value[tabIndex].images.splice(imageIndex, 1)
  emitUpdate()
}

watch(() => props.modelValue, (value) => {
  localTabs.value = (Array.isArray(value) ? value : []).slice(0, 6).map(normalizeTab)
}, { immediate: true, deep: true })
</script>

<style scoped>
.dsf-tabbed-showcase-field { display: flex; flex-direction: column; gap: 0.75rem; }
.dsf-tabbed-showcase-field__tab,
.dsf-tabbed-showcase-field__image { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--dsf-gray-200); border-radius: var(--dsf-radius-md); background: var(--dsf-gray-50); }
.dsf-tabbed-showcase-field__tab-header,
.dsf-tabbed-showcase-field__image-header { display: flex; align-items: center; justify-content: space-between; }
.dsf-tabbed-showcase-field__images { display: flex; flex-direction: column; gap: 0.75rem; }
.dsf-icon-btn { border: 0; background: transparent; color: var(--dsf-gray-500); cursor: pointer; font-size: 1.25rem; }
</style>
