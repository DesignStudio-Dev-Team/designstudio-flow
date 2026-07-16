<template>
  <section class="dsf-product-details-split" :class="{ 'is-image-right': settings.imageSide === 'right' }" :style="blockStyle">
    <div ref="root" class="dsf-product-details-split__inner" :style="innerStyle">
      <div class="dsf-product-details-split__gallery">
        <div class="dsf-product-details-split__image-wrap">
          <img v-if="activeImage.large" :src="activeImage.large" :srcset="activeImage.srcset || undefined" :alt="activeImage.alt || ''" fetchpriority="high" decoding="async" />
        </div>
        <div v-if="images.length > 1" class="dsf-product-details-split__thumbs">
          <button v-for="(image, index) in images.slice(0, 6)" :key="image.id || index" type="button" :class="{ 'is-active': activeIndex === index }" :aria-label="`Show image ${index + 1}`" @click="activeIndex = index">
            <img :src="image.thumb" :alt="image.alt || ''" loading="lazy" decoding="async" />
          </button>
        </div>
      </div>
      <article class="dsf-product-details-split__card">
        <p v-if="settings.showRating !== false && product.ratingCount" class="dsf-product-details-split__rating">★ {{ Number(product.averageRating || 0).toFixed(1) }} <span>({{ product.reviewCount || 0 }} reviews)</span></p>
        <h1>{{ product.name }}</h1>
        <div v-if="product.priceHtml" class="dsf-product-details-split__price" v-html="product.priceHtml"></div>
        <div v-if="settings.showShortDescription !== false && product.shortDescriptionHtml" class="dsf-product-details-split__description" v-html="product.shortDescriptionHtml"></div>
        <p class="dsf-product-details-split__stock" :class="product.isInStock ? 'in-stock' : 'out-stock'">{{ product.isInStock ? 'In stock and ready to ship' : 'Currently unavailable' }}</p>
        <div v-if="showCart && cartHtml" class="dsf-product-details-split__cart" v-html="cartHtml"></div>
        <p v-else-if="showCart" class="dsf-product-details-split__cart-placeholder">Add-to-cart controls appear on the live product page.</p>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'
import { useWooCartForm } from '../../utils/useWooCartForm'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const { product } = useProductContext()
const renderMode = inject('dsfRenderMode', null)
const images = computed(() => Array.isArray(product.value?.gallery) ? product.value.gallery : [])
const activeIndex = ref(0)
watch(images, () => { activeIndex.value = 0 })
const activeImage = computed(() => images.value[activeIndex.value] || images.value[0] || {})
const showCart = computed(() => props.settings?.showAddToCart !== false)
const cartHtml = computed(() => product.value?.addToCartHtml || '')
const root = ref(null)
useWooCartForm(root, () => !props.isEditor && renderMode !== 'snapshot' && showCart.value && Boolean(cartHtml.value))
const blockStyle = computed(() => {
  const padding = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 48
  const style = { paddingTop: `${padding}px`, paddingBottom: `${padding}px`, backgroundColor: props.settings?.backgroundColor || 'transparent', '--dsf-pds-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)' }
  if (props.settings?.buttonColor) style['--dsf-cart-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-cart-btn-color'] = props.settings.buttonTextColor
  return style
})
const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1280}px` }))
</script>

<style scoped>
.dsf-product-details-split { width: 100%; }
.dsf-product-details-split__inner { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(340px, .8fr); gap: clamp(1.5rem, 5vw, 5rem); align-items: center; margin: 0 auto; }
.dsf-product-details-split.is-image-right .dsf-product-details-split__gallery { order: 2; }
.dsf-product-details-split__image-wrap { overflow: hidden; border-radius: 30px; background: #edf1f4; aspect-ratio: 1 / 1; }
.dsf-product-details-split__image-wrap img { display: block; width: 100%; height: 100%; object-fit: cover; }
.dsf-product-details-split__thumbs { display: grid; grid-template-columns: repeat(6, 1fr); gap: .65rem; margin-top: .8rem; }
.dsf-product-details-split__thumbs button { padding: 0; overflow: hidden; border: 2px solid transparent; border-radius: 12px; background: #edf1f4; aspect-ratio: 1; cursor: pointer; }
.dsf-product-details-split__thumbs button.is-active { border-color: var(--dsf-pds-accent); }
.dsf-product-details-split__thumbs img { width: 100%; height: 100%; object-fit: cover; }
.dsf-product-details-split__card { padding: clamp(1.5rem, 4vw, 3rem); border-radius: 28px; background: #fff; box-shadow: 0 22px 55px rgb(15 23 42 / 12%); }
.dsf-product-details-split__rating { margin: 0 0 .8rem; color: var(--dsf-pds-accent); font-weight: 800; }.dsf-product-details-split__rating span { color: #64748b; font-weight: 500; }
.dsf-product-details-split__card h1 { margin: 0; color: var(--dsf-theme-text, #172033); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2rem, 4vw, 3.4rem); line-height: 1; }
.dsf-product-details-split__price { margin: 1.2rem 0; color: var(--dsf-pds-accent); font-size: 1.6rem; font-weight: 800; }.dsf-product-details-split__description { color: #475569; line-height: 1.65; }
.dsf-product-details-split__stock { margin: 1.25rem 0; font-weight: 700; }.dsf-product-details-split__stock.in-stock { color: #15803d; }.dsf-product-details-split__stock.out-stock { color: #b91c1c; }
.dsf-product-details-split__cart :deep(.single_add_to_cart_button) { border-radius: 12px; background: var(--dsf-cart-btn-bg, var(--dsf-pds-accent)); color: var(--dsf-cart-btn-color, #fff); }
.dsf-product-details-split__cart-placeholder { padding: 1rem; border-radius: 12px; background: #f1f5f9; color: #64748b; }
@media (max-width: 760px) { .dsf-product-details-split__inner { grid-template-columns: 1fr; }.dsf-product-details-split.is-image-right .dsf-product-details-split__gallery { order: 0; } }
</style>
