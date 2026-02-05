import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FeaturesGridPreview from '../blocks/FeaturesGridPreview.vue'

const openModal = vi.fn()

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal }),
}))

describe('FeaturesGridPreview', () => {
  beforeEach(() => {
    openModal.mockReset()
  })

  it('opens modal for feature button action modal', async () => {
    const settings = {
      features: [
        {
          title: 'Feature',
          description: 'Desc',
          buttonText: 'Open',
          buttonAction: 'modal',
          buttonModalLayout: 'center',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Hi</p>',
        },
      ],
    }

    const wrapper = mount(FeaturesGridPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-feature-card-preview__btn').trigger('click')

    expect(openModal).toHaveBeenCalledTimes(1)
    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>Hi</p>',
    })
  })

  it('uses link href when button action is link', () => {
    const settings = {
      features: [
        {
          title: 'Feature',
          description: 'Desc',
          buttonText: 'Go',
          buttonAction: 'link',
          buttonUrl: '/learn',
        },
      ],
    }

    const wrapper = mount(FeaturesGridPreview, {
      props: { settings, isEditor: false },
    })

    const link = wrapper.find('.dsf-feature-card-preview__btn')
    expect(link.attributes('href')).toBe('/learn')
  })
})
