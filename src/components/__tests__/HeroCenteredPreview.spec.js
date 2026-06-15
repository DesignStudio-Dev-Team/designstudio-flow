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

  it('supports the bottom split layout with bottom gradient and spacing controls', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: {
          title: 'Pool Season',
          subtitle: 'Beat the heat',
          showButton: true,
          buttonText: 'Shop',
          layoutStyle: 'bottom-split',
          gradientType: 'bottom-dark',
          gradientHeight: 75,
          bottomOffset: 15,
          titleSubtitleGap: 0,
          textButtonGap: 0,
          textColumnWidth: 360,
        },
        isEditor: false,
      },
    })

    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')
    const textStyle = wrapper.get('.dsf-hero-centered-preview__text').attributes('style')
    const gradientStyle = wrapper.get('.dsf-hero-centered-preview__gradient').attributes('style')

    expect(wrapper.classes()).toContain('dsf-hero-centered-preview--bottom-split')
    // Columns hug their content so the button sits next to the text.
    expect(contentStyle).toContain('grid-template-columns: auto auto;')
    expect(contentStyle).toContain('align-items: center;')
    expect(contentStyle).toContain('gap: 0px;')
    expect(contentStyle).toContain('--hero-title-subtitle-gap: 0px;')
    // The text column width becomes a max-width cap on the text block.
    expect(textStyle).toContain('max-width: 360px;')
    expect(gradientStyle).toContain('height: 75%;')
  })

  it('defaults bottom-split to left alignment and keeps content off the edge', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { layoutStyle: 'bottom-split', paddingX: 0 },
        isEditor: false,
      },
    })

    const rootStyle = wrapper.attributes('style')
    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')

    expect(contentStyle).toContain('justify-content: flex-start;')
    expect(contentStyle).toContain('text-align: left;')
    // paddingX of 0 is bumped to a 15px minimum at the edge.
    expect(rootStyle).toContain('padding: 80px 15px 15px;')
  })

  it('right-aligns bottom-split content when configured', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { layoutStyle: 'bottom-split', bottomSplitAlign: 'right' },
        isEditor: false,
      },
    })

    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')
    expect(contentStyle).toContain('justify-content: flex-end;')
    expect(contentStyle).toContain('text-align: right;')
  })

  it('does not force a 15px minimum when bottom-split is centered', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { layoutStyle: 'bottom-split', bottomSplitAlign: 'center', paddingX: 0 },
        isEditor: false,
      },
    })

    const rootStyle = wrapper.attributes('style')
    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')

    expect(contentStyle).toContain('justify-content: center;')
    expect(rootStyle).toContain('padding: 80px 0px 15px;')
  })
})
