const RESPONSIVE_KEYS = ['height', 'gap', 'padding', 'paddingX', 'marginY']

// Classic block components that render their OWN horizontal padding (via
// getResponsiveValue('paddingX')). For these the wrapper must NOT also apply
// paddingX, or it would double up. Landing blocks rely on the wrapper instead
// (their margin/paddingX were removed from landingBlockStyle for this reason).
const SELF_PADDED_X_TYPES = new Set([
  'faq', 'featured-promo-banner', 'product-grid', 'brand-carousel', 'duo-hero',
  'ecommerce-showcase', 'hero', 'countdown', 'features-grid', 'card-columns', 'cta-banner',
  'testimonials', 'expander-hero', 'newsletter', 'text-image', 'form-with-content',
])

// Block components that render their own vertical margin.
const SELF_MARGIN_Y_TYPES = new Set(['text-image'])

export function blockSelfAppliesPaddingX(type) {
  return SELF_PADDED_X_TYPES.has(type)
}

export function blockSelfAppliesMarginY(type) {
  return SELF_MARGIN_Y_TYPES.has(type)
}

export function hasResponsiveKey(settings = {}, key) {
  if (settings && settings[key] !== undefined && settings[key] !== null) return true
  const responsive = (settings && settings.responsive) || {}
  return ['desktop', 'tablet', 'mobile'].some(
    (bp) => responsive[bp]?.[key] !== undefined && responsive[bp]?.[key] !== null
  )
}

/**
 * The inline style the block WRAPPER (.dsf-block) applies for a given breakpoint:
 * vertical margin, horizontal padding, and min-height — each resolved per
 * breakpoint, and each skipped for block types that render it themselves (so it
 * is applied exactly once). Shared by the editor (BlockWrapper) and the
 * frontend (FrontendApp) so they stay identical.
 */
export function blockWrapperStyle(settings = {}, breakpoint = 'desktop', options = {}) {
  const type = options.type || ''
  const marginFallback = options.marginFallback ?? 25
  const style = {}

  if (!blockSelfAppliesMarginY(type)) {
    const marginY = getResponsiveValue(settings, breakpoint, 'marginY') ?? marginFallback
    style.marginTop = `${marginY}px`
    style.marginBottom = `${marginY}px`
  }

  if (!blockSelfAppliesPaddingX(type)) {
    const paddingX = getResponsiveValue(settings, breakpoint, 'paddingX') ?? 0
    style.paddingLeft = `${paddingX}px`
    style.paddingRight = `${paddingX}px`

    // The wrapper is full viewport width, but its horizontal padding insets the
    // block's own background-bearing root, leaving side gaps. Painting the block
    // background on the wrapper instead lets it run edge-to-edge (full-bleed)
    // while the content stays inset by the padding / its own max-width.
    // Self-padded blocks already render a full-width background, so are skipped.
    const background = settings.backgroundColor
    if (typeof background === 'string' && background !== '' && background !== 'transparent') {
      style.backgroundColor = background
    }
  }

  if (hasResponsiveKey(settings, 'height')) {
    const height = getResponsiveValue(settings, breakpoint, 'height')
    if (height !== undefined && height !== null) {
      style.minHeight = `${height}px`
    }
  }

  return style
}

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
