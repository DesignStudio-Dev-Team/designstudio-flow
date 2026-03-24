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
})
