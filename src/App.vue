<template>
  <div ref="editorRoot" class="dsf-editor">
    <!-- Header -->
    <EditorHeader 
      :title="pageTitle"
      :is-saving="isSaving"
      :preview-mode="previewMode"
      :post-type="postType"
      :layout-type="layoutType"
      @update:title="updateTitle"
      @view="openView"
      @save="savePage"
      @set-preview-mode="setPreviewMode"
      @open-theme="showThemePanel = true"
      @open-settings="showPageSettings = true"
      @save-as-template="openSaveTemplate"
    />
    
    <!-- Main Content -->
    <div class="dsf-editor__body">
      <!-- Canvas -->
      <div 
        class="dsf-canvas"
        :class="{ 'dsf-canvas--with-panel': selectedBlock || showThemePanel }"
      >
        <div 
          class="dsf-canvas__inner"
          :style="canvasStyle"
        >
          <!-- Empty State -->
          <div v-if="blocks.length === 0" class="dsf-canvas-empty">
            <div class="dsf-canvas-empty__icon">
              <LayoutTemplate :size="48" :stroke-width="1.5" />
            </div>
            <h3 class="dsf-canvas-empty__title">{{ isTemplateEditor ? 'Your template is empty' : 'Your page is empty' }}</h3>
            <p v-if="isTemplateEditor && availableTemplateBlocksCount === 0" class="dsf-canvas-empty__text">
              No {{ layoutType === 'footer' ? 'footer' : 'header' }} blocks are available yet.
            </p>
            <p v-else class="dsf-canvas-empty__text">Add blocks from the library to get started</p>
          </div>
          
          <!-- Blocks -->
          <draggable 
            v-else
            v-model="blocks"
            :disabled="singleBlockTemplate"
            item-key="id"
            handle=".dsf-block-toolbar__btn--drag"
            ghost-class="dsf-block--ghost"
            @end="onDragEnd"
          >
            <template #item="{ element, index }">
              <BlockWrapper
                :block="element"
                :index="index"
                :is-selected="selectedBlockId === element.id"
                :preview-mode="previewMode"
                :allow-reorder="!singleBlockTemplate"
                @select="selectBlock(element)"
                @move-up="moveBlockUp(index)"
                @move-down="moveBlockDown(index)"
                @delete="deleteBlock(index)"
                @open-settings="openBlockSettings(element)"
                @save-block="handleSaveBlock(element)"
              />
            </template>
          </draggable>

          <div v-if="canAddBlock" class="dsf-canvas__add-zone">
            <button
              type="button"
              class="dsf-add-block-btn"
              @click="openBlockLibrary"
            >
              <Plus :size="18" />
              {{ isTemplateEditor ? 'Add Template Block' : 'Add Block' }}
            </button>
          </div>
        </div>
      </div>
      
      <!-- Side Panel -->
      <SidePanel
        v-if="selectedBlock"
        :block="selectedBlock"
        :block-definition="getBlockDefinition(selectedBlock.type)"
        @close="selectedBlock = null; selectedBlockId = null"
        @update:settings="updateBlockSettings"
      />
      
      <!-- Theme Panel -->
      <ThemePanel
        v-if="showThemePanel"
        :settings="pageSettings"
        :default-theme="SITE_DEFAULT_THEME"
        :can-undo="themeHistory.length > 0"
        :post-type="postType"
        :layout-templates="layoutTemplates"
        :layout-create-urls="layoutCreateUrls"
        @close="showThemePanel = false"
        @update:settings="updatePageSettings"
        @undo-theme="undoThemeChange"
        @restore-defaults="restoreSiteThemeDefaults"
      />

      <PageSettingsModal
        :visible="showPageSettings"
        :title="pageTitle"
        :slug="pageSlug"
        :status="currentPostStatus"
        :parent-id="pageParentId"
        :parent-pages="parentPages"
        :popup="pageSettings.popup"
        :popup-id="pageSettings.popupId || 0"
        :popups="availablePopups"
        :popup-create-url="popupCreateUrl"
        :popup-edit-url-base="popupEditUrlBase"
        @close="showPageSettings = false"
        @save="updatePageDetails"
      />
    </div>
    
    <!-- Block Library Modal -->
    <BlockLibrary
      v-if="showBlockLibrary"
      :categories="blockCategories"
      :saved-blocks="availableSavedBlocks"
      :presets="blockPresets"
      :templates="availableTemplates"
      @close="showBlockLibrary = false"
      @add="addBlock"
      @insert-saved="insertSavedBlock"
      @delete-saved="deleteSavedBlock"
      @insert-preset="insertPreset"
      @insert-template="insertTemplate"
      @delete-template="deleteTemplate"
    />
    
    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      :visible="deleteConfirmVisible"
      title="Delete Block?"
      message="This block will be removed from your page. This action cannot be undone."
      confirm-text="Delete"
      cancel-text="Cancel"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    />

    <!-- Save block to library -->
    <SaveBlockModal
      :visible="saveModalVisible"
      :suggested-name="saveModalSuggestedName"
      :existing="saveModalExisting"
      :show-folder="true"
      :folders="savedBlockFolders"
      @save="onSaveBlockConfirm"
      @cancel="saveModalVisible = false"
    />

    <!-- Delete saved block confirmation -->
    <ConfirmDialog
      :visible="savedDeleteVisible"
      title="Delete saved block?"
      :message="`This removes &quot;${savedToDelete?.name || ''}&quot; from the library for everyone. This cannot be undone.`"
      confirm-text="Delete"
      cancel-text="Cancel"
      variant="danger"
      @confirm="confirmDeleteSavedBlock"
      @cancel="cancelDeleteSavedBlock"
    />

    <!-- Save page as template -->
    <SaveBlockModal
      :visible="templateModalVisible"
      title="Save page as template"
      :suggested-name="templateSuggestedName"
      :existing="[]"
      @save="onSaveTemplateConfirm"
      @cancel="templateModalVisible = false"
    />

    <!-- Delete template confirmation -->
    <ConfirmDialog
      :visible="templateDeleteVisible"
      title="Delete template?"
      :message="`This removes &quot;${templateToDelete?.name || ''}&quot; from the library for everyone. This cannot be undone.`"
      confirm-text="Delete"
      cancel-text="Cancel"
      variant="danger"
      @confirm="confirmDeleteTemplate"
      @cancel="cancelDeleteTemplate"
    />

    <!-- Transient toast -->
    <Teleport to="body">
      <Transition name="dsf-toast">
        <div v-if="toast.visible" class="dsf-toast" :class="`dsf-toast--${toast.type}`">{{ toast.message }}</div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, createApp, nextTick, watch } from 'vue'
import draggable from 'vuedraggable'
import { Plus, LayoutTemplate } from 'lucide-vue-next'
import { gsap } from 'gsap'

// Components
import EditorHeader from './components/EditorHeader.vue'
import BlockWrapper from './components/BlockWrapper.vue'
import SidePanel from './components/SidePanel.vue'
import ThemePanel from './components/ThemePanel.vue'
import BlockLibrary from './components/BlockLibrary.vue'
import PageSettingsModal from './components/PageSettingsModal.vue'
import ConfirmDialog from './components/common/ConfirmDialog.vue'
import SaveBlockModal from './components/SaveBlockModal.vue'
import FrontendApp from './frontend/FrontendApp.vue'
import { applyThemeToBlocks, resolveThemeKey } from './utils/themeSync'
import { canAddTemplateBlock, isSingleBlockTemplate, normalizeTemplateBlocks } from './utils/templateBlockRules'

// Get WordPress data
const wpData = window.dsfEditorData || {}
const postType = wpData.postType === 'dsf_layout' ? 'dsf_layout' : 'page'
const layoutType = wpData.layoutType === 'footer' ? 'footer' : 'header'
const isTemplateEditor = postType === 'dsf_layout'
const layoutTemplates = computed(() => wpData.layoutTemplates || { headers: [], footers: [] })
const layoutCreateUrls = computed(() => wpData.layoutCreateUrls || {})
const availableForms = Array.isArray(wpData.forms) ? wpData.forms : []

hydrateFormBlockDefinition()

function hydrateFormBlockDefinition() {
  const formOptions = buildFormOptions(availableForms)

  const formEmbedField = wpData.blocks?.['form-embed']?.settings?.formId
  if (formEmbedField) formEmbedField.options = formOptions

  const formWithContentField = wpData.blocks?.['form-with-content']?.settings?.formId
  if (formWithContentField) formWithContentField.options = formOptions
}

function buildFormOptions(forms) {
  const options = { 'Select a form': '' }

  if (!Array.isArray(forms) || !forms.length) {
    return options
  }

  forms.forEach((form) => {
    const formId = String(form?.id || '').trim()
    if (!formId) return

    const title = (form?.title || '').trim() || `Form #${formId}`
    options[`${title} (ID: ${formId})`] = formId
  })

  return options
}

// Font loading state
const loadedFonts = ref(new Set())
const googleFontNames = new Set([
  'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Outfit',
  'Source Sans 3', 'Nunito', 'Raleway', 'Playfair Display', 'Merriweather',
  'Lora', 'DM Sans', 'Work Sans', 'Oswald', 'Ubuntu', 'Rubik', 'Manrope',
  'Space Grotesk',
])

function loadGoogleFont(fontFamily) {
  if (!fontFamily) return
  const fontName = String(fontFamily).split(',')[0].trim().replace(/^['"]|['"]$/g, '')
  if (!googleFontNames.has(fontName)) return
  if (loadedFonts.value.has(fontName)) return
  
  loadedFonts.value.add(fontName)
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/ /g, '+')}:wght@300;400;500;600;700&display=swap`
  document.head.appendChild(link)
}

// Theme defaults & sync helpers
const FALLBACK_THEME = {
  primaryColor: '#2C5F5D',
  secondaryColor: '#1E40AF',
  textColor: '#1F2937',
  backgroundColor: '#FFFFFF',
  headingFont: '',
  bodyFont: '',
}
const SITE_DEFAULT_THEME = Object.freeze({ ...FALLBACK_THEME, ...(wpData.defaultTheme || {}) })
const DEFAULT_THEME = SITE_DEFAULT_THEME

const DEFAULT_LAYOUT = {
  containerWidth: 1800,
  contentPadding: 10,
  showHeader: true,
  showFooter: true,
  headerTemplateId: 0,
  footerTemplateId: 0,
  template: 'default',
}


const themeLinkedSettings = (() => {
  const linked = {}
  const registeredBlocks = wpData.blocks || {}
  Object.values(registeredBlocks).forEach((block) => {
    if (!block?.id || !block?.settings) return
    Object.entries(block.settings).forEach(([key, config]) => {
      // Pass both default value and key name to resolveThemeKey
      const themeKey = resolveThemeKey(config?.default, key)
      if (!themeKey) return
      if (!linked[block.id]) linked[block.id] = {}
      linked[block.id][key] = themeKey
    })
  })
  return linked
})()

function syncThemeToBlocks(oldTheme, newTheme, forceChangedThemeKeys = false) {
  if (!oldTheme || !newTheme) return
  if (!blocks.value.length) return

  blocks.value = applyThemeToBlocks(
    blocks.value,
    oldTheme,
    newTheme,
    themeLinkedSettings,
    { forceChangedThemeKeys }
  )
}

// State
const blocks = ref(normalizeTemplateBlocks(wpData.pageData?.blocks, postType, layoutType))
const initialSettings = wpData.pageData?.settings || {}
const pageSettings = ref({
  theme: { ...DEFAULT_THEME, ...(initialSettings.theme || {}) },
  layout: { ...DEFAULT_LAYOUT, ...(initialSettings.layout || {}) },
  popup: { ...(initialSettings.popup || {}) },
  popupId: Number.parseInt(initialSettings.popupId, 10) || 0,
})

const availablePopups = ref(Array.isArray(wpData.popups) ? wpData.popups : [])
const availableSavedBlocks = ref([])
const blockPresets = Array.isArray(wpData.blockPresets) ? wpData.blockPresets : []
const popupCreateUrl = wpData.popupCreateUrl || ''
const popupEditUrlBase = wpData.popupEditUrlBase || ''

const pageTitle = ref(wpData.postTitle || 'Untitled Page')
const currentPostStatus = ref(wpData.postStatus || 'draft')
const currentViewUrl = ref(wpData.viewUrl || '')
const currentPreviewUrl = ref(wpData.previewUrl || '')
const pageSlug = ref(wpData.postSlug || '')
const pageParentId = ref(Number.parseInt(wpData.postParent, 10) || 0)
const parentPages = computed(() => Array.isArray(wpData.parentPages) ? wpData.parentPages : [])
const isSaving = ref(false)
const previewMode = ref('desktop')
const selectedBlock = ref(null)
const selectedBlockId = ref(null)
const showBlockLibrary = ref(false)
const showThemePanel = ref(false)
const showPageSettings = ref(false)
const deleteConfirmVisible = ref(false)
const pendingDeleteIndex = ref(null)
const editorRoot = ref(null)
const themeHistory = ref([])
let lastThemeHistoryKey = ''
let lastThemeHistoryAt = 0
const singleBlockTemplate = isSingleBlockTemplate(postType, layoutType)
const canAddBlock = computed(() => canAddTemplateBlock(blocks.value, postType, layoutType))

// Computed
const blockCategories = computed(() => {
  const registeredBlocks = wpData.blocks || {}
  const categories = {
    heroes: { label: 'Heroes', icon: 'layout', blocks: [] },
    content: { label: 'Content', icon: 'file-text', blocks: [] },
    marketing: { label: 'Marketing', icon: 'target', blocks: [] },
    ecommerce: { label: 'Ecommerce', icon: 'shopping-cart', blocks: [] },
    footers: { label: 'Footers', icon: 'layout-template', blocks: [] },
  }

  const allBlocks = Object.values(registeredBlocks)
  const allowedBlocks = allBlocks.filter((block) => isBlockAllowedInCurrentEditor(block))

  if (isTemplateEditor) {
    const label = layoutType === 'footer' ? 'Footer Blocks' : 'Header Blocks'
    const templateBlocks = canAddBlock.value
      ? allowedBlocks.filter((block) => block.category === 'content')
      : []
    return {
      content: { label, icon: 'layout-template', blocks: templateBlocks },
    }
  }

  // Define exact order for each category
  const heroOrder = ['hero', 'landing-hero', 'bento-hero', 'spotlight-hero', 'expander-hero', 'duo-hero', 'featured-promo-banner']
  const contentOrder = ['content', 'faq', 'text-image', 'landing-block-explorer', 'landing-block-ready', 'landing-product-story', 'landing-engagement-suite', 'landing-trust-workflow', 'features-grid', 'testimonials', 'form-embed', 'form-with-content']
  const marketingOrder = ['landing-progress-header', 'pricing', 'countdown', 'promo-banner', 'cta-banner', 'brand-carousel']
  const ecommerceOrder = ['ecommerce-showcase', 'featured-product-banner', 'product-grid']
  const footerOrder = ['landing-marketing-footer']
  const heroBlockIds = new Set(heroOrder)

  allowedBlocks.forEach(block => {
    if (heroBlockIds.has(block.id)) {
      categories.heroes.blocks.push(block)
    } else if (categories[block.category]) {
      categories[block.category].blocks.push(block)
    }
  })

  // Sort blocks within categories
  categories.heroes.blocks.sort((a, b) => heroOrder.indexOf(a.id) - heroOrder.indexOf(b.id))
  categories.content.blocks.sort((a, b) => contentOrder.indexOf(a.id) - contentOrder.indexOf(b.id))
  categories.marketing.blocks.sort((a, b) => marketingOrder.indexOf(a.id) - marketingOrder.indexOf(b.id))
  categories.ecommerce.blocks.sort((a, b) => ecommerceOrder.indexOf(a.id) - ecommerceOrder.indexOf(b.id))
  categories.footers.blocks.sort((a, b) => footerOrder.indexOf(a.id) - footerOrder.indexOf(b.id))

  return categories
})

const availableTemplateBlocksCount = computed(() => {
  if (!isTemplateEditor) return 0
  return Object.values(blockCategories.value).reduce((total, category) => {
    return total + (Array.isArray(category?.blocks) ? category.blocks.length : 0)
  }, 0)
})

const canvasStyle = computed(() => {
  const widths = {
    desktop: '1800px',
    tablet: '768px',
    mobile: '375px',
  }

  const theme = pageSettings.value?.theme || DEFAULT_THEME
  const layout = pageSettings.value?.layout || DEFAULT_LAYOUT

  const style = {
    maxWidth: widths[previewMode.value],
    backgroundColor: theme.backgroundColor,
    '--dsf-theme-primary': theme.primaryColor,
    '--dsf-theme-secondary': theme.secondaryColor,
    '--dsf-theme-text': theme.textColor,
    '--dsf-theme-background': theme.backgroundColor,
    '--dsf-theme-container-width': `${layout.containerWidth || DEFAULT_LAYOUT.containerWidth}px`,
    '--dsf-theme-content-padding': `${layout.contentPadding || DEFAULT_LAYOUT.contentPadding}px`,
  }
  
  // Font family: per-page wins; otherwise fall back to admin Typography override.
  const adminTypography = wpData?.themeTypography || {}
  const headingFont = theme.headingFont || adminTypography.headingFont || ''
  const bodyFont = theme.bodyFont || adminTypography.bodyFont || ''
  if (headingFont) {
    style['--dsf-theme-heading-font'] = headingFont
  }
  if (bodyFont) {
    style['--dsf-theme-body-font'] = bodyFont
  }

  // Typography scale tokens (computed server-side from theme.json + admin overrides).
  if (adminTypography.tokens && typeof adminTypography.tokens === 'object') {
    Object.assign(style, adminTypography.tokens)
  }

  return style
})

// Methods
function getBlockDefinition(blockType) {
  return wpData.blocks?.[blockType] || null
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
}

function animateAfterRender(callback) {
  if (prefersReducedMotion()) return
  nextTick(() => callback())
}

function animatePanel(selector, fromX) {
  animateAfterRender(() => {
    const panel = document.querySelector(selector)
    if (!panel) return
    gsap.fromTo(panel,
      { autoAlpha: 0, x: fromX, scale: 0.985 },
      { autoAlpha: 1, x: 0, scale: 1, duration: 0.36, ease: 'power3.out', clearProps: 'transform,opacity,visibility' }
    )
  })
}

function selectBlock(block) {
  selectedBlock.value = block
  selectedBlockId.value = block.id
  showThemePanel.value = false
  animateAfterRender(() => {
    const wrapper = document.getElementById('block-' + block.id)
    const toolbar = wrapper?.querySelector('.dsf-block-toolbar')
    if (!toolbar) return
    gsap.fromTo(toolbar,
      { autoAlpha: 0, y: -8, scale: 0.92 },
      { autoAlpha: 1, y: 0, scale: 1, duration: 0.28, ease: 'back.out(1.7)', clearProps: 'transform,opacity,visibility' }
    )
  })
}

function openBlockSettings(block) {
  selectedBlock.value = block
  selectedBlockId.value = block.id
  showThemePanel.value = false
}

function updateBlockSettings(settings) {
  if (selectedBlock.value) {
    selectedBlock.value.settings = { ...selectedBlock.value.settings, ...settings }
  }
}

function addBlock(blockDefinition) {
  if (!isBlockAllowedInCurrentEditor(blockDefinition)) {
    alert('This block is not available for this template type.')
    return
  }

  if (!canAddBlock.value) {
    showBlockLibrary.value = false
    alert('A header template can contain only one header. Delete the current header before choosing another design.')
    return
  }

  const newBlock = {
    id: 'block_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
    type: blockDefinition.id,
    // A saved block/preset carries its own stored settings; layer them over the
    // current defaults so keys added to the schema since it was saved still exist
    // (the saved values win). A fresh block starts from defaults only.
    settings: blockDefinition.savedSettings
      ? { ...getDefaultSettings(blockDefinition), ...JSON.parse(JSON.stringify(blockDefinition.savedSettings)) }
      : getDefaultSettings(blockDefinition),
  }
  
  blocks.value.push(newBlock)
  showBlockLibrary.value = false
  
  // Auto-select new block and scroll into view
  setTimeout(() => {
    selectBlock(newBlock)
    
    // Scroll to the new block
    const element = document.getElementById('block-' + newBlock.id)
    if (element) {
      if (!prefersReducedMotion()) {
        gsap.fromTo(element,
          { autoAlpha: 0, y: 28, scale: 0.985 },
          { autoAlpha: 1, y: 0, scale: 1, duration: 0.48, ease: 'power3.out', clearProps: 'transform,opacity,visibility' }
        )
      }
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, 100)
}

// ---- Saved Blocks (reusable block library) ----

async function loadSavedBlocks() {
  if (!wpData.ajaxUrl) return
  try {
    const formData = new FormData()
    formData.append('action', 'dsf_list_saved_blocks')
    formData.append('nonce', wpData.nonce)
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success && Array.isArray(json.data?.savedBlocks)) {
      availableSavedBlocks.value = json.data.savedBlocks
    }
  } catch (e) {
    // Non-fatal: the picker simply shows no saved blocks.
  }
}

// Save-block modal state. handleSaveBlock opens it; onSaveBlockConfirm performs
// the AJAX (creating a new saved block, or updating an existing one in place).
const saveModalVisible = ref(false)
const saveModalBlock = ref(null)
const saveModalSuggestedName = ref('')
const saveModalExisting = computed(() => {
  const type = saveModalBlock.value?.type
  if (!type) return []
  return availableSavedBlocks.value.filter((b) => b.type === type)
})
const savedBlockFolders = computed(() => {
  const set = new Set()
  availableSavedBlocks.value.forEach((b) => { if (b.category) set.add(b.category) })
  return [...set].sort((a, b) => a.localeCompare(b))
})

function handleSaveBlock(block) {
  if (!block?.type) return
  const def = getBlockDefinition(block.type)
  saveModalBlock.value = block
  saveModalSuggestedName.value = (def && def.name) ? def.name : 'Saved block'
  saveModalVisible.value = true
}

async function onSaveBlockConfirm({ name, id, category }) {
  const block = saveModalBlock.value
  saveModalVisible.value = false
  if (!block?.type) return

  try {
    const formData = new FormData()
    formData.append('action', 'dsf_save_block')
    formData.append('nonce', wpData.nonce)
    formData.append('name', name)
    formData.append('type', block.type)
    formData.append('settings', JSON.stringify(block.settings || {}))
    formData.append('category', category || '')
    if (id) formData.append('id', id)
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success && json.data) {
      const idx = availableSavedBlocks.value.findIndex((b) => b.id === json.data.id)
      if (idx >= 0) {
        availableSavedBlocks.value.splice(idx, 1, json.data)
      } else {
        availableSavedBlocks.value = [...availableSavedBlocks.value, json.data]
      }
      availableSavedBlocks.value.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
      showToast(id ? 'Saved block updated' : 'Block saved to library')
    } else {
      showToast(json.data?.message || 'Could not save this block.', 'error')
    }
  } catch (e) {
    showToast('Could not save this block.', 'error')
  }
}

function insertSavedBlock(saved) {
  if (!saved?.type) return
  const def = (wpData.blocks || {})[saved.type]
  if (!def) {
    alert('This saved block type is no longer available.')
    return
  }
  // Reuse the normal insertion path, but seed it with the saved settings.
  addBlock({ ...def, id: saved.type, savedSettings: saved.settings || {} })
}

function insertPreset(preset) {
  if (!preset?.type) return
  const def = (wpData.blocks || {})[preset.type]
  if (!def) {
    alert('This preset is not available for this site.')
    return
  }
  addBlock({ ...def, id: preset.type, savedSettings: preset.settings || {} })
}

// ---- Templates (reusable groups of blocks) ----

const availableTemplates = ref([])
const templateModalVisible = ref(false)
const templateSuggestedName = ref('')

async function loadTemplates() {
  if (!wpData.ajaxUrl) return
  try {
    const formData = new FormData()
    formData.append('action', 'dsf_list_templates')
    formData.append('nonce', wpData.nonce)
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success && Array.isArray(json.data?.templates)) {
      availableTemplates.value = json.data.templates
    }
  } catch (e) {
    // Non-fatal: picker simply shows no templates.
  }
}

function openSaveTemplate() {
  if (!blocks.value.length) {
    showToast('Add some blocks before saving a template.', 'error')
    return
  }
  templateSuggestedName.value = pageTitle.value && pageTitle.value !== 'Untitled Page'
    ? `${pageTitle.value} template`
    : 'Page template'
  templateModalVisible.value = true
}

async function onSaveTemplateConfirm({ name }) {
  templateModalVisible.value = false
  // Persist type + settings only; ids are regenerated on insert.
  const payloadBlocks = blocks.value.map((b) => ({ type: b.type, settings: b.settings || {} }))
  try {
    const formData = new FormData()
    formData.append('action', 'dsf_save_template')
    formData.append('nonce', wpData.nonce)
    formData.append('name', name)
    formData.append('kind', 'page')
    formData.append('blocks', JSON.stringify(payloadBlocks))
    formData.append('theme', JSON.stringify(pageSettings.value?.theme || {}))
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success && json.data) {
      const idx = availableTemplates.value.findIndex((t) => t.id === json.data.id)
      if (idx >= 0) availableTemplates.value.splice(idx, 1, json.data)
      else availableTemplates.value = [...availableTemplates.value, json.data]
      availableTemplates.value.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
      showToast('Page saved as template')
    } else {
      showToast(json.data?.message || 'Could not save this template.', 'error')
    }
  } catch (e) {
    showToast('Could not save this template.', 'error')
  }
}

function insertTemplate(template) {
  const list = Array.isArray(template?.blocks) ? template.blocks : []
  if (!list.length) return
  let added = 0
  list.forEach((tplBlock) => {
    const def = (wpData.blocks || {})[tplBlock.type]
    if (!def) return // skip block types not available on this site
    const newBlock = {
      id: 'block_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
      type: tplBlock.type,
      // Layer saved settings over current defaults (see addBlock) so the template
      // survives block-schema changes.
      settings: { ...getDefaultSettings(def), ...JSON.parse(JSON.stringify(tplBlock.settings || {})) },
    }
    blocks.value.push(newBlock)
    added++
  })
  showBlockLibrary.value = false
  showToast(added ? `Inserted ${added} block${added === 1 ? '' : 's'} from template` : 'No usable blocks in this template', added ? 'success' : 'error')
}

// Template deletion via the shared confirm dialog.
const templateDeleteVisible = ref(false)
const templateToDelete = ref(null)

function deleteTemplate(template) {
  if (!template?.id) return
  templateToDelete.value = template
  templateDeleteVisible.value = true
}

function cancelDeleteTemplate() {
  templateDeleteVisible.value = false
  templateToDelete.value = null
}

async function confirmDeleteTemplate() {
  const template = templateToDelete.value
  templateDeleteVisible.value = false
  if (!template?.id) return
  try {
    const formData = new FormData()
    formData.append('action', 'dsf_delete_template')
    formData.append('nonce', wpData.nonce)
    formData.append('id', template.id)
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success) {
      availableTemplates.value = availableTemplates.value.filter((t) => t.id !== template.id)
      showToast('Template deleted')
    } else {
      showToast(json.data?.message || 'Could not delete this template.', 'error')
    }
  } catch (e) {
    showToast('Could not delete this template.', 'error')
  } finally {
    templateToDelete.value = null
  }
}

// Saved-block deletion uses the shared confirm dialog instead of window.confirm.
const savedDeleteVisible = ref(false)
const savedToDelete = ref(null)

function deleteSavedBlock(saved) {
  if (!saved?.id) return
  savedToDelete.value = saved
  savedDeleteVisible.value = true
}

function cancelDeleteSavedBlock() {
  savedDeleteVisible.value = false
  savedToDelete.value = null
}

async function confirmDeleteSavedBlock() {
  const saved = savedToDelete.value
  savedDeleteVisible.value = false
  if (!saved?.id) return
  try {
    const formData = new FormData()
    formData.append('action', 'dsf_delete_saved_block')
    formData.append('nonce', wpData.nonce)
    formData.append('id', saved.id)
    const response = await fetch(wpData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
    const json = await response.json()
    if (json.success) {
      availableSavedBlocks.value = availableSavedBlocks.value.filter((b) => b.id !== saved.id)
      showToast('Saved block deleted')
    } else {
      showToast(json.data?.message || 'Could not delete this saved block.', 'error')
    }
  } catch (e) {
    showToast('Could not delete this saved block.', 'error')
  } finally {
    savedToDelete.value = null
  }
}

// Lightweight transient toast for editor actions.
const toast = ref({ visible: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  toast.value = { visible: true, message, type }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value.visible = false }, 3000)
}

function getDefaultSettings(blockDef) {
  const defaults = {}

  if (blockDef.settings) {
    Object.entries(blockDef.settings).forEach(([key, config]) => {
      // Use helper to determine if this setting maps to a theme property
      const themeKey = resolveThemeKey(config.default, key)
      if (themeKey && pageSettings.value?.theme?.[themeKey]) {
        defaults[key] = pageSettings.value.theme[themeKey]
      } else {
        defaults[key] = config.default
      }
    })
  }
  
  return defaults
}

function deleteBlock(index) {
  pendingDeleteIndex.value = index
  deleteConfirmVisible.value = true
}

function confirmDelete() {
  if (pendingDeleteIndex.value !== null) {
    blocks.value.splice(pendingDeleteIndex.value, 1)
    selectedBlock.value = null
    selectedBlockId.value = null
  }
  cancelDelete()
}

function cancelDelete() {
  deleteConfirmVisible.value = false
  pendingDeleteIndex.value = null
}

function moveBlockUp(index) {
  if (index > 0) {
    const block = blocks.value.splice(index, 1)[0]
    blocks.value.splice(index - 1, 0, block)
  }
}

function moveBlockDown(index) {
  if (index < blocks.value.length - 1) {
    const block = blocks.value.splice(index, 1)[0]
    blocks.value.splice(index + 1, 0, block)
  }
}

function onDragEnd() {
  // Blocks array is already updated by v-model
}

function getBlockScope(blockDefinition) {
  return blockDefinition?.template_scope || 'page'
}

function isBlockAllowedInCurrentEditor(blockDefinition) {
  const scope = getBlockScope(blockDefinition)

  if (!isTemplateEditor) {
    return scope === 'page'
  }

  return scope === layoutType
}

function openBlockLibrary() {
  if (!canAddBlock.value) {
    showBlockLibrary.value = false
    return
  }
  if (isTemplateEditor && availableTemplateBlocksCount.value === 0) {
    const typeLabel = layoutType === 'footer' ? 'footer' : 'header'
    alert(`No ${typeLabel} blocks are available yet.`)
    return
  }
  showBlockLibrary.value = true
}

function setPreviewMode(mode) {
  previewMode.value = mode
}

function updateTitle(newTitle) {
  pageTitle.value = newTitle
}

function updatePageDetails(details) {
  pageTitle.value = details.title || pageTitle.value
  pageSlug.value = details.slug || ''
  currentPostStatus.value = details.status === 'publish' ? 'publish' : 'draft'
  pageParentId.value = Number.parseInt(details.parentId, 10) || 0
  pageSettings.value = {
    ...pageSettings.value,
    popup: { ...(details.popup || {}) },
    popupId: Number.parseInt(details.popupId, 10) || 0,
  }
  showPageSettings.value = false
}

function updatePageSettings(newSettings) {
  const oldTheme = { ...(pageSettings.value?.theme || DEFAULT_THEME) }
  const { _themeChangeKey: themeChangeKey = '', ...settingsPatch } = newSettings || {}
  const nextSettings = { ...pageSettings.value, ...settingsPatch }

  if (settingsPatch?.theme) {
    const now = Date.now()
    const coalescesWithPrevious = themeChangeKey
      && themeChangeKey === lastThemeHistoryKey
      && now - lastThemeHistoryAt < 900

    if (!coalescesWithPrevious) {
      themeHistory.value = [...themeHistory.value.slice(-19), oldTheme]
    }
    lastThemeHistoryKey = themeChangeKey
    lastThemeHistoryAt = now
  }

  pageSettings.value = nextSettings

  if (settingsPatch?.theme) {
    syncThemeToBlocks(oldTheme, nextSettings.theme || DEFAULT_THEME, true)
  }
}

function undoThemeChange() {
  const previousTheme = themeHistory.value.at(-1)
  if (!previousTheme) return

  const currentTheme = { ...(pageSettings.value?.theme || DEFAULT_THEME) }
  themeHistory.value = themeHistory.value.slice(0, -1)
  lastThemeHistoryKey = ''
  pageSettings.value = { ...pageSettings.value, theme: { ...previousTheme } }
  syncThemeToBlocks(currentTheme, previousTheme, true)
}

function restoreSiteThemeDefaults() {
  updatePageSettings({
    theme: { ...SITE_DEFAULT_THEME },
    _themeChangeKey: 'restore-site-defaults',
  })
}

function isDefaultUntitledTitle(rawTitle) {
  const normalized = (rawTitle || '').trim().toLowerCase()
  return [
    'untitled',
    'untitled page',
    'untitled header template',
    'untitled footer template',
  ].includes(normalized)
}

function hasMeaningfulTitle(rawTitle) {
  const title = (rawTitle || '').trim()
  return title.length > 0 && !isDefaultUntitledTitle(title)
}

function promptForTitle() {
  const typeLabel = postType === 'dsf_layout'
    ? (layoutType === 'footer' ? 'footer template' : 'header template')
    : 'page'

  const suggested = hasMeaningfulTitle(pageTitle.value) ? pageTitle.value.trim() : ''
  const entered = window.prompt(`Enter a name for this ${typeLabel}:`, suggested)
  if (entered === null) {
    return null
  }

  const cleaned = entered.trim()
  if (!cleaned) {
    alert('Please enter a name before saving.')
    return null
  }

  return cleaned
}

async function savePage(options = {}) {
  const { status, silent, skipSnapshot, requireNamePrompt = true } = options
  isSaving.value = true

  let titleToSave = (pageTitle.value || '').trim()
  const needsNameOnFirstSave = requireNamePrompt
    && currentPostStatus.value === 'draft'
    && !hasMeaningfulTitle(titleToSave)

  if (needsNameOnFirstSave) {
    const enteredTitle = promptForTitle()
    if (!enteredTitle) {
      isSaving.value = false
      return false
    }
    titleToSave = enteredTitle
    pageTitle.value = enteredTitle
  }

  const statusToSave = status || currentPostStatus.value || 'draft'

  let htmlSnapshot = ''
  if (!skipSnapshot) {
    try {
      htmlSnapshot = await generateHtmlSnapshot()
    } catch (error) {
      console.warn('Snapshot generation failed:', error)
    }
  }

  if (htmlSnapshot) {
    const maxSnapshotBytes = Number.isFinite(wpData.snapshotMaxBytes)
      ? wpData.snapshotMaxBytes
      : 2 * 1024 * 1024
    const snapshotBytes = new Blob([htmlSnapshot]).size
    if (snapshotBytes > maxSnapshotBytes) {
      console.warn(
        `Snapshot skipped (size ${snapshotBytes} bytes exceeds ${maxSnapshotBytes}).`
      )
      htmlSnapshot = ''
    }
  }

  const formData = new FormData()
  formData.append('action', 'dsf_save_page')
  formData.append('nonce', wpData.nonce)
  formData.append('post_id', wpData.postId)
  formData.append('blocks', JSON.stringify(blocks.value))
  formData.append('settings', JSON.stringify(pageSettings.value))
  formData.append('title', titleToSave)
  formData.append('slug', pageSlug.value || '')
  formData.append('parent_id', String(pageParentId.value || 0))
  if (postType === 'dsf_layout') {
    formData.append('layout_type', layoutType)
  }
  if (htmlSnapshot) {
    formData.append('html_snapshot', htmlSnapshot)
  }
  if (statusToSave) {
    formData.append('status', statusToSave)
  }

  async function attemptSave(payload) {
    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      body: payload,
    })

    let data
    try {
      data = await response.json()
    } catch (parseError) {
      const text = await response.text()
      throw new Error(text || 'Invalid JSON response')
    }

    if (!response.ok || !data?.success) {
      const message = data?.data?.message || 'Unknown error'
      throw new Error(message)
    }

    return data?.data || {}
  }

  try {
    const saveResult = await attemptSave(formData)
    if (saveResult?.post_status) {
      currentPostStatus.value = saveResult.post_status
    }
    if (saveResult?.post_title) {
      pageTitle.value = saveResult.post_title
    }
    if (Object.prototype.hasOwnProperty.call(saveResult, 'post_name')) {
      pageSlug.value = saveResult.post_name || ''
    }
    if (Object.prototype.hasOwnProperty.call(saveResult, 'post_parent')) {
      pageParentId.value = Number.parseInt(saveResult.post_parent, 10) || 0
    }
    if (saveResult?.permalink) {
      currentViewUrl.value = saveResult.permalink
    }
    if (saveResult?.preview_url) {
      currentPreviewUrl.value = saveResult.preview_url
    }
    console.log('Page saved successfully')
    return true
  } catch (error) {
    console.error('Save error:', error)
    if (htmlSnapshot) {
      try {
        const fallbackData = new FormData()
        fallbackData.append('action', 'dsf_save_page')
        fallbackData.append('nonce', wpData.nonce)
        fallbackData.append('post_id', wpData.postId)
        fallbackData.append('blocks', JSON.stringify(blocks.value))
        fallbackData.append('settings', JSON.stringify(pageSettings.value))
        fallbackData.append('title', titleToSave)
        fallbackData.append('slug', pageSlug.value || '')
        fallbackData.append('parent_id', String(pageParentId.value || 0))
        if (postType === 'dsf_layout') {
          fallbackData.append('layout_type', layoutType)
        }
        if (statusToSave) {
          fallbackData.append('status', statusToSave)
        }
        const fallbackResult = await attemptSave(fallbackData)
        if (fallbackResult?.post_status) {
          currentPostStatus.value = fallbackResult.post_status
        }
        if (fallbackResult?.post_title) {
          pageTitle.value = fallbackResult.post_title
        }
        if (Object.prototype.hasOwnProperty.call(fallbackResult, 'post_name')) {
          pageSlug.value = fallbackResult.post_name || ''
        }
        if (Object.prototype.hasOwnProperty.call(fallbackResult, 'post_parent')) {
          pageParentId.value = Number.parseInt(fallbackResult.post_parent, 10) || 0
        }
        if (fallbackResult?.permalink) {
          currentViewUrl.value = fallbackResult.permalink
        }
        if (fallbackResult?.preview_url) {
          currentPreviewUrl.value = fallbackResult.preview_url
        }
        console.warn('Saved without snapshot due to snapshot error.')
        return true
      } catch (fallbackError) {
        console.error('Fallback save error:', fallbackError)
      }
    }

    if (!silent) {
      alert('Error saving page: ' + (error?.message || 'Unknown error'))
    }
    return false
  } finally {
    isSaving.value = false
  }
}

async function generateHtmlSnapshot() {
  const mount = document.createElement('div')
  mount.style.position = 'absolute'
  mount.style.left = '-99999px'
  mount.style.top = '0'
  mount.style.width = '1200px'
  mount.style.pointerEvents = 'none'
  mount.setAttribute('aria-hidden', 'true')
  document.body.appendChild(mount)

  let app = null
  try {
    const snapshotBlocks = JSON.parse(JSON.stringify(blocks.value || []))
    app = createApp(FrontendApp, { blocks: snapshotBlocks })
    // Blocks read this via inject('dsfRenderMode') to skip side effects
    // (Gravity Forms init scripts, document.body appends) that would otherwise
    // leak from the offscreen snapshot mount into the live editor DOM.
    app.provide('dsfRenderMode', 'snapshot')
    app.mount(mount)

    await nextTick()
    return stripHtmlComments(mount.innerHTML)
  } finally {
    if (app) {
      app.unmount()
    }
    mount.remove()
  }
}

// Vue's runtime serializes v-if placeholders and some fragment markers as
// HTML comments. When the captured innerHTML is later echoed verbatim by
// PHP, a stray "-->" inside user-supplied WYSIWYG content (e.g. pasted
// marketing HTML) can prematurely close one of those comments, swallowing
// closing tags and corrupting the DOM tree on the live page — which shows
// up as duplicate, unstyled blocks at the bottom because they escape the
// #dsf-frontend-app container that Vue clears on mount.
// The snapshot is only a first-paint placeholder; Vue replaces it on
// hydration, so dropping comments is safe.
function stripHtmlComments(html) {
  return (html || '').replace(/<!--[\s\S]*?-->/g, '')
}

async function openView() {
  if (postType === 'dsf_layout') {
    alert('Header and footer templates do not have a direct view URL yet.')
    return
  }

  const saved = await savePage({ silent: false })
  if (!saved) {
    alert('Could not save the page for viewing. Please try again.')
    return
  }

  const targetUrl = currentPostStatus.value === 'publish'
    ? currentViewUrl.value
    : (currentPreviewUrl.value || currentViewUrl.value)

  if (!targetUrl) {
    alert('View link is unavailable for this page yet.')
    return
  }

  window.open(targetUrl, '_blank')
}

// Watch for font changes and load them dynamically
watch(() => [pageSettings.value?.theme?.headingFont, pageSettings.value?.theme?.bodyFont], 
  ([heading, body]) => {
    if (heading) loadGoogleFont(heading)
    if (body) loadGoogleFont(body)
  }, 
  { immediate: true }
)

watch([selectedBlock, showThemePanel], ([block, themeVisible]) => {
  if (block || themeVisible) animatePanel('#dsf-editor-app .dsf-panel', 28)
})

watch(showBlockLibrary, (visible) => {
  if (!visible) return
  animateAfterRender(() => {
    const overlay = editorRoot.value?.querySelector('.dsf-library-overlay')
    const panel = editorRoot.value?.querySelector('.dsf-library-panel')
    if (overlay) gsap.fromTo(overlay, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.22, ease: 'power2.out' })
    if (panel) {
      gsap.fromTo(panel,
        { autoAlpha: 0, x: -44 },
        { autoAlpha: 1, x: 0, duration: 0.4, ease: 'power3.out', clearProps: 'transform,opacity,visibility' }
      )
    }
  })
})

watch(showPageSettings, (visible) => {
  if (!visible) return
  animateAfterRender(() => {
    const modal = document.querySelector('.dsf-page-settings-modal')
    if (!modal) return
    gsap.fromTo(modal,
      { autoAlpha: 0, y: 18, scale: 0.975 },
      { autoAlpha: 1, y: 0, scale: 1, duration: 0.34, ease: 'power3.out', clearProps: 'transform,opacity,visibility' }
    )
  })
})

// Lifecycle
onMounted(() => {
  console.log('DesignStudio Flow Editor loaded')
  console.log('Registered blocks:', wpData.blocks)
  // Ensure existing blocks reflect stored theme settings on load.
  if (pageSettings.value?.theme) {
    syncThemeToBlocks(DEFAULT_THEME, pageSettings.value.theme)
  }

  // Populate the reusable saved-block + template libraries for the picker.
  loadSavedBlocks()
  loadTemplates()

  if (!prefersReducedMotion()) {
    const root = editorRoot.value
    const timeline = gsap.timeline({ defaults: { ease: 'power3.out' } })
    timeline
      .from(root?.querySelector('.dsf-header'), { autoAlpha: 0, y: -16, duration: 0.38 })
      .from(root?.querySelector('.dsf-canvas__inner'), { autoAlpha: 0, y: 18, scale: 0.992, duration: 0.48 }, '-=0.2')
      .from(root?.querySelectorAll('.dsf-block') || [], { autoAlpha: 0, y: 14, duration: 0.34, stagger: 0.055 }, '-=0.26')
      .from(root?.querySelector('.dsf-add-block-btn'), { autoAlpha: 0, y: 12, scale: 0.94, duration: 0.3 }, '-=0.18')
  }
})

onBeforeUnmount(() => {
  if (editorRoot.value) gsap.killTweensOf(editorRoot.value.querySelectorAll('*'))
})
</script>

<style>
/* Editor toast (teleported to body, so intentionally unscoped). */
.dsf-toast {
  position: fixed;
  left: 50%;
  bottom: 28px;
  transform: translateX(-50%);
  z-index: 1200;
  padding: 0.7rem 1.1rem;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #fff;
  background: #111827;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
  max-width: 90vw;
}

.dsf-toast--success { background: #15803d; }
.dsf-toast--error { background: #b91c1c; }

.dsf-toast-enter-active,
.dsf-toast-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }

.dsf-toast-enter-from,
.dsf-toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
