import { inject, computed, isRef } from 'vue'

// Shown in the editor before a preview product is chosen, and as a safe fallback
// if a product block ever renders without product data (e.g. snapshot mode).
export const PRODUCT_PLACEHOLDER = Object.freeze({
  id: 0,
  name: 'Sample Product',
  permalink: '#',
  sku: 'SKU-0000',
  type: 'simple',
  priceHtml: '<span class="woocommerce-Price-amount amount">$49.00</span>',
  shortDescriptionHtml:
    '<p>Your product’s short description shows here. Choose a preview product in Page Settings → Product to see real data.</p>',
  descriptionHtml: '<p>The full product description shows here.</p>',
  gallery: [],
  specs: [],
  stockStatus: 'instock',
  isInStock: true,
  onSale: false,
  isPurchasable: true,
  averageRating: 0,
  ratingCount: 0,
  reviewCount: 0,
})

// Used on the frontend when no product could be resolved. Every product block
// renders its fields conditionally, so an empty product renders nothing.
const EMPTY_PRODUCT = Object.freeze({})

/**
 * Resolve the current product the product blocks should render.
 *
 * The product data is provided by the app root (a ref in the editor, the viewed
 * product on the frontend). Falls back to the localized window data (for snapshot
 * rendering) and finally to a safe empty/placeholder product so blocks never crash.
 *
 * @returns {{ product: import('vue').ComputedRef<object> }}
 */
export function useProductContext() {
  const injected = inject('dsfProductContext', null)

  const product = computed(() => {
    const fromInject = isRef(injected) ? injected.value : injected
    if (fromInject && typeof fromInject === 'object') return fromInject

    if (typeof window !== 'undefined') {
      const fromWindow =
        window.dsfFrontendData?.currentProduct || window.dsfEditorData?.currentProduct
      if (fromWindow && typeof fromWindow === 'object') return fromWindow

      // The placeholder is editor scaffolding: it invents a name and a $49.00
      // price. On the frontend an unresolved product means the page genuinely has
      // none, and a visitor must never be shown a made-up price.
      if (window.dsfFrontendData && !window.dsfEditorData) {
        return EMPTY_PRODUCT
      }
    }

    return PRODUCT_PLACEHOLDER
  })

  return { product }
}
