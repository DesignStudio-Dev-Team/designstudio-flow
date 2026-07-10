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
          contentPosition: 'bottom-left',
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

  it('defaults two-column content to center and keeps content off the edge', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { layoutStyle: 'bottom-split', paddingX: 0 },
        isEditor: false,
      },
    })

    const rootStyle = wrapper.attributes('style')
    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')

    expect(contentStyle).toContain('justify-content: center;')
    expect(contentStyle).toContain('text-align: center;')
    // paddingX of 0 is bumped to a 15px minimum at the edge.
    expect(rootStyle).toContain('padding: 80px 15px 15px;')
  })

  it('positions two-column content from the content position setting', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { layoutStyle: 'bottom-split', contentPosition: 'bottom-right' },
        isEditor: false,
      },
    })

    const rootStyle = wrapper.attributes('style')
    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')

    expect(rootStyle).toContain('justify-content: flex-end;')
    expect(contentStyle).toContain('justify-content: flex-end;')
    expect(contentStyle).toContain('text-align: right;')
  })

  it('defaults to a 500px min-height with vertically centered content', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: { settings: { title: 'Hi' }, isEditor: false },
    })
    const rootStyle = wrapper.attributes('style')
    // Background lives on the root, which carries the height, so it grows too.
    expect(rootStyle).toContain('min-height: 500px;')
    expect(rootStyle).toContain('justify-content: center;')
    expect(rootStyle).toContain('background-color: rgb(59, 130, 246);')
  })

  it('grows the hero (and its background) to the Height setting', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: { settings: { title: 'Hi', height: 760 }, isEditor: false },
    })
    const rootStyle = wrapper.attributes('style')
    expect(rootStyle).toContain('min-height: 760px;')
    // Still vertically centered as it grows.
    expect(rootStyle).toContain('justify-content: center;')
  })

  it('honors a per-breakpoint Height for the active preview mode', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: { height: 700, responsive: { mobile: { height: 320 } } },
        isEditor: false,
        previewMode: 'mobile',
      },
    })
    expect(wrapper.attributes('style')).toContain('min-height: 320px;')
  })

  it('falls back to 500px when the Height value is invalid', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: { settings: { height: 'not-a-number' }, isEditor: false },
    })
    expect(wrapper.attributes('style')).toContain('min-height: 500px;')
  })

  it('uses the configured edge padding as the minimum safe padding', () => {
    const wrapper = mount(HeroCenteredPreview, {
      props: {
        settings: {
          layoutStyle: 'bottom-split',
          contentPosition: 'center-center',
          paddingX: 0,
          bottomOffset: 0,
          contentEdgePadding: 32,
        },
        isEditor: false,
      },
    })

    const rootStyle = wrapper.attributes('style')
    const contentStyle = wrapper.get('.dsf-hero-centered-preview__content').attributes('style')

    expect(contentStyle).toContain('justify-content: center;')
    expect(rootStyle).toContain('padding: 80px 32px 32px;')
  })
})
