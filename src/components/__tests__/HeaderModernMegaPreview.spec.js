import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import HeaderModernMegaPreview from '../blocks/HeaderModernMegaPreview.vue'

const settings = {
  logoText: 'Acme',
  menuItems: [
    {
      label: 'Products',
      url: '#',
      hasMega: true,
      columns: [
        { heading: 'Categories', layout: 'links', links: [{ label: 'New', url: '/new' }, { label: 'Sale', url: '/sale' }] },
        { heading: 'Brands', layout: 'cards', imageColumns: 2, links: [{ label: 'Brand A', url: '#', image: 'https://example.com/a.png' }] },
        { heading: 'Services', layout: 'icons', links: [{ label: 'Support', url: '#', icon: 'shield-check' }] },
      ],
      banner: { title: 'Featured', text: 'Check it out', buttonLabel: 'Go', url: '/feat' },
    },
    { label: 'Contact', url: '/contact', hasMega: false, columns: [], banner: {} },
  ],
}

describe('Modern Mega Header', () => {
  afterEach(() => {
    document.body.style.overflow = ''
    document.body.innerHTML = ''
  })

  it('renders the brand and a centered nav', () => {
    const wrapper = mount(HeaderModernMegaPreview, { props: { settings, isEditor: true } })
    expect(wrapper.find('.dsf-mmega__brand').text()).toContain('Acme')
    const items = wrapper.findAll('.dsf-mmega__nav-item')
    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('Products')
  })

  it('opens a mega panel with links, cards, and icon layouts plus a featured card', async () => {
    const wrapper = mount(HeaderModernMegaPreview, { props: { settings, isEditor: true } })
    await wrapper.findAll('.dsf-mmega__nav-item')[0].trigger('click')

    expect(wrapper.find('.dsf-mmega__panel').exists()).toBe(true)
    expect(wrapper.find('.dsf-mmega__links').exists()).toBe(true)
    expect(wrapper.find('.dsf-mmega__cards').exists()).toBe(true)
    expect(wrapper.find('.dsf-mmega__icons').exists()).toBe(true)
    expect(wrapper.find('.dsf-mmega__card img').attributes('src')).toBe('https://example.com/a.png')
    expect(wrapper.find('.dsf-mmega__featured').text()).toContain('Featured')
    expect(wrapper.find('.dsf-mmega__featured').text()).toContain('Go')
  })

  it('toggles the mega panel closed on a second click', async () => {
    const wrapper = mount(HeaderModernMegaPreview, { props: { settings, isEditor: true } })
    const item = wrapper.findAll('.dsf-mmega__nav-item')[0]
    await item.trigger('click')
    expect(wrapper.find('.dsf-mmega__panel').exists()).toBe(true)
    await item.trigger('click')
    expect(wrapper.find('.dsf-mmega__panel').exists()).toBe(false)
  })

  it('opens the mobile drawer and lists items', async () => {
    const wrapper = mount(HeaderModernMegaPreview, { props: { settings, isEditor: true }, attachTo: document.body })
    expect(wrapper.find('.dsf-mmega__drawer').classes()).not.toContain('is-open')
    await wrapper.find('.dsf-mmega__mobile-toggle').trigger('click')
    expect(wrapper.find('.dsf-mmega__drawer').classes()).toContain('is-open')
    expect(wrapper.find('.dsf-mmega__drawer-nav').text()).toContain('Products')
  })

  it('renders a fallback menu when none is provided', () => {
    const wrapper = mount(HeaderModernMegaPreview, { props: { settings: {}, isEditor: false } })
    expect(wrapper.findAll('.dsf-mmega__nav-item').length).toBeGreaterThan(0)
  })
})
