import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductSummaryPreview from '../blocks/ProductSummaryPreview.vue'
import { PRODUCT_PLACEHOLDER } from '../../utils/useProductContext'

const PRODUCT = {
  id: 12,
  name: 'Trail Runner Jacket',
  sku: 'TRJ-001',
  type: 'simple',
  priceHtml: '<span class="woocommerce-Price-amount">$129.00</span>',
  shortDescriptionHtml: '<p>Lightweight, water-resistant shell.</p>',
  gallery: [],
  specs: [],
  isInStock: true,
  averageRating: 4,
  ratingCount: 8,
  reviewCount: 8,
}

function mountWithProduct(settings = {}, product = PRODUCT) {
  return mount(ProductSummaryPreview, {
    props: { settings, previewMode: 'desktop' },
    global: { provide: { dsfProductContext: ref(product) } },
  })
}

describe('ProductSummaryPreview', () => {
  it('renders the product title, price, and short description from context', () => {
    const wrapper = mountWithProduct()
    expect(wrapper.find('.dsf-product-summary__title').text()).toBe('Trail Runner Jacket')
    expect(wrapper.find('.dsf-product-summary__price').html()).toContain('$129.00')
    expect(wrapper.find('.dsf-product-summary__excerpt').html()).toContain('water-resistant')
  })

  it('uses the configured heading level', () => {
    const wrapper = mountWithProduct({ headingTag: 'h2' })
    expect(wrapper.find('h2.dsf-product-summary__title').exists()).toBe(true)
    expect(wrapper.find('h1.dsf-product-summary__title').exists()).toBe(false)
  })

  it('respects visibility toggles', () => {
    const wrapper = mountWithProduct({
      showPrice: false,
      showShortDescription: false,
      showSku: true,
      showRating: false,
    })
    expect(wrapper.find('.dsf-product-summary__price').exists()).toBe(false)
    expect(wrapper.find('.dsf-product-summary__excerpt').exists()).toBe(false)
    expect(wrapper.find('.dsf-product-summary__rating').exists()).toBe(false)
    expect(wrapper.find('.dsf-product-summary__sku').text()).toContain('TRJ-001')
  })

  it('shows the rating only when there are ratings', () => {
    expect(mountWithProduct().find('.dsf-product-summary__rating').exists()).toBe(true)
    const noRating = mountWithProduct({}, { ...PRODUCT, ratingCount: 0 })
    expect(noRating.find('.dsf-product-summary__rating').exists()).toBe(false)
  })

  it('marks out-of-stock products', () => {
    const wrapper = mountWithProduct({}, { ...PRODUCT, isInStock: false })
    expect(wrapper.find('.dsf-product-summary__stock').classes()).toContain('is-out-of-stock')
    expect(wrapper.find('.dsf-product-summary__stock').text()).toBe('Out of stock')
  })

  it('applies center alignment', () => {
    const wrapper = mountWithProduct({ alignment: 'center' })
    expect(wrapper.find('.dsf-product-summary').attributes('style')).toContain('text-align: center')
    expect(wrapper.find('.dsf-product-summary__inner').attributes('style')).toContain('margin-left: auto')
  })

  it('falls back to a safe placeholder when no product context is provided', () => {
    const wrapper = mount(ProductSummaryPreview, { props: { settings: {} } })
    expect(wrapper.find('.dsf-product-summary__title').text()).toBe(PRODUCT_PLACEHOLDER.name)
    // Must not throw on empty gallery/specs.
    expect(wrapper.find('.dsf-product-summary').exists()).toBe(true)
  })

  it('does not render markup for fields the product is missing', () => {
    const wrapper = mountWithProduct({}, { ...PRODUCT, priceHtml: '', shortDescriptionHtml: '' })
    expect(wrapper.find('.dsf-product-summary__price').exists()).toBe(false)
    expect(wrapper.find('.dsf-product-summary__excerpt').exists()).toBe(false)
  })
})
