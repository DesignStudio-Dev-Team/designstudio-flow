import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BentoHeroPreview from '../blocks/BentoHeroPreview.vue'
import DuoHeroPreview from '../blocks/DuoHeroPreview.vue'
import PromoBannerPreview from '../blocks/PromoBannerPreview.vue'
import FeaturedProductBannerPreview from '../blocks/FeaturedProductBannerPreview.vue'
import FeaturedPromoBannerPreview from '../blocks/FeaturedPromoBannerPreview.vue'
import CtaBannerPreview from '../blocks/CtaBannerPreview.vue'
import TextImagePreview from '../blocks/TextImagePreview.vue'

const openModal = vi.fn()

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal }),
}))

describe('Modal CTA Buttons', () => {
  beforeEach(() => {
    openModal.mockReset()
  })

  it('opens modal from Bento Hero main button', async () => {
    const settings = {
      heroType: 'button',
      heroButtonText: 'Open',
      heroButtonAction: 'modal',
      heroButtonModalLayout: 'drawer',
      heroButtonModalContentType: 'html',
      heroButtonModalHtml: '<p>Hero</p>',
    }

    const wrapper = mount(BentoHeroPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-bento-hero__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'html',
      content: '<p>Hero</p>',
    })
  })

  it('opens modal from Bento Hero CTA', async () => {
    const settings = {
      ctaText: 'CTA',
      ctaAction: 'modal',
      ctaModalLayout: 'center',
      ctaModalContentType: 'wysiwyg',
      ctaModalContent: '<p>CTA</p>',
    }

    const wrapper = mount(BentoHeroPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-bento-hero__cta').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>CTA</p>',
    })
  })

  it('opens modal from Duo Hero left button', async () => {
    const settings = {
      leftType: 'button',
      leftButtonText: 'Left',
      leftButtonAction: 'modal',
      leftButtonModalLayout: 'center',
      leftButtonModalContentType: 'wysiwyg',
      leftButtonModalContent: '<p>Left</p>',
    }

    const wrapper = mount(DuoHeroPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-duo-hero__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>Left</p>',
    })
  })

  it('opens modal from Duo Hero right button', async () => {
    const settings = {
      rightType: 'button',
      rightButtonText: 'Right',
      rightButtonAction: 'modal',
      rightButtonModalLayout: 'drawer',
      rightButtonModalContentType: 'shortcode',
      rightButtonModalShortcode: '[shortcode]',
    }

    const wrapper = mount(DuoHeroPreview, {
      props: { settings, isEditor: false },
    })

    const buttons = wrapper.findAll('.dsf-duo-hero__btn')
    await buttons.at(1).trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'shortcode',
      content: '[shortcode]',
    })
  })

  it('opens modal from Promo Banner button', async () => {
    const settings = {
      buttonText: 'Promo',
      buttonAction: 'modal',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '<p>Promo</p>',
    }

    const wrapper = mount(PromoBannerPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-promo-banner__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>Promo</p>',
    })
  })

  it('opens modal from Featured Product Banner button', async () => {
    const settings = {
      buttonText: 'Shop',
      buttonAction: 'modal',
      buttonModalLayout: 'drawer',
      buttonModalContentType: 'html',
      buttonModalHtml: '<p>Product</p>',
    }

    const wrapper = mount(FeaturedProductBannerPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-fpb__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'html',
      content: '<p>Product</p>',
    })
  })

  it('opens modal from Featured Promo Banner button', async () => {
    const settings = {
      buttonText: 'Open',
      buttonAction: 'modal',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '<p>Featured</p>',
    }

    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-featured-promo__arrow-btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>Featured</p>',
    })
  })

  it('opens modal from CTA Banner button', async () => {
    const settings = {
      buttonText: 'CTA',
      buttonAction: 'modal',
      buttonModalLayout: 'center',
      buttonModalContentType: 'wysiwyg',
      buttonModalContent: '<p>CTA Banner</p>',
    }

    const wrapper = mount(CtaBannerPreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-cta-banner-preview__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'center',
      contentType: 'wysiwyg',
      content: '<p>CTA Banner</p>',
    })
  })

  it('opens modal from Text Image button', async () => {
    const settings = {
      showButton: true,
      buttonText: 'Learn',
      buttonAction: 'modal',
      buttonModalLayout: 'drawer',
      buttonModalContentType: 'shortcode',
      buttonModalShortcode: '[text]',
    }

    const wrapper = mount(TextImagePreview, {
      props: { settings, isEditor: false },
    })

    await wrapper.find('.dsf-text-image-preview__btn').trigger('click')

    expect(openModal).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'shortcode',
      content: '[text]',
    })
  })
})
