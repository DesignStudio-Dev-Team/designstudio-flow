import { describe, it, expect } from 'vitest'
import {
  getResponsiveValue,
  setResponsiveValue,
  blockWrapperStyle,
  hasResponsiveKey,
  blockSelfAppliesPaddingX,
  blockSelfAppliesMarginY,
} from '../../utils/responsiveSettings'

describe('getResponsiveValue cascade', () => {
  const settings = {
    paddingX: 10,
    responsive: {
      desktop: { paddingX: 10 },
      tablet: { paddingX: 20 },
      // mobile intentionally has no paddingX → should fall back to desktop
      mobile: {},
    },
  }

  it('desktop reads the desktop override (or flat value)', () => {
    expect(getResponsiveValue(settings, 'desktop', 'paddingX')).toBe(10)
    expect(getResponsiveValue({ paddingX: 7 }, 'desktop', 'paddingX')).toBe(7)
  })

  it('tablet reads its own override when present', () => {
    expect(getResponsiveValue(settings, 'tablet', 'paddingX')).toBe(20)
  })

  it('mobile falls back to desktop when it has no override', () => {
    expect(getResponsiveValue(settings, 'mobile', 'paddingX')).toBe(10)
  })

  it('setResponsiveValue writes the breakpoint (and mirrors desktop to the flat key)', () => {
    const next = setResponsiveValue({}, 'desktop', 'marginY', 30)
    expect(next.marginY).toBe(30)
    expect(next.responsive.desktop.marginY).toBe(30)

    const tab = setResponsiveValue(next, 'tablet', 'marginY', 5)
    expect(tab.responsive.tablet.marginY).toBe(5)
    expect(tab.marginY).toBe(30) // flat (desktop) unchanged
  })
})

describe('blockWrapperStyle applies each spacing key exactly once', () => {
  it('a generic block gets margin + paddingX from the wrapper, per breakpoint', () => {
    const settings = { marginY: 25, paddingX: 0, responsive: { desktop: { marginY: 25, paddingX: 0 }, mobile: { marginY: 5, paddingX: 12 } } }
    const desktop = blockWrapperStyle(settings, 'desktop', { type: 'content', marginFallback: 25 })
    const mobile = blockWrapperStyle(settings, 'mobile', { type: 'content', marginFallback: 25 })

    expect(desktop.marginTop).toBe('25px')
    expect(desktop.paddingLeft).toBe('0px')
    expect(mobile.marginTop).toBe('5px')   // per-breakpoint override applied
    expect(mobile.paddingLeft).toBe('12px')
  })

  it('skips paddingX for classic self-padded blocks (no double)', () => {
    const style = blockWrapperStyle({ paddingX: 40 }, 'desktop', { type: 'features-grid', marginFallback: 25 })
    expect(style).not.toHaveProperty('paddingLeft')
    expect(style).not.toHaveProperty('paddingRight')
    expect(style.marginTop).toBe('25px') // wrapper still owns margin
  })

  it('skips both margin and paddingX for text-image (it self-applies both)', () => {
    const style = blockWrapperStyle({ paddingX: 40, marginY: 30 }, 'desktop', { type: 'text-image', marginFallback: 25 })
    expect(style).not.toHaveProperty('paddingLeft')
    expect(style).not.toHaveProperty('marginTop')
  })

  it('owns margin + paddingX for landing blocks (responsive, single source)', () => {
    const settings = { paddingX: 0, marginY: 0, responsive: { desktop: { paddingX: 0 }, tablet: { paddingX: 36 } } }
    const desktop = blockWrapperStyle(settings, 'desktop', { type: 'landing-hero', marginFallback: 0 })
    const tablet = blockWrapperStyle(settings, 'tablet', { type: 'landing-hero', marginFallback: 0 })
    expect(desktop.paddingLeft).toBe('0px')
    expect(tablet.paddingLeft).toBe('36px') // responsive override reaches the wrapper
  })

  it('applies min-height only when a height value exists, per breakpoint', () => {
    expect(blockWrapperStyle({}, 'desktop', { type: 'content' })).not.toHaveProperty('minHeight')
    const withHeight = blockWrapperStyle(
      { height: 400, responsive: { desktop: { height: 400 }, mobile: { height: 250 } } },
      'mobile',
      { type: 'spotlight-hero' }
    )
    expect(withHeight.minHeight).toBe('250px')
  })
})

describe('blockWrapperStyle full-bleed background', () => {
  it('paints backgroundColor on the full-width wrapper for non-self-padded blocks', () => {
    const style = blockWrapperStyle({ backgroundColor: '#ff0000', paddingX: 24 }, 'desktop', { type: 'content' })
    // Background on the wrapper (which is viewport-wide) → full-bleed, while the
    // paddingX still insets the content.
    expect(style.backgroundColor).toBe('#ff0000')
    expect(style.paddingLeft).toBe('24px')
  })

  it('accepts an rgba background', () => {
    const style = blockWrapperStyle({ backgroundColor: 'rgba(0, 0, 0, 0.5)' }, 'desktop', { type: 'content' })
    expect(style.backgroundColor).toBe('rgba(0, 0, 0, 0.5)')
  })

  it('adds no background when empty or transparent', () => {
    expect(blockWrapperStyle({ backgroundColor: '' }, 'desktop', { type: 'content' })).not.toHaveProperty('backgroundColor')
    expect(blockWrapperStyle({ backgroundColor: 'transparent' }, 'desktop', { type: 'content' })).not.toHaveProperty('backgroundColor')
    expect(blockWrapperStyle({}, 'desktop', { type: 'content' })).not.toHaveProperty('backgroundColor')
  })

  it('leaves self-padded blocks to paint their own full-width background', () => {
    // faq self-applies paddingX and renders its own full-width <section> bg, so
    // the wrapper must not also set it (would double-paint / fight rounded areas).
    const style = blockWrapperStyle({ backgroundColor: '#ff0000', paddingX: 40 }, 'desktop', { type: 'faq' })
    expect(style).not.toHaveProperty('backgroundColor')
  })
})

describe('self-apply registry', () => {
  it('marks the right blocks as self-padded / self-margined', () => {
    expect(blockSelfAppliesPaddingX('features-grid')).toBe(true)
    expect(blockSelfAppliesPaddingX('text-image')).toBe(true)
    expect(blockSelfAppliesPaddingX('landing-hero')).toBe(false) // wrapper owns it
    expect(blockSelfAppliesPaddingX('content')).toBe(false)
    expect(blockSelfAppliesMarginY('text-image')).toBe(true)
    expect(blockSelfAppliesMarginY('features-grid')).toBe(false)
  })

  it('hasResponsiveKey detects flat and per-breakpoint values', () => {
    expect(hasResponsiveKey({ height: 300 }, 'height')).toBe(true)
    expect(hasResponsiveKey({ responsive: { tablet: { height: 300 } } }, 'height')).toBe(true)
    expect(hasResponsiveKey({}, 'height')).toBe(false)
  })
})
