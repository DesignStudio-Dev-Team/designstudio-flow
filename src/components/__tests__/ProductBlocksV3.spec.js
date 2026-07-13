import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductReviewsPreview from '../blocks/ProductReviewsPreview.vue'
import ProductMetaPreview from '../blocks/ProductMetaPreview.vue'
import ProductUpsellsPreview from '../blocks/ProductUpsellsPreview.vue'

const PRODUCT = {
  name: 'Alpine Boots',
  sku: 'AB-9',
  priceHtml: '<span class="amount">$189.00</span>',
  averageRating: 4.5,
  ratingCount: 12,
  reviewCount: 12,
  reviewsHtml: '<ol class="commentlist"><li>Great boots!</li></ol>',
  categories: [
    { name: 'Footwear', url: '/cat/footwear' },
    { name: 'Outdoor', url: '/cat/outdoor' },
  ],
  tags: [{ name: 'waterproof', url: '/tag/waterproof' }],
  upsellProducts: [
    { id: 21, name: 'Wool Socks', permalink: '/p/wool-socks', priceHtml: '<span>$15</span>', image: 'w.jpg', imageAlt: '', onSale: false },
    { id: 22, name: 'Boot Wax', permalink: '/p/boot-wax', priceHtml: '<span>$9</span>', image: 'x.jpg', imageAlt: '', onSale: true },
    { id: 23, name: 'Insoles', permalink: '/p/insoles', priceHtml: '<span>$19</span>', image: 'i.jpg', imageAlt: '', onSale: false },
  ],
}

function mountBlock(Component, settings = {}, product = PRODUCT, isEditor = true) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 'x1', isEditor },
    global: { provide: { dsfProductContext: ref(product) } },
  })
}

describe('ProductReviewsPreview', () => {
  it('renders heading, rating summary, and the reviews body', () => {
    const w = mountBlock(ProductReviewsPreview)
    expect(w.find('.dsf-product-reviews__heading').text()).toBe('Customer Reviews')
    expect(w.find('.dsf-product-reviews__average').text()).toBe('4.5')
    expect(w.find('.dsf-product-reviews__count').text()).toContain('Based on 12 reviews')
    expect(w.find('.dsf-product-reviews__body').html()).toContain('Great boots!')
  })

  it('uses a custom heading and hides it when toggled off', () => {
    const custom = mountBlock(ProductReviewsPreview, { headingText: 'What buyers say' })
    expect(custom.find('.dsf-product-reviews__heading').text()).toBe('What buyers say')

    const hidden = mountBlock(ProductReviewsPreview, { showHeading: false })
    expect(hidden.find('.dsf-product-reviews__heading').exists()).toBe(false)
  })

  it('hides the summary when toggled off or when there are no ratings', () => {
    const toggled = mountBlock(ProductReviewsPreview, { showSummary: false })
    expect(toggled.find('.dsf-product-reviews__summary').exists()).toBe(false)

    const unrated = mountBlock(ProductReviewsPreview, {}, { ...PRODUCT, ratingCount: 0, averageRating: 0 })
    expect(unrated.find('.dsf-product-reviews__summary').exists()).toBe(false)
  })

  it('shows an editor placeholder when the reviews HTML is missing', () => {
    const w = mountBlock(ProductReviewsPreview, {}, { ...PRODUCT, reviewsHtml: '' })
    expect(w.find('.dsf-product-reviews__body').exists()).toBe(false)
    expect(w.find('.dsf-product-reviews__empty').text()).toContain('live product page')
  })

  it('shows a frontend empty message when there are no reviews', () => {
    const w = mountBlock(ProductReviewsPreview, {}, { ...PRODUCT, reviewsHtml: '' }, false)
    expect(w.find('.dsf-product-reviews__empty').text()).toContain('no reviews yet')
  })

  it('does not crash on a product without review fields', () => {
    const w = mountBlock(ProductReviewsPreview, {}, { name: 'Bare' })
    expect(w.find('.dsf-product-reviews').exists()).toBe(true)
  })
})

describe('ProductMetaPreview', () => {
  it('renders SKU, category links, and tag links', () => {
    const w = mountBlock(ProductMetaPreview)
    expect(w.text()).toContain('SKU:')
    expect(w.text()).toContain('AB-9')
    const links = w.findAll('.dsf-product-meta__link')
    expect(links.map((l) => l.text())).toEqual(['Footwear', 'Outdoor', 'waterproof'])
    expect(links[0].attributes('href')).toBe('/cat/footwear')
  })

  it('pluralizes labels by term count', () => {
    const w = mountBlock(ProductMetaPreview)
    expect(w.text()).toContain('Categories:')
    expect(w.text()).toContain('Tag:')
    expect(w.text()).not.toContain('Tags:')
  })

  it('hides rows per toggle', () => {
    const w = mountBlock(ProductMetaPreview, { showSku: false, showTags: false })
    expect(w.text()).not.toContain('SKU:')
    expect(w.text()).not.toContain('Tag:')
    expect(w.text()).toContain('Categories:')
  })

  it('filters malformed terms without crashing', () => {
    const w = mountBlock(ProductMetaPreview, {}, {
      ...PRODUCT,
      categories: ['bad', null, { name: 'Real', url: '/c/real' }, { url: '/no-name' }],
      tags: 'not-an-array',
    })
    const links = w.findAll('.dsf-product-meta__link')
    expect(links.map((l) => l.text())).toEqual(['Real'])
  })

  it('shows the editor empty state when nothing is displayable', () => {
    const w = mountBlock(ProductMetaPreview, {}, { name: 'Bare', sku: '' })
    expect(w.find('.dsf-product-meta__empty').text()).toContain('preview product')
  })

  it('applies the inline layout and center alignment classes', () => {
    const w = mountBlock(ProductMetaPreview, { layout: 'inline', alignment: 'center' })
    expect(w.find('.dsf-product-meta--inline').exists()).toBe(true)
    expect(w.find('.dsf-product-meta--center').exists()).toBe(true)
  })
})

describe('ProductUpsellsPreview', () => {
  it('renders upsell cards with names and prices', () => {
    const w = mountBlock(ProductUpsellsPreview)
    const cards = w.findAll('.dsf-product-upsells__card')
    expect(cards).toHaveLength(3)
    expect(cards[0].text()).toContain('Wool Socks')
    expect(cards[0].find('.dsf-product-upsells__price').html()).toContain('$15')
  })

  it('caps cards at the configured count', () => {
    const w = mountBlock(ProductUpsellsPreview, { count: 2 })
    expect(w.findAll('.dsf-product-upsells__card')).toHaveLength(2)
  })

  it('shows a sale badge only on discounted cards', () => {
    const w = mountBlock(ProductUpsellsPreview)
    expect(w.findAll('.dsf-product-upsells__badge')).toHaveLength(1)
  })

  it('hides prices when toggled off', () => {
    const w = mountBlock(ProductUpsellsPreview, { showPrice: false })
    expect(w.find('.dsf-product-upsells__price').exists()).toBe(false)
  })

  it('applies the column count and clamps it on tablet', () => {
    const desktop = mountBlock(ProductUpsellsPreview, { columns: 4 })
    expect(desktop.find('.dsf-product-upsells__grid').attributes('style')).toContain('repeat(4')

    const tablet = mount(ProductUpsellsPreview, {
      props: { settings: { columns: 4 }, previewMode: 'tablet', blockId: 'x1', isEditor: true },
      global: { provide: { dsfProductContext: ref(PRODUCT) } },
    })
    expect(tablet.find('.dsf-product-upsells__grid').attributes('style')).toContain('repeat(3')
  })

  it('shows the editor empty state without upsells and survives malformed data', () => {
    const empty = mountBlock(ProductUpsellsPreview, {}, { ...PRODUCT, upsellProducts: [] })
    expect(empty.find('.dsf-product-upsells__empty').text()).toContain('Linked Products')

    const malformed = mountBlock(ProductUpsellsPreview, {}, { ...PRODUCT, upsellProducts: ['bad', null] })
    expect(malformed.findAll('.dsf-product-upsells__card')).toHaveLength(0)
  })
})
