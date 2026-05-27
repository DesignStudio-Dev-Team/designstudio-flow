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

function mountGrid(settings = {}) {
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
      isEditor: false,
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
    const wrapper = mountGrid()

    await wrapper.get('input[type="checkbox"][value="Sofas"]').setValue(true)
    await wrapper.get('.dsf-product-grid-preview__search-input').setValue('Acme')
    await nextTick()

    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(0)
    expect(wrapper.find('.dsf-product-grid-preview__no-results').text()).toContain('current filters')
  })

  it('stores filters in the URL, restores them on remount, and keeps search terms out of the URL', async () => {
    const wrapper = mountGrid()

    await wrapper.get('input[type="checkbox"][value="Chairs"]').setValue(true)
    await nextTick()

    expect(mocks.navigateToUrl).toHaveBeenCalledWith('/shop?dsf_pg_grid_1_cat=chairs')
    expect(window.location.search).toBe('?dsf_pg_grid_1_cat=chairs')

    await wrapper.get('.dsf-product-grid-preview__search-input').setValue('chair')
    await nextTick()

    expect(window.location.search).toBe('?dsf_pg_grid_1_cat=chairs')

    wrapper.unmount()

    const remounted = mountGrid()
    await nextTick()

    expect(remounted.get('input[type="checkbox"][value="Chairs"]').element.checked).toBe(true)
    expect(remounted.findAll('.dsf-product-card-preview')).toHaveLength(2)
  })

  it('limits category-source filters and results to the selected source category', async () => {
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
                name: 'Wrong Chair',
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

    expect(wrapper.findAll('.dsf-product-card-preview')).toHaveLength(1)
    expect(wrapper.text()).toContain('Source Sofa')
    expect(wrapper.text()).not.toContain('Wrong Chair')
    expect(wrapper.find('input[type="checkbox"][value="Chairs"]').exists()).toBe(false)
    expect(wrapper.find('input[type="checkbox"][value="Sofas"]').exists()).toBe(true)
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
    expect(fetchMock.mock.calls[1][1].body.get('product_id')).toBe('11')
    expect(fetchMock.mock.calls[1][1].body.get('quantity')).toBe('1')
    expect(wrapper.get('.dsf-product-card-preview--modern .dsf-product-card-preview__btn').text()).toContain('Added')
    expect(mocks.navigateToUrl).not.toHaveBeenCalled()
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
})
