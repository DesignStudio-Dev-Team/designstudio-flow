<template>
  <section class="dsf-product-cart" :style="blockStyle">
    <div ref="rootEl" class="dsf-product-cart__inner" :style="innerStyle">
      <!--
        addToCartHtml is WooCommerce's own single add-to-cart form, server-rendered and
        sanitized with a Woo-form-aware wp_kses() allowlist in DSF_Product_Templates.
        Re-initialized on the frontend so variation selectors work after Vue injects it.
      -->
      <div v-if="cartHtml" class="dsf-product-cart__form" v-html="cartHtml"></div>
      <div v-else class="dsf-product-cart__placeholder">
        <ShoppingCart :size="18" />
        <span>The add-to-cart form appears here on the live product page.</span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, inject, onMounted, onBeforeUnmount } from 'vue'
import { ShoppingCart } from 'lucide-vue-next'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { product } = useProductContext()
const renderMode = inject('dsfRenderMode', null)

const cartHtml = computed(() => product.value?.addToCartHtml || '')

const rootEl = ref(null)
let retryTimer = null
let attempts = 0

// WooCommerce's variation script binds to .variations_form on DOM ready, but Vue
// injects this form afterwards, so initialize it ourselves once jQuery + the plugin
// are available. Frontend only — never in the editor or snapshot render.
function initVariationForm() {
  const $ = typeof window !== 'undefined' ? window.jQuery : null
  if (!$ || typeof $.fn?.wc_variation_form !== 'function') {
    if (attempts++ < 20) retryTimer = window.setTimeout(initVariationForm, 150)
    return
  }
  const root = rootEl.value
  if (!root) return
  $(root)
    .find('.variations_form')
    .each(function initOne() {
      const $form = $(this)
      if (!$form.data('dsf-wc-init')) {
        $form.wc_variation_form()
        $form.data('dsf-wc-init', true)
      }
    })
}

onMounted(() => {
  if (props.isEditor || renderMode === 'snapshot' || !cartHtml.value) return
  initVariationForm()
})

onBeforeUnmount(() => {
  if (retryTimer) window.clearTimeout(retryTimer)
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    textAlign: props.settings?.alignment === 'center' ? 'center' : 'left',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 460
  const style = {
    maxWidth: `${maxWidth}px`,
    marginLeft: props.settings?.alignment === 'center' ? 'auto' : '0',
    marginRight: props.settings?.alignment === 'center' ? 'auto' : '0',
  }
  if (props.settings?.buttonColor) style['--dsf-cart-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-cart-btn-color'] = props.settings.buttonTextColor
  return style
})
</script>

<style scoped>
.dsf-product-cart { width: 100%; }
.dsf-product-cart__inner { font-family: var(--dsf-theme-body-font, inherit); }

.dsf-product-cart__placeholder {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  border: 1px dashed var(--dsf-gray-300, #d1d5db);
  border-radius: 10px;
  color: var(--dsf-gray-500, #6b7280);
  font-size: 0.9rem;
}

/* Theme WooCommerce's own markup that we inject via v-html. */
.dsf-product-cart__form :deep(.quantity) { margin-right: 0.5rem; }
.dsf-product-cart__form :deep(input.qty) {
  width: 4.5rem;
  padding: 0.6rem 0.5rem;
  border: 1px solid var(--dsf-gray-300, #d1d5db);
  border-radius: 8px;
}
.dsf-product-cart__form :deep(.single_add_to_cart_button) {
  background: var(--dsf-cart-btn-bg, var(--dsf-theme-primary, #2c5f5d));
  color: var(--dsf-cart-btn-color, #fff);
  border: 0;
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  cursor: pointer;
}
.dsf-product-cart__form :deep(.single_add_to_cart_button:hover) { opacity: 0.92; }
.dsf-product-cart__form :deep(table.variations) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}
.dsf-product-cart__form :deep(table.variations td),
.dsf-product-cart__form :deep(table.variations th) {
  padding: 0.4rem 0.5rem 0.4rem 0;
  text-align: left;
  vertical-align: middle;
}
.dsf-product-cart__form :deep(table.variations select) {
  width: 100%;
  padding: 0.55rem 0.6rem;
  border: 1px solid var(--dsf-gray-300, #d1d5db);
  border-radius: 8px;
}
.dsf-product-cart__form :deep(.reset_variations) {
  display: inline-block;
  margin-left: 0.5rem;
  font-size: 0.85rem;
}
.dsf-product-cart__form :deep(.woocommerce-variation-price) { margin-bottom: 0.75rem; font-weight: 600; }
</style>
