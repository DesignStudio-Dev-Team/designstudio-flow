const RESPONSIVE_KEYS = ['height', 'gap', 'padding', 'paddingX', 'marginY']

export function resolveResponsiveSettings(settings = {}, breakpoint = 'desktop') {
  const responsive = settings.responsive || {}
  const desktop = responsive.desktop || {}
  const current = responsive[breakpoint] || {}
  const resolved = { ...settings }

  RESPONSIVE_KEYS.forEach((key) => {
    if (breakpoint === 'desktop') {
      if (desktop[key] !== undefined && desktop[key] !== null) {
        resolved[key] = desktop[key]
      }
      return
    }

    if (current[key] !== undefined && current[key] !== null) {
      resolved[key] = current[key]
      return
    }

    if (desktop[key] !== undefined && desktop[key] !== null) {
      resolved[key] = desktop[key]
    }
  })

  return resolved
}

export function getResponsiveValue(settings = {}, breakpoint = 'desktop', key) {
  const responsive = settings.responsive || {}
  const desktop = responsive.desktop || {}
  const current = responsive[breakpoint] || {}

  if (breakpoint === 'desktop') {
    return desktop[key] ?? settings[key]
  }

  if (current[key] !== undefined && current[key] !== null) {
    return current[key]
  }

  return desktop[key] ?? settings[key]
}

export function setResponsiveValue(settings = {}, breakpoint = 'desktop', key, value) {
  const responsive = { ...(settings.responsive || {}) }
  responsive.desktop = { ...(responsive.desktop || {}) }
  responsive.tablet = { ...(responsive.tablet || {}) }
  responsive.mobile = { ...(responsive.mobile || {}) }

  if (breakpoint === 'desktop') {
    responsive.desktop[key] = value
    return { ...settings, [key]: value, responsive }
  }

  responsive[breakpoint][key] = value
  return { ...settings, responsive }
}
