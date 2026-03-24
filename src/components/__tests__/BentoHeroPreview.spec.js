import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BentoHeroPreview from '../blocks/BentoHeroPreview.vue'

describe('BentoHeroPreview bars', () => {
  beforeEach(() => {
    window.dsfEditorData = {}
    window.dsfFrontendData = {}
  })

  it('renders the top bar by itself when enabled', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          showTopBar: true,
          topBarText: 'Shop by Category',
          showBottomBar: false,
        },
        isEditor: false,
      },
    })

    const bars = wrapper.findAll('.dsf-bento-hero__section-bar')
    expect(bars).toHaveLength(1)
    expect(bars.at(0).text()).toContain('Shop by Category')
  })

  it('renders the bottom bar by itself when enabled', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          showTopBar: false,
          showBottomBar: true,
          bottomBarText: 'Shop by Brand',
        },
        isEditor: false,
      },
    })

    const bars = wrapper.findAll('.dsf-bento-hero__section-bar')
    expect(bars).toHaveLength(1)
    expect(bars.at(0).text()).toContain('Shop by Brand')
  })

  it('renders 4 boxes when boxCount is set to 4', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '4',
        },
        isEditor: false,
      },
    })

    expect(wrapper.findAll('.dsf-bento-hero__box')).toHaveLength(4)
    expect(wrapper.find('.dsf-bento-hero__cta').exists()).toBe(false)
  })

  it('renders 6 boxes when boxCount is set to 6 and the last tile is a box', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '6',
          ctaType: 'box',
        },
        isEditor: false,
      },
    })

    expect(wrapper.findAll('.dsf-bento-hero__box')).toHaveLength(6)
  })

  it('centers a standard box image when its title is hidden', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '4',
          box1ShowTitle: false,
        },
        isEditor: false,
      },
    })

    const firstBox = wrapper.findAll('.dsf-bento-hero__box').at(0)
    expect(firstBox.classes()).toContain('dsf-bento-hero__box--image-only')
    expect(firstBox.find('.dsf-bento-hero__box-title').exists()).toBe(false)
  })

  it('centers the last tile image when its title is hidden', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '6',
          ctaType: 'box',
          box6ShowTitle: false,
        },
        isEditor: false,
      },
    })

    const boxes = wrapper.findAll('.dsf-bento-hero__box')
    const lastBox = boxes.at(boxes.length - 1)
    expect(lastBox.classes()).toContain('dsf-bento-hero__box--image-only')
    expect(lastBox.find('.dsf-bento-hero__box-title').exists()).toBe(false)
  })

  it('uses the saved WordPress media alt text for standard box images', () => {
    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '4',
          box1Image: 'https://example.com/spa-water-care.jpg',
          box1ImageAlt: 'Spa Water Care jug',
          box1Title: 'Fallback title',
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-bento-hero__box-img').attributes('alt')).toBe('Spa Water Care jug')
  })

  it('uses the category image alt text for category last tiles', () => {
    window.dsfFrontendData = {
      categories: [
        {
          id: 12,
          name: 'Spa Water Care',
          image: 'https://example.com/category.jpg',
          imageAlt: 'Spa Water Care category graphic',
          url: '/category/spa-water-care',
        },
      ],
    }

    const wrapper = mount(BentoHeroPreview, {
      props: {
        settings: {
          boxCount: '6',
          ctaType: 'category',
          box6CategoryId: 12,
        },
        isEditor: false,
      },
    })

    const images = wrapper.findAll('.dsf-bento-hero__box-img')
    const lastImage = images.at(images.length - 1)
    expect(lastImage.attributes('alt')).toBe('Spa Water Care category graphic')
  })
})
