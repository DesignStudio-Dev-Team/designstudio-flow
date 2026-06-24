import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LandingProgressHeaderPreview from '../blocks/LandingProgressHeaderPreview.vue'
import LandingHeroPreview from '../blocks/LandingHeroPreview.vue'
import LandingBlockExplorerPreview from '../blocks/LandingBlockExplorerPreview.vue'
import LandingProductStoryPreview from '../blocks/LandingProductStoryPreview.vue'
import LandingTrustWorkflowPreview from '../blocks/LandingTrustWorkflowPreview.vue'
import LandingMarketingFooterPreview from '../blocks/LandingMarketingFooterPreview.vue'

describe('DSFlow landing page blocks', () => {
  it('renders safe header actions and blocks unsafe URLs', () => {
    const wrapper = mount(LandingProgressHeaderPreview, {
      props: {
        isEditor: true,
        settings: {
          showAnnouncement: true,
          announcementText: 'Now available',
          announcementLinkText: 'Explore',
          announcementUrl: '#blocks',
          homeUrl: '#why-dsflow',
          docsText: 'Documentation',
          docsUrl: 'javascript:alert(1)',
          ctaText: 'Get DSFlow',
          ctaUrl: '#get-dsflow',
        },
      },
    })

    expect(wrapper.text()).toContain('DesignStudio Flow')
    expect(wrapper.find('.dsf-landing-header__mark').attributes('src')).toContain('assets/images/dsflow-logo.png')
    expect(wrapper.find('.dsf-button--ghost').attributes('href')).toBe('#')
    expect(wrapper.find('.dsf-landing-header__announcement a').attributes('href')).toBe('#blocks')
    const navTargets = wrapper.findAll('.dsf-landing-header__nav a').map((link) => link.attributes('href'))
    expect(navTargets).toContain('#engagement')
    expect(navTargets.indexOf('#security')).toBeLessThan(navTargets.indexOf('#audience'))
  })

  it('renders the product hero and interactive block filtering', async () => {
    const hero = mount(LandingHeroPreview, {
      props: {
        isEditor: true,
        settings: {
          eyebrow: 'Visual builder',
          title: 'Build freely.',
          description: 'A focused WordPress workflow.',
          primaryText: 'Explore',
          primaryUrl: '#blocks',
          secondaryText: 'See how',
          secondaryUrl: '#editor',
          note: 'Secure and structured.',
        },
      },
    })
    expect(hero.find('h1').text()).toBe('Build freely.')
    expect(hero.find('.dsf-studio').exists()).toBe(true)
    expect(hero.find('.dsf-studio__brand img').attributes('src')).toContain('assets/images/dsflow-logo.png')
    expect(hero.text()).toContain('Save Page')
    expect(hero.text()).toContain('Customize Block')
    expect(hero.text()).toContain('Add Block')
    expect(hero.find('[data-dsf-builder-art]').exists()).toBe(true)

    const explorer = mount(LandingBlockExplorerPreview, {
      props: { isEditor: true, settings: { eyebrow: 'Blocks', title: 'Explore', description: 'Choose well.', footnote: 'More to come.' } },
    })
    expect(explorer.findAll('.dsf-explorer-card')).toHaveLength(18)
    expect(explorer.findAll('[data-dsf-card]')).toHaveLength(18)
    expect(explorer.text()).toContain('Product Grid')

    // Filtering keeps every card mounted but focuses only the matches.
    const ecommerceButton = explorer.findAll('.dsf-block-explorer__filters button').find((button) => button.text() === 'Ecommerce')
    await ecommerceButton.trigger('click')
    const focused = explorer.findAll('.dsf-explorer-card.is-focus')
    expect(focused).toHaveLength(1)
    expect(focused.at(0).text()).toContain('Product Grid')
    expect(explorer.findAll('.dsf-explorer-card.is-dim')).toHaveLength(17)
    expect(explorer.find('[aria-label="Show previous blocks"]').attributes('disabled')).toBeDefined()
    expect(explorer.find('[aria-label="Show next blocks"]').attributes('aria-controls')).toContain('dsf-block-explorer-rail-')
  })

  it('offers keyboard-accessible carousel controls on the frontend', async () => {
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })))
    const scrollBy = vi.fn()
    const explorer = mount(LandingBlockExplorerPreview, {
      attachTo: document.body,
      props: { blockId: 'blocks-1', settings: {} },
    })
    const rail = explorer.find('.dsf-block-explorer__rail').element
    rail.scrollBy = scrollBy

    await explorer.find('[aria-label="Show next blocks"]').trigger('click')

    expect(scrollBy).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }))
    expect(explorer.find('[aria-label="Show next blocks"]').attributes('aria-controls')).toBe('dsf-block-explorer-rail-blocks-1')
    explorer.unmount()
    vi.unstubAllGlobals()
  })

  it('switches product story and trust visuals by bounded variants', () => {
    const story = mount(LandingProductStoryPreview, {
      props: {
        isEditor: true,
        settings: { variant: 'commerce', eyebrow: 'Commerce', title: 'Real products', description: 'A native experience.', featureOne: 'Filters', featureTwo: 'Search', featureThree: 'Categories' },
      },
    })
    expect(story.attributes('id')).toBe('woocommerce')
    expect(story.find('.dsf-story-ui--commerce').exists()).toBe(true)
    expect(story.findAll('li')).toHaveLength(3)

    const trust = mount(LandingTrustWorkflowPreview, {
      props: { isEditor: true, settings: { variant: 'security', eyebrow: 'Security', title: 'Clear boundaries', description: 'Safe by design.' } },
    })
    expect(trust.attributes('id')).toBe('security')
    expect(trust.findAll('.dsf-trust__security article')).toHaveLength(4)
  })

  it('renders the final CTA and current footer structure', () => {
    const wrapper = mount(LandingMarketingFooterPreview, {
      props: {
        isEditor: true,
        settings: {
          eyebrow: 'Next page',
          title: 'Give WordPress room to flow.',
          description: 'Build with confidence.',
          primaryText: 'Get DSFlow',
          primaryUrl: '#',
          secondaryText: 'Read docs',
          secondaryUrl: '#workflow',
          homeUrl: '#why-dsflow',
          docsUrl: '#workflow',
          brandStatement: 'A modern visual builder.',
        },
      },
    })

    expect(wrapper.find('h2').text()).toContain('room to flow')
    expect(wrapper.find('.dsf-footer-mark').attributes('src')).toContain('assets/images/dsflow-logo.png')
    expect(wrapper.findAll('nav')).toHaveLength(3)
    expect(wrapper.find('.dsf-footer-button--light').classes()).toContain('dsf-footer-button--light')
    expect(wrapper.text()).toContain('Built for WordPress')
  })

  it('renders genuinely distinct CTA footer variants', () => {
    const baseSettings = {
      title: 'Build something useful.',
      description: 'A closing message.',
      primaryText: 'Start',
      primaryUrl: '#start',
      secondaryText: 'Learn',
      secondaryUrl: '#learn',
      brandStatement: 'A flexible footer.',
    }

    const columns = mount(LandingMarketingFooterPreview, {
      props: { isEditor: true, settings: { ...baseSettings, variant: 'columns' } },
    })
    expect(columns.find('.dsf-marketing-footer__cta').exists()).toBe(false)
    expect(columns.find('.dsf-marketing-footer__main').exists()).toBe(true)
    expect(columns.findAll('nav')).toHaveLength(3)

    const simple = mount(LandingMarketingFooterPreview, {
      props: { isEditor: true, settings: { ...baseSettings, variant: 'simple' } },
    })
    expect(simple.find('.dsf-marketing-footer__cta').exists()).toBe(true)
    expect(simple.find('.dsf-marketing-footer__main').exists()).toBe(false)
  })

  it('applies hero alignment and visual-position classes from settings', () => {
    const hero = mount(LandingHeroPreview, {
      props: { isEditor: true, settings: { title: 'Aligned', align: 'center', mediaPosition: 'left' } },
    })
    expect(hero.classes()).toContain('is-center')
    expect(hero.classes()).toContain('is-media-left')
  })

  it('falls back to default footer copyright and tagline when unset', () => {
    const footer = mount(LandingMarketingFooterPreview, {
      props: { isEditor: true, settings: { title: 'Footer' } },
    })
    const bottom = footer.find('.dsf-marketing-footer__bottom').text()
    expect(bottom).toContain('DesignStudio Flow. Built for WordPress.')
    expect(bottom).toContain('Build freely. Stay beautifully consistent.')
  })
})
