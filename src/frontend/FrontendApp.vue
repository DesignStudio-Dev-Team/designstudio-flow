<template>
  <div class="dsf-frontend-blocks">
    <div
      v-for="block in blocks"
      :key="block.id"
      class="dsf-block"
      :style="getBlockStyle(block)"
    >
      <component
        :is="getPreviewComponent(block.type)"
        :settings="block.settings"
        :is-editor="false"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import HeroPreview from '../components/blocks/HeroCenteredPreview.vue'
import ProductGridPreview from '../components/blocks/ProductGridPreview.vue'
import EcommerceShowcasePreview from '../components/blocks/EcommerceShowcasePreview.vue'
import FeaturesGridPreview from '../components/blocks/FeaturesGridPreview.vue'
import BentoHeroPreview from '../components/blocks/BentoHeroPreview.vue'
import TextImagePreview from '../components/blocks/TextImagePreview.vue'
import TestimonialsPreview from '../components/blocks/TestimonialsPreview.vue'
import CtaBannerPreview from '../components/blocks/CtaBannerPreview.vue'
import NewsletterPreview from '../components/blocks/NewsletterPreview.vue'
import BrandLogosPreview from '../components/blocks/BrandLogosPreview.vue'
import PromoBannerPreview from '../components/blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from '../components/blocks/FeaturedProductBannerPreview.vue'
import DuoHeroPreview from '../components/blocks/DuoHeroPreview.vue'
import FeaturedPromoBannerPreview from '../components/blocks/FeaturedPromoBannerPreview.vue'
import HeaderMegaMenuPreview from '../components/blocks/HeaderMegaMenuPreview.vue'
import HeaderCutoutMegaPreview from '../components/blocks/HeaderCutoutMegaPreview.vue'
import FooterDealersPreview from '../components/blocks/FooterDealersPreview.vue'
import FormEmbedPreview from '../components/blocks/FormEmbedPreview.vue'
import GenericBlockPreview from '../components/blocks/GenericBlockPreview.vue'
import FlowModal from '../components/common/FlowModal.vue'
import { provideFlowModal } from '../components/common/useFlowModal'
import { createModalController } from './modalController'
import { getResponsiveValue } from '../utils/responsiveSettings'

const props = defineProps({
  blocks: {
    type: Array,
    default: () => [],
  },
})

const previewComponents = {
  'hero': HeroPreview,
  'product-grid': ProductGridPreview,
  'ecommerce-showcase': EcommerceShowcasePreview,
  'features-grid': FeaturesGridPreview,
  'bento-hero': BentoHeroPreview,
  'text-image': TextImagePreview,
  'testimonials': TestimonialsPreview,
  'cta-banner': CtaBannerPreview,
  'newsletter': NewsletterPreview,
  'brand-carousel': BrandLogosPreview,
  'promo-banner': PromoBannerPreview,
  'featured-product-banner': FeaturedProductBannerPreview,
  'duo-hero': DuoHeroPreview,
  'featured-promo-banner': FeaturedPromoBannerPreview,
  'header-mega-menu': HeaderMegaMenuPreview,
  'header-cutout-mega': HeaderCutoutMegaPreview,
  'footer-dealers': FooterDealersPreview,
  'form-embed': FormEmbedPreview,
}

function getPreviewComponent(blockType) {
  return previewComponents[blockType] || GenericBlockPreview
}

const { modalState: modal, openModalAction: openModal, closeModalAction: closeModal } =
  createModalController()

provideFlowModal({ openModal, closeModal })

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
})

onUnmounted(() => {
  if (resizeHandler) {
    window.removeEventListener('resize', resizeHandler)
  }
})

function getResponsiveField(settings, key, fallback) {
  const value = getResponsiveValue(settings || {}, breakpoint.value, key)
  return value ?? fallback
}

function hasExplicitResponsiveValue(settings, key) {
  if (!settings) return false
  if (settings[key] !== undefined && settings[key] !== null) return true
  const responsive = settings.responsive || {}
  return ['desktop', 'tablet', 'mobile'].some(
    (breakpointKey) => responsive[breakpointKey]?.[key] !== undefined && responsive[breakpointKey]?.[key] !== null
  )
}

function getDefaultMarginByType(blockType) {
  if (blockType === 'header-mega-menu' || blockType === 'header-cutout-mega' || blockType === 'footer-dealers') {
    return 0
  }
  return 25
}

function getBlockStyle(block) {
  const settings = block?.settings || {}
  const marginFallback = getDefaultMarginByType(block?.type)
  const style = {
    marginTop: `${getResponsiveField(settings, 'marginY', marginFallback)}px`,
    marginBottom: `${getResponsiveField(settings, 'marginY', marginFallback)}px`,
    paddingLeft: `${getResponsiveField(settings, 'paddingX', 0)}px`,
    paddingRight: `${getResponsiveField(settings, 'paddingX', 0)}px`,
  }

  if (hasExplicitResponsiveValue(settings, 'height')) {
    const heightValue = getResponsiveValue(settings || {}, breakpoint.value, 'height')
    if (heightValue !== undefined && heightValue !== null) {
      style.minHeight = `${heightValue}px`
    }
  }

  return style
}
</script>
