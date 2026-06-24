/**
 * Maps a landing block's color settings onto the theme CSS variables its scoped
 * styles already read, so per-block colors override the page theme. Empty values
 * are omitted so the block inherits the page theme.
 */
export function landingBlockStyle(settings = {}) {
  const style = {}
  const safeColor = (value) => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : ''
  const boundedSpace = (value) => Math.max(0, Math.min(80, Number.parseInt(value, 10) || 0))
  const background = safeColor(settings.backgroundColor)
  const text = safeColor(settings.textColor)
  const accent = safeColor(settings.accentColor)
  const secondary = safeColor(settings.secondaryColor)

  if (background) {
    style['--dsf-theme-background'] = background
    style['--dsf-landing-background'] = background
    // Longhand only: never the `background` shorthand, so a block's CSS-defined
    // layered gradients/overlays (e.g. the explorer glow) survive a color change.
    style.backgroundColor = background
  }
  if (text) {
    style['--dsf-theme-text'] = text
    style['--dsf-landing-text'] = text
    style.color = text
  }
  if (accent) style['--dsf-theme-primary'] = accent
  if (secondary) style['--dsf-theme-secondary'] = secondary

  const paddingX = boundedSpace(settings.paddingX)
  const marginY = boundedSpace(settings.marginY)
  if (paddingX) {
    style.paddingLeft = `${paddingX}px`
    style.paddingRight = `${paddingX}px`
  }
  if (marginY) {
    style.marginTop = `${marginY}px`
    style.marginBottom = `${marginY}px`
  }
  return style
}
