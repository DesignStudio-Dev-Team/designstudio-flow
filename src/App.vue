<template>
  <div class="dsf-editor">
    <!-- Header -->
    <EditorHeader 
      :title="pageTitle"
      :is-saving="isSaving"
      :preview-mode="previewMode"
      :post-type="postType"
      :layout-type="layoutType"
      @update:title="updateTitle"
      @preview="openPreview"
      @view="openView"
      @save="savePage"
      @set-preview-mode="setPreviewMode"
      @open-theme="showThemePanel = true"
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
                @select="selectBlock(element)"
                @move-up="moveBlockUp(index)"
                @move-down="moveBlockDown(index)"
                @delete="deleteBlock(index)"
                @open-settings="openBlockSettings(element)"
              />
            </template>
          </draggable>
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
        :post-type="postType"
        :layout-templates="layoutTemplates"
        :layout-create-urls="layoutCreateUrls"
        @close="showThemePanel = false"
        @update:settings="updatePageSettings"
      />
    </div>
    
    <!-- Add Block Button -->
    <button 
      class="dsf-add-block-btn"
      @click="openBlockLibrary"
    >
      <Plus :size="20" />
      {{ isTemplateEditor ? 'Add Template Block' : 'Add Block' }}
    </button>
    
    <!-- Block Library Modal -->
    <BlockLibrary
      v-if="showBlockLibrary"
      :categories="blockCategories"
      @close="showBlockLibrary = false"
      @add="addBlock"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, createApp, nextTick, watch } from 'vue'
import draggable from 'vuedraggable'
import { Plus, LayoutTemplate } from 'lucide-vue-next'

// Components
import EditorHeader from './components/EditorHeader.vue'
import BlockWrapper from './components/BlockWrapper.vue'
import SidePanel from './components/SidePanel.vue'
import ThemePanel from './components/ThemePanel.vue'
import BlockLibrary from './components/BlockLibrary.vue'
import ConfirmDialog from './components/common/ConfirmDialog.vue'
import FrontendApp from './frontend/FrontendApp.vue'
import { applyThemeToBlocks, resolveThemeKey } from './utils/themeSync'

// Get WordPress data
const wpData = window.dsfEditorData || {}
const postType = wpData.postType === 'dsf_layout' ? 'dsf_layout' : 'dsf_page'
const layoutType = wpData.layoutType === 'footer' ? 'footer' : 'header'
const isTemplateEditor = postType === 'dsf_layout'
const layoutTemplates = computed(() => wpData.layoutTemplates || { headers: [], footers: [] })
const layoutCreateUrls = computed(() => wpData.layoutCreateUrls || {})
const availableForms = Array.isArray(wpData.forms) ? wpData.forms : []

hydrateFormBlockDefinition()

function hydrateFormBlockDefinition() {
  const formBlock = wpData.blocks?.['form-embed']
  const formField = formBlock?.settings?.formId
  if (!formField) return

  formField.options = buildFormOptions(availableForms)
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

function loadGoogleFont(fontFamily) {
  if (!fontFamily) return
  const match = fontFamily.match(/'([^']+)'/)
  if (!match) return
  
  const fontName = match[1]
  if (loadedFonts.value.has(fontName)) return
  
  loadedFonts.value.add(fontName)
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/ /g, '+')}:wght@300;400;500;600;700&display=swap`
  document.head.appendChild(link)
}

// Theme defaults & sync helpers
const DEFAULT_THEME = {
  primaryColor: '#2C5F5D',
  secondaryColor: '#1E40AF',
  textColor: '#1F2937',
  backgroundColor: '#FFFFFF',
  headingFont: '',
  bodyFont: '',
}

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

function syncThemeToBlocks(oldTheme, newTheme) {
  if (!oldTheme || !newTheme) return
  if (!blocks.value.length) return

  blocks.value = applyThemeToBlocks(blocks.value, oldTheme, newTheme, themeLinkedSettings)
}

// State
const blocks = ref(wpData.pageData?.blocks || [])
const initialSettings = wpData.pageData?.settings || {}
const pageSettings = ref({
  theme: { ...DEFAULT_THEME, ...(initialSettings.theme || {}) },
  layout: { ...DEFAULT_LAYOUT, ...(initialSettings.layout || {}) },
})

const pageTitle = ref(wpData.postTitle || 'Untitled Page')
const currentPostStatus = ref(wpData.postStatus || 'draft')
const currentViewUrl = ref(wpData.viewUrl || '')
const currentPreviewUrl = ref(wpData.previewUrl || '')
const isSaving = ref(false)
const previewMode = ref('desktop')
const selectedBlock = ref(null)
const selectedBlockId = ref(null)
const showBlockLibrary = ref(false)
const showThemePanel = ref(false)
const deleteConfirmVisible = ref(false)
const pendingDeleteIndex = ref(null)

// Computed
const blockCategories = computed(() => {
  const registeredBlocks = wpData.blocks || {}
  const categories = {
    content: { label: 'Content', icon: 'file-text', blocks: [] },
    marketing: { label: 'Marketing', icon: 'target', blocks: [] },
    ecommerce: { label: 'Ecommerce', icon: 'shopping-cart', blocks: [] },
  }

  const allBlocks = Object.values(registeredBlocks)
  const allowedBlocks = allBlocks.filter((block) => isBlockAllowedInCurrentEditor(block))

  if (isTemplateEditor) {
    const label = layoutType === 'footer' ? 'Footer Blocks' : 'Header Blocks'
    const templateBlocks = allowedBlocks.filter((block) => block.category === 'content')
    return {
      content: { label, icon: 'layout-template', blocks: templateBlocks },
    }
  }

  // Define exact order for each category
  const contentOrder = ['hero', 'bento-hero', 'duo-hero', 'text-image', 'features-grid', 'testimonials', 'form-embed']
  const marketingOrder = ['featured-promo-banner', 'promo-banner', 'cta-banner', 'brand-carousel']
  const ecommerceOrder = ['ecommerce-showcase', 'featured-product-banner', 'product-grid']

  allowedBlocks.forEach(block => {
    if (categories[block.category]) {
      categories[block.category].blocks.push(block)
    }
  })

  // Sort blocks within categories
  categories.content.blocks.sort((a, b) => contentOrder.indexOf(a.id) - contentOrder.indexOf(b.id))
  categories.marketing.blocks.sort((a, b) => marketingOrder.indexOf(a.id) - marketingOrder.indexOf(b.id))
  categories.ecommerce.blocks.sort((a, b) => ecommerceOrder.indexOf(a.id) - ecommerceOrder.indexOf(b.id))

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
  
  // Add font CSS variables if set
  if (theme.headingFont) {
    style['--dsf-theme-heading-font'] = theme.headingFont
  }
  if (theme.bodyFont) {
    style['--dsf-theme-body-font'] = theme.bodyFont
  }
  
  return style
})

// Methods
function getBlockDefinition(blockType) {
  return wpData.blocks?.[blockType] || null
}

function selectBlock(block) {
  selectedBlock.value = block
  selectedBlockId.value = block.id
  showThemePanel.value = false
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

  const newBlock = {
    id: 'block_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
    type: blockDefinition.id,
    settings: getDefaultSettings(blockDefinition),
  }
  
  blocks.value.push(newBlock)
  showBlockLibrary.value = false
  
  // Auto-select new block and scroll into view
  setTimeout(() => {
    selectBlock(newBlock)
    
    // Scroll to the new block
    const element = document.getElementById('block-' + newBlock.id)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, 100)
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

function updatePageSettings(newSettings) {
  const oldTheme = { ...(pageSettings.value?.theme || DEFAULT_THEME) }
  const nextSettings = { ...pageSettings.value, ...newSettings }
  pageSettings.value = nextSettings

  if (newSettings?.theme) {
    syncThemeToBlocks(oldTheme, nextSettings.theme || DEFAULT_THEME)
  }
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

  let statusToSave = status
  if (!statusToSave && currentPostStatus.value === 'draft' && hasMeaningfulTitle(titleToSave)) {
    statusToSave = 'publish'
  }

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
  document.body.appendChild(mount)

  let app = null
  try {
    const snapshotBlocks = JSON.parse(JSON.stringify(blocks.value || []))
    app = createApp(FrontendApp, { blocks: snapshotBlocks })
    app.mount(mount)

    await nextTick()
    return mount.innerHTML
  } finally {
    if (app) {
      app.unmount()
    }
    mount.remove()
  }
}

async function openPreview() {
  if (postType === 'dsf_layout') {
    alert('Header and footer templates do not have a direct preview URL yet.')
    return
  }

  if (!currentPreviewUrl.value) {
    alert('Preview link is unavailable for this page yet.')
    return
  }

  const saved = await savePage({ status: 'draft', silent: false, requireNamePrompt: false })
  if (saved) {
    window.open(currentPreviewUrl.value, '_blank')
  } else {
    alert('Could not save the page for preview. Please try again.')
  }
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

  if (!currentViewUrl.value) {
    alert('View link is unavailable for this page yet.')
    return
  }

  window.open(currentViewUrl.value, '_blank')
}

// Watch for font changes and load them dynamically
watch(() => [pageSettings.value?.theme?.headingFont, pageSettings.value?.theme?.bodyFont], 
  ([heading, body]) => {
    if (heading) loadGoogleFont(heading)
    if (body) loadGoogleFont(body)
  }, 
  { immediate: true }
)

// Lifecycle
onMounted(() => {
  console.log('DesignStudio Flow Editor loaded')
  console.log('Registered blocks:', wpData.blocks)
  // Ensure existing blocks reflect stored theme settings on load.
  if (pageSettings.value?.theme) {
    syncThemeToBlocks(DEFAULT_THEME, pageSettings.value.theme)
  }
})
</script>
