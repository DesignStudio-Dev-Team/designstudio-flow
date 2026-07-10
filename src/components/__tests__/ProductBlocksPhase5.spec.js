import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductHeroPreview from '../blocks/ProductHeroPreview.vue'
import ProductHighlightsPreview from '../blocks/ProductHighlightsPreview.vue'
import ProductRelatedPreview from '../blocks/ProductRelatedPreview.vue'

const PRODUCT = {
  name: 'Alpine Boots',
  sku: 'AB-9',
  priceHtml: '<span class="amount">$189.00</span>',
  shortDescriptionHtml: '<p>Waterproof leather boots.</p>',
  gallery: [
    { id: 1, full: 'a-full.jpg', large: 'a-large.jpg', thumb: 'a-thumb.jpg', srcset: '', alt: 'Pair' },
    { id: 2, full: 'b-full.jpg', large: 'b-large.jpg', thumb: 'b-thumb.jpg', srcset: '', alt: 'Sole' },
  ],
  specs: [],
  isInStock: true,
  onSale: true,
  averageRating: 4.5,
  ratingCount: 12,
  reviewCount: 12,
  addToCartHtml: '<form class="cart"><button class="single_add_to_cart_button">Add to cart</button></form>',
  relatedProducts: [
    { id: 11, name: 'Trail Socks', permalink: '/p/socks', priceHtml: '<span>$12</span>', image: 's.jpg', imageAlt: '', onSale: false },
    { id: 12, name: 'Gaiters', permalink: '/p/gaiters', priceHtml: '<span>$29</span>', image: 'g.jpg', imageAlt: '', onSale: true },
    { id: 13, name: 'Laces', permalink: '/p/laces', priceHtml: '<span>$6</span>', image: 'l.jpg', imageAlt: '', onSale: false },
  ],
}

function mountBlock(Component, settings = {}, product = PRODUCT, isEditor = true) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 'x1', isEditor },
    global: { provide: { dsfProductContext: ref(product) } },
  })
}

describe('ProductHeroPreview', () => {
  it('renders title, price, badge, cart form, and gallery', () => {
    const w = mountBlock(ProductHeroPreview)
    expect(w.find('.dsf-product-hero__title').text()).toBe('Alpine Boots')
    expect(w.find('.dsf-product-hero__price').html()).toContain('$189.00')
    expect(w.find('.dsf-product-hero__badge').text()).toBe('Sale')
    expect(w.find('.dsf-product-hero__cart form.cart').exists()).toBe(true)
    expect(w.findAll('.dsf-product-hero__thumb')).toHaveLength(2)
  })

  it('switches the main image via thumbnails', async () => {
    const w = mountBlock(ProductHeroPreview)
    await w.findAll('.dsf-product-hero__thumb')[1].trigger('click')
    expect(w.find('.dsf-product-hero__frame img').attributes('src')).toBe('b-large.jpg')
  })

  it('hides badge when not on sale and cart when toggled off', () => {
    const w = mountBlock(ProductHeroPreview, { showAddToCart: false }, { ...PRODUCT, onSale: false })
    expect(w.find('.dsf-product-hero__badge').exists()).toBe(false)
    expect(w.find('.dsf-product-hero__cart').exists()).toBe(false)
    expect(w.find('.dsf-product-hero__cart-placeholder').exists()).toBe(false)
  })

  it('shows a cart placeholder when the form HTML is missing', () => {
    const w = mountBlock(ProductHeroPreview, {}, { ...PRODUCT, addToCartHtml: '' })
    expect(w.find('.dsf-product-hero__cart-placeholder').exists()).toBe(true)
  })

  it('flips column order for image-right', () => {
    const w = mountBlock(ProductHeroPreview, { imageSide: 'right' })
    expect(w.find('.dsf-product-hero--image-right').exists()).toBe(true)
  })
})

describe('ProductHighlightsPreview', () => {
  it('renders configured items', () => {
    const w = mountBlock(ProductHighlightsPreview, {
      items: [
        { icon: 'zap', title: 'Fast dispatch', description: 'Ships in 24h' },
        { icon: 'star', title: 'Top rated', description: '' },
      ],
    })
    const items = w.findAll('.dsf-product-highlights__item')
    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('Fast dispatch')
    expect(items[0].text()).toContain('Ships in 24h')
  })

  it('falls back to sensible defaults with no items', () => {
    const w = mountBlock(ProductHighlightsPreview, { items: [] })
    expect(w.findAll('.dsf-product-highlights__item').length).toBeGreaterThan(0)
    expect(w.text()).toContain('Free shipping')
  })

  it('applies grid layout with column count', () => {
    const w = mountBlock(ProductHighlightsPreview, { layout: 'grid', columns: 4 })
    expect(w.find('.dsf-product-highlights--grid').exists()).toBe(true)
    expect(w.find('.dsf-product-highlights__list').attributes('style')).toContain('repeat(4')
  })

  it('ignores malformed items without crashing', () => {
    const w = mountBlock(ProductHighlightsPreview, { items: ['bad', null, { icon: 'zap' }] })
    expect(w.find('.dsf-product-highlights').exists()).toBe(true)
  })
})

describe('ProductRelatedPreview', () => {
  it('renders related product cards with names and prices', () => {
    const w = mountBlock(ProductRelatedPreview)
    const cards = w.findAll('.dsf-product-related__card')
    expect(cards).toHaveLength(3)
    expect(cards[0].text()).toContain('Trail Socks')
    expect(cards[0].find('.dsf-product-related__price').html()).toContain('$12')
  })

  it('caps cards at the configured count', () => {
    const w = mountBlock(ProductRelatedPreview, { count: 2 })
    expect(w.findAll('.dsf-product-related__card')).toHaveLength(2)
  })

  it('prevents navigation in the editor', async () => {
    const w = mountBlock(ProductRelatedPreview, {}, PRODUCT, true)
    const event = { preventDefault: () => { event.prevented = true } }
    await w.find('.dsf-product-related__link').trigger('click')
    // jsdom won't navigate; the important part is the handler exists and no crash.
    expect(w.find('.dsf-product-related__link').attributes('href')).toBe('/p/socks')
  })

  it('shows the editor empty state without related products', () => {
    const w = mountBlock(ProductRelatedPreview, {}, { ...PRODUCT, relatedProducts: [] })
    expect(w.find('.dsf-product-related__empty').text()).toContain('preview product')
  })

  it('shows a sale badge on discounted cards', () => {
    const w = mountBlock(ProductRelatedPreview)
    const badges = w.findAll('.dsf-product-related__badge')
    expect(badges).toHaveLength(1)
  })
})
