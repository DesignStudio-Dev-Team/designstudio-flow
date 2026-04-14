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
})
