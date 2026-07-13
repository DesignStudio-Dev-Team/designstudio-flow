import { describe, it, expect, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import StoreCartPreview from '../blocks/StoreCartPreview.vue'
import StoreCheckoutPreview from '../blocks/StoreCheckoutPreview.vue'
import StoreAccountPreview from '../blocks/StoreAccountPreview.vue'
import StoreStepsPreview from '../blocks/StoreStepsPreview.vue'

function mountBlock(Component, settings = {}, { isEditor = true, renderMode = null } = {}) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 's1', isEditor },
    global: { provide: { dsfRenderMode: renderMode } },
  })
}

/** Plant a PHP-style hidden fragments container into the jsdom document. */
function plantFragment(key, html = '<form class="cart-form">live</form>') {
  const container = document.createElement('div')
  container.className = 'dsf-store-fragments'
  container.setAttribute('hidden', '')
  container.innerHTML = `<div class="dsf-store-fragment" data-dsf-store-fragment="${key}">${html}</div>`
  document.body.appendChild(container)
  return container
}

afterEach(() => {
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
})

describe('store fragment blocks (cart / checkout / account)', () => {
  it('render a mock preview in the editor, never the live host content', () => {
    for (const Component of [StoreCartPreview, StoreCheckoutPreview, StoreAccountPreview]) {
      const w = mountBlock(Component)
      expect(w.find('[class$="__mock"]').exists()).toBe(true)
      expect(w.find('[class$="__note"]').text()).toContain('renders here')
      w.unmount()
    }
  })

  it('render the mock in snapshot mode without adopting anything', () => {
    const container = plantFragment('cart')
    const w = mountBlock(StoreCartPreview, {}, { isEditor: false, renderMode: 'snapshot' })
    expect(w.find('.dsf-store-cart__mock').exists()).toBe(true)
    // Fragment untouched in its hidden container.
    expect(container.querySelector('[data-dsf-store-fragment="cart"]')).not.toBeNull()
    w.unmount()
  })

  it('adopts the live cart fragment into the host on the frontend', () => {
    plantFragment('cart')
    const w = mountBlock(StoreCartPreview, {}, { isEditor: false })
    const host = w.find('.dsf-store-cart__host')
    expect(host.element.querySelector('form.cart-form')).not.toBeNull()
    const adoptedNode = host.element.querySelector('[data-dsf-store-fragment="cart"]')
    expect(adoptedNode.getAttribute('data-dsf-adopted')).toBe('true')
    expect(w.find('.dsf-store-cart__note').exists()).toBe(false)
    w.unmount()
  })

  it('does not steal an already-adopted fragment: the second block reports missing', async () => {
    plantFragment('checkout', '<form class="checkout">pay</form>')
    const first = mountBlock(StoreCheckoutPreview, {}, { isEditor: false })
    expect(first.find('.dsf-store-checkout__host form.checkout').exists()).toBe(true)

    const second = mountBlock(StoreCheckoutPreview, {}, { isEditor: false })
    await nextTick()
    expect(second.find('.dsf-store-checkout__host form.checkout').exists()).toBe(false)
    expect(second.find('.dsf-store-checkout__note').exists()).toBe(true)
    first.unmount()
    second.unmount()
  })

  it('shows a graceful note when no fragment exists on the page', async () => {
    const w = mountBlock(StoreAccountPreview, {}, { isEditor: false })
    await nextTick()
    expect(w.find('.dsf-store-account__note').text()).toContain('could not be loaded')
    w.unmount()
  })

  it('applies layout variants from settings', () => {
    const checkout = mountBlock(StoreCheckoutPreview, { layout: 'stacked' })
    expect(checkout.find('.dsf-store-checkout--stacked').exists()).toBe(true)

    const account = mountBlock(StoreAccountPreview, { navStyle: 'top' })
    expect(account.find('.dsf-store-account--nav-top').exists()).toBe(true)

    const cart = mountBlock(StoreCartPreview, { showCrossSells: false })
    expect(cart.find('.dsf-store-cart--hide-cross-sells').exists()).toBe(true)
  })
})

describe('StoreStepsPreview', () => {
  it('renders three steps with default labels and previews the checkout step in the editor', () => {
    const w = mountBlock(StoreStepsPreview)
    const steps = w.findAll('.dsf-store-steps__step')
    expect(steps).toHaveLength(3)
    expect(w.text()).toContain('Cart')
    expect(w.text()).toContain('Checkout')
    expect(w.text()).toContain('Order Complete')
    expect(steps[1].classes()).toContain('is-current')
    expect(steps[0].classes()).toContain('is-done')
  })

  it('uses custom labels and a manual current step', () => {
    const w = mountBlock(StoreStepsPreview, {
      labelCart: 'Basket',
      labelComplete: 'Done!',
      currentStep: 'complete',
    })
    expect(w.text()).toContain('Basket')
    expect(w.text()).toContain('Done!')
    const steps = w.findAll('.dsf-store-steps__step')
    expect(steps[2].classes()).toContain('is-current')
    expect(steps[0].classes()).toContain('is-done')
    expect(steps[1].classes()).toContain('is-done')
  })

  it('detects the step from the localized store context on the frontend', () => {
    vi.stubGlobal('dsfFrontendData', {
      storeContext: { step: 'checkout', urls: { cart: '/cart/', checkout: '/checkout/' }, fragments: [] },
    })
    window.dsfFrontendData = { storeContext: { step: 'checkout', urls: { cart: '/cart/', checkout: '/checkout/' }, fragments: [] } }

    const w = mountBlock(StoreStepsPreview, {}, { isEditor: false })
    const steps = w.findAll('.dsf-store-steps__step')
    expect(steps[1].classes()).toContain('is-current')
    // The completed cart step links back to the cart page.
    expect(steps[0].find('a').attributes('href')).toBe('/cart/')
    delete window.dsfFrontendData
  })

  it('does not link completed steps when linking is disabled', () => {
    window.dsfFrontendData = { storeContext: { step: 'checkout', urls: { cart: '/cart/' }, fragments: [] } }
    const w = mountBlock(StoreStepsPreview, { linkSteps: false }, { isEditor: false })
    expect(w.findAll('.dsf-store-steps__step')[0].find('a').exists()).toBe(false)
    delete window.dsfFrontendData
  })

  it('falls back safely with no store context on the frontend', () => {
    const w = mountBlock(StoreStepsPreview, {}, { isEditor: false })
    const steps = w.findAll('.dsf-store-steps__step')
    expect(steps[0].classes()).toContain('is-current')
    expect(steps[0].find('a').exists()).toBe(false)
  })
})
