import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import HeaderShowcaseMegaPreview from '../blocks/HeaderShowcaseMegaPreview.vue'

const panel = {
  introTitle: 'Shop Our Spas', introText: 'Choose the right spa.', buttonText: 'Shop now', buttonUrl: '/shop/',
  accentText: 'Accessories', accentUrl: '/accessories/', promoImage: 'https://example.com/special.jpg',
  promoTitle: 'Spa Specials', promoSubtitle: 'Limited time only', promoUrl: '/specials/',
  cards: [{ eyebrow: 'Luxury', title: 'Premium Spas', url: '/premium/', image: 'https://example.com/card.jpg' }],
}

const settings = {
  promoText: 'Summer Event', promoUrl: '/sale/', logoText: 'Example', specialButtonText: 'Specials', specialButtonUrl: '/specials/',
  navigation: {
    utility: [
      { label: 'Services', url: '#', icon: 'settings', kind: 'mega', links: [], panel },
      { label: 'Resources', url: '#', icon: 'book', kind: 'dropdown', links: [{ label: 'Blog', url: '/blog/' }], panel: {} },
    ],
    menu: [{ label: 'Hot Tubs', url: '#', hasMega: true, panel }, { label: 'Patio', url: '/patio/', hasMega: false, panel: {} }],
    locations: [{ name: 'Boise', address: '123 Main', hours: 'Mon-Sat', phone: '555-0100', phoneUrl: 'tel:+15550100', directionsUrl: '/directions/' }],
    calls: [{ label: 'Sales', url: 'tel:+15550100' }],
  },
}

describe('Showcase Mega Header', () => {
  afterEach(() => { document.body.style.overflow = ''; document.body.innerHTML = '' })

  it('opens rich and compact desktop navigation', async () => {
    const wrapper = mount(HeaderShowcaseMegaPreview, { props: { settings, isEditor: true }, attachTo: document.body })
    await wrapper.findAll('.dsf-showcase-header__desktop-nav>a')[0].trigger('click')
    expect(wrapper.find('.dsf-showcase-header__panel-shell').text()).toContain('Shop Our Spas')
    expect(wrapper.find('.dsf-showcase-header__cards').text()).toContain('Premium Spas')

    await wrapper.findAll('.dsf-showcase-header__utility-item>a')[1].trigger('click')
    expect(wrapper.find('.dsf-showcase-header__dropdown').text()).toContain('Blog')
  })

  it('supports nested mobile panels, locations, calls, and Escape cleanup', async () => {
    const wrapper = mount(HeaderShowcaseMegaPreview, { props: { settings, isEditor: true }, attachTo: document.body })
    await wrapper.find('.dsf-showcase-header__mobile-actions button').trigger('click')
    expect(document.body.style.overflow).toBe('hidden')

    await wrapper.findAll('.dsf-showcase-header__drawer-list button')[0].trigger('click')
    expect(wrapper.find('.dsf-showcase-header__mobile-panel').text()).toContain('Shop Our Spas')
    await wrapper.find('.dsf-showcase-header__drawer-back button').trigger('click')
    await wrapper.findAll('.dsf-showcase-header__drawer-top button')[0].trigger('click')
    expect(wrapper.find('.dsf-showcase-header__locations').text()).toContain('Boise')
    await wrapper.findAll('.dsf-showcase-header__drawer-top button')[1].trigger('click')
    expect(wrapper.find('.dsf-showcase-header__drawer-list').text()).toContain('Sales')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()
    expect(document.body.style.overflow).toBe('')
    expect(wrapper.find('.dsf-showcase-header__drawer').classes()).not.toContain('is-open')
  })

  it('rejects script URLs at render time', () => {
    const unsafe = { ...settings, promoUrl: 'javascript:alert(1)' }
    const wrapper = mount(HeaderShowcaseMegaPreview, { props: { settings: unsafe } })
    expect(wrapper.find('.dsf-showcase-header__promo').attributes('href')).toBe('#')
  })
})
