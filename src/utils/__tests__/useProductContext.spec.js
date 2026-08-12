import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useProductContext, PRODUCT_PLACEHOLDER } from '../useProductContext'

// Renders whatever the composable resolves so the test can assert on it.
const Probe = defineComponent({
  setup() {
    const { product } = useProductContext()
    return () => h('div', { class: 'probe' }, JSON.stringify(product.value))
  },
})

/*
 * PRODUCT_PLACEHOLDER is editor scaffolding: it invents a "Sample Product" named
 * item priced at $49.00 so blocks have something to lay out before a preview
 * product is chosen. On the frontend an unresolved product means the page really
 * has none, and a visitor must never be shown that invented price.
 */
describe('useProductContext', () => {
  beforeEach(() => {
    delete window.dsfFrontendData
    delete window.dsfEditorData
  })

  afterEach(() => {
    delete window.dsfFrontendData
    delete window.dsfEditorData
  })

  it('resolves the injected product when one is provided', () => {
    const wrapper = mount(Probe, {
      global: { provide: { dsfProductContext: { name: 'Real Product', priceHtml: '<span>$12.00</span>' } } },
    })

    expect(wrapper.text()).toContain('Real Product')
    expect(wrapper.text()).toContain('$12.00')
  })

  it('reads the localized frontend product when nothing is injected', () => {
    window.dsfFrontendData = { currentProduct: { name: 'Localized Product', priceHtml: '<span>$30.00</span>' } }

    const wrapper = mount(Probe)

    expect(wrapper.text()).toContain('Localized Product')
    expect(wrapper.text()).toContain('$30.00')
  })

  it('returns an empty product on the frontend when none can be resolved', () => {
    window.dsfFrontendData = { ajaxUrl: '/ajax' }

    const wrapper = mount(Probe)

    expect(wrapper.text()).toBe('{}')
    expect(wrapper.text()).not.toContain('$49.00')
    expect(wrapper.text()).not.toContain('Sample Product')
  })

  it('still falls back to the placeholder in the editor', () => {
    window.dsfEditorData = {}

    const wrapper = mount(Probe)

    expect(wrapper.text()).toContain('Sample Product')
    expect(PRODUCT_PLACEHOLDER.priceHtml).toContain('$49.00')
  })
})
