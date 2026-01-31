<template>
  <div class="dsf-editor">
    <!-- Header -->
    <EditorHeader 
      :title="pageTitle"
      :is-saving="isSaving"
      :preview-mode="previewMode"
      @update:title="updateTitle"
      @preview="openPreview"
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
            <h3 class="dsf-canvas-empty__title">Your page is empty</h3>
            <p class="dsf-canvas-empty__text">Add blocks from the library to get started</p>
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
        @close="showThemePanel = false"
        @update:settings="updatePageSettings"
      />
    </div>
    
    <!-- Add Block Button -->
    <button 
      class="dsf-add-block-btn"
      @click="showBlockLibrary = true"
    >
      <Plus :size="20" />
      Add Block
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
import { ref, computed, onMounted } from 'vue'
import draggable from 'vuedraggable'
import { Plus, LayoutTemplate } from 'lucide-vue-next'

// Components
import EditorHeader from './components/EditorHeader.vue'
import BlockWrapper from './components/BlockWrapper.vue'
import SidePanel from './components/SidePanel.vue'
import ThemePanel from './components/ThemePanel.vue'
import BlockLibrary from './components/BlockLibrary.vue'
import ConfirmDialog from './components/common/ConfirmDialog.vue'

// Get WordPress data
const wpData = window.dsfEditorData || {}

// State
const blocks = ref(wpData.pageData?.blocks || [])
const pageSettings = ref(wpData.pageData?.settings || {
  theme: {
    primaryColor: '#3B82F6',
    secondaryColor: '#1E40AF',
    textColor: '#1F2937',
    backgroundColor: '#FFFFFF',
  },
  layout: {
    containerWidth: 1200,
    contentPadding: 24,
  },
})

const pageTitle = ref(wpData.postTitle || 'Untitled Page')
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
  
  // Define exact order for each category
  const contentOrder = ['hero', 'bento-hero', 'duo-hero', 'text-image', 'features-grid', 'testimonials']
  const marketingOrder = ['featured-promo-banner', 'promo-banner', 'cta-banner', 'brand-carousel']
  const ecommerceOrder = ['ecommerce-showcase', 'featured-product-banner', 'product-grid']
  
  Object.values(registeredBlocks).forEach(block => {
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

const canvasStyle = computed(() => {
  const widths = {
    desktop: '1800px',
    tablet: '768px',
    mobile: '375px',
  }
  return {
    maxWidth: widths[previewMode.value],
  }
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
      defaults[key] = config.default
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

function setPreviewMode(mode) {
  previewMode.value = mode
}

function updateTitle(newTitle) {
  pageTitle.value = newTitle
}

function updatePageSettings(newSettings) {
  pageSettings.value = { ...pageSettings.value, ...newSettings }
}

async function savePage() {
  isSaving.value = true
  
  const formData = new FormData()
  formData.append('action', 'dsf_save_page')
  formData.append('nonce', wpData.nonce)
  formData.append('post_id', wpData.postId)
  formData.append('blocks', JSON.stringify(blocks.value))
  formData.append('settings', JSON.stringify(pageSettings.value))
  
  try {
    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      body: formData,
    })
    
    const data = await response.json()
    
    if (data.success) {
      // Show success notification
      console.log('Page saved successfully')
    } else {
      alert('Error saving page: ' + (data.data?.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Save error:', error)
    alert('Error saving page')
  } finally {
    isSaving.value = false
  }
}

function openPreview() {
  if (wpData.previewUrl) {
    window.open(wpData.previewUrl, '_blank')
  }
}

// Lifecycle
onMounted(() => {
  console.log('DesignStudio Flow Editor loaded')
  console.log('Registered blocks:', wpData.blocks)
})
</script>
