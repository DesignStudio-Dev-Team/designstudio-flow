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

describe('Modal CTA Buttons (Editor Mode)', () => {
  beforeEach(() => {
    openModal.mockReset()
  })

  it('does not open modal for Bento Hero main button in editor', async () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          heroType: 'button',
          heroButtonAction: 'modal',
          heroButtonModalContentType: 'wysiwyg',
          heroButtonModalContent: '<p>Hero</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-bento-hero__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Bento Hero CTA in editor', async () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          ctaAction: 'modal',
          ctaModalContentType: 'wysiwyg',
          ctaModalContent: '<p>CTA</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-bento-hero__cta').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Duo Hero buttons in editor', async () => {
    const wrapper = mount(DuoHeroPreview, {
      props: {
        settings: {
          leftType: 'button',
          leftButtonAction: 'modal',
          leftButtonModalContentType: 'wysiwyg',
          leftButtonModalContent: '<p>Left</p>',
          rightType: 'button',
          rightButtonAction: 'modal',
          rightButtonModalContentType: 'wysiwyg',
          rightButtonModalContent: '<p>Right</p>',
        },
        isEditor: true,
      },
    })

    const buttons = wrapper.findAll('.dsf-duo-hero__btn')
    await buttons.at(0).trigger('click')
    await buttons.at(1).trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Promo Banner button in editor', async () => {
    const wrapper = mount(PromoBannerPreview, {
      props: {
        settings: {
          buttonAction: 'modal',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Promo</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-promo-banner__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Featured Product Banner button in editor', async () => {
    const wrapper = mount(FeaturedProductBannerPreview, {
      props: {
        settings: {
          buttonAction: 'modal',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Featured</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-fpb__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Featured Promo Banner button in editor', async () => {
    const wrapper = mount(FeaturedPromoBannerPreview, {
      props: {
        settings: {
          buttonAction: 'modal',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Featured Promo</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-featured-promo__arrow-btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for CTA Banner button in editor', async () => {
    const wrapper = mount(CtaBannerPreview, {
      props: {
        settings: {
          buttonAction: 'modal',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>CTA</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-cta-banner-preview__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })

  it('does not open modal for Text Image button in editor', async () => {
    const wrapper = mount(TextImagePreview, {
      props: {
        settings: {
          showButton: true,
          buttonAction: 'modal',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Text</p>',
        },
        isEditor: true,
      },
    })

    await wrapper.find('.dsf-text-image-preview__btn').trigger('click')
    expect(openModal).not.toHaveBeenCalled()
  })
})
