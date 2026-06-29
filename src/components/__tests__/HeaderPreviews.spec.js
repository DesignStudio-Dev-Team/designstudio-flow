import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import HeaderMegaMenuPreview from '../blocks/HeaderMegaMenuPreview.vue'
import HeaderCutoutMegaPreview from '../blocks/HeaderCutoutMegaPreview.vue'
import BlockWrapper from '../BlockWrapper.vue'

describe('header cleanup', () => {
  afterEach(() => {
    document.body.style.overflow = ''
    document.body.innerHTML = ''
  })

  it('uses configurable action links and restores scrolling when the mobile menu closes', async () => {
    const wrapper = mount(HeaderMegaMenuPreview, {
      props: {
        settings: {
          logoImage: 'https://example.com/logo.svg',
          logoAlt: 'DesignStudio home',
          showSearch: true,
          searchUrl: '/search/',
          showAccount: true,
          accountUrl: '/account/',
          showCart: true,
          cartUrl: '/basket/',
          utilityLinks: [{ label: 'About', url: '/about/' }],
          menuItems: [{ label: 'Products', url: '/products/', hasMega: false, columns: [], banner: {} }],
          mobileMenuItems: [{ label: 'Products', url: '/products/', hasMega: false, columns: [], banner: {} }],
          mobileStores: [{ title: 'Store', address: 'Address', mapsLabel: 'Map', mapsUrl: '#', buttonLabel: '', buttonUrl: '#' }],
        },
      },
      attachTo: document.body,
    })

    expect(wrapper.find('.dsf-header-mega__brand-image').attributes('alt')).toBe('DesignStudio home')
    expect(wrapper.find('[aria-label="Search"]').attributes('href')).toBe('/search/')
    expect(wrapper.find('[aria-label="Account"]').attributes('href')).toBe('/account/')
    expect(wrapper.find('[aria-label="Cart"]').attributes('href')).toBe('/basket/')

    await wrapper.find('.dsf-header-mega__mobile-toggle').trigger('click')
    expect(wrapper.find('.dsf-header-mega__mobile-toggle').attributes('aria-expanded')).toBe('true')
    expect(document.body.style.overflow).toBe('hidden')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.dsf-header-mega__mobile-toggle').attributes('aria-expanded')).toBe('false')
    expect(document.body.style.overflow).toBe('')
  })

  it('adds accessible state and real search configuration to the cutout header', async () => {
    const wrapper = mount(HeaderCutoutMegaPreview, {
      props: {
        settings: {
          logoImage: 'https://example.com/logo.svg',
          logoAlt: 'Company home',
          showSearch: true,
          searchUrl: '/find/',
          utilityLinks: [{ label: 'Contact', url: '/contact/' }],
          menuItems: [{ label: 'Shop', url: '#', hasMega: true, columns: [], banner: {} }],
        },
      },
    })

    expect(wrapper.find('.dsf-header-cutout__logo img').attributes('alt')).toBe('Company home')
    expect(wrapper.find('.dsf-header-cutout__search-btn').attributes('href')).toBe('/find/')
    await wrapper.find('.dsf-header-cutout__menu-item').trigger('focus')
    expect(wrapper.find('.dsf-header-cutout__menu-item').attributes('aria-expanded')).toBe('true')
  })

  it('removes reorder controls from a single-header toolbar', () => {
    const wrapper = mount(BlockWrapper, {
      props: {
        block: { id: 'header-1', type: 'unknown-header', settings: {} },
        allowReorder: false,
      },
    })

    expect(wrapper.find('.dsf-block-toolbar__btn--drag').exists()).toBe(false)
    expect(wrapper.find('[title="Move up"]').exists()).toBe(false)
    expect(wrapper.find('[title="Move down"]').exists()).toBe(false)
    expect(wrapper.find('[title="Settings"]').exists()).toBe(true)
    expect(wrapper.find('[title="Delete"]').exists()).toBe(true)
  })

  it('gives regular blocks the complete accessible editor toolbar', () => {
    const wrapper = mount(BlockWrapper, {
      props: {
        block: { id: 'content-1', type: 'unknown-content', settings: {} },
        allowReorder: true,
        isSelected: true,
      },
    })

    const labels = wrapper.findAll('.dsf-block-toolbar__btn').map((button) => button.attributes('aria-label'))
    expect(labels).toEqual([
      'Drag to reorder',
      'Open block settings',
      'Save block to library',
      'Select for section template',
      'Move block up',
      'Move block down',
      'Delete block',
    ])
    expect(wrapper.classes()).toContain('dsf-block--selected')
    expect(wrapper.findAll('.dsf-block-toolbar__btn').every((button) => button.attributes('type') === 'button')).toBe(true)
  })
})
