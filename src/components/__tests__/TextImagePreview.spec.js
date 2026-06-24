import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TextImagePreview from '../blocks/TextImagePreview.vue'

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal: vi.fn() }),
}))

describe('TextImagePreview', () => {
  it('renders the editable description as a normal paragraph', () => {
    const wrapper = mount(TextImagePreview, {
      props: {
        settings: {
          title: 'Our story',
          content: 'A normal paragraph description.',
          descriptionSize: 'normal',
        },
      },
    })

    const description = wrapper.find('p.dsf-text-image-preview__text')
    expect(description.text()).toBe('A normal paragraph description.')
    expect(description.classes()).toContain('dsf-text-image-preview__text--normal')
  })

  it('uses large description styling by default and rejects malformed size values', () => {
    const wrapper = mount(TextImagePreview, {
      props: { settings: { content: 'Large text', descriptionSize: 'huge-script-value' } },
    })

    expect(wrapper.find('.dsf-text-image-preview__text').classes()).not.toContain('dsf-text-image-preview__text--normal')
  })

  it('supports a compact 100px block with zero vertical padding', () => {
    const wrapper = mount(TextImagePreview, {
      props: { settings: { height: 100, padding: 0 } },
    })

    const style = wrapper.find('.dsf-text-image-container').attributes('style')
    expect(style).toContain('--dsf-text-image-height: 100px;')
    expect(style).toContain('--dsf-text-image-padding-y: 0px;')
    expect(style).toContain('padding: 0px 20px;')
  })

  it('uses paragraph-sized CTA text and rejects executable links', () => {
    const wrapper = mount(TextImagePreview, {
      props: {
        settings: {
          showButton: true,
          buttonText: 'Learn More',
          buttonAction: 'link',
          buttonUrl: 'javascript:alert(1)',
        },
      },
    })

    expect(wrapper.find('.dsf-text-image-preview__btn').attributes('href')).toBe('#')
    expect(wrapper.find('.dsf-text-image-preview__btn').text()).toBe('Learn More')
  })
})
