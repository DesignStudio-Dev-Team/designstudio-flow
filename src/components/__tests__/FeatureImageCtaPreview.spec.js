import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FeatureImageCtaPreview from '../blocks/FeatureImageCtaPreview.vue'

describe('FeatureImageCtaPreview', () => {
  it('renders defaults safely when settings are missing', () => {
    const wrapper = mount(FeatureImageCtaPreview)
    expect(wrapper.find('.dsf-feature-image-cta__placeholder').exists()).toBe(true)
    expect(wrapper.find('.dsf-feature-image-cta').attributes('style')).toContain('background-color: rgb(243, 244, 246)')
  })

  it('renders repeatable icon labels and reverses the image layout', () => {
    const wrapper = mount(FeatureImageCtaPreview, { props: { settings: { imagePosition: 'left', features: [{ icon: 'zap', title: 'Efficient' }, { icon: 'star', title: 'Premium' }] } } })
    expect(wrapper.findAll('.dsf-feature-image-cta__features li')).toHaveLength(2)
    expect(wrapper.classes()).toContain('dsf-feature-image-cta--image-left')
  })

  it('applies safe icon foreground and background colors', () => {
    const wrapper = mount(FeatureImageCtaPreview, {
      props: { settings: { iconColor: '#123456', iconBackground: '#abcdef', features: [{ icon: 'zap', title: 'Efficient' }] } },
    })

    const style = wrapper.find('.dsf-feature-image-cta__icon').attributes('style')
    expect(style).toContain('color: rgb(18, 52, 86)')
    expect(style).toContain('background-color: rgb(171, 205, 239)')
  })

  it('uses a safe custom image icon and falls back from unsafe URLs', () => {
    const safe = mount(FeatureImageCtaPreview, { props: { settings: { features: [{ icon: 'zap', title: 'Efficient', iconImage: 'https://example.com/icon.svg' }] } } })
    expect(safe.find('.dsf-feature-image-cta__icon-image').attributes('src')).toBe('https://example.com/icon.svg')

    const unsafe = mount(FeatureImageCtaPreview, { props: { settings: { features: [{ icon: 'zap', title: 'Efficient', iconImage: 'javascript:alert(1)' }] } } })
    expect(unsafe.find('.dsf-feature-image-cta__icon-image').exists()).toBe(false)
  })

  it('prevents editor CTA navigation and rejects executable URLs', async () => {
    const wrapper = mount(FeatureImageCtaPreview, { props: { isEditor: true, settings: { showButton: true, buttonUrl: 'javascript:alert(1)' } } })
    const button = wrapper.find('.dsf-feature-image-cta__button')
    expect(button.attributes('href')).toBe('#')
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    button.element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })

  it('clamps malformed visual values to safe bounds', () => {
    const wrapper = mount(FeatureImageCtaPreview, { props: { settings: { imageInset: 9999, borderRadius: -4, paddingY: 'bad', backgroundColor: 'url(javascript:alert(1))' } } })
    expect(wrapper.find('.dsf-feature-image-cta').attributes('style')).toContain('--dsf-feature-image-cta-radius: 0px')
    expect(wrapper.find('.dsf-feature-image-cta__image-wrap').attributes('style')).toContain('padding: 200px')
  })
})
