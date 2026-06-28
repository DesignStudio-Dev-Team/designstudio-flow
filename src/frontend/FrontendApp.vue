<template>
  <div class="dsf-frontend-blocks">
    <div
      v-for="block in blocks"
      :key="block.id"
      class="dsf-block"
      :class="{ 'dsf-block--landing': block.type?.startsWith('landing-') }"
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import ContentPreview from '../components/blocks/ContentPreview.vue'
import FaqPreview from '../components/blocks/FaqPreview.vue'
import HeroPreview from '../components/blocks/HeroCenteredPreview.vue'
import ProductGridPreview from '../components/blocks/ProductGridPreview.vue'
import EcommerceShowcasePreview from '../components/blocks/EcommerceShowcasePreview.vue'
import FeaturesGridPreview from '../components/blocks/FeaturesGridPreview.vue'
import BentoHeroPreview from '../components/blocks/BentoHeroPreview.vue'
import SpotlightHeroPreview from '../components/blocks/SpotlightHeroPreview.vue'
import ExpanderHeroPreview from '../components/blocks/ExpanderHeroPreview.vue'
import PricingPreview from '../components/blocks/PricingPreview.vue'
import TextImagePreview from '../components/blocks/TextImagePreview.vue'
import TestimonialsPreview from '../components/blocks/TestimonialsPreview.vue'
import CtaBannerPreview from '../components/blocks/CtaBannerPreview.vue'
import CountdownPreview from '../components/blocks/CountdownPreview.vue'
import NewsletterPreview from '../components/blocks/NewsletterPreview.vue'
import BrandLogosPreview from '../components/blocks/BrandLogosPreview.vue'
import PromoBannerPreview from '../components/blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from '../components/blocks/FeaturedProductBannerPreview.vue'
import DuoHeroPreview from '../components/blocks/DuoHeroPreview.vue'
import FeaturedPromoBannerPreview from '../components/blocks/FeaturedPromoBannerPreview.vue'
import HeaderMegaMenuPreview from '../components/blocks/HeaderMegaMenuPreview.vue'
import HeaderShowcaseMegaPreview from '../components/blocks/HeaderShowcaseMegaPreview.vue'
import HeaderCutoutMegaPreview from '../components/blocks/HeaderCutoutMegaPreview.vue'
import FooterDealersPreview from '../components/blocks/FooterDealersPreview.vue'
import FormEmbedPreview from '../components/blocks/FormEmbedPreview.vue'
import FormWithContentPreview from '../components/blocks/FormWithContentPreview.vue'
import LandingProgressHeaderPreview from '../components/blocks/LandingProgressHeaderPreview.vue'
import LandingHeroPreview from '../components/blocks/LandingHeroPreview.vue'
import LandingBlockExplorerPreview from '../components/blocks/LandingBlockExplorerPreview.vue'
import LandingBlockReadyPreview from '../components/blocks/LandingBlockReadyPreview.vue'
import LandingProductStoryPreview from '../components/blocks/LandingProductStoryPreview.vue'
import LandingTrustWorkflowPreview from '../components/blocks/LandingTrustWorkflowPreview.vue'
import LandingEngagementSuitePreview from '../components/blocks/LandingEngagementSuitePreview.vue'
import LandingRedirectToolPreview from '../components/blocks/LandingRedirectToolPreview.vue'
import LandingMailToolPreview from '../components/blocks/LandingMailToolPreview.vue'
import LandingMarketingFooterPreview from '../components/blocks/LandingMarketingFooterPreview.vue'
import GenericBlockPreview from '../components/blocks/GenericBlockPreview.vue'
import FlowModal from '../components/common/FlowModal.vue'
import PagePopup from '../components/common/PagePopup.vue'
import { provideFlowModal } from '../components/common/useFlowModal'
import { createModalController } from './modalController'
import { getResponsiveValue } from '../utils/responsiveSettings'

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

const previewComponents = {
  'content': ContentPreview,
  'faq': FaqPreview,
  'hero': HeroPreview,
  'product-grid': ProductGridPreview,
  'ecommerce-showcase': EcommerceShowcasePreview,
  'features-grid': FeaturesGridPreview,
  'bento-hero': BentoHeroPreview,
  'spotlight-hero': SpotlightHeroPreview,
  'expander-hero': ExpanderHeroPreview,
  'pricing': PricingPreview,
  'text-image': TextImagePreview,
  'testimonials': TestimonialsPreview,
  'cta-banner': CtaBannerPreview,
  'countdown': CountdownPreview,
  'newsletter': NewsletterPreview,
  'brand-carousel': BrandLogosPreview,
  'promo-banner': PromoBannerPreview,
  'featured-product-banner': FeaturedProductBannerPreview,
  'duo-hero': DuoHeroPreview,
  'featured-promo-banner': FeaturedPromoBannerPreview,
  'header-mega-menu': HeaderMegaMenuPreview,
  'header-showcase-mega': HeaderShowcaseMegaPreview,
  'header-cutout-mega': HeaderCutoutMegaPreview,
  'footer-dealers': FooterDealersPreview,
  'form-embed': FormEmbedPreview,
  'form-with-content': FormWithContentPreview,
  'landing-progress-header': LandingProgressHeaderPreview,
  'landing-hero': LandingHeroPreview,
  'landing-block-explorer': LandingBlockExplorerPreview,
  'landing-block-ready': LandingBlockReadyPreview,
  'landing-product-story': LandingProductStoryPreview,
  'landing-trust-workflow': LandingTrustWorkflowPreview,
  'landing-engagement-suite': LandingEngagementSuitePreview,
  'landing-redirect-tool': LandingRedirectToolPreview,
  'landing-mail-tool': LandingMailToolPreview,
  'landing-marketing-footer': LandingMarketingFooterPreview,
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
  if (blockType === 'header-mega-menu' || blockType === 'header-showcase-mega' || blockType === 'header-cutout-mega' || blockType === 'footer-dealers' || blockType?.startsWith('landing-')) {
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
