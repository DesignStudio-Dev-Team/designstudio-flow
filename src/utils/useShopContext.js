import { inject, computed, isRef } from 'vue'

// Shown in the shop-template editor before catalog data loads, and as a safe
// fallback if a shop block ever renders without archive data (e.g. snapshots).
export const ARCHIVE_PLACEHOLDER = Object.freeze({
  title: 'Shop',
  descriptionHtml: '',
  products: [],
  total: 0,
  perPage: 12,
  currentPage: 1,
  totalPages: 1,
  pagination: [],
  orderby: 'menu_order',
  orderbyOptions: [
    { value: 'menu_order', label: 'Default sorting' },
    { value: 'popularity', label: 'Sort by popularity' },
    { value: 'rating', label: 'Sort by average rating' },
    { value: 'date', label: 'Sort by latest' },
    { value: 'price', label: 'Sort by price: low to high' },
    { value: 'price-desc', label: 'Sort by price: high to low' },
  ],
})

/**
 * Resolve the product-archive payload the shop blocks should render.
 *
 * Provided by the app root (a ref in the editor holding the sample archive, the
 * viewed archive on the frontend). Falls back to the localized window data (for
 * snapshot rendering) and finally to a safe placeholder so blocks never crash.
 *
 * @returns {{ archive: import('vue').ComputedRef<object> }}
 */
export function useShopContext() {
  const injected = inject('dsfShopContext', null)

  const archive = computed(() => {
    const fromInject = isRef(injected) ? injected.value : injected
    if (fromInject && typeof fromInject === 'object') return fromInject

    if (typeof window !== 'undefined') {
      const fromWindow =
        window.dsfFrontendData?.currentArchive || window.dsfEditorData?.currentArchive
      if (fromWindow && typeof fromWindow === 'object') return fromWindow
    }

    return ARCHIVE_PLACEHOLDER
  })

  return { archive }
}
