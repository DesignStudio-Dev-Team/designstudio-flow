import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ExpanderHeroPreview from '../blocks/ExpanderHeroPreview.vue'

function makeCards(count) {
  return Array.from({ length: count }, (_, index) => ({
    title: `Card ${index + 1}`,
    image: `https://example.com/card-${index + 1}.jpg`,
    url: '#',
  }))
}

describe('ExpanderHeroPreview', () => {
  it('renders split layout with three top cards, bar, and three bottom cards', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          cards: makeCards(6),
          barTitle: 'Test Title 1',
          buttonText: 'test',
        },
        isEditor: false,
      },
    })

    expect(wrapper.findAll('.dsf-expander-hero__card-grid--top .dsf-expander-hero__card')).toHaveLength(3)
    expect(wrapper.findAll('.dsf-expander-hero__card-grid--bottom .dsf-expander-hero__card')).toHaveLength(3)
    expect(wrapper.findAll('.dsf-expander-hero__card--expandable')).toHaveLength(6)
    expect(wrapper.find('.dsf-expander-hero__grid').classes())
      .toContain('dsf-expander-hero__grid--bar-middle')
    expect(wrapper.find('.dsf-expander-hero__bar-title').text()).toContain('Test Title 1')
    expect(wrapper.find('.dsf-expander-hero__bar-btn').exists()).toBe(true)
  })

  it('uses a two-column bottom row when there are five cards', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          cards: makeCards(5),
        },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__card-grid--bottom').classes())
      .toContain('dsf-expander-hero__card-grid--two')
    expect(wrapper.findAll('.dsf-expander-hero__card-grid--bottom .dsf-expander-hero__card')).toHaveLength(2)
  })

  it('renders all cards in one expanding row when configured', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'row',
          cards: makeCards(6),
        },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__row').exists()).toBe(true)
    expect(wrapper.findAll('.dsf-expander-hero__card--row')).toHaveLength(6)
    expect(wrapper.find('.dsf-expander-hero__bar').exists()).toBe(false)
  })

  it('can hide the center CTA button', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          showButton: false,
          cards: makeCards(6),
        },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__bar-btn').exists()).toBe(false)
  })

  it('uses a high-contrast default CTA while preserving editable button colors', async () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          cards: makeCards(6),
        },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__bar-btn').attributes('style'))
      .toContain('background-color: rgb(23, 33, 43)')
    expect(wrapper.find('.dsf-expander-hero__bar-btn').attributes('style'))
      .toContain('color: rgb(255, 255, 255)')

    await wrapper.setProps({
      settings: {
        layoutStyle: 'split-bar',
        cards: makeCards(6),
        buttonColor: '#F5B942',
        buttonTextColor: '#17212B',
      },
    })

    expect(wrapper.find('.dsf-expander-hero__bar-btn').attributes('style'))
      .toContain('background-color: rgb(245, 185, 66)')
    expect(wrapper.find('.dsf-expander-hero__bar-btn').attributes('style'))
      .toContain('color: rgb(23, 33, 43)')
  })

  it.each(['top', 'middle', 'bottom'])('places the split bar at %s', (barPosition) => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          barPosition,
          cards: makeCards(6),
        },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__grid').classes())
      .toContain(`dsf-expander-hero__grid--bar-${barPosition}`)
  })

  it('applies the responsive height to the complete layout', () => {
    const split = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'split-bar',
          height: 640,
          responsive: { tablet: { height: 520 } },
          cards: makeCards(6),
        },
        previewMode: 'tablet',
      },
    })
    const row = mount(ExpanderHeroPreview, {
      props: {
        settings: { layoutStyle: 'row', height: 420, cards: makeCards(6) },
      },
    })

    expect(split.find('.dsf-expander-hero__grid').attributes('style')).toContain('height: 520px')
    expect(row.find('.dsf-expander-hero__row').attributes('style')).toContain('height: 420px')
  })

  it('uses card height as an exact fallback until an overall height is selected', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: { layoutStyle: 'split-bar', cardHeight: 360, cards: makeCards(6) },
      },
    })

    expect(wrapper.find('.dsf-expander-hero__card').attributes('style')).toContain('height: 360px')
    expect(wrapper.find('.dsf-expander-hero__grid').attributes('style')).not.toContain('height:')
  })

  it('falls back to safe layout values and rejects unsafe public URLs', () => {
    const wrapper = mount(ExpanderHeroPreview, {
      props: {
        settings: {
          layoutStyle: 'unexpected',
          barPosition: 'sideways',
          buttonUrl: 'javascript:alert(1)',
          cards: [{ title: 'Unsafe', image: 'javascript:alert(1)', url: 'javascript:alert(1)' }],
        },
      },
    })

    expect(wrapper.classes()).toContain('dsf-expander-hero--split-bar')
    expect(wrapper.find('.dsf-expander-hero__grid').classes())
      .toContain('dsf-expander-hero__grid--bar-middle')
    expect(wrapper.find('.dsf-expander-hero__bar-btn').attributes('href')).toBe('#')
    expect(wrapper.find('.dsf-expander-hero__card-img').exists()).toBe(false)
    expect(wrapper.find('.dsf-expander-hero__card').element.tagName).toBe('DIV')
  })
})
