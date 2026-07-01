import { describe, expect, it } from 'vitest'
import { landingBlockStyle } from '../../utils/landingStyle'

describe('landing block style settings', () => {
  it('maps colors and bounded spacing to real CSS properties', () => {
    expect(landingBlockStyle({
      backgroundColor: '#112233',
      background: '#112233',
      textColor: '#fefefe',
      accentColor: '#ff6600',
      paddingX: 28,
      marginY: 16,
    })).toEqual(expect.objectContaining({
      backgroundColor: '#112233',
      '--dsf-theme-background': '#112233',
      '--dsf-theme-text': '#fefefe',
      '--dsf-landing-text': '#fefefe',
      '--dsf-theme-primary': '#ff6600',
    }))
  })

  it('does not apply margin/padding — the block wrapper owns responsive spacing', () => {
    const style = landingBlockStyle({ paddingX: 28, marginY: 16 })
    expect(style).not.toHaveProperty('paddingLeft')
    expect(style).not.toHaveProperty('paddingRight')
    expect(style).not.toHaveProperty('marginTop')
    expect(style).not.toHaveProperty('marginBottom')
  })

  it('text color drives only the theme text variable, never a blanket inline color', () => {
    // Text color must not cascade over eyebrow / accent / button colors, so it is
    // applied via --dsf-theme-text only (each block routes that to its body text).
    const style = landingBlockStyle({ textColor: '#abcdef' })
    expect(style['--dsf-theme-text']).toBe('#abcdef')
    expect(style).not.toHaveProperty('color')
  })

  it('rejects malformed colors and clamps spacing', () => {
    const style = landingBlockStyle({
      backgroundColor: 'url(javascript:alert(1))',
      textColor: '#123',
      paddingX: 999,
      marginY: -20,
    })

    expect(style).not.toHaveProperty('backgroundColor')
    expect(style).not.toHaveProperty('color')
    expect(style).not.toHaveProperty('paddingLeft')
    expect(style).not.toHaveProperty('marginTop')
  })
})
