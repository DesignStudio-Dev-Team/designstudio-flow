import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ShopHeaderPreview from '../blocks/ShopHeaderPreview.vue'
import ShopProductsPreview from '../blocks/ShopProductsPreview.vue'

const ARCHIVE = {
  title: 'Hiking Boots',
  descriptionHtml: '<p>Everything for the trail.</p>',
  total: 27,
  perPage: 12,
  currentPage: 2,
  totalPages: 3,
  orderby: 'price',
  orderbyOptions: [
    { value: 'menu_order', label: 'Default sorting' },
    { value: 'price', label: 'Sort by price: low to high' },
  ],
  pagination: [
    { label: '1', url: '/shop/', current: false },
    { label: '2', url: '/shop/page/2/', current: true },
    { label: '3', url: '/shop/page/3/', current: false },
  ],
  products: [
    { id: 1, name: 'Alpine Boots', permalink: '/p/alpine', priceHtml: '<span>$189</span>', image: 'a.jpg', imageAlt: '', onSale: true, averageRating: 4.5, ratingCount: 12, addToCartUrl: '/?add-to-cart=1' },
    { id: 2, name: 'Trail Runners', permalink: '/p/trail', priceHtml: '<span>$120</span>', image: 't.jpg', imageAlt: '', onSale: false, averageRating: 0, ratingCount: 0, addToCartUrl: '' },
    'malformed',
    null,
  ],
}

function mountBlock(Component, settings = {}, archive = ARCHIVE, isEditor = true) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 's1', isEditor },
    global: { provide: { dsfShopContext: ref(archive) } },
  })
}

describe('ShopHeaderPreview', () => {
  it('renders the archive title, product count, and description', () => {
    const w = mountBlock(ShopHeaderPreview)
    expect(w.find('.dsf-shop-header__title').text()).toBe('Hiking Boots')
    expect(w.find('.dsf-shop-header__count').text()).toContain('27 products')
    expect(w.find('.dsf-shop-header__description').html()).toContain('Everything for the trail.')
  })

  it('hides sections per toggles', () => {
    const w = mountBlock(ShopHeaderPreview, { showTitle: false, showCount: false, showDescription: false })
    expect(w.find('.dsf-shop-header__title').exists()).toBe(false)
    expect(w.find('.dsf-shop-header__count').exists()).toBe(false)
    expect(w.find('.dsf-shop-header__description').exists()).toBe(false)
  })

  it('applies center alignment and falls back to the placeholder title', () => {
    const w = mountBlock(ShopHeaderPreview, { alignment: 'center' }, null)
    expect(w.find('.dsf-shop-header--center').exists()).toBe(true)
    expect(w.find('.dsf-shop-header__title').text()).toBe('Shop')
    expect(w.find('.dsf-shop-header__count').exists()).toBe(false)
  })
})

describe('ShopProductsPreview', () => {
  it('renders product cards, skipping malformed entries', () => {
    const w = mountBlock(ShopProductsPreview)
    const cards = w.findAll('.dsf-shop-products__card')
    expect(cards).toHaveLength(2)
    expect(cards[0].find('.dsf-shop-products__name').text()).toBe('Alpine Boots')
    expect(cards[0].find('.dsf-shop-products__price').html()).toContain('$189')
    expect(w.findAll('.dsf-shop-products__badge')).toHaveLength(1)
  })

  it('shows quick-add for simple products and view link otherwise', () => {
    const w = mountBlock(ShopProductsPreview)
    const buttons = w.findAll('.dsf-shop-products__button')
    expect(buttons[0].text()).toBe('Add to cart')
    expect(buttons[0].attributes('href')).toBe('/?add-to-cart=1')
    expect(buttons[1].text()).toBe('View product')
    expect(buttons[1].attributes('href')).toBe('/p/trail')
  })

  it('shows the rating only when the product has ratings', () => {
    const w = mountBlock(ShopProductsPreview)
    const cards = w.findAll('.dsf-shop-products__card')
    expect(cards[0].find('.dsf-shop-products__rating').exists()).toBe(true)
    expect(cards[1].find('.dsf-shop-products__rating').exists()).toBe(false)
  })

  it('renders the result count and the sort select with the current value', () => {
    const w = mountBlock(ShopProductsPreview)
    expect(w.find('.dsf-shop-products__count').text()).toBe('Showing 13–24 of 27 products')
    const select = w.find('.dsf-shop-products__sort-select')
    expect(select.element.value).toBe('price')
    expect(select.findAll('option')).toHaveLength(2)
  })

  it('renders pagination with the current page marked', () => {
    const w = mountBlock(ShopProductsPreview)
    const current = w.find('.dsf-shop-products__page.is-current')
    expect(current.text()).toBe('2')
    expect(current.attributes('aria-current')).toBe('page')
    const links = w.findAll('a.dsf-shop-products__page')
    expect(links[0].attributes('href')).toBe('/shop/')
  })

  it('hides toolbar pieces and pagination per toggles', () => {
    const w = mountBlock(ShopProductsPreview, { showSorting: false, showCount: false, showPagination: false })
    expect(w.find('.dsf-shop-products__toolbar').exists()).toBe(false)
    expect(w.find('.dsf-shop-products__pagination').exists()).toBe(false)
  })

  it('clamps grid columns on tablet', () => {
    const w = mount(ShopProductsPreview, {
      props: { settings: { columns: 5 }, previewMode: 'tablet', blockId: 's1', isEditor: true },
      global: { provide: { dsfShopContext: ref(ARCHIVE) } },
    })
    expect(w.find('.dsf-shop-products__grid').attributes('style')).toContain('repeat(3')
  })

  it('shows editor ghosts when the archive has no products', () => {
    const w = mountBlock(ShopProductsPreview, {}, { ...ARCHIVE, products: [], pagination: [] })
    expect(w.findAll('.dsf-shop-products__ghost')).toHaveLength(6)
    expect(w.find('.dsf-shop-products__note').text()).toContain('preview category')
  })

  it('shows the frontend empty message without products', () => {
    const w = mountBlock(ShopProductsPreview, {}, { ...ARCHIVE, products: [] }, false)
    expect(w.find('.dsf-shop-products__note').text()).toContain('No products were found')
  })

  it('survives a missing archive context via the placeholder', () => {
    const w = mountBlock(ShopProductsPreview, {}, null)
    expect(w.find('.dsf-shop-products').exists()).toBe(true)
    expect(w.find('.dsf-shop-products__count').text()).toBe('No products')
  })
})
