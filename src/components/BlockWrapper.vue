<template>
  <div 
    class="dsf-block"
    :id="'block-' + block.id"
    :class="{ 'dsf-block--selected': isSelected }"
    :style="wrapperStyle"
    @click.stop="$emit('select')"
  >
    <!-- Block Toolbar -->
    <div class="dsf-block-toolbar">
      <button class="dsf-block-toolbar__btn dsf-block-toolbar__btn--drag" title="Drag to reorder">
        <GripVertical :size="16" />
      </button>
      <button class="dsf-block-toolbar__btn" title="Settings" @click.stop="$emit('open-settings')">
        <Settings :size="16" />
      </button>
      <button class="dsf-block-toolbar__btn" title="Move up" @click.stop="$emit('move-up')">
        <ChevronUp :size="16" />
      </button>
      <button class="dsf-block-toolbar__btn" title="Move down" @click.stop="$emit('move-down')">
        <ChevronDown :size="16" />
      </button>
      <button class="dsf-block-toolbar__btn dsf-block-toolbar__btn--delete" title="Delete" @click.stop="$emit('delete')">
        <Trash2 :size="16" />
      </button>
    </div>
    
    <!-- Block Content Preview -->
    <component 
      :is="getPreviewComponent(block.type)" 
      :settings="block.settings"
      :is-editor="true"
      :preview-mode="previewMode"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { GripVertical, Settings, ChevronUp, ChevronDown, Trash2 } from 'lucide-vue-next'
import { getResponsiveValue } from '../utils/responsiveSettings'

// Block preview components
import HeroPreview from './blocks/HeroCenteredPreview.vue'
import ProductGridPreview from './blocks/ProductGridPreview.vue'
import EcommerceShowcasePreview from './blocks/EcommerceShowcasePreview.vue'
import FeaturesGridPreview from './blocks/FeaturesGridPreview.vue'
import BentoHeroPreview from './blocks/BentoHeroPreview.vue'
import TextImagePreview from './blocks/TextImagePreview.vue'
import TestimonialsPreview from './blocks/TestimonialsPreview.vue'
import CtaBannerPreview from './blocks/CtaBannerPreview.vue'
import NewsletterPreview from './blocks/NewsletterPreview.vue'
import BrandLogosPreview from './blocks/BrandLogosPreview.vue'
import PromoBannerPreview from './blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from './blocks/FeaturedProductBannerPreview.vue'
import DuoHeroPreview from './blocks/DuoHeroPreview.vue'
import FeaturedPromoBannerPreview from './blocks/FeaturedPromoBannerPreview.vue'
import HeaderMegaMenuPreview from './blocks/HeaderMegaMenuPreview.vue'
import HeaderCutoutMegaPreview from './blocks/HeaderCutoutMegaPreview.vue'
import FooterDealersPreview from './blocks/FooterDealersPreview.vue'
import FormEmbedPreview from './blocks/FormEmbedPreview.vue'
import GenericBlockPreview from './blocks/GenericBlockPreview.vue'

const props = defineProps({
  block: Object,
  index: Number,
  isSelected: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

defineEmits(['select', 'move-up', 'move-down', 'delete', 'open-settings'])

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

const templateBlockTypes = new Set(['header-mega-menu', 'header-cutout-mega', 'footer-dealers'])

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
