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
import ContentPreview from '../components/blocks/ContentPreview.vue'
import FaqPreview from '../components/blocks/FaqPreview.vue'
import BreadcrumbsPreview from '../components/blocks/BreadcrumbsPreview.vue'
import HeroPreview from '../components/blocks/HeroCenteredPreview.vue'
import ProductGridPreview from '../components/blocks/ProductGridPreview.vue'
import EcommerceShowcasePreview from '../components/blocks/EcommerceShowcasePreview.vue'
import FeaturesGridPreview from '../components/blocks/FeaturesGridPreview.vue'
import CardColumnsPreview from '../components/blocks/CardColumnsPreview.vue'
import BentoHeroPreview from '../components/blocks/BentoHeroPreview.vue'
import SpotlightHeroPreview from '../components/blocks/SpotlightHeroPreview.vue'
import ExpanderHeroPreview from '../components/blocks/ExpanderHeroPreview.vue'
import PricingPreview from '../components/blocks/PricingPreview.vue'
import PricingTablesPreview from '../components/blocks/PricingTablesPreview.vue'
import TextImagePreview from '../components/blocks/TextImagePreview.vue'
import TestimonialsPreview from '../components/blocks/TestimonialsPreview.vue'
import CtaBannerPreview from '../components/blocks/CtaBannerPreview.vue'
import CountdownPreview from '../components/blocks/CountdownPreview.vue'
import NewsletterPreview from '../components/blocks/NewsletterPreview.vue'
import BrandLogosPreview from '../components/blocks/BrandLogosPreview.vue'
import PromoBannerPreview from '../components/blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from '../components/blocks/FeaturedProductBannerPreview.vue'
import ProductSummaryPreview from '../components/blocks/ProductSummaryPreview.vue'
import ProductGalleryPreview from '../components/blocks/ProductGalleryPreview.vue'
import ProductDescriptionPreview from '../components/blocks/ProductDescriptionPreview.vue'
import ProductSpecsPreview from '../components/blocks/ProductSpecsPreview.vue'
import ProductTabsPreview from '../components/blocks/ProductTabsPreview.vue'
import ProductAddToCartPreview from '../components/blocks/ProductAddToCartPreview.vue'
import ProductHeroPreview from '../components/blocks/ProductHeroPreview.vue'
import ProductDetailsSplitPreview from '../components/blocks/ProductDetailsSplitPreview.vue'
import ProductHighlightsPreview from '../components/blocks/ProductHighlightsPreview.vue'
import ProductRelatedPreview from '../components/blocks/ProductRelatedPreview.vue'
import ProductSpotlightPreview from '../components/blocks/ProductSpotlightPreview.vue'
import ProductUpsellsPreview from '../components/blocks/ProductUpsellsPreview.vue'
import ProductReviewsPreview from '../components/blocks/ProductReviewsPreview.vue'
import ProductMetaPreview from '../components/blocks/ProductMetaPreview.vue'
import StoreCartPreview from '../components/blocks/StoreCartPreview.vue'
import StoreCheckoutPreview from '../components/blocks/StoreCheckoutPreview.vue'
import StoreAccountPreview from '../components/blocks/StoreAccountPreview.vue'
import StoreLoginPreview from '../components/blocks/StoreLoginPreview.vue'
import StoreStepsPreview from '../components/blocks/StoreStepsPreview.vue'
import ShopHeaderPreview from '../components/blocks/ShopHeaderPreview.vue'
import ShopCategoryHeroPreview from '../components/blocks/ShopCategoryHeroPreview.vue'
import ShopSubcategoryGridPreview from '../components/blocks/ShopSubcategoryGridPreview.vue'
import ShopProductsPreview from '../components/blocks/ShopProductsPreview.vue'
import ShopFiltersPreview from '../components/blocks/ShopFiltersPreview.vue'
import StoreMiniCartPreview from '../components/blocks/StoreMiniCartPreview.vue'
import StoreThankyouPreview from '../components/blocks/StoreThankyouPreview.vue'
import SiteLoginPreview from '../components/blocks/SiteLoginPreview.vue'
import SiteSearchPreview from '../components/blocks/SiteSearchPreview.vue'
import UserDashboardPreview from '../components/blocks/UserDashboardPreview.vue'
import BlogHeaderPreview from '../components/blocks/BlogHeaderPreview.vue'
import PostLoopPreview from '../components/blocks/PostLoopPreview.vue'
import DuoHeroPreview from '../components/blocks/DuoHeroPreview.vue'
import FeaturedPromoBannerPreview from '../components/blocks/FeaturedPromoBannerPreview.vue'
import HeaderMegaMenuPreview from '../components/blocks/HeaderMegaMenuPreview.vue'
import HeaderShowcaseMegaPreview from '../components/blocks/HeaderShowcaseMegaPreview.vue'
import HeaderCutoutMegaPreview from '../components/blocks/HeaderCutoutMegaPreview.vue'
import HeaderModernMegaPreview from '../components/blocks/HeaderModernMegaPreview.vue'
import FooterDealersPreview from '../components/blocks/FooterDealersPreview.vue'
import FooterCommercePreview from '../components/blocks/FooterCommercePreview.vue'
import FormEmbedPreview from '../components/blocks/FormEmbedPreview.vue'
import FormWithContentPreview from '../components/blocks/FormWithContentPreview.vue'
import LandingProgressHeaderPreview from '../components/blocks/LandingProgressHeaderPreview.vue'
import LandingDockHeaderPreview from '../components/blocks/LandingDockHeaderPreview.vue'
import LandingHeroPreview from '../components/blocks/LandingHeroPreview.vue'
import LandingShowcaseHeroPreview from '../components/blocks/LandingShowcaseHeroPreview.vue'
import LandingBlockExplorerPreview from '../components/blocks/LandingBlockExplorerPreview.vue'
import LandingBlockReadyPreview from '../components/blocks/LandingBlockReadyPreview.vue'
import LandingProductStoryPreview from '../components/blocks/LandingProductStoryPreview.vue'
import LandingTrustWorkflowPreview from '../components/blocks/LandingTrustWorkflowPreview.vue'
import LandingEngagementSuitePreview from '../components/blocks/LandingEngagementSuitePreview.vue'
import LandingRedirectToolPreview from '../components/blocks/LandingRedirectToolPreview.vue'
import LandingMailToolPreview from '../components/blocks/LandingMailToolPreview.vue'
import LandingMarketingFooterPreview from '../components/blocks/LandingMarketingFooterPreview.vue'
import GenericBlockPreview from '../components/blocks/GenericBlockPreview.vue'
import { getCustomBlock } from '../blockRegistry.js'
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

const previewComponents = {
  'content': ContentPreview,
  'faq': FaqPreview,
  'breadcrumbs': BreadcrumbsPreview,
  'hero': HeroPreview,
  'product-grid': ProductGridPreview,
  'ecommerce-showcase': EcommerceShowcasePreview,
  'features-grid': FeaturesGridPreview,
  'card-columns': CardColumnsPreview,
  'bento-hero': BentoHeroPreview,
  'spotlight-hero': SpotlightHeroPreview,
  'expander-hero': ExpanderHeroPreview,
  'pricing': PricingPreview,
  'pricing-tables': PricingTablesPreview,
  'text-image': TextImagePreview,
  'testimonials': TestimonialsPreview,
  'cta-banner': CtaBannerPreview,
  'countdown': CountdownPreview,
  'newsletter': NewsletterPreview,
  'brand-carousel': BrandLogosPreview,
  'promo-banner': PromoBannerPreview,
  'featured-product-banner': FeaturedProductBannerPreview,
  'product-summary': ProductSummaryPreview,
  'product-gallery': ProductGalleryPreview,
  'product-description': ProductDescriptionPreview,
  'product-specs': ProductSpecsPreview,
  'product-tabs': ProductTabsPreview,
  'product-add-to-cart': ProductAddToCartPreview,
  'product-hero': ProductHeroPreview,
  'product-details-split': ProductDetailsSplitPreview,
  'product-highlights': ProductHighlightsPreview,
  'product-related': ProductRelatedPreview,
  'product-spotlight': ProductSpotlightPreview,
  'product-upsells': ProductUpsellsPreview,
  'product-reviews': ProductReviewsPreview,
  'product-meta': ProductMetaPreview,
  'store-cart': StoreCartPreview,
  'store-checkout': StoreCheckoutPreview,
  'store-account': StoreAccountPreview,
  'store-login': StoreLoginPreview,
  'store-steps': StoreStepsPreview,
  'shop-header': ShopHeaderPreview,
  'shop-category-hero': ShopCategoryHeroPreview,
  'shop-subcategory-grid': ShopSubcategoryGridPreview,
  'shop-products': ShopProductsPreview,
  'shop-filters': ShopFiltersPreview,
  'store-mini-cart': StoreMiniCartPreview,
  'store-thankyou': StoreThankyouPreview,
  'site-login': SiteLoginPreview,
  'site-search': SiteSearchPreview,
  'user-dashboard': UserDashboardPreview,
  'blog-header': BlogHeaderPreview,
  'post-loop': PostLoopPreview,
  'duo-hero': DuoHeroPreview,
  'featured-promo-banner': FeaturedPromoBannerPreview,
  'header-mega-menu': HeaderMegaMenuPreview,
  'header-showcase-mega': HeaderShowcaseMegaPreview,
  'header-cutout-mega': HeaderCutoutMegaPreview,
  'header-modern-mega': HeaderModernMegaPreview,
  'footer-dealers': FooterDealersPreview,
  'footer-commerce': FooterCommercePreview,
  'form-embed': FormEmbedPreview,
  'form-with-content': FormWithContentPreview,
  'landing-progress-header': LandingProgressHeaderPreview,
  'landing-dock-header': LandingDockHeaderPreview,
  'landing-hero': LandingHeroPreview,
  'landing-showcase-hero': LandingShowcaseHeroPreview,
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
  // Built-in components win; then runtime-registered add-on blocks (reactive, so
  // a late registration re-renders); finally the generic placeholder.
  return previewComponents[blockType] || getCustomBlock(blockType) || GenericBlockPreview
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
