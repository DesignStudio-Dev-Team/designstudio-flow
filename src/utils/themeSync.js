const THEME_COLOR_ALIASES = {
  secondaryColor: new Set(['#1e40af']),
  primaryColor: new Set(['#3b82f6', '#2563eb', '#60a5fa', '#2c5f5d']),
  textColor: new Set(['#1f2937', '#4b5563', '#6b7280', '#9ca3af']),
  backgroundColor: new Set(['#ffffff', '#f5f5f4']),
}

export function normalizeValue(value) {
  if (typeof value !== 'string') return ''
  return value.trim().toLowerCase()
}

export function resolveThemeKey(value, key = '') {
  // Check key name first for fonts/explicit mapping
  if (key === 'headingFont') return 'headingFont'
  if (key === 'bodyFont') return 'bodyFont'
  if (key === 'titleFont') return 'headingFont'
  if (key === 'textFont') return 'bodyFont'

  // Then check values (colors)
  const normalized = normalizeValue(value)
  if (!normalized) return null
  
  if (THEME_COLOR_ALIASES.secondaryColor.has(normalized)) return 'secondaryColor'
  if (THEME_COLOR_ALIASES.primaryColor.has(normalized)) return 'primaryColor'
  if (THEME_COLOR_ALIASES.textColor.has(normalized)) return 'textColor'
  if (THEME_COLOR_ALIASES.backgroundColor.has(normalized)) return 'backgroundColor'
  
  return null
}

export function shouldSyncSetting(currentValue, themeKey, oldThemeValue) {
  if (currentValue === undefined || currentValue === null || currentValue === '') {
    return true
  }
  
  const normalized = normalizeValue(currentValue)
  
  // For fonts or exact matches
  if (oldThemeValue && normalized === normalizeValue(oldThemeValue)) return true
  
  // For color aliases
  const aliasSet = THEME_COLOR_ALIASES[themeKey]
  return aliasSet ? aliasSet.has(normalized) : false
}

export function applyThemeToBlocks(blocksList, oldTheme, newTheme, linkedSettingsMap) {
  if (!oldTheme || !newTheme) return blocksList
  if (!Array.isArray(blocksList)) return blocksList

  return blocksList.map((block) => {
    const linkedKeys = linkedSettingsMap?.[block.type]
    if (!linkedKeys || !block.settings) return block

    const nextSettings = { ...block.settings }
    let updated = false

    Object.entries(linkedKeys).forEach(([settingKey, themeKey]) => {
      // Allow syncing if the key exists OR if it's a theme property that should be there
      // if (!(settingKey in nextSettings)) return // - Removed to allow injecting theme defaults if missing? No, safer to keep.
      if (!(settingKey in nextSettings)) return

      if (!shouldSyncSetting(nextSettings[settingKey], themeKey, oldTheme[themeKey])) return
      
      nextSettings[settingKey] = newTheme[themeKey]
      updated = true
    })

    if (!updated) return block
    return { ...block, settings: nextSettings }
  })
}
