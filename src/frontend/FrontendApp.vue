<template>
  <div class="dsf-frontend-blocks">
    <div
      v-for="block in blocks"
      :key="block.id"
      class="dsf-block"
      :style="{
        marginTop: (block.settings?.marginY ?? 25) + 'px',
        marginBottom: (block.settings?.marginY ?? 25) + 'px',
        paddingLeft: (block.settings?.paddingX ?? 0) + 'px',
        paddingRight: (block.settings?.paddingX ?? 0) + 'px',
      }"
    >
      <component
        :is="getPreviewComponent(block.type)"
        :settings="block.settings"
        :is-editor="false"
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
import GenericBlockPreview from '../components/blocks/GenericBlockPreview.vue'
import FlowModal from '../components/common/FlowModal.vue'
import { provideFlowModal } from '../components/common/useFlowModal'
import { createModalController } from './modalController'

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
}

function getPreviewComponent(blockType) {
  return previewComponents[blockType] || GenericBlockPreview
}

const { modalState: modal, openModalAction: openModal, closeModalAction: closeModal } =
  createModalController()

provideFlowModal({ openModal, closeModal })
</script>
