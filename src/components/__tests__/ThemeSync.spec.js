import { describe, it, expect } from 'vitest'
import { applyThemeToBlocks } from '../../utils/themeSync.js'

describe('Theme sync', () => {
  it('updates block color settings tied to theme defaults', () => {
    const blocks = [
      {
        type: 'hero',
        settings: {
          backgroundColor: '#2C5F5D',
          textColor: '#FFFFFF',
        },
      },
    ]

    const linkedSettings = {
      hero: {
        backgroundColor: 'primaryColor',
      },
    }

    const oldTheme = { primaryColor: '#2C5F5D' }
    const newTheme = { primaryColor: '#123456' }

    const result = applyThemeToBlocks(blocks, oldTheme, newTheme, linkedSettings)

    expect(result[0].settings.backgroundColor).toBe('#123456')
    expect(result[0].settings.textColor).toBe('#FFFFFF')
  })

  it('does not update when current value is custom', () => {
    const blocks = [
      {
        type: 'hero',
        settings: {
          backgroundColor: '#FF0000',
        },
      },
    ]

    const linkedSettings = {
      hero: {
        backgroundColor: 'primaryColor',
      },
    }

    const oldTheme = { primaryColor: '#2C5F5D' }
    const newTheme = { primaryColor: '#123456' }

    const result = applyThemeToBlocks(blocks, oldTheme, newTheme, linkedSettings)

    expect(result[0].settings.backgroundColor).toBe('#FF0000')
  })

  it('preserves custom backgrounds through a theme change and undo', () => {
    const blocks = [
      { type: 'hero', settings: { backgroundColor: '#FF0000' } },
      { type: 'hero', settings: { backgroundColor: '#FFFFFF' } },
      { type: 'hero', settings: { backgroundColor: '#FCFBF7' } },
    ]
    const linkedSettings = { hero: { backgroundColor: 'backgroundColor' } }
    const originalTheme = { backgroundColor: '#FCFBF7' }
    const changedTheme = { backgroundColor: '#111827' }

    const changedBlocks = applyThemeToBlocks(
      blocks,
      originalTheme,
      changedTheme,
      linkedSettings,
      { allowAliases: false }
    )
    const undoneBlocks = applyThemeToBlocks(
      changedBlocks,
      changedTheme,
      originalTheme,
      linkedSettings,
      { allowAliases: false }
    )

    expect(changedBlocks[0].settings.backgroundColor).toBe('#FF0000')
    expect(changedBlocks[1].settings.backgroundColor).toBe('#FFFFFF')
    expect(changedBlocks[2].settings.backgroundColor).toBe('#111827')
    expect(undoneBlocks[0].settings.backgroundColor).toBe('#FF0000')
    expect(undoneBlocks[1].settings.backgroundColor).toBe('#FFFFFF')
    expect(undoneBlocks[2].settings.backgroundColor).toBe('#FCFBF7')
  })

  it('maps semantic color setting names to page theme tokens', async () => {
    const { resolveThemeKey } = await import('../../utils/themeSync.js')

    expect(resolveThemeKey('#abcdef', 'buttonColor')).toBe('primaryColor')
    expect(resolveThemeKey('#abcdef', 'titleColor')).toBe('textColor')
    expect(resolveThemeKey('#abcdef', 'buttonTextColor')).toBe('backgroundColor')
    expect(resolveThemeKey('#abcdef', 'dividerColor')).toBe('secondaryColor')
  })

  it('can intentionally propagate a changed theme token over custom block defaults', () => {
    const blocks = [{ type: 'hero', settings: { buttonColor: '#FF0000' } }]
    const linkedSettings = { hero: { buttonColor: 'primaryColor' } }

    const result = applyThemeToBlocks(
      blocks,
      { primaryColor: '#2C5F5D' },
      { primaryColor: '#123456' },
      linkedSettings,
      { forceChangedThemeKeys: true }
    )

    expect(result[0].settings.buttonColor).toBe('#123456')
  })
})
