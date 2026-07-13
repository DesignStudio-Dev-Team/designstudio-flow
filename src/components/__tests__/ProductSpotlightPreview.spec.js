import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductSpotlightPreview from '../blocks/ProductSpotlightPreview.vue'

const PRODUCT = {
  name: 'Alpine Boots',
  sku: 'AB-9',
  priceHtml: '<span class="amount">$189.00</span>',
  shortDescriptionHtml: '<p>Waterproof leather boots.</p>',
  gallery: [
    { id: 1, full: 'a-full.jpg', large: 'a-large.jpg', thumb: 'a-thumb.jpg', srcset: '', alt: 'Pair' },
    { id: 2, full: 'b-full.jpg', large: 'b-large.jpg', thumb: 'b-thumb.jpg', srcset: '', alt: 'Sole' },
    { id: 3, full: 'c-full.jpg', large: 'c-large.jpg', thumb: 'c-thumb.jpg', srcset: '', alt: 'Top' },
  ],
  isInStock: true,
  onSale: true,
  averageRating: 4.5,
  ratingCount: 12,
  reviewCount: 12,
  addToCartHtml: '<form class="cart"><button class="single_add_to_cart_button">Add to cart</button></form>',
}

function mountBlock(settings = {}, product = PRODUCT, isEditor = true) {
  return mount(ProductSpotlightPreview, {
    props: { settings, previewMode: 'desktop', blockId: 'x1', isEditor },
    global: { provide: { dsfProductContext: ref(product) } },
  })
}

describe('ProductSpotlightPreview', () => {
  it('renders title, price, sale chip, stock chip, cart form, and thumb rail', () => {
    const w = mountBlock()
    expect(w.find('.dsf-product-spotlight__title').text()).toBe('Alpine Boots')
    expect(w.find('.dsf-product-spotlight__price').html()).toContain('$189.00')
    expect(w.find('.dsf-product-spotlight__chip--sale').text()).toBe('Sale')
    expect(w.find('.dsf-product-spotlight__chip.is-in-stock').text()).toContain('In stock')
    expect(w.find('.dsf-product-spotlight__buy form.cart').exists()).toBe(true)
    expect(w.findAll('.dsf-product-spotlight__thumb')).toHaveLength(3)
  })

  it('switches the main image via the thumbnail rail', async () => {
    const w = mountBlock()
    await w.findAll('.dsf-product-spotlight__thumb')[1].trigger('click')
    expect(w.find('.dsf-product-spotlight__frame img').attributes('src')).toBe('b-large.jpg')
    expect(w.findAll('.dsf-product-spotlight__thumb')[1].classes()).toContain('is-active')
  })

  it('shows the rating chip with average and count', () => {
    const w = mountBlock()
    const chip = w.find('.dsf-product-spotlight__chips .dsf-product-spotlight__chip')
    expect(chip.text()).toContain('4.5')
    expect(chip.text()).toContain('12')
  })

  it('hides sale chip when not on sale and cart when toggled off', () => {
    const w = mountBlock({ showAddToCart: false }, { ...PRODUCT, onSale: false })
    expect(w.find('.dsf-product-spotlight__chip--sale').exists()).toBe(false)
    expect(w.find('.dsf-product-spotlight__buy').exists()).toBe(false)
  })

  it('shows a buy placeholder when the form HTML is missing', () => {
    const w = mountBlock({}, { ...PRODUCT, addToCartHtml: '' })
    expect(w.find('.dsf-product-spotlight__buy--placeholder').exists()).toBe(true)
  })

  it('renders the eyebrow only when configured and shows SKU chip on demand', () => {
    const off = mountBlock()
    expect(off.find('.dsf-product-spotlight__eyebrow').exists()).toBe(false)

    const on = mountBlock({ eyebrowText: 'New Season', showSku: true })
    expect(on.find('.dsf-product-spotlight__eyebrow').text()).toContain('New Season')
    expect(on.text()).toContain('SKU AB-9')
  })

  it('flips column order for image-right and applies the backdrop variant', () => {
    const w = mountBlock({ imageSide: 'right', backdrop: 'none' })
    expect(w.find('.dsf-product-spotlight__stage--image-right').exists()).toBe(true)
    expect(w.find('.dsf-product-spotlight__stage--none').exists()).toBe(true)
    expect(w.find('.dsf-product-spotlight__stage--soft').exists()).toBe(false)
  })

  it('falls back to the soft backdrop for unknown values', () => {
    const w = mountBlock({ backdrop: 'party-mode' })
    expect(w.find('.dsf-product-spotlight__stage--soft').exists()).toBe(true)
  })

  it('survives an empty gallery and missing product fields', () => {
    const w = mountBlock({}, { name: 'Bare' })
    expect(w.find('.dsf-product-spotlight__title').text()).toBe('Bare')
    expect(w.find('.dsf-product-spotlight__rail').exists()).toBe(false)
    expect(w.find('.dsf-product-spotlight__frame img').exists()).toBe(false)
  })
})
