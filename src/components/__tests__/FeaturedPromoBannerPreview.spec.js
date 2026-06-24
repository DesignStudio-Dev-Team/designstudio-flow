import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FeaturedPromoBannerPreview from '../blocks/FeaturedPromoBannerPreview.vue'

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal: vi.fn() }),
}))

describe('FeaturedPromoBannerPreview', () => {
  it('uses generic default editor text placeholders', () => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: {
        settings: {
          headerText: 'Default title here',
          descriptionText: 'Default text here',
        },
        isEditor: false,
      },
    })

    expect(wrapper.text()).toContain('Default title here')
    expect(wrapper.text()).toContain('Default text here')
  })

  it.each([
    ['arrow', 'M0 0H500L600 225L500 450H0Z'],
    ['vertical', 'M0 0H520V450H0Z'],
    ['diagonal-forward', 'M0 0H600L500 450H0Z'],
    ['diagonal-backward', 'M0 0H500L600 450H0Z'],
  ])('renders %s as a filled panel-edge shape', (dividerStyle, path) => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: { settings: { dividerStyle }, isEditor: false },
    })

    expect(wrapper.find(`.dsf-featured-promo__container--divider-${dividerStyle}`).exists()).toBe(true)
    expect(wrapper.find('.dsf-featured-promo__edge-divider').exists()).toBe(false)
    expect(wrapper.find('.dsf-featured-promo__divider-shape').attributes('d')).toBe(path)
    expect(wrapper.find('.dsf-featured-promo__divider-shape').attributes('fill')).toBe('#E0F2F1')
    expect(wrapper.text()).not.toContain('>')
  })

  it('falls back to the circle shape for an unknown divider value', () => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: { settings: { dividerStyle: 'not-a-divider' }, isEditor: false },
    })

    expect(wrapper.find('.dsf-featured-promo__container--divider-circle').exists()).toBe(true)
    expect(wrapper.find('.dsf-featured-promo__divider-shape').exists()).toBe(false)
  })

  it('can switch description text to normal paragraph sizing', () => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: {
        settings: {
          descriptionText: 'Normal body copy',
          descriptionSize: 'normal',
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-featured-promo__description--normal').exists()).toBe(true)
  })

  it('exposes compact height and spacing controls as CSS variables', () => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: {
        settings: {
          height: 100,
          contentSpacing: 0,
        },
        isEditor: false,
      },
    })

    const style = wrapper.find('.dsf-featured-promo__container').attributes('style')
    expect(style).toContain('--dsf-featured-promo-height: 100px;')
    expect(style).toContain('--dsf-featured-promo-spacing: 0px;')
  })
})
