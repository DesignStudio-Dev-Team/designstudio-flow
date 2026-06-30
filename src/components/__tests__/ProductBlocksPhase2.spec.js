import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductDescriptionPreview from '../blocks/ProductDescriptionPreview.vue'
import ProductSpecsPreview from '../blocks/ProductSpecsPreview.vue'
import ProductGalleryPreview from '../blocks/ProductGalleryPreview.vue'
import ProductTabsPreview from '../blocks/ProductTabsPreview.vue'

const PRODUCT = {
  name: 'Trail Jacket',
  descriptionHtml: '<p>Full description with <strong>details</strong>.</p>',
  specs: [
    { name: 'Material', value: 'Nylon' },
    { name: 'Weight', value: '320 g' },
  ],
  gallery: [
    { id: 1, full: 'a-full.jpg', large: 'a-large.jpg', thumb: 'a-thumb.jpg', srcset: '', alt: 'Front' },
    { id: 2, full: 'b-full.jpg', large: 'b-large.jpg', thumb: 'b-thumb.jpg', srcset: '', alt: 'Back' },
    { id: 3, full: 'c-full.jpg', large: 'c-large.jpg', thumb: 'c-thumb.jpg', srcset: '', alt: 'Side' },
  ],
}

function mountBlock(Component, settings = {}, product = PRODUCT) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 'b1' },
    global: { provide: { dsfProductContext: ref(product) } },
  })
}

describe('ProductDescriptionPreview', () => {
  it('renders the long description and heading', () => {
    const w = mountBlock(ProductDescriptionPreview, { headingText: 'About' })
    expect(w.find('.dsf-product-description__heading').text()).toBe('About')
    expect(w.find('.dsf-product-description__body').html()).toContain('<strong>details</strong>')
  })

  it('shows an empty state when there is no description', () => {
    const w = mountBlock(ProductDescriptionPreview, {}, { ...PRODUCT, descriptionHtml: '' })
    expect(w.find('.dsf-product-description__empty').exists()).toBe(true)
  })

  it('can hide the heading', () => {
    const w = mountBlock(ProductDescriptionPreview, { showHeading: false })
    expect(w.find('.dsf-product-description__heading').exists()).toBe(false)
  })
})

describe('ProductSpecsPreview', () => {
  it('renders a striped table by default', () => {
    const w = mountBlock(ProductSpecsPreview)
    expect(w.find('.dsf-product-specs--striped').exists()).toBe(true)
    expect(w.findAll('.dsf-product-specs__table tr')).toHaveLength(2)
    expect(w.text()).toContain('Material')
    expect(w.text()).toContain('Nylon')
  })

  it('switches to card layout', () => {
    const w = mountBlock(ProductSpecsPreview, { layout: 'cards', columns: 2 })
    expect(w.find('.dsf-product-specs__cards').exists()).toBe(true)
    expect(w.findAll('.dsf-product-specs__card')).toHaveLength(2)
  })

  it('falls back to striped on a bad layout value', () => {
    const w = mountBlock(ProductSpecsPreview, { layout: 'evil' })
    expect(w.find('.dsf-product-specs--striped').exists()).toBe(true)
  })

  it('shows an empty state with no specs', () => {
    const w = mountBlock(ProductSpecsPreview, {}, { ...PRODUCT, specs: [] })
    expect(w.find('.dsf-product-specs__empty').exists()).toBe(true)
  })
})

describe('ProductGalleryPreview', () => {
  it('renders the main image and thumbnails', () => {
    const w = mountBlock(ProductGalleryPreview)
    expect(w.find('.dsf-product-gallery__main img').attributes('src')).toBe('a-large.jpg')
    expect(w.findAll('.dsf-product-gallery__thumb')).toHaveLength(3)
  })

  it('swaps the active image when a thumbnail is clicked', async () => {
    const w = mountBlock(ProductGalleryPreview)
    await w.findAll('.dsf-product-gallery__thumb')[1].trigger('click')
    expect(w.find('.dsf-product-gallery__main img').attributes('src')).toBe('b-large.jpg')
  })

  it('opens and closes the lightbox with the full image', async () => {
    const w = mountBlock(ProductGalleryPreview, { enableLightbox: true })
    await w.find('.dsf-product-gallery__main').trigger('click')
    const lb = document.querySelector('.dsf-product-gallery__lightbox')
    expect(lb).not.toBeNull()
    expect(lb.querySelector('.dsf-product-gallery__lb-image').getAttribute('src')).toBe('a-full.jpg')
    w.unmount()
    expect(document.querySelector('.dsf-product-gallery__lightbox')).toBeNull()
  })

  it('does not open a lightbox when disabled', async () => {
    const w = mountBlock(ProductGalleryPreview, { enableLightbox: false })
    await w.find('.dsf-product-gallery__main').trigger('click')
    expect(document.querySelector('.dsf-product-gallery__lightbox')).toBeNull()
    w.unmount()
  })

  it('renders a single image layout without thumbnails', () => {
    const w = mountBlock(ProductGalleryPreview, { layout: 'single' })
    expect(w.find('.dsf-product-gallery__main').exists()).toBe(true)
    expect(w.findAll('.dsf-product-gallery__thumb')).toHaveLength(0)
  })

  it('shows a placeholder when there are no images', () => {
    const w = mountBlock(ProductGalleryPreview, {}, { ...PRODUCT, gallery: [] })
    expect(w.find('.dsf-product-gallery__placeholder').exists()).toBe(true)
  })
})

describe('ProductTabsPreview', () => {
  const tabs = [
    { label: 'Description', source: 'description', content: '' },
    { label: 'Specs', source: 'specs', content: '' },
    { label: 'Care', source: 'custom', content: '<p>Hand wash only.</p>' },
  ]

  it('renders a tablist and shows the first panel', () => {
    const w = mountBlock(ProductTabsPreview, { tabs })
    const tabBtns = w.findAll('[role="tab"]')
    expect(tabBtns).toHaveLength(3)
    expect(tabBtns[0].attributes('aria-selected')).toBe('true')
    expect(w.html()).toContain('Full description')
  })

  it('activates a tab on click and renders its source content', async () => {
    const w = mountBlock(ProductTabsPreview, { tabs })
    await w.findAll('[role="tab"]')[2].trigger('click')
    expect(w.findAll('[role="tab"]')[2].attributes('aria-selected')).toBe('true')
    expect(w.html()).toContain('Hand wash only')
  })

  it('renders the specs source as a table', async () => {
    const w = mountBlock(ProductTabsPreview, { tabs })
    await w.findAll('[role="tab"]')[1].trigger('click')
    expect(w.find('.dsf-product-tabs__specs').exists()).toBe(true)
    expect(w.text()).toContain('Nylon')
  })

  it('moves focus with arrow keys', async () => {
    const w = mountBlock(ProductTabsPreview, { tabs })
    await w.findAll('[role="tab"]')[0].trigger('keydown', { key: 'ArrowRight' })
    expect(w.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true')
  })

  it('falls back to a default tab when none are configured', () => {
    const w = mountBlock(ProductTabsPreview, { tabs: [] })
    expect(w.findAll('[role="tab"]')).toHaveLength(1)
  })
})
