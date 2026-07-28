import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TabbedProductShowcasePreview from '../blocks/TabbedProductShowcasePreview.vue'
import InlineText from '../common/InlineText.vue'

describe('TabbedProductShowcasePreview', () => {
  const settings = {
    title: 'Featured Hot Tubs',
    tabs: [
      {
        label: 'Ultimate Luxury',
        source: 'images',
        images: [{ image: 'https://cdn.example.test/tahiti.jpg', title: 'Tahiti', subtitle: '6 Seats | 48 Jets', url: '/tahiti' }],
      },
      { label: 'Performance & Value', source: 'products', productIds: [12] },
    ],
    primaryText: 'Shop All',
    secondaryText: 'Request Pricing',
  }

  it('renders tabs and the selected image tab', () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { settings } })
    expect(wrapper.findAll('[role="tab"]')).toHaveLength(2)
    expect(wrapper.find('.dsf-tabbed-showcase__card-title').text()).toBe('Tahiti')
    expect(wrapper.find('.dsf-tabbed-showcase__image').attributes('src')).toBe('https://cdn.example.test/tahiti.jpg')
  })

  it('renders the full image showcase card details and a safe whole-card URL', () => {
    const wrapper = mount(TabbedProductShowcasePreview, {
      props: {
        settings: {
          ...settings,
          style: 'image',
          tabs: [{ label: 'Featured', source: 'images', images: [{ image: 'https://cdn.example.test/tahiti.jpg', title: 'Tahiti', subtitle: '6 Seats', secondarySubtitle: '48 Jets', url: '/tahiti' }] }],
        },
      },
    })

    expect(wrapper.find('.dsf-tabbed-showcase__card-title').text()).toBe('Tahiti')
    expect(wrapper.find('.dsf-tabbed-showcase__card-subtitle').text()).toBe('6 Seats')
    expect(wrapper.find('.dsf-tabbed-showcase__card-secondary-subtitle').text()).toBe('48 Jets')
    expect(wrapper.find('.dsf-tabbed-showcase__card-link').attributes('href')).toBe('/tahiti')
    expect(wrapper.classes()).toContain('dsf-tabbed-showcase--image')
  })

  it('does not render the removed legacy product supporting text', () => {
    const wrapper = mount(TabbedProductShowcasePreview, {
      props: {
        settings: {
          ...settings,
          tabs: [{ label: 'Featured', source: 'products', supportingText: 'This should not render', productIds: [] }],
        },
      },
    })

    expect(wrapper.text()).not.toContain('This should not render')
    expect(wrapper.find('.dsf-tabbed-showcase__card-supporting').exists()).toBe(false)
  })

  it('shows three generic placeholders when a tab has no content', () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { title: '', tabs: [{ label: 'Featured', source: 'images', images: [] }] } } })
    expect(wrapper.findAll('.dsf-tabbed-showcase__card')).toHaveLength(3)
    expect(wrapper.findAll('.dsf-tabbed-showcase__placeholder')).toHaveLength(3)
  })

  it('supports an editable description that can be toggled off', () => {
    const visible = mount(TabbedProductShowcasePreview, { props: { isEditor: true, settings: { ...settings, description: 'Explore our collection.' } } })
    const hidden = mount(TabbedProductShowcasePreview, { props: { isEditor: true, settings: { ...settings, showDescription: false, description: 'Hidden description.' } } })

    expect(visible.find('.dsf-tabbed-showcase__description').text()).toBe('Explore our collection.')
    expect(visible.find('.dsf-tabbed-showcase__description').attributes('contenteditable')).toBe('true')
    expect(hidden.find('.dsf-tabbed-showcase__description').exists()).toBe(false)
  })

  it('groups the title and description in the shared split section heading', () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, description: 'Explore our collection.' } } })
    expect(wrapper.find('.dsf-tabbed-showcase__header .dsf-tabbed-showcase__title').exists()).toBe(true)
    expect(wrapper.find('.dsf-tabbed-showcase__header .dsf-tabbed-showcase__description').exists()).toBe(true)
  })

  it('shows carousel controls when a tab has more than three items', async () => {
    const images = Array.from({ length: 5 }, (_, index) => ({ image: `https://cdn.example.test/${index}.jpg`, title: `Item ${index + 1}`, subtitle: '' }))
    const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, tabs: [{ label: 'Featured', source: 'images', images }] } } })
    expect(wrapper.find('.dsf-tabbed-showcase__carousel').classes()).toContain('is-carousel')
    expect(wrapper.findAll('.dsf-tabbed-showcase__card')).toHaveLength(3)
    await wrapper.find('.dsf-tabbed-showcase__nav--next').trigger('click')
    expect(wrapper.find('.dsf-tabbed-showcase__card-title').text()).toBe('Item 2')
  })

  it('supports image, product, and standard tab presentation styles', () => {
    for (const style of ['image', 'products', 'tabs']) {
      const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, style } } })
      expect(wrapper.classes()).toContain(`dsf-tabbed-showcase--${style}`)
    }
  })

  it('supports modern, underline, and chevron tab system styles', () => {
    for (const style of ['modern', 'underline', 'chevron']) {
      const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, tabStyle: style } } })
      expect(wrapper.classes()).toContain(`dsf-tabbed-showcase--tab-style-${style}`)
    }

    const chevronWrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, tabStyle: 'chevron' } } })
    expect(chevronWrapper.find('.dsf-tabbed-showcase__chevron').exists()).toBe(true)
  })

  it('applies independent CTA and modern tab colors', () => {
    const wrapper = mount(TabbedProductShowcasePreview, {
      props: {
        settings: {
          ...settings,
          tabStyle: 'modern',
          primaryButtonColor: '#123456',
          primaryButtonTextColor: '#FFFFFF',
          secondaryButtonColor: '#654321',
          secondaryButtonTextColor: '#ABCDEF',
          tabTextColor: '#112233',
          activeTabTextColor: '#445566',
          modernTabsBackgroundColor: '#AABBCC',
          modernActiveTabBackgroundColor: '#DDEEFF',
        },
      },
    })

    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-primary-button-background')).toBe('#123456')
    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-secondary-button-background')).toBe('#654321')
    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-tab-text-color')).toBe('#112233')
    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-active-tab-text-color')).toBe('#445566')
    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-modern-tabs-background')).toBe('#AABBCC')
    expect(wrapper.element.style.getPropertyValue('--dsf-tabbed-showcase-modern-active-tab-background')).toBe('#DDEEFF')
    expect(wrapper.find('.dsf-tabbed-showcase__button--primary').exists()).toBe(true)
    expect(wrapper.find('.dsf-tabbed-showcase__button--secondary').exists()).toBe(true)
  })

  it('switches tabs with click and keeps editor links from navigating', async () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { isEditor: true, settings: { ...settings, primaryUrl: '/explore', secondaryUrl: 'https://example.com/more' } } })
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toContain('Performance & Value')
    const link = wrapper.find('.dsf-tabbed-showcase__button')
    await link.trigger('click')
    expect(link.attributes('href')).toBe('/explore')
  })

  it('keeps configured CTA URLs on the frontend', () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { settings: { ...settings, primaryUrl: '/explore', secondaryUrl: 'https://example.com/more' } } })
    expect(wrapper.findAll('.dsf-tabbed-showcase__button')[0].attributes('href')).toBe('/explore')
    expect(wrapper.findAll('.dsf-tabbed-showcase__button')[1].attributes('href')).toBe('https://example.com/more')
  })

  it('exposes all builder-editable text through InlineText', () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { isEditor: true, settings } })
    expect(wrapper.find('.dsf-tabbed-showcase__title').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('[role="tab"] .dsf-inline-text').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-tabbed-showcase__card-title').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-tabbed-showcase__card-subtitle').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-tabbed-showcase__button .dsf-inline-text').attributes('contenteditable')).toBe('true')
  })

  it('persists inline edits to the tab and image settings', async () => {
    const wrapper = mount(TabbedProductShowcasePreview, { props: { isEditor: true, settings } })
    await wrapper.find('.dsf-tabbed-showcase__tab').findComponent(InlineText).vm.$emit('update:modelValue', 'Luxury Updated')
    await wrapper.find('.dsf-tabbed-showcase__card-title').findComponent(InlineText).vm.$emit('update:modelValue', 'Tahiti Updated')
    expect(settings.tabs[0].label).toBe('Luxury Updated')
    expect(settings.tabs[0].images[0].title).toBe('Tahiti Updated')
  })

  it('does not expose inline editing outside the builder and rejects unsafe links', () => {
    const wrapper = mount(TabbedProductShowcasePreview, {
      props: {
        settings: {
          ...settings,
          primaryUrl: 'javascript:alert(1)',
          tabs: [{ ...settings.tabs[0], images: [{ ...settings.tabs[0].images[0], url: 'javascript:alert(1)' }] }],
        },
      },
    })
    expect(wrapper.find('[contenteditable="true"]').exists()).toBe(false)
    expect(wrapper.find('.dsf-tabbed-showcase__card-link').attributes('href')).toBe('#')
    expect(wrapper.find('.dsf-tabbed-showcase__button').attributes('href')).toBe('#')
  })
})
