import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import HeroCenteredPreview from '../blocks/HeroCenteredPreview.vue'

const openModal = vi.fn()

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal }),
}))

describe('HeroCenteredPreview', () => {
  beforeEach(() => {
    openModal.mockReset()
  })

  it('opens modal when button action is modal', async () => {
    const settings = {
      showButton: true,
      buttonText: 'Open',
      buttonAction: 'modal',
      buttonModalLayout: 'drawer',
      buttonModalContentType: 'html',
      buttonModalHtml: '<p>Hello</p>',
    }

    const wrapper = mount(HeroCenteredPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-hero-centered-preview__btn').trigger('click')

    expect(openModal).toHaveBeenCalledTimes(1)
    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'html',
      content: '<p>Hello</p>',
    })
  })

  it('does not open modal when button action is link', async () => {
    const settings = {
      showButton: true,
      buttonText: 'Go',
      buttonAction: 'link',
      buttonUrl: '/shop',
    }

    const wrapper = mount(HeroCenteredPreview, {
      props: { settings, isEditor: false },
    })

    const btn = wrapper.find('.dsf-hero-centered-preview__btn')
    expect(btn.attributes('href')).toBe('/shop')

    await btn.trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal while in editor mode', async () => {
    const settings = {
      showButton: true,
      buttonText: 'Open',
      buttonAction: 'modal',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '<p>Editor</p>',
    }

    const wrapper = mount(HeroCenteredPreview, {
      props: { settings, isEditor: true },
    })

    await wrapper.find('.dsf-hero-centered-preview__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })
})
