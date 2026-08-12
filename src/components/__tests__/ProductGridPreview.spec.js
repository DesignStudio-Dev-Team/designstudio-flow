import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import ProductGridPreview from '../blocks/ProductGridPreview.vue'

const mocks = vi.hoisted(() => ({
  navigateToUrl: vi.fn(),
}))

vi.mock('../../utils/browserNavigation', () => ({
  navigateToUrl: mocks.navigateToUrl,
}))

// The demo product set only renders in the editor, so tests that exercise the
// filter/search/URL logic against it mount with isEditor: true.
function mountGrid(settings = {}, isEditor = false) {
  return mount(ProductGridPreview, {
    props: {
      settings: {
        title: 'Featured Products',
        columns: '3',
        limit: 6,
        enableFilters: true,
        enableSearch: true,
        filterShowPrice: true,
        filterShowCategory: true,
        filterShowBrand: true,
        ...settings,
      },
      isEditor,
      blockId: 'grid-1',
    },
  })
}

describe('ProductGridPreview', () => {
  beforeEach(() => {
    window.dsfEditorData = {}
    window.dsfFrontendData = {}
    window.sessionStorage.clear()
    window.history.replaceState({}, '', '/shop')
    global.fetch = vi.fn()
    mocks.navigateToUrl.mockReset()
    mocks.navigateToUrl.mockImplementation((url) => {
      window.history.replaceState({}, '', url)
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('applies search within the currently filtered result set', async () => {
    const wrapper = mountGrid({}, true)

    await wrapper.get('input[type="checkbox"][value="Sofas"]').setValue(true)
    await wrapper.get('.dsf-product-grid-preview__search-input').setValue('Acme')
    await nextTick()

    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(0)
    expect(wrapper.find('.dsf-product-grid-preview__no-results').text()).toContain('current filters')
  })

  it('limits tag filter options to the configured product tags', async () => {
    const wrapper = mountGrid(
      {
        filterShowTags: true,
        filterTags: ['sale'],
      },
      true
    )

    await nextTick()
    await wrapper.findAll('.dsf-filter-group__header').find((button) => button.text().includes('Tags')).trigger('click')
    await nextTick()

    expect(wrapper.find('input[type="checkbox"][value="sale"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"][value="bestseller"]').exists()).toBe(false)
    expect(wrapper.find('input[type="checkbox"][value="new"]').exists()).toBe(false)

    await wrapper.get('input[type="checkbox"][value="sale"]').setValue(true)
    await nextTick()

    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(3)
  })

  it('stores filters in the URL, restores them on remount, and keeps search terms out of the URL', async () => {
    // URL persistence is deliberately frontend-only, so this runs against real
    // fetched products rather than the editor-only demo set.
    const catalog = [
      { id: 1, name: 'Premium Teak Chair', price: '$349.00', price_num: 349, rating: 4.8, image: '', categories: ['Chairs'], category_ids: [22], tags: [], attributes: { brand: ['Acme'] } },
      { id: 2, name: 'Acme Lounger', price: '$229.00', price_num: 229, rating: 4.2, image: '', categories: ['Chairs'], category_ids: [22], tags: [], attributes: { brand: ['Acme'] } },
      { id: 3, name: 'Coastal Sofa', price: '$899.00', price_num: 899, rating: 4.5, image: '', categories: ['Sofas'], category_ids: [10], tags: [], attributes: { brand: ['Coastal Living'] } },
    ]
    global.fetch = vi.fn().mockResolvedValue({
      json: () => Promise.resolve({ success: true, data: { products: catalog } }),
    })
    window.dsfFrontendData = { ajaxUrl: '/ajax', nonce: 'nonce', isWooActive: true }

    const wrapper = mountGrid()
    await flushPromises()
    await nextTick()

    await wrapper.get('input[type="checkbox"][value="Chairs"]').setValue(true)
    await nextTick()

    expect(mocks.navigateToUrl).toHaveBeenCalledWith('/shop?dsf_pg_grid_1_cat=chairs')
    expect(window.location.search).toBe('?dsf_pg_grid_1_cat=chairs')

    await wrapper.get('.dsf-product-grid-preview__search-input').setValue('chair')
    await nextTick()

    expect(window.location.search).toBe('?dsf_pg_grid_1_cat=chairs')

    wrapper.unmount()

    const remounted = mountGrid()
    await flushPromises()
    await nextTick()

    expect(remounted.get('input[type="checkbox"][value="Chairs"]').element.checked).toBe(true)
    expect(remounted.findAll('.dsf-product-card-preview')).toHaveLength(2)
  })

  it('limits category-source filters and results to the selected source categories in order', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      json: () =>
        Promise.resolve({
          success: true,
          data: {
            products: [
              {
                id: 101,
                name: 'Source Sofa',
                price: '$100.00',
                price_num: 100,
                rating: 4.2,
                image: '',
                categories: ['Sofas'],
                category_ids: [10],
                tags: [],
                attributes: { brand: ['Acme'] },
              },
              {
                id: 202,
                name: 'Source Chair',
                price: '$120.00',
                price_num: 120,
                rating: 4.1,
                image: '',
                categories: ['Chairs'],
                category_ids: [22],
                tags: [],
                attributes: { brand: ['Other Brand'] },
              },
              {
                id: 212,
                name: 'Child Dining Chair',
                price: '$160.00',
                price_num: 160,
                rating: 4.3,
                image: '',
                categories: ['Dining Chairs'],
                category_ids: [23],
                tags: [],
                attributes: { brand: ['Other Brand'] },
              },
              {
                id: 303,
                name: 'Wrong Table',
                price: '$180.00',
                price_num: 180,
                rating: 4.0,
                image: '',
                categories: ['Tables'],
                category_ids: [35],
                tags: [],
                attributes: { brand: ['Other Brand'] },
              },
            ],
          },
        }),
    })

    global.fetch = fetchMock
    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
      isWooActive: true,
      categories: [
        { id: 10, name: 'Sofas', parent: 0 },
        { id: 22, name: 'Chairs', parent: 0 },
        { id: 23, name: 'Dining Chairs', parent: 22 },
        { id: 35, name: 'Tables' },
      ],
    }

    const wrapper = mountGrid({
      source: 'category',
      categoryIds: [10, 22],
    })

    await flushPromises()
    await nextTick()

    const requestBody = fetchMock.mock.calls[0][1].body
    expect(requestBody.get('category_ids')).toBe('[10,22]')
    expect(requestBody.get('category_id')).toBe('10')

    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(3)
    expect(wrapper.text()).toContain('Source Sofa')
    expect(wrapper.text()).toContain('Source Chair')
    expect(wrapper.text()).toContain('Child Dining Chair')
    expect(wrapper.text()).not.toContain('Wrong Table')
    expect(wrapper.find('input[type="checkbox"][value="Sofas"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"][value="Chairs"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"][value="Dining Chairs"]').exists()).toBe(true)
    expect(wrapper.find('input[type="checkbox"][value="Tables"]').exists()).toBe(false)
  })

  it('supports legacy single category settings for older product-grid data', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      json: () =>
        Promise.resolve({
          success: true,
          data: {
            products: [
              {
                id: 101,
                name: 'Legacy Sofa',
                price: '$100.00',
                price_num: 100,
                rating: 4.2,
                image: '',
                categories: ['Sofas'],
                category_ids: [10],
                tags: [],
                attributes: { brand: ['Acme'] },
              },
              {
                id: 202,
                name: 'Legacy Chair',
                price: '$120.00',
                price_num: 120,
                rating: 4.1,
                image: '',
                categories: ['Chairs'],
                category_ids: [22],
                tags: [],
                attributes: { brand: ['Other Brand'] },
              },
            ],
          },
        }),
    })

    global.fetch = fetchMock
    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
      isWooActive: true,
      categories: [
        { id: 10, name: 'Sofas' },
        { id: 22, name: 'Chairs' },
      ],
    }

    const wrapper = mountGrid({
      source: 'category',
      categoryId: 10,
    })

    await flushPromises()
    await nextTick()

    expect(fetchMock.mock.calls[0][1].body.get('category_ids')).toBe('[10]')
    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(1)
    expect(wrapper.text()).toContain('Legacy Sofa')
    expect(wrapper.text()).not.toContain('Legacy Chair')
  })

  it('adds simple products to cart from the modern card without navigating away', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        json: () =>
          Promise.resolve({
            success: true,
            data: {
              products: [
                {
                  id: 11,
                  name: 'Simple Chair',
                  price: '$120.00',
                  price_num: 120,
                  rating: 4.4,
                  image: '',
                  permalink: '/products/simple-chair',
                  add_to_cart_url: '/cart/?add-to-cart=11',
                  product_type: 'simple',
                  stock_status: 'instock',
                  categories: ['Chairs'],
                  category_ids: [22],
                  tags: [],
                  attributes: { brand: ['Acme'] },
                },
              ],
            },
          }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        text: () => Promise.resolve(JSON.stringify({ fragments: {}, cart_hash: 'hash123' })),
      })

    global.fetch = fetchMock
    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
      isWooActive: true,
      wcAjaxUrl: '/?wc-ajax=add_to_cart',
    }

    const wrapper = mountGrid({
      cardStyle: 'modern',
      enableFilters: false,
      enableSearch: false,
    })

    await flushPromises()
    await nextTick()

    expect(wrapper.find('.dsf-product-card-preview__image-link .dsf-product-card-preview__btn').exists()).toBe(false)

    await wrapper.get('.dsf-product-card-preview--modern .dsf-product-card-preview__btn').trigger('click')
    await flushPromises()
    await nextTick()

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      '/?wc-ajax=add_to_cart',
      expect.objectContaining({
        method: 'POST',
        credentials: 'same-origin',
      }),
    )
    const requestBody = new URLSearchParams(fetchMock.mock.calls[1][1].body)
    expect(requestBody.get('product_id')).toBe('11')
    expect(requestBody.get('quantity')).toBe('1')
    expect(requestBody.get('add-to-cart')).toBe('11')
    expect(wrapper.get('.dsf-product-card-preview--modern .dsf-product-card-preview__btn').text()).toContain('Added')
    expect(mocks.navigateToUrl).not.toHaveBeenCalled()
  })

  it('falls back to the native add-to-cart URL when Woo ajax returns a server error', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        json: () =>
          Promise.resolve({
            success: true,
            data: {
              products: [
                {
                  id: 15,
                  name: 'Fallback Chair',
                  price: '$140.00',
                  price_num: 140,
                  rating: 4.1,
                  image: '',
                  permalink: '/products/fallback-chair',
                  add_to_cart_url: '/shop/?add-to-cart=15',
                  product_type: 'simple',
                  stock_status: 'instock',
                  categories: ['Chairs'],
                  category_ids: [22],
                  tags: [],
                  attributes: { brand: ['Acme'] },
                },
              ],
            },
          }),
      })
      .mockResolvedValueOnce({
        ok: false,
        status: 500,
        text: () => Promise.resolve('Server error'),
      })

    global.fetch = fetchMock
    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
      isWooActive: true,
      wcAjaxUrl: '/?wc-ajax=add_to_cart',
    }

    const wrapper = mountGrid({
      enableFilters: false,
      enableSearch: false,
    })

    await flushPromises()
    await nextTick()

    await wrapper.get('.dsf-product-card-preview__btn').trigger('click')

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(mocks.navigateToUrl).toHaveBeenCalledWith('/shop/?add-to-cart=15')
  })

  it('sends variable products to the product page instead of calling add-to-cart ajax', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      json: () =>
        Promise.resolve({
          success: true,
          data: {
            products: [
              {
                id: 77,
                name: 'Configurable Sofa',
                price: '$899.00',
                price_num: 899,
                rating: 4.7,
                image: '',
                permalink: '/products/configurable-sofa',
                add_to_cart_url: '/products/configurable-sofa',
                product_type: 'variable',
                stock_status: 'instock',
                categories: ['Sofas'],
                category_ids: [10],
                tags: [],
                attributes: { brand: ['Acme'] },
              },
            ],
          },
        }),
    })

    global.fetch = fetchMock
    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
      isWooActive: true,
      wcAjaxUrl: '/?wc-ajax=add_to_cart',
    }

    const wrapper = mountGrid({
      enableFilters: false,
      enableSearch: false,
    })

    await flushPromises()
    await nextTick()

    await wrapper.get('.dsf-product-card-preview__btn').trigger('click')

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(mocks.navigateToUrl).toHaveBeenCalledWith('/products/configurable-sofa')
  })
  /*
   * Products with no price — and products the Syndified plugin marks as not sold
   * online — must show neither a price nor an "Add to Cart" button. The server
   * blanks price/price_num/add_to_cart_url for these, so the card has to degrade
   * to the Syndified CTAs rather than falling back to its demo "$99.00" price.
   */
  function mockProducts(products) {
    global.fetch = vi.fn().mockResolvedValue({
      json: () => Promise.resolve({ success: true, data: { products } }),
    })
    window.dsfFrontendData = { ajaxUrl: '/ajax', nonce: 'nonce', isWooActive: true }
  }

  const notSoldOnline = {
    id: 31,
    name: 'Syndicated Hot Tub',
    price: '',
    price_num: 0,
    rating: 0,
    image: '',
    permalink: '/products/syndicated-hot-tub',
    add_to_cart_url: '',
    product_type: 'simple',
    stock_status: 'instock',
    categories: ['Hot Tubs'],
    category_ids: [9],
    tags: [],
    attributes: {},
    sold_online: false,
    cta_buttons: [{ label: 'Find a Dealer' }, { label: 'Request a Quote' }],
  }

  it('shows no price and no cart button for a product that is not sold online', async () => {
    mockProducts([notSoldOnline])

    const wrapper = mountGrid({ enableFilters: false, enableSearch: false })
    await flushPromises()
    await nextTick()

    expect(wrapper.find('.dsf-product-card-preview__price').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('$99.00')
    expect(wrapper.find('button.dsf-product-card-preview__btn').exists()).toBe(false)
  })

  it("renders the Syndified CTAs in place of the cart button", async () => {
    mockProducts([notSoldOnline])

    const wrapper = mountGrid({ enableFilters: false, enableSearch: false })
    await flushPromises()
    await nextTick()

    const ctas = wrapper.findAll('.dsf-product-card-preview__btn--cta')
    expect(ctas.map((c) => c.text())).toEqual(['Find a Dealer', 'Request a Quote'])
    expect(ctas[0].attributes('href')).toBe('/products/syndicated-hot-tub')
  })

  it('renders no CTAs when an unpriced product has none configured', async () => {
    mockProducts([{ ...notSoldOnline, cta_buttons: [] }])

    const wrapper = mountGrid({ enableFilters: false, enableSearch: false })
    await flushPromises()
    await nextTick()

    expect(wrapper.find('.dsf-product-card-preview__btn').exists()).toBe(false)
    expect(wrapper.find('.dsf-product-card-preview__price').exists()).toBe(false)
  })
  /*
   * demoProducts carry invented prices ($349.00 …) so the editor canvas has
   * something to lay out. With WooCommerce switched off they used to render on
   * the frontend too, showing visitors eight products that do not exist.
   */
  it('renders no demo products on the frontend when WooCommerce is inactive', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      json: () => Promise.resolve({ success: false, data: {} }),
    })
    window.dsfFrontendData = { ajaxUrl: '/ajax', nonce: 'nonce', isWooActive: false }

    const wrapper = mountGrid({ enableFilters: false, enableSearch: false })
    await flushPromises()
    await nextTick()

    expect(wrapper.findAll('.dsf-product-card-preview').length).toBe(0)
    expect(wrapper.text()).not.toContain('$349.00')
    expect(wrapper.text()).not.toContain('Premium Teak Chair')
  })
})
