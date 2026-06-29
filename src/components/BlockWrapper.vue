<template>
  <div 
    class="dsf-block"
    :id="'block-' + block.id"
    :class="{
      'dsf-block--selected': isSelected,
      'dsf-block--landing': isLandingBlock,
      'dsf-block--template-selected': isSelectedForTemplate,
    }"
    :style="wrapperStyle"
    @click.stop="$emit('select')"
  >
    <!-- Block Toolbar -->
    <div class="dsf-block-toolbar">
      <button v-if="allowReorder" type="button" class="dsf-block-toolbar__btn dsf-block-toolbar__btn--drag" title="Drag to reorder" aria-label="Drag to reorder">
        <GripVertical :size="16" />
      </button>
      <button type="button" class="dsf-block-toolbar__btn" title="Settings" aria-label="Open block settings" @click.stop="$emit('open-settings')">
        <Settings :size="16" />
      </button>
      <button type="button" class="dsf-block-toolbar__btn" title="Save block to library" aria-label="Save block to library" @click.stop="$emit('save-block')">
        <Bookmark :size="16" />
      </button>
      <button
        type="button"
        class="dsf-block-toolbar__btn"
        :class="{ 'dsf-block-toolbar__btn--active': isSelectedForTemplate }"
        :title="isSelectedForTemplate ? 'Selected for template' : 'Select for section template'"
        :aria-label="isSelectedForTemplate ? 'Selected for template' : 'Select for section template'"
        :aria-pressed="isSelectedForTemplate"
        @click.stop="$emit('toggle-select')"
      >
        <component :is="isSelectedForTemplate ? CheckSquare : Square" :size="16" />
      </button>
      <button v-if="allowReorder" type="button" class="dsf-block-toolbar__btn" title="Move up" aria-label="Move block up" @click.stop="$emit('move-up')">
        <ChevronUp :size="16" />
      </button>
      <button v-if="allowReorder" type="button" class="dsf-block-toolbar__btn" title="Move down" aria-label="Move block down" @click.stop="$emit('move-down')">
        <ChevronDown :size="16" />
      </button>
      <button type="button" class="dsf-block-toolbar__btn dsf-block-toolbar__btn--delete" title="Delete" aria-label="Delete block" @click.stop="$emit('delete')">
        <Trash2 :size="16" />
      </button>
    </div>
    
    <!-- Block Content Preview -->
    <component 
      :is="getPreviewComponent(block.type)" 
      :settings="block.settings"
      :is-editor="true"
      :block-id="block.id"
      :preview-mode="previewMode"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { GripVertical, Settings, ChevronUp, ChevronDown, Trash2, Bookmark, CheckSquare, Square } from 'lucide-vue-next'
import { getResponsiveValue } from '../utils/responsiveSettings'

// Block preview components
import ContentPreview from './blocks/ContentPreview.vue'
import FaqPreview from './blocks/FaqPreview.vue'
import HeroPreview from './blocks/HeroCenteredPreview.vue'
import ProductGridPreview from './blocks/ProductGridPreview.vue'
import EcommerceShowcasePreview from './blocks/EcommerceShowcasePreview.vue'
import FeaturesGridPreview from './blocks/FeaturesGridPreview.vue'
import BentoHeroPreview from './blocks/BentoHeroPreview.vue'
import SpotlightHeroPreview from './blocks/SpotlightHeroPreview.vue'
import ExpanderHeroPreview from './blocks/ExpanderHeroPreview.vue'
import PricingPreview from './blocks/PricingPreview.vue'
import TextImagePreview from './blocks/TextImagePreview.vue'
import TestimonialsPreview from './blocks/TestimonialsPreview.vue'
import CtaBannerPreview from './blocks/CtaBannerPreview.vue'
import CountdownPreview from './blocks/CountdownPreview.vue'
import NewsletterPreview from './blocks/NewsletterPreview.vue'
import BrandLogosPreview from './blocks/BrandLogosPreview.vue'
import PromoBannerPreview from './blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from './blocks/FeaturedProductBannerPreview.vue'
import DuoHeroPreview from './blocks/DuoHeroPreview.vue'
import FeaturedPromoBannerPreview from './blocks/FeaturedPromoBannerPreview.vue'
import HeaderMegaMenuPreview from './blocks/HeaderMegaMenuPreview.vue'
import HeaderShowcaseMegaPreview from './blocks/HeaderShowcaseMegaPreview.vue'
import HeaderCutoutMegaPreview from './blocks/HeaderCutoutMegaPreview.vue'
import FooterDealersPreview from './blocks/FooterDealersPreview.vue'
import FormEmbedPreview from './blocks/FormEmbedPreview.vue'
import FormWithContentPreview from './blocks/FormWithContentPreview.vue'
import LandingProgressHeaderPreview from './blocks/LandingProgressHeaderPreview.vue'
import LandingHeroPreview from './blocks/LandingHeroPreview.vue'
import LandingBlockExplorerPreview from './blocks/LandingBlockExplorerPreview.vue'
import LandingBlockReadyPreview from './blocks/LandingBlockReadyPreview.vue'
import LandingProductStoryPreview from './blocks/LandingProductStoryPreview.vue'
import LandingTrustWorkflowPreview from './blocks/LandingTrustWorkflowPreview.vue'
import LandingEngagementSuitePreview from './blocks/LandingEngagementSuitePreview.vue'
import LandingRedirectToolPreview from './blocks/LandingRedirectToolPreview.vue'
import LandingMailToolPreview from './blocks/LandingMailToolPreview.vue'
import LandingMarketingFooterPreview from './blocks/LandingMarketingFooterPreview.vue'
import GenericBlockPreview from './blocks/GenericBlockPreview.vue'

const props = defineProps({
  block: Object,
  index: Number,
  isSelected: Boolean,
  allowReorder: {
    type: Boolean,
    default: true,
  },
  previewMode: {
    type: String,
    default: 'desktop',
  },
  isSelectedForTemplate: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['select', 'move-up', 'move-down', 'delete', 'open-settings', 'save-block', 'toggle-select'])

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

const templateBlockTypes = new Set(['header-mega-menu', 'header-showcase-mega', 'header-cutout-mega', 'footer-dealers', 'landing-progress-header', 'landing-hero', 'landing-block-explorer', 'landing-block-ready', 'landing-product-story', 'landing-trust-workflow', 'landing-engagement-suite', 'landing-redirect-tool', 'landing-mail-tool', 'landing-marketing-footer'])
const isLandingBlock = computed(() => props.block?.type?.startsWith('landing-') === true)

const defaultMargin = computed(() => (
  templateBlockTypes.has(props.block?.type) ? 0 : 25
))

const marginY = computed(() =>
  getResponsiveValue(props.block?.settings || {}, props.previewMode, 'marginY') ?? defaultMargin.value
)

const paddingX = computed(() =>
  getResponsiveValue(props.block?.settings || {}, props.previewMode, 'paddingX') ?? 0
)

const heightValue = computed(() =>
  getResponsiveValue(props.block?.settings || {}, props.previewMode, 'height')
)

const hasExplicitHeight = computed(() => {
  const settings = props.block?.settings || {}
  if (settings.height !== undefined && settings.height !== null) return true
  const responsive = settings.responsive || {}
  return ['desktop', 'tablet', 'mobile'].some(
    (key) => responsive[key]?.height !== undefined && responsive[key]?.height !== null
  )
})

const wrapperStyle = computed(() => {
  const style = {
    marginTop: `${marginY.value}px`,
    marginBottom: `${marginY.value}px`,
    paddingLeft: `${paddingX.value}px`,
    paddingRight: `${paddingX.value}px`,
  }

  if (hasExplicitHeight.value && heightValue.value !== undefined && heightValue.value !== null) {
    style.minHeight = `${heightValue.value}px`
  }

  return style
})
</script>
