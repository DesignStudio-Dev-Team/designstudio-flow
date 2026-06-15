import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SpotlightHeroPreview from '../blocks/SpotlightHeroPreview.vue'

describe('SpotlightHeroPreview', () => {
  const makeButtons = (labels) =>
    labels.map((text) => ({ text, url: '#', enabled: true }))

  it('renders the main image, promo tile, and the configured buttons', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          mediaType: 'image',
          mainImage: 'https://example.com/pool.jpg',
          mainTitle: 'Can you afford a new pool?',
          mainButtonText: 'Start Here',
          promoImage: 'https://example.com/promo.jpg',
          sideButtons: makeButtons([
            'Pool Water Care',
            'Pool Maintenance',
            'Pool Inspiration Gallery',
          ]),
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__main .dsf-spotlight-hero__media').attributes('src'))
      .toBe('https://example.com/pool.jpg')
    expect(wrapper.find('.dsf-spotlight-hero__promo').exists()).toBe(true)

    const buttons = wrapper.findAll('.dsf-spotlight-hero__side-btn')
    expect(buttons).toHaveLength(3)
    expect(buttons.at(0).text()).toContain('Pool Water Care')
    expect(buttons.at(2).text()).toContain('Pool Inspiration Gallery')
  })

  it('defaults the main content to left alignment', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: { settings: { mainTitle: 'Hi' }, isEditor: false },
    })

    expect(wrapper.find('.dsf-spotlight-hero__main-content').classes())
      .toContain('dsf-spotlight-hero__main-content--left')
  })

  it.each(['left', 'center', 'right'])('applies %s content alignment', (align) => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: { settings: { mainTitle: 'Hi', mainContentAlign: align }, isEditor: false },
    })

    expect(wrapper.find('.dsf-spotlight-hero__main-content').classes())
      .toContain(`dsf-spotlight-hero__main-content--${align}`)
  })

  it('hides the main button when showMainButton is false', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { mainButtonText: 'Start Here', showMainButton: false },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__btn').exists()).toBe(false)
  })

  it('shows the main button by default', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { mainButtonText: 'Start Here' },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__btn').text()).toContain('Start Here')
  })

  it('removes the promo caption and gradient when showPromoCaption is false', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          promoImage: 'https://example.com/promo.jpg',
          promoTitle: 'Sale this week',
          showPromoCaption: false,
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__promo-content').exists()).toBe(false)
  })

  it('shows the promo caption when enabled and a caption is set', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          promoImage: 'https://example.com/promo.jpg',
          promoTitle: 'Sale this week',
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__promo-content').text()).toContain('Sale this week')
  })

  it('hides all buttons when showButtons is false', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          showButtons: false,
          sideButtons: makeButtons(['One', 'Two']),
        },
        isEditor: false,
      },
    })

    expect(wrapper.findAll('.dsf-spotlight-hero__side-btn')).toHaveLength(0)
  })

  it('skips individually disabled buttons', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          sideButtons: [
            { text: 'Visible', url: '#', enabled: true },
            { text: 'Hidden', url: '#', enabled: false },
            { text: 'Also Visible', url: '#', enabled: true },
          ],
        },
        isEditor: false,
      },
    })

    const buttons = wrapper.findAll('.dsf-spotlight-hero__side-btn')
    expect(buttons).toHaveLength(2)
    expect(buttons.at(0).text()).toContain('Visible')
    expect(buttons.at(1).text()).toContain('Also Visible')
  })

  it('stacks the first three buttons full width (no 2-column grid)', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { sideButtons: makeButtons(['A', 'B', 'C']) },
        isEditor: false,
      },
    })

    const container = wrapper.find('.dsf-spotlight-hero__buttons')
    expect(container.classes()).not.toContain('dsf-spotlight-hero__buttons--two-col')
    const fullWidth = wrapper
      .findAll('.dsf-spotlight-hero__side-btn')
      .filter((b) => b.classes().includes('dsf-spotlight-hero__side-btn--full'))
    expect(fullWidth).toHaveLength(0)
  })

  it('switches to the 2-column grid once a fourth button is added', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { sideButtons: makeButtons(['A', 'B', 'C', 'D']) },
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__buttons').classes())
      .toContain('dsf-spotlight-hero__buttons--two-col')
  })

  it('makes only the trailing button full width when the count is odd', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { sideButtons: makeButtons(['A', 'B', 'C', 'D', 'E']) },
        isEditor: false,
      },
    })

    const buttons = wrapper.findAll('.dsf-spotlight-hero__side-btn')
    expect(buttons).toHaveLength(5)
    const fullWidth = buttons.filter((b) => b.classes().includes('dsf-spotlight-hero__side-btn--full'))
    expect(fullWidth).toHaveLength(1)
    expect(buttons.at(4).classes()).toContain('dsf-spotlight-hero__side-btn--full')
  })

  it('keeps every button half width when the count is even', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: { sideButtons: makeButtons(['A', 'B', 'C', 'D']) },
        isEditor: false,
      },
    })

    const fullWidth = wrapper
      .findAll('.dsf-spotlight-hero__side-btn')
      .filter((b) => b.classes().includes('dsf-spotlight-hero__side-btn--full'))
    expect(fullWidth).toHaveLength(0)
  })

  it('renders a video iframe for a YouTube URL in video mode', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          mediaType: 'video',
          mainVideo: 'https://youtu.be/abc123',
        },
        isEditor: false,
      },
    })

    const iframe = wrapper.find('.dsf-spotlight-hero__media--embed')
    expect(iframe.exists()).toBe(true)
    expect(iframe.attributes('src')).toContain('youtube.com/embed/abc123')
  })

  it('renders an HTML5 video for an mp4 URL in video mode', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          mediaType: 'video',
          mainVideo: 'https://example.com/clip.mp4',
        },
        isEditor: false,
      },
    })

    expect(wrapper.find('video').exists()).toBe(true)
    expect(wrapper.find('video source').attributes('type')).toBe('video/mp4')
  })

  it('falls back to the placeholder when no media is set', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {},
        isEditor: false,
      },
    })

    expect(wrapper.find('.dsf-spotlight-hero__main .dsf-spotlight-hero__placeholder').exists()).toBe(true)
  })

  it('applies button colors from settings', () => {
    const wrapper = mount(SpotlightHeroPreview, {
      props: {
        settings: {
          sideButtons: [{ text: 'One', url: '#', enabled: true }],
          buttonColor: '#123456',
          buttonTextColor: '#abcdef',
        },
        isEditor: false,
      },
    })

    const btn = wrapper.find('.dsf-spotlight-hero__side-btn')
    expect(btn.attributes('style')).toContain('background-color: rgb(18, 52, 86)')
    expect(btn.attributes('style')).toContain('color: rgb(171, 205, 239)')
  })
})
