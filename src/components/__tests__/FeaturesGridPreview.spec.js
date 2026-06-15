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

  it('uses black feature button styles by default', () => {
    const settings = {
      features: [
        {
          title: 'Feature',
          description: 'Desc',
          buttonText: 'Go',
        },
      ],
    }

    const wrapper = mount(FeaturesGridPreview, {
      props: { settings, isEditor: false },
    })

    const link = wrapper.find('.dsf-feature-card-preview__btn')
    expect(link.attributes('style')).toContain('background-color: rgb(0, 0, 0);')
    expect(link.attributes('style')).toContain('border-color: rgb(0, 0, 0);')
    expect(link.attributes('style')).toContain('color: rgb(255, 255, 255);')
  })

  it('renders bottom content and bottom CTA link', () => {
    const settings = {
      features: [],
      bottomContent: '<p>More reasons to work with us.</p>',
      bottomButtonText: 'Start Project',
      bottomButtonAction: 'link',
      bottomButtonUrl: '/start',
    }

    const wrapper = mount(FeaturesGridPreview, {
      props: { settings, isEditor: false },
    })

    expect(wrapper.find('.dsf-features-grid-preview__bottom-content').html()).toContain('More reasons')
    expect(wrapper.find('.dsf-features-grid-preview__bottom-btn').attributes('href')).toBe('/start')
  })

  it('opens modal for bottom CTA modal action', async () => {
    const settings = {
      features: [],
      bottomButtonText: 'Open Details',
      bottomButtonAction: 'modal',
      bottomButtonModalLayout: 'drawer-right',
      bottomButtonModalContentType: 'wysiwyg',
      bottomButtonModalContent: '<p>Bottom modal</p>',
    }

    const wrapper = mount(FeaturesGridPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-features-grid-preview__bottom-btn').trigger('click')

    expect(openModal).toHaveBeenCalledTimes(1)
    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer-right',
      contentType: 'wysiwyg',
      content: '<p>Bottom modal</p>',
    })
  })
})
