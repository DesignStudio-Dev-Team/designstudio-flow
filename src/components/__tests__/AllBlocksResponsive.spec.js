import { describe, it, expect, vi, beforeAll } from 'vitest'
import { mount } from '@vue/test-utils'
import FrontendApp from '../../frontend/FrontendApp.vue'

beforeAll(() => {
  vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} })))
  window.innerWidth = 1280
})

// Every registered block type (mirrors DSF_Blocks::get_registered_blocks()).
const BLOCK_TYPES = [
  'content', 'faq', 'hero', 'countdown', 'pricing', 'expander-hero', 'features-grid',
  'bento-hero', 'spotlight-hero', 'duo-hero', 'featured-promo-banner', 'text-image',
  'testimonials', 'form-embed', 'form-with-content', 'product-grid', 'ecommerce-showcase',
  'brand-carousel', 'promo-banner', 'featured-product-banner', 'product-summary',
  'product-gallery', 'product-description', 'product-specs', 'product-tabs',
  'product-add-to-cart', 'header-mega-menu',
  'header-showcase-mega', 'header-cutout-mega', 'footer-dealers', 'cta-banner',
  'landing-progress-header', 'landing-hero', 'landing-block-explorer', 'landing-block-ready',
  'landing-product-story', 'landing-trust-workflow', 'landing-engagement-suite',
  'landing-redirect-tool', 'landing-mail-tool', 'landing-marketing-footer',
]

// Generous settings so blocks expecting arrays/strings mount cleanly.
function settingsFor(extra = {}) {
  return {
    paddingX: 41,
    plans: [], features: [], items: [], testimonials: [], logos: [], cards: [],
    options: [], col1Links: [], col2Links: [], col3Links: [],
    ...extra,
  }
}

function mountBlock(type, extra) {
  return mount(FrontendApp, {
    props: { blocks: [{ id: 'b', type, settings: settingsFor(extra) }], popupSettings: {}, postId: 0 },
  })
}

const PADX = /padding(-left|-right)?:\s*[^;]*41px/

describe('every block applies horizontal padding exactly once (no double, no dead slider)', () => {
  it('wrapper XOR block — never both, never neither', () => {
    const offenders = []
    for (const type of BLOCK_TYPES) {
      let wrapperHas = false
      let innerHas = false
      try {
        const w = mountBlock(type)
        const wrapperStyle = w.find('.dsf-block').attributes('style') || ''
        const inner = w.find('.dsf-block > *')
        const innerStyle = inner.exists() ? (inner.attributes('style') || '') : ''
        wrapperHas = /padding-left:\s*41px/.test(wrapperStyle)
        innerHas = PADX.test(innerStyle)
        w.unmount()
      } catch (e) {
        offenders.push(`${type}: MOUNT ERROR ${String(e.message).slice(0, 60)}`)
        continue
      }
      if (wrapperHas && innerHas) offenders.push(`${type}: DOUBLE (wrapper + block both pad)`)
      if (!wrapperHas && !innerHas) offenders.push(`${type}: DEAD (neither applies paddingX)`)
    }
    expect(offenders, offenders.join('\n')).toEqual([])
  })
})

describe('responsive override reaches the frontend per breakpoint', () => {
  it('wrapper-owned block reflects the mobile paddingX override', () => {
    window.innerWidth = 375 // mobile breakpoint
    const w = mount(FrontendApp, {
      props: {
        blocks: [{ id: 'b', type: 'content', settings: { paddingX: 0, responsive: { desktop: { paddingX: 0 }, mobile: { paddingX: 18 } } } }],
        popupSettings: {}, postId: 0,
      },
    })
    window.dispatchEvent(new Event('resize'))
    const style = w.find('.dsf-block').attributes('style') || ''
    expect(style).toMatch(/padding-left:\s*18px/)
    w.unmount()
    window.innerWidth = 1280
  })

  it('an explicit height puts min-height on the wrapper and marks it to fill (bg grows)', () => {
    const w = mount(FrontendApp, {
      props: {
        blocks: [{ id: 'b', type: 'cta-banner', settings: { height: 520, responsive: { desktop: { height: 520 } } } }],
        popupSettings: {}, postId: 0,
      },
    })
    const block = w.find('.dsf-block')
    expect(block.classes()).toContain('dsf-block--has-height') // CSS stretches the child to fill
    expect(block.attributes('style') || '').toMatch(/min-height:\s*520px/)
    w.unmount()
  })

  it('no height set → no min-height and no fill class', () => {
    const w = mount(FrontendApp, {
      props: { blocks: [{ id: 'b', type: 'cta-banner', settings: {} }], popupSettings: {}, postId: 0 },
    })
    const block = w.find('.dsf-block')
    expect(block.classes()).not.toContain('dsf-block--has-height')
    expect(block.attributes('style') || '').not.toMatch(/min-height/)
    w.unmount()
  })

  it('self-padded block reflects the mobile paddingX override on its own root', () => {
    window.innerWidth = 375
    const w = mount(FrontendApp, {
      props: {
        blocks: [{ id: 'b', type: 'features-grid', settings: { features: [], paddingX: 0, responsive: { desktop: { paddingX: 0 }, mobile: { paddingX: 22 } } } }],
        popupSettings: {}, postId: 0,
      },
    })
    window.dispatchEvent(new Event('resize'))
    const wrapperStyle = w.find('.dsf-block').attributes('style') || ''
    const innerStyle = w.find('.dsf-block > *').attributes('style') || ''
    expect(wrapperStyle).not.toMatch(/padding-left:\s*22px/) // wrapper must NOT add it
    expect(innerStyle).toMatch(/22px/)                       // block applies it
    w.unmount()
    window.innerWidth = 1280
  })
})
