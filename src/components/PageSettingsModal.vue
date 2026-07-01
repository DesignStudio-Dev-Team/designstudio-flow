<template>
  <Teleport to="body">
    <Transition name="dsf-page-settings">
      <div v-if="visible" class="dsf-page-settings-overlay" @click.self="close">
        <form class="dsf-page-settings-modal" @submit.prevent="save">
          <div class="dsf-page-settings-modal__header">
            <div>
              <h2 class="dsf-page-settings-modal__title">Page Settings</h2>
              <p class="dsf-page-settings-modal__subtitle">Manage WordPress details and page-level features.</p>
            </div>
            <button type="button" class="dsf-page-settings-modal__close" @click="close">
              <X :size="20" />
            </button>
          </div>

          <div class="dsf-page-settings-modal__tabs" role="tablist" aria-label="Page settings sections">
            <button type="button" role="tab" :aria-selected="activeTab === 'general'" :class="{ 'is-active': activeTab === 'general' }" @click="activeTab = 'general'">General</button>
            <button v-if="isProductTemplate" type="button" role="tab" :aria-selected="activeTab === 'product'" :class="{ 'is-active': activeTab === 'product' }" @click="activeTab = 'product'">Product</button>
            <button type="button" role="tab" :aria-selected="activeTab === 'popup'" :class="{ 'is-active': activeTab === 'popup' }" @click="activeTab = 'popup'">Popup</button>
          </div>

          <div class="dsf-page-settings-modal__body">
            <template v-if="activeTab === 'general'">
            <div class="dsf-form-group">
              <label class="dsf-label" for="dsf-page-title">Page Title</label>
              <input
                id="dsf-page-title"
                v-model="localTitle"
                class="dsf-input"
                type="text"
                placeholder="Untitled Page"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label" for="dsf-page-slug">Slug</label>
              <div class="dsf-page-settings-modal__slug-row">
                <input
                  id="dsf-page-slug"
                  v-model="localSlug"
                  class="dsf-input"
                  type="text"
                  placeholder="page-slug"
                />
                <button type="button" class="dsf-btn dsf-btn--secondary" @click="generateSlug">
                  Use Title
                </button>
              </div>
              <p class="dsf-page-settings-modal__hint">Leave blank to let WordPress generate it from the title.</p>
            </div>

            <div class="dsf-page-settings-modal__grid">
              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-page-status">Status</label>
                <select id="dsf-page-status" v-model="localStatus" class="dsf-input">
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                </select>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-page-parent">Parent Page</label>
                <select id="dsf-page-parent" v-model.number="localParentId" class="dsf-input">
                  <option :value="0">No parent</option>
                  <option
                    v-for="page in parentPages"
                    :key="page.id"
                    :value="page.id"
                  >
                    {{ page.depthLabel }}{{ page.title }}
                  </option>
                </select>
              </div>
            </div>
            </template>

            <div v-else-if="activeTab === 'product' && isProductTemplate" class="dsf-pt-settings">
              <label class="dsf-pt-toggle">
                <input type="checkbox" v-model="localPtActive" />
                <span>
                  <strong>Make this template live</strong>
                  <small>When live and published, this design replaces the WooCommerce template for matching products.</small>
                </span>
              </label>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-pt-applies">Applies to</label>
                <select id="dsf-pt-applies" class="dsf-input" v-model="localPtMode">
                  <option value="all">All products</option>
                  <option value="categories">Specific product categories</option>
                </select>
              </div>

              <div v-if="localPtMode === 'categories'" class="dsf-form-group">
                <label class="dsf-label">Product categories</label>
                <div class="dsf-pt-categories">
                  <label v-for="cat in productCategories" :key="cat.id" class="dsf-pt-category">
                    <input type="checkbox" :value="cat.id" v-model="localPtCategoryIds" />
                    <span>{{ cat.name }}</span>
                  </label>
                  <p v-if="!productCategories.length" class="dsf-page-settings-modal__hint">No product categories found.</p>
                </div>
                <p class="dsf-page-settings-modal__hint">Category-specific templates take priority over an "all products" template.</p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-pt-preview">Preview product</label>
                <div class="dsf-relative">
                  <input
                    id="dsf-pt-preview"
                    class="dsf-input"
                    type="text"
                    :placeholder="selectedPreviewName || 'Search a product to preview…'"
                    v-model="previewSearch"
                    @input="debouncedPreviewSearch"
                  />
                  <ul v-if="previewResults.length" class="dsf-pt-results">
                    <li v-for="product in previewResults" :key="product.id">
                      <button type="button" @click="choosePreviewProduct(product)">{{ product.name }}</button>
                    </li>
                  </ul>
                </div>
                <p class="dsf-page-settings-modal__hint">Used only in the editor so product blocks show real data. It is never saved into the live page.</p>
              </div>
            </div>

            <div v-else class="dsf-popup-picker">
              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-popup-pick">Popup for this page</label>
                <select id="dsf-popup-pick" class="dsf-input" :value="localPopupId" @change="localPopupId = Number($event.target.value)">
                  <option :value="0">No popup</option>
                  <option v-for="item in localPopups" :key="item.id" :value="item.id">
                    {{ item.title }}<template v-if="item.status && item.status !== 'publish'"> (draft)</template>
                  </option>
                </select>
              </div>

              <div class="dsf-popup-picker__actions">
                <a class="dsf-btn dsf-btn--secondary" :href="popupCreateUrl || '#'" target="_blank" rel="noopener">Create new popup</a>
                <a v-if="localPopupId" class="dsf-btn dsf-btn--secondary" :href="(popupEditUrlBase || '') + localPopupId" target="_blank" rel="noopener">Edit selected</a>
                <button type="button" class="dsf-btn dsf-btn--secondary" :disabled="refreshing" @click="refreshPopups">
                  {{ refreshing ? 'Refreshing…' : 'Refresh list' }}
                </button>
              </div>

              <p v-if="localPopupId && selectedIsDraft" class="dsf-helper-text">This popup is a draft. Publish it for it to appear on the live page.</p>
              <p v-else-if="!localPopupId && legacyActive" class="dsf-helper-text">An inline popup from older settings is active on this page. Choosing a popup above replaces it.</p>
              <p v-else class="dsf-helper-text">Popups are reusable. Create and manage them under DesignStudio Flow → Popups.</p>
            </div>
          </div>

          <div class="dsf-page-settings-modal__footer">
            <button type="button" class="dsf-btn dsf-btn--secondary" @click="close">Cancel</button>
            <button type="submit" class="dsf-btn dsf-btn--primary">Apply Settings</button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  slug: {
    type: String,
    default: '',
  },
  status: {
    type: String,
    default: 'draft',
  },
  parentId: {
    type: Number,
    default: 0,
  },
  parentPages: {
    type: Array,
    default: () => [],
  },
  popup: {
    type: Object,
    default: () => ({}),
  },
  popupId: {
    type: Number,
    default: 0,
  },
  popups: {
    type: Array,
    default: () => [],
  },
  popupCreateUrl: {
    type: String,
    default: '',
  },
  popupEditUrlBase: {
    type: String,
    default: '',
  },
  isProductTemplate: {
    type: Boolean,
    default: false,
  },
  productTemplate: {
    type: Object,
    default: null,
  },
  productCategories: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['close', 'save'])

const localTitle = ref('')
const localSlug = ref('')
const localStatus = ref('draft')
const localParentId = ref(0)
const localPopup = ref({})
const localPopupId = ref(0)
const localPopups = ref([])
const refreshing = ref(false)
const activeTab = ref('general')

// Product template controls.
const localPtActive = ref(false)
const localPtMode = ref('all')
const localPtCategoryIds = ref([])
const localPtPreviewId = ref(0)
const selectedPreviewName = ref('')
const previewSearch = ref('')
const previewResults = ref([])
let previewSearchTimer = null

const selectedIsDraft = computed(() => {
  const match = localPopups.value.find((item) => item.id === localPopupId.value)
  return match ? match.status && match.status !== 'publish' : false
})
const legacyActive = computed(() => Boolean(localPopup.value && localPopup.value.enabled))

watch(
  () => props.visible,
  (isVisible) => {
    if (!isVisible) return
    localTitle.value = props.title || ''
    localSlug.value = props.slug || ''
    localStatus.value = props.status === 'publish' ? 'publish' : 'draft'
    localParentId.value = Number.parseInt(props.parentId, 10) || 0
    localPopup.value = { ...(props.popup || {}) }
    localPopupId.value = Number.parseInt(props.popupId, 10) || 0
    localPopups.value = Array.isArray(props.popups) ? [...props.popups] : []
    activeTab.value = 'general'

    const pt = props.productTemplate || {}
    localPtActive.value = Boolean(pt.active)
    localPtMode.value = pt.assignment?.mode === 'categories' ? 'categories' : 'all'
    localPtCategoryIds.value = Array.isArray(pt.assignment?.categoryIds)
      ? pt.assignment.categoryIds.map((id) => Number.parseInt(id, 10)).filter(Boolean)
      : []
    localPtPreviewId.value = Number.parseInt(pt.previewProduct, 10) || 0
    selectedPreviewName.value = ''
    previewSearch.value = ''
    previewResults.value = []
  },
  { immediate: true }
)

function debouncedPreviewSearch() {
  window.clearTimeout(previewSearchTimer)
  previewSearchTimer = window.setTimeout(runPreviewSearch, 280)
}

async function runPreviewSearch() {
  const data = typeof window !== 'undefined' ? window.dsfEditorData : null
  const term = previewSearch.value.trim()
  if (!data?.ajaxUrl || term.length < 2) {
    previewResults.value = []
    return
  }
  try {
    const body = new URLSearchParams({ action: 'dsf_search_products', nonce: data.nonce || '', search: term })
    const response = await fetch(data.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
    const json = await response.json()
    previewResults.value = json?.success && Array.isArray(json.data?.products) ? json.data.products : []
  } catch (error) {
    previewResults.value = []
  }
}

function choosePreviewProduct(product) {
  localPtPreviewId.value = Number.parseInt(product.id, 10) || 0
  selectedPreviewName.value = product.name || ''
  previewSearch.value = ''
  previewResults.value = []
}

async function refreshPopups() {
  const data = typeof window !== 'undefined' ? window.dsfEditorData : null
  if (!data?.ajaxUrl || refreshing.value) return
  refreshing.value = true
  try {
    const body = new URLSearchParams({ action: 'dsf_list_popups', nonce: data.nonce || '' })
    const response = await fetch(data.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
    const json = await response.json()
    if (json?.success && Array.isArray(json.data?.popups)) {
      localPopups.value = json.data.popups
    }
  } catch (error) {
    // Non-fatal; keep the existing list.
  } finally {
    refreshing.value = false
  }
}

function slugify(value) {
  return String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function generateSlug() {
  localSlug.value = slugify(localTitle.value)
}

function save() {
  const payload = {
    title: localTitle.value.trim(),
    slug: slugify(localSlug.value),
    status: localStatus.value === 'publish' ? 'publish' : 'draft',
    parentId: Number.parseInt(localParentId.value, 10) || 0,
    popup: { ...localPopup.value },
    popupId: Number.parseInt(localPopupId.value, 10) || 0,
  }

  if (props.isProductTemplate) {
    payload.productTemplate = {
      active: localPtActive.value === true,
      assignment: {
        mode: localPtMode.value === 'categories' ? 'categories' : 'all',
        categoryIds: localPtCategoryIds.value.map((id) => Number.parseInt(id, 10)).filter(Boolean),
      },
      previewProduct: Number.parseInt(localPtPreviewId.value, 10) || 0,
    }
  }

  emit('save', payload)
}

onBeforeUnmount(() => window.clearTimeout(previewSearchTimer))

function close() {
  emit('close')
}
</script>

<style scoped>
.dsf-page-settings-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.58);
  backdrop-filter: blur(4px);
}

.dsf-page-settings-modal {
  display: flex;
  flex-direction: column;
  width: min(760px, 100%);
  max-height: min(860px, calc(100dvh - 48px));
  overflow: hidden;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 26px 60px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.dsf-page-settings-modal__header,
.dsf-page-settings-modal__footer {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 20px 22px;
}

.dsf-page-settings-modal__header {
  border-bottom: 1px solid var(--dsf-gray-200);
}

.dsf-page-settings-modal__footer {
  position: relative;
  z-index: 2;
  border-top: 1px solid var(--dsf-gray-200);
  background: var(--dsf-gray-50);
}

.dsf-page-settings-modal__title,
.dsf-page-settings-modal__subtitle {
  margin: 0;
}

.dsf-page-settings-modal__title {
  color: var(--dsf-gray-900);
  font-size: 1.125rem;
  font-weight: 700;
}

.dsf-page-settings-modal__subtitle,
.dsf-page-settings-modal__hint {
  color: var(--dsf-gray-500);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.dsf-page-settings-modal__hint {
  margin: 0.375rem 0 0;
}

.dsf-page-settings-modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-gray-100);
  color: var(--dsf-gray-500);
  cursor: pointer;
}

.dsf-page-settings-modal__close:hover {
  background: var(--dsf-gray-200);
  color: var(--dsf-gray-800);
}

.dsf-page-settings-modal__body {
  flex: 1 1 auto;
  min-height: 0;
  display: grid;
  gap: 1rem;
  padding: 22px;
  overflow-y: auto;
}

.dsf-page-settings-modal__tabs {
  flex: 0 0 auto;
  display: flex;
  gap: 6px;
  padding: 10px 22px 0;
  border-bottom: 1px solid var(--dsf-gray-200);
}

.dsf-page-settings-modal__tabs button {
  padding: 10px 14px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--dsf-gray-500);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

.dsf-page-settings-modal__tabs button.is-active {
  border-bottom-color: var(--dsf-primary-600);
  color: var(--dsf-primary-700);
}

.dsf-page-settings-modal__slug-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.625rem;
}

.dsf-page-settings-modal__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.dsf-page-settings-enter-active,
.dsf-page-settings-leave-active {
  transition: opacity 0.16s ease;
}

.dsf-page-settings-enter-active .dsf-page-settings-modal,
.dsf-page-settings-leave-active .dsf-page-settings-modal {
  transition: transform 0.16s ease;
}

.dsf-page-settings-enter-from,
.dsf-page-settings-leave-to {
  opacity: 0;
}

.dsf-page-settings-enter-from .dsf-page-settings-modal,
.dsf-page-settings-leave-to .dsf-page-settings-modal {
  transform: translateY(10px) scale(0.98);
}

@media (max-width: 640px) {
  .dsf-page-settings-modal__slug-row,
  .dsf-page-settings-modal__grid {
    grid-template-columns: 1fr;
  }

  .dsf-page-settings-modal__footer {
    flex-direction: column-reverse;
  }

  .dsf-page-settings-modal__footer .dsf-btn {
    width: 100%;
  }
}

@media (max-height: 600px) {
  .dsf-page-settings-overlay {
    padding: 10px;
  }

  .dsf-page-settings-modal {
    max-height: calc(100dvh - 20px);
  }

  .dsf-page-settings-modal__header,
  .dsf-page-settings-modal__footer {
    padding: 12px 16px;
  }

  .dsf-page-settings-modal__body {
    padding: 16px;
  }
}

.dsf-popup-picker { display: grid; gap: 14px; }
.dsf-popup-picker__actions { display: flex; flex-wrap: wrap; gap: 8px; }

.dsf-pt-settings { display: grid; gap: 16px; }
.dsf-pt-toggle { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }
.dsf-pt-toggle input { margin-top: 3px; }
.dsf-pt-toggle span { display: grid; gap: 2px; }
.dsf-pt-toggle small { color: var(--dsf-gray-500); font-size: 0.78rem; line-height: 1.4; }
.dsf-pt-categories { display: grid; gap: 6px; max-height: 180px; overflow-y: auto; padding: 4px; border: 1px solid var(--dsf-gray-200); border-radius: 8px; }
.dsf-pt-category { display: flex; gap: 8px; align-items: center; font-size: 0.85rem; }
.dsf-relative { position: relative; }
.dsf-pt-results { position: absolute; z-index: 5; left: 0; right: 0; margin: 4px 0 0; padding: 4px; list-style: none; background: #fff; border: 1px solid var(--dsf-gray-200); border-radius: 8px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16); max-height: 220px; overflow-y: auto; }
.dsf-pt-results li { margin: 0; }
.dsf-pt-results button { display: block; width: 100%; text-align: left; padding: 7px 10px; border: 0; border-radius: 6px; background: transparent; cursor: pointer; font-size: 0.85rem; }
.dsf-pt-results button:hover { background: var(--dsf-gray-100); }
</style>
