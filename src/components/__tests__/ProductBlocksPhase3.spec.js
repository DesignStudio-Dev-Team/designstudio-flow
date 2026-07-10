import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProductAddToCartPreview from '../blocks/ProductAddToCartPreview.vue'
import ProductTabsPreview from '../blocks/ProductTabsPreview.vue'

const CART_HTML =
  '<form class="cart"><div class="quantity"><input type="number" class="qty" value="1" /></div>' +
  '<button type="submit" class="single_add_to_cart_button">Add to cart</button></form>'

const VARIABLE_CART_HTML =
  '<form class="variations_form cart"><table class="variations"><tr><td><select name="attribute_color">' +
  '<option value="">Choose</option><option value="red">Red</option></select></td></tr></table>' +
  '<button type="submit" class="single_add_to_cart_button">Add to cart</button></form>'

function mountCart(settings = {}, product = {}, options = {}) {
  return mount(ProductAddToCartPreview, {
    props: { settings, previewMode: 'desktop', isEditor: options.isEditor || false, blockId: 'c1' },
    global: { provide: { dsfProductContext: ref(product), ...(options.provide || {}) } },
  })
}

describe('ProductAddToCartPreview', () => {
  afterEach(() => {
    delete window.jQuery
  })

  it('renders the server add-to-cart form', () => {
    const w = mountCart({}, { addToCartHtml: CART_HTML }, { isEditor: true })
    expect(w.find('form.cart').exists()).toBe(true)
    expect(w.find('.single_add_to_cart_button').text()).toBe('Add to cart')
  })

  it('shows a placeholder when no form is available', () => {
    const w = mountCart({}, { addToCartHtml: '' }, { isEditor: true })
    expect(w.find('.dsf-product-cart__placeholder').exists()).toBe(true)
  })

  it('applies center alignment and button color', () => {
    const w = mountCart({ alignment: 'center', buttonColor: '#ff0000' }, { addToCartHtml: CART_HTML }, { isEditor: true })
    expect(w.find('.dsf-product-cart').attributes('style')).toContain('text-align: center')
    expect(w.find('.dsf-product-cart').classes()).toContain('dsf-product-cart--center')
    expect(w.find('.dsf-product-cart__inner').attributes('style')).toContain('--dsf-cart-btn-bg: #ff0000')
  })

  it('renders the amount next to the add-to-cart form in one row', () => {
    const w = mountCart({}, { addToCartHtml: CART_HTML, priceHtml: '<span class="amount">$99.00</span>' }, { isEditor: true })
    const row = w.find('.dsf-product-cart__row')
    expect(row.exists()).toBe(true)
    // Price and form are siblings inside the same row.
    expect(row.find('.dsf-product-cart__price').html()).toContain('$99.00')
    expect(row.find('form.cart').exists()).toBe(true)
  })

  it('can hide the price and applies a custom price color', () => {
    const withColor = mountCart(
      { priceColor: '#123456' },
      { addToCartHtml: CART_HTML, priceHtml: '<span class="amount">$99.00</span>' },
      { isEditor: true }
    )
    // jsdom serializes the hex color to rgb() in the inline style.
    expect(withColor.find('.dsf-product-cart__price').attributes('style')).toContain('rgb(18, 52, 86)')

    const hidden = mountCart(
      { showPrice: false },
      { addToCartHtml: CART_HTML, priceHtml: '<span class="amount">$99.00</span>' },
      { isEditor: true }
    )
    expect(hidden.find('.dsf-product-cart__price').exists()).toBe(false)
  })

  it('does not initialize the variation form in the editor', () => {
    let initialized = 0
    window.jQuery = makeJQueryStub(() => { initialized++ })
    mountCart({}, { addToCartHtml: VARIABLE_CART_HTML }, { isEditor: true })
    expect(initialized).toBe(0)
  })

  it('initializes the Woo variation form on the frontend', () => {
    let initialized = 0
    window.jQuery = makeJQueryStub(() => { initialized++ })
    mountCart({}, { addToCartHtml: VARIABLE_CART_HTML }, { isEditor: false })
    expect(initialized).toBe(1)
  })
})

describe('ProductTabsPreview reviews source', () => {
  const product = {
    descriptionHtml: '<p>Desc</p>',
    specs: [],
    reviewsHtml: '<div id="reviews"><p>Great product</p></div>',
  }

  function mountTabs(tabs, prod = product) {
    return mount(ProductTabsPreview, {
      props: { settings: { tabs }, previewMode: 'desktop', blockId: 'r1' },
      global: { provide: { dsfProductContext: ref(prod) } },
    })
  }

  it('renders server reviews HTML in a reviews tab', () => {
    const w = mountTabs([{ label: 'Reviews', source: 'reviews', content: '' }])
    expect(w.find('.dsf-product-tabs__reviews').html()).toContain('Great product')
  })

  it('shows a placeholder when reviews HTML is absent', () => {
    const w = mountTabs([{ label: 'Reviews', source: 'reviews', content: '' }], { ...product, reviewsHtml: '' })
    expect(w.find('.dsf-product-tabs__empty').exists()).toBe(true)
  })
})

// Minimal jQuery stand-in: $(root).find('.variations_form').each(fn) calls fn on a
// fake form whose .wc_variation_form() invokes the provided spy.
function makeJQueryStub(onInit) {
  const formApi = {
    _data: {},
    data(key, val) {
      if (val === undefined) return this._data[key]
      this._data[key] = val
      return this
    },
    wc_variation_form() {
      onInit()
      return this
    },
  }
  const collection = { each(cb) { cb.call(formApi, 0, formApi) } }
  const jq = (arg) => (arg === formApi ? formApi : { find: () => collection })
  jq.fn = { wc_variation_form: formApi.wc_variation_form }
  return jq
}
