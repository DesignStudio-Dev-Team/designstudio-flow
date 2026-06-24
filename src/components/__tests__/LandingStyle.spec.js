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
      color: '#fefefe',
      '--dsf-theme-background': '#112233',
      '--dsf-landing-text': '#fefefe',
      '--dsf-theme-primary': '#ff6600',
      paddingLeft: '28px',
      paddingRight: '28px',
      marginTop: '16px',
      marginBottom: '16px',
    }))
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
    expect(style.paddingLeft).toBe('80px')
    expect(style).not.toHaveProperty('marginTop')
  })
})
