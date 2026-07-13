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
            <button v-if="isShopTemplate" type="button" role="tab" :aria-selected="activeTab === 'shop'" :class="{ 'is-active': activeTab === 'shop' }" @click="activeTab = 'shop'">Shop</button>
            <button v-if="isBlogTemplate" type="button" role="tab" :aria-selected="activeTab === 'blog'" :class="{ 'is-active': activeTab === 'blog' }" @click="activeTab = 'blog'">Blog</button>
            <button v-if="supportsSeo" type="button" role="tab" :aria-selected="activeTab === 'seo'" :class="{ 'is-active': activeTab === 'seo' }" @click="activeTab = 'seo'">SEO</button>
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

            <div v-if="!isProductTemplate && !isShopTemplate && !isBlogTemplate" class="dsf-form-group">
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

              <div v-if="!isProductTemplate && !isShopTemplate && !isBlogTemplate" class="dsf-form-group">
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

            <div v-else-if="activeTab === 'shop' && isShopTemplate" class="dsf-pt-settings">
              <label class="dsf-pt-toggle">
                <input type="checkbox" v-model="localStActive" />
                <span>
                  <strong>Make this template live</strong>
                  <small>When live and published, this design replaces the WooCommerce template for matching shop and category pages.</small>
                </span>
              </label>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-st-applies">Applies to</label>
                <select id="dsf-st-applies" class="dsf-input" v-model="localStMode">
                  <option value="all">Entire catalog (shop, categories, tags)</option>
                  <option value="categories">Specific product categories</option>
                </select>
              </div>

              <div v-if="localStMode === 'categories'" class="dsf-form-group">
                <label class="dsf-label">Product categories</label>
                <div class="dsf-pt-categories">
                  <label v-for="cat in productCategories" :key="cat.id" class="dsf-pt-category">
                    <input type="checkbox" :value="cat.id" v-model="localStCategoryIds" />
                    <span>{{ cat.name }}</span>
                  </label>
                  <p v-if="!productCategories.length" class="dsf-page-settings-modal__hint">No product categories found.</p>
                </div>
                <p class="dsf-page-settings-modal__hint">Category-specific templates take priority over an "entire catalog" template on category pages.</p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-st-preview">Preview category</label>
                <select id="dsf-st-preview" class="dsf-input" v-model.number="localStPreviewTerm">
                  <option :value="0">Whole catalog (shop page)</option>
                  <option v-for="cat in productCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <p class="dsf-page-settings-modal__hint">Used only in the editor so shop blocks show that archive's products. It is never saved into the live template.</p>
              </div>
            </div>

            <div v-else-if="activeTab === 'blog' && isBlogTemplate" class="dsf-pt-settings">
              <label class="dsf-pt-toggle">
                <input type="checkbox" v-model="localBtActive" />
                <span>
                  <strong>Make this template live</strong>
                  <small>When live and published, this design replaces the theme template for matching blog archives.</small>
                </span>
              </label>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-bt-applies">Applies to</label>
                <select id="dsf-bt-applies" class="dsf-input" v-model="localBtMode">
                  <option value="all">All blog archives (posts page, categories, tags, authors)</option>
                  <option value="categories">Specific post categories</option>
                </select>
              </div>

              <div v-if="localBtMode === 'categories'" class="dsf-form-group">
                <label class="dsf-label">Post categories</label>
                <div class="dsf-pt-categories">
                  <label v-for="cat in blogCategories" :key="cat.id" class="dsf-pt-category">
                    <input type="checkbox" :value="cat.id" v-model="localBtCategoryIds" />
                    <span>{{ cat.name }}</span>
                  </label>
                  <p v-if="!blogCategories.length" class="dsf-page-settings-modal__hint">No post categories found.</p>
                </div>
                <p class="dsf-page-settings-modal__hint">Category-specific templates take priority over an "all archives" template on category pages.</p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-bt-preview">Preview category</label>
                <select id="dsf-bt-preview" class="dsf-input" v-model.number="localBtPreviewTerm">
                  <option :value="0">Latest posts (posts page)</option>
                  <option v-for="cat in blogCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <p class="dsf-page-settings-modal__hint">Used only in the editor so blog blocks show that archive's posts. It is never saved into the live template.</p>
              </div>
            </div>

            <div v-else-if="activeTab === 'seo' && supportsSeo" class="dsf-seo-settings">
              <div class="dsf-seo-preview-toggle" role="tablist" aria-label="Preview type">
                <button type="button" role="tab" :aria-selected="seoPreviewMode === 'search'" :class="{ 'is-active': seoPreviewMode === 'search' }" @click="seoPreviewMode = 'search'">Google</button>
                <button type="button" role="tab" :aria-selected="seoPreviewMode === 'social'" :class="{ 'is-active': seoPreviewMode === 'social' }" @click="seoPreviewMode = 'social'">Social</button>
              </div>

              <div v-if="seoPreviewMode === 'search'" class="dsf-seo-snippet" aria-hidden="true">
                <span class="dsf-seo-snippet__site">{{ siteName || 'Your site' }}</span>
                <span class="dsf-seo-snippet__url">{{ snippetUrl }}</span>
                <span class="dsf-seo-snippet__title">{{ snippetTitle }}</span>
                <span class="dsf-seo-snippet__desc">{{ snippetDescription }}</span>
              </div>

              <div v-else class="dsf-seo-card" aria-hidden="true">
                <div class="dsf-seo-card__media" :class="{ 'dsf-seo-card__media--empty': !socialCardImage }">
                  <img v-if="socialCardImage" :src="socialCardImage" alt="" />
                  <span v-else>No social image — falls back to the hero/product image</span>
                </div>
                <div class="dsf-seo-card__body">
                  <span class="dsf-seo-card__domain">{{ socialDomain }}</span>
                  <span class="dsf-seo-card__title">{{ snippetTitle }}</span>
                  <span class="dsf-seo-card__desc">{{ snippetDescription }}</span>
                </div>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-seo-title">SEO title</label>
                <input id="dsf-seo-title" v-model="localSeoTitle" class="dsf-input" type="text" maxlength="200" placeholder="{title} {sep} {site_name}" />
                <div class="dsf-seo-meter">
                  <div class="dsf-seo-meter__bar"><span :style="{ width: Math.min(100, (titlePixels / TITLE_PX_LIMIT) * 100) + '%' }" :class="{ 'dsf-seo-meter__fill--over': titlePixels > TITLE_PX_LIMIT }"></span></div>
                  <span class="dsf-seo-meter__label" :class="{ 'dsf-seo-count--over': titlePixels > TITLE_PX_LIMIT }">{{ titlePixels }} / {{ TITLE_PX_LIMIT }}px</span>
                </div>
                <p class="dsf-page-settings-modal__hint">
                  Variables: <code v-pre>{title} {site_name} {tagline} {sep} {excerpt} {price} {category}</code> — resolved per page/product/archive. Leave blank to keep the default title.
                </p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-seo-description">Meta description</label>
                <textarea id="dsf-seo-description" v-model="localSeoDescription" class="dsf-input dsf-seo-textarea" rows="3" maxlength="300" placeholder="A compelling summary shown in search results. Supports the same variables."></textarea>
                <div class="dsf-seo-meter">
                  <div class="dsf-seo-meter__bar"><span :style="{ width: Math.min(100, (descPixels / DESC_PX_LIMIT) * 100) + '%' }" :class="{ 'dsf-seo-meter__fill--over': descPixels > DESC_PX_LIMIT }"></span></div>
                  <span class="dsf-seo-meter__label" :class="{ 'dsf-seo-count--over': descPixels > DESC_PX_LIMIT }">{{ descPixels }} / {{ DESC_PX_LIMIT }}px</span>
                </div>
                <p class="dsf-page-settings-modal__hint">
                  Google truncates by width, not characters. Keep the bar out of the red on both.
                </p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-seo-image">Social share image</label>
                <div class="dsf-page-settings-modal__slug-row">
                  <input id="dsf-seo-image" v-model="localSeoSocialImage" class="dsf-input" type="url" placeholder="https://… (og:image)" />
                  <button type="button" class="dsf-btn dsf-btn--secondary" @click="pickSocialImage">Media Library</button>
                </div>
                <p class="dsf-page-settings-modal__hint">Used for Facebook/X link previews. Falls back to the page's hero image or the product photo.</p>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-seo-canonical">Canonical URL</label>
                <input id="dsf-seo-canonical" v-model="localSeoCanonical" class="dsf-input" type="url" placeholder="Leave blank for the default" />
              </div>

              <label class="dsf-pt-toggle">
                <input type="checkbox" v-model="localSeoNoindex" />
                <span>
                  <strong>Hide from search engines (noindex)</strong>
                  <small>Asks Google and others not to index this page, and removes it from the sitemap. Useful for thank-you or utility pages.</small>
                </span>
              </label>

              <label class="dsf-pt-toggle">
                <input type="checkbox" v-model="localSeoNofollow" />
                <span>
                  <strong>Don't follow links (nofollow)</strong>
                  <small>Asks search engines not to follow links on this page. Most pages should leave this off.</small>
                </span>
              </label>

              <p class="dsf-page-settings-modal__hint">If an SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) is active, it takes over and these settings are not output.</p>
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
  isShopTemplate: {
    type: Boolean,
    default: false,
  },
  shopTemplate: {
    type: Object,
    default: null,
  },
  isBlogTemplate: {
    type: Boolean,
    default: false,
  },
  blogTemplate: {
    type: Object,
    default: null,
  },
  blogCategories: {
    type: Array,
    default: () => [],
  },
  supportsSeo: {
    type: Boolean,
    default: false,
  },
  seo: {
    type: Object,
    default: null,
  },
  siteName: {
    type: String,
    default: '',
  },
  pageUrl: {
    type: String,
    default: '',
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

// Shop template controls.
const localStActive = ref(false)
const localStMode = ref('all')
const localStCategoryIds = ref([])
const localStPreviewTerm = ref(0)

// Blog template controls.
const localBtActive = ref(false)
const localBtMode = ref('all')
const localBtCategoryIds = ref([])
const localBtPreviewTerm = ref(0)

// SEO controls.
const localSeoTitle = ref('')
const localSeoDescription = ref('')
const localSeoSocialImage = ref('')
const localSeoCanonical = ref('')
const localSeoNoindex = ref(false)
const localSeoNofollow = ref(false)

// Client-side variable resolution for the snippet preview (sample values; the
// server resolves the real ones per URL at render time).
function previewVariables(text) {
  const values = {
    title: props.title || 'Page title',
    site_name: props.siteName || 'Your site',
    tagline: '',
    sep: '–',
    excerpt: '',
    price: '',
    category: '',
  }
  return String(text || '')
    .replace(/\{([a-z0-9_]+)\}/gi, (m, key) => (key in values ? values[key] : ''))
    .replace(/\s{2,}/g, ' ')
    .replace(/^[\s\-–—|·:]+|[\s\-–—|·:]+$/g, '')
    .trim()
}

const snippetTitle = computed(() =>
  previewVariables(localSeoTitle.value) || props.title || 'Page title'
)
const snippetDescription = computed(() =>
  previewVariables(localSeoDescription.value) ||
  'Add a meta description to control how this page appears in search results.'
)
const snippetUrl = computed(() => {
  const raw = props.pageUrl || ''
  return raw ? raw.replace(/^https?:\/\//, '').replace(/\/$/, '') : 'example.com/page'
})

// Google truncates the title/description by pixel width, not character count, so
// measure the rendered text against the same fonts the SERP uses. Limits are
// Google's common desktop thresholds.
const TITLE_PX_LIMIT = 580
const DESC_PX_LIMIT = 920
let measureCanvas = null
function measureTextPixels(text, font) {
  if (typeof document === 'undefined') return 0
  if (!measureCanvas) measureCanvas = document.createElement('canvas')
  const ctx = measureCanvas.getContext('2d')
  if (!ctx) return 0
  ctx.font = font
  return Math.round(ctx.measureText(String(text || '')).width)
}
const titlePixels = computed(() => measureTextPixels(snippetTitle.value, '400 20px arial, sans-serif'))
const descPixels = computed(() => measureTextPixels(snippetDescription.value, '400 14px arial, sans-serif'))

// Social (Facebook/X) card preview.
const seoPreviewMode = ref('search')
const socialCardImage = computed(() => localSeoSocialImage.value.trim())
const socialDomain = computed(() => {
  const raw = props.pageUrl || ''
  const host = raw.replace(/^https?:\/\//, '').split('/')[0]
  return (host || props.siteName || 'example.com').toUpperCase()
})

function pickSocialImage() {
  const media = typeof window !== 'undefined' ? window.wp?.media : null
  if (!media) return
  const frame = media({ title: 'Choose social image', multiple: false, library: { type: 'image' } })
  frame.on('select', () => {
    const attachment = frame.state().get('selection').first()?.toJSON()
    if (attachment?.url) localSeoSocialImage.value = attachment.url
  })
  frame.open()
}
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

    const st = props.shopTemplate || {}
    localStActive.value = Boolean(st.active)
    localStMode.value = st.assignment?.mode === 'categories' ? 'categories' : 'all'
    localStCategoryIds.value = Array.isArray(st.assignment?.categoryIds)
      ? st.assignment.categoryIds.map((id) => Number.parseInt(id, 10)).filter(Boolean)
      : []
    localStPreviewTerm.value = Number.parseInt(st.previewTerm, 10) || 0

    const bt = props.blogTemplate || {}
    localBtActive.value = Boolean(bt.active)
    localBtMode.value = bt.assignment?.mode === 'categories' ? 'categories' : 'all'
    localBtCategoryIds.value = Array.isArray(bt.assignment?.categoryIds)
      ? bt.assignment.categoryIds.map((id) => Number.parseInt(id, 10)).filter(Boolean)
      : []
    localBtPreviewTerm.value = Number.parseInt(bt.previewTerm, 10) || 0

    const seo = props.seo || {}
    localSeoTitle.value = typeof seo.title === 'string' ? seo.title : ''
    localSeoDescription.value = typeof seo.description === 'string' ? seo.description : ''
    localSeoSocialImage.value = typeof seo.socialImage === 'string' ? seo.socialImage : ''
    localSeoCanonical.value = typeof seo.canonical === 'string' ? seo.canonical : ''
    localSeoNoindex.value = Boolean(seo.noindex)
    localSeoNofollow.value = Boolean(seo.nofollow)
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

  if (props.isShopTemplate) {
    payload.shopTemplate = {
      active: localStActive.value === true,
      assignment: {
        mode: localStMode.value === 'categories' ? 'categories' : 'all',
        categoryIds: localStCategoryIds.value.map((id) => Number.parseInt(id, 10)).filter(Boolean),
      },
      previewTerm: Number.parseInt(localStPreviewTerm.value, 10) || 0,
    }
  }

  if (props.isBlogTemplate) {
    payload.blogTemplate = {
      active: localBtActive.value === true,
      assignment: {
        mode: localBtMode.value === 'categories' ? 'categories' : 'all',
        categoryIds: localBtCategoryIds.value.map((id) => Number.parseInt(id, 10)).filter(Boolean),
      },
      previewTerm: Number.parseInt(localBtPreviewTerm.value, 10) || 0,
    }
  }

  if (props.supportsSeo) {
    payload.seo = {
      title: localSeoTitle.value.trim(),
      description: localSeoDescription.value.trim(),
      socialImage: localSeoSocialImage.value.trim(),
      canonical: localSeoCanonical.value.trim(),
      noindex: localSeoNoindex.value === true,
      nofollow: localSeoNofollow.value === true,
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

/* SEO tab */
.dsf-seo-settings {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.dsf-seo-snippet {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  background: #fff;
  font-family: arial, sans-serif;
}

.dsf-seo-snippet__site {
  color: #202124;
  font-size: 13px;
}

.dsf-seo-snippet__url {
  color: #4d5156;
  font-size: 12px;
}

.dsf-seo-snippet__title {
  color: #1a0dab;
  font-size: 19px;
  line-height: 1.3;
}

.dsf-seo-snippet__desc {
  color: #4d5156;
  font-size: 13px;
  line-height: 1.45;
}

.dsf-seo-textarea {
  resize: vertical;
}

.dsf-seo-count--over {
  color: #b45309;
  font-weight: 700;
}

.dsf-seo-preview-toggle {
  display: inline-flex;
  gap: 4px;
  padding: 3px;
  margin-bottom: 10px;
  background: var(--dsf-gray-100);
  border-radius: 8px;
}

.dsf-seo-preview-toggle button {
  border: 0;
  background: transparent;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--dsf-gray-500);
  border-radius: 6px;
  cursor: pointer;
}

.dsf-seo-preview-toggle button.is-active {
  background: #fff;
  color: var(--dsf-gray-900);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

/* Facebook/X-style link card preview. */
.dsf-seo-card {
  border: 1px solid var(--dsf-gray-200);
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
  max-width: 480px;
}

.dsf-seo-card__media {
  aspect-ratio: 1200 / 630;
  background: var(--dsf-gray-100);
}

.dsf-seo-card__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-seo-card__media--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 16px;
  color: var(--dsf-gray-400);
  font-size: 12px;
}

.dsf-seo-card__body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 14px;
  border-top: 1px solid var(--dsf-gray-200);
  background: #f2f3f5;
  font-family: arial, sans-serif;
}

.dsf-seo-card__domain {
  color: #606770;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.dsf-seo-card__title {
  color: #1d2129;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.dsf-seo-card__desc {
  color: #606770;
  font-size: 13px;
  line-height: 1.4;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* Pixel-width meter under the title/description fields. */
.dsf-seo-meter {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.dsf-seo-meter__bar {
  flex: 1;
  height: 4px;
  border-radius: 999px;
  background: var(--dsf-gray-200);
  overflow: hidden;
}

.dsf-seo-meter__bar span {
  display: block;
  height: 100%;
  background: var(--dsf-green-500, #16a34a);
  border-radius: 999px;
  transition: width 0.15s ease;
}

.dsf-seo-meter__bar span.dsf-seo-meter__fill--over {
  background: #b45309;
}

.dsf-seo-meter__label {
  font-size: 11px;
  color: var(--dsf-gray-500);
  white-space: nowrap;
  min-width: 74px;
  text-align: right;
}

</style>
