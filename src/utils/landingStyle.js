/**
 * Maps a landing block's color settings onto the theme CSS variables its scoped
 * styles already read, so per-block colors override the page theme. Empty values
 * are omitted so the block inherits the page theme.
 */
export function landingBlockStyle(settings = {}) {
  const style = {}
  const safeColor = (value) => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : ''
  const background = safeColor(settings.backgroundColor)
  const text = safeColor(settings.textColor)
  const accent = safeColor(settings.accentColor)
  const secondary = safeColor(settings.secondaryColor)
  const eyebrow = safeColor(settings.eyebrowColor)
  const eyebrowLine = safeColor(settings.eyebrowLineColor)
  const buttonBg = safeColor(settings.buttonColor)
  const buttonText = safeColor(settings.buttonTextColor)

  if (background) {
    style['--dsf-theme-background'] = background
    style['--dsf-landing-background'] = background
    // Longhand only: never the `background` shorthand, so a block's CSS-defined
    // layered gradients/overlays (e.g. the explorer glow) survive a color change.
    style.backgroundColor = background
  }
  if (text) {
    // Drive text color ONLY through the theme variable, which each block routes to
    // its body copy / headings (via `--ink`). We deliberately do NOT set a blanket
    // inline `color` on the block root — that would cascade and override every other
    // color (eyebrow, accent, button, links). Those have their own controls.
    style['--dsf-theme-text'] = text
    style['--dsf-landing-text'] = text
  }
  if (accent) style['--dsf-theme-primary'] = accent
  if (secondary) style['--dsf-theme-secondary'] = secondary
  // CTA button background + label get their own override, independent of the
  // accent (which also tints many decorative elements in these blocks).
  if (buttonBg) style['--dsf-button-bg'] = buttonBg
  if (buttonText) style['--dsf-button-text'] = buttonText
  // Eyebrow text + line (the small accent mark) override their CSS fallbacks.
  if (eyebrow) style['--dsf-eyebrow-color'] = eyebrow
  if (eyebrowLine) style['--dsf-eyebrow-line-color'] = eyebrowLine

  // marginY + paddingX are intentionally NOT applied here: the block wrapper
  // (.dsf-block) applies them per breakpoint. Doing it here too would double the
  // spacing, and this util only sees flat (non-responsive) settings — letting
  // the wrapper own them keeps the Desktop/Tablet/Mobile sliders working.
  return style
}
