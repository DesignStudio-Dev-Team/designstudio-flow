import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EcommerceShowcasePreview from '../blocks/EcommerceShowcasePreview.vue'

function mountShowcase(settings = {}, isEditor = true) {
  return mount(EcommerceShowcasePreview, {
    props: {
      settings: {
        displayMode: 'products',
        title: 'Featured products',
        limit: 5,
        showProductCount: true,
        countText: 'Browse {count} products',
        countColor: '#334155',
        showAddToCart: true,
        buttonText: 'Buy now',
        buttonColor: '#112233',
        buttonTextColor: '#F8FAFC',
        priceColor: '#445566',
        salePriceColor: '#BB1122',
        imageFit: 'scale-down',
        ...settings,
      },
      isEditor,
      previewMode: 'desktop',
    },
  })
}

describe('EcommerceShowcasePreview', () => {
  beforeEach(() => {
    window.dsfEditorData = {}
    window.dsfFrontendData = {}
  })

  it('renders customizable count, prices, and add-to-cart controls in product mode', () => {
    const wrapper = mountShowcase()

    expect(wrapper.get('.dsf-ecommerce-showcase__count').text()).toBe('Browse 6 products')
    expect(wrapper.get('.dsf-ecommerce-showcase__count').element.style.color).toBe('rgb(51, 65, 85)')
    expect(wrapper.get('.dsf-showcase-product__price').attributes('style')).toContain('--price-color: #445566')

    const buttons = wrapper.findAll('.dsf-showcase-product__cart')
    expect(buttons).toHaveLength(5)
    expect(buttons[0].text()).toBe('Buy now')
    expect(buttons[0].element.style.backgroundColor).toBe('rgb(17, 34, 51)')
    expect(buttons[0].element.style.color).toBe('rgb(248, 250, 252)')
    expect(wrapper.vm.productImageFit).toBe('scale-down')
  })

  it('can hide the product count and add-to-cart controls', () => {
    const wrapper = mountShowcase({ showProductCount: false, showAddToCart: false })

    expect(wrapper.find('.dsf-ecommerce-showcase__count').exists()).toBe(false)
    expect(wrapper.find('.dsf-showcase-product__cart').exists()).toBe(false)
  })

  it('renders safely with empty settings', () => {
    const wrapper = mount(EcommerceShowcasePreview, { props: { isEditor: true } })

    expect(wrapper.find('.dsf-ecommerce-showcase').exists()).toBe(true)
    expect(wrapper.findAll('.dsf-showcase-category')).toHaveLength(5)
  })

  it('falls back to fitting the whole image for an unknown image mode', () => {
    window.dsfEditorData = {
      categories: [],
    }
    const wrapper = mountShowcase({ imageFit: 'zoom-and-crop' })

    expect(wrapper.vm.productImageFit).toBe('contain')
  })

  it('prevents product actions from navigating in the editor', async () => {
    const wrapper = mountShowcase()
    const button = wrapper.get('.dsf-showcase-product__cart')
    let defaultPrevented = false
    button.element.addEventListener('click', (event) => {
      defaultPrevented = event.defaultPrevented
    })

    await button.trigger('click')

    expect(defaultPrevented).toBe(true)
  })
  /*
   * The demo fire pits carry invented prices ($3,499.00 etc). They exist to give
   * the editor canvas something to lay out and must never reach a visitor when
   * the block resolves no real products.
   */
  it('renders no demo products or prices on the frontend', () => {
    const wrapper = mountShowcase({}, false)

    expect(wrapper.findAll('.dsf-showcase-product').length).toBe(0)
    expect(wrapper.text()).not.toContain('$3,499.00')
    expect(wrapper.text()).not.toContain('Capri Fire Pit')
    expect(wrapper.text()).toContain('Browse 0 products')
  })

  it('still shows the demo set in the editor', () => {
    const wrapper = mountShowcase({}, true)

    expect(wrapper.findAll('.dsf-showcase-product').length).toBeGreaterThan(0)
    expect(wrapper.text()).toContain('$3,499.00')
  })

  it('hides the price and cart button for a product that is not sold online', () => {
    window.dsfFrontendData = {
      ecommerceShowcase: {},
    }
    const wrapper = mountShowcase({}, true)
    wrapper.vm.products = [
      {
        id: 77,
        name: 'Syndicated Spa',
        price: '',
        regularPrice: '',
        salePrice: '',
        onSale: false,
        image: null,
        permalink: '/products/syndicated-spa',
        sold_online: false,
        cta_buttons: [{ label: 'Find a Dealer' }],
      },
    ]

    return wrapper.vm.$nextTick().then(() => {
      expect(wrapper.find('.dsf-showcase-product__price').exists()).toBe(false)
      const ctas = wrapper.findAll('.dsf-showcase-product__cart')
      expect(ctas.map((c) => c.text())).toEqual(['Find a Dealer'])
      expect(wrapper.text()).not.toContain('Buy now')
    })
  })
})
