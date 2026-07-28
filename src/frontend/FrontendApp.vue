<template>
  <div class="dsf-frontend-blocks">
    <div
      v-for="block in blocks"
      :key="block.id"
      :id="blockAnchorId(block)"
      class="dsf-block"
      :class="{
        'dsf-block--landing': block.type?.startsWith('landing-'),
        'dsf-block--has-height': hasResponsiveKey(block.settings, 'height'),
      }"
      :style="getBlockStyle(block)"
    >
      <component
        :is="getPreviewComponent(block.type)"
        :settings="block.settings"
        :is-editor="false"
        :block-id="block.id"
        :preview-mode="breakpoint"
      />
    </div>
    <transition name="dsf-modal" appear>
      <FlowModal
        v-if="modal.open"
        :layout="modal.layout"
        :content="modal.content"
        :loading="modal.loading"
        @close="closeModal"
      />
    </transition>
    <PagePopup :settings="popupSettings" :post-id="postId" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, provide, nextTick } from 'vue'
import GenericBlockPreview from '../components/blocks/GenericBlockPreview.vue'
import { getCustomBlock } from '../blockRegistry.js'
import { getLazyPreviewComponent } from './lazyBlockRegistry.js'
import { blockAnchorId } from '../utils/anchor.js'
import FlowModal from '../components/common/FlowModal.vue'
import PagePopup from '../components/common/PagePopup.vue'
import { provideFlowModal } from '../components/common/useFlowModal'
import { createModalController } from './modalController'
import { blockWrapperStyle, hasResponsiveKey } from '../utils/responsiveSettings'

const props = defineProps({
  blocks: {
    type: Array,
    default: () => [],
  },
  popupSettings: {
    type: Object,
    default: () => ({}),
  },
  postId: {
    type: [Number, String],
    default: 0,
  },
})

function getPreviewComponent(blockType) {
  // Built-in components win; then runtime-registered add-on blocks (reactive, so
  // a late registration re-renders); finally the generic placeholder.
  return getLazyPreviewComponent(blockType) || getCustomBlock(blockType) || GenericBlockPreview
}

const { modalState: modal, openModalAction: openModal, closeModalAction: closeModal } =
  createModalController()

provideFlowModal({ openModal, closeModal })

// Product blocks (in a product template) read the viewed product from this context.
const currentProduct = ref(
  (typeof window !== 'undefined' && window.dsfFrontendData?.currentProduct) || null
)
provide('dsfProductContext', currentProduct)

const storeContext = ref(
  (typeof window !== 'undefined' && window.dsfFrontendData?.storeContext) || null
)
provide('dsfStoreContext', storeContext)

// Shop blocks (in a shop template) read the viewed archive from this context.
const currentArchive = ref(
  (typeof window !== 'undefined' && window.dsfFrontendData?.currentArchive) || null
)
provide('dsfShopContext', currentArchive)

// Blog blocks (in a blog template) read the viewed post archive from this context.
const currentBlogArchive = ref(
  (typeof window !== 'undefined' && window.dsfFrontendData?.currentBlogArchive) || null
)
provide('dsfBlogContext', currentBlogArchive)

// Server-built breadcrumb trail ([{name,url}]) for the Breadcrumbs block.
const breadcrumbTrail = ref(
  (typeof window !== 'undefined' && Array.isArray(window.dsfFrontendData?.breadcrumbs))
    ? window.dsfFrontendData.breadcrumbs
    : []
)
provide('dsfBreadcrumbs', breadcrumbTrail)

const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
let resizeHandler = null

const breakpoint = computed(() => {
  if (viewportWidth.value >= 1024) return 'desktop'
  if (viewportWidth.value >= 768) return 'tablet'
  return 'mobile'
})

onMounted(() => {
  resizeHandler = () => {
    viewportWidth.value = window.innerWidth
  }
  window.addEventListener('resize', resizeHandler)
  resizeHandler()

  // Mounting replaces the server snapshot, so a #anchor the browser jumped to at
  // load is lost. Re-scroll to it once our real block ids exist. nextTick lets
  // the blocks render first; the guard keeps us from hijacking unrelated hashes.
  nextTick(() => {
    scrollToHash(window.location.hash)
  })
  window.addEventListener('hashchange', onHashChange)
})

function onHashChange() {
  scrollToHash(window.location.hash)
}

function scrollToHash(hash) {
  if (!hash || hash.length < 2 || typeof document === 'undefined') return
  let id = ''
  try {
    id = decodeURIComponent(hash.slice(1))
  } catch {
    id = hash.slice(1)
  }
  if (!id) return
  const el = document.getElementById(id)
  if (!el) return
  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  el.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' })
}

onUnmounted(() => {
  if (resizeHandler) {
    window.removeEventListener('resize', resizeHandler)
  }
  window.removeEventListener('hashchange', onHashChange)
})

function getDefaultMarginByType(blockType) {
  if (blockType === 'header-mega-menu' || blockType === 'header-showcase-mega' || blockType === 'header-cutout-mega' || blockType === 'header-modern-mega' || blockType === 'footer-dealers' || blockType === 'footer-commerce' || blockType?.startsWith('landing-')) {
    return 0
  }
  return 25
}

function getBlockStyle(block) {
  return blockWrapperStyle(block?.settings || {}, breakpoint.value, {
    type: block?.type,
    marginFallback: getDefaultMarginByType(block?.type),
  })
}
</script>
