import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const css = readFileSync(
  resolve(process.cwd(), 'assets/css/form-controls.css'),
  'utf8',
)

// Every rule needs ID-level specificity: themes override choice inputs with
// selectors like `#dsf-frontend-app .gfield-choice-input { display: none !important }`
// and `!important` alone does not outrank an ID.
const rules = css
  .replace(/\/\*[\s\S]*?\*\//g, '')
  .split('}')
  .map((chunk) => chunk.split('{')[0].trim())
  .filter((selector) => selector && !selector.startsWith('@'))

describe('form-controls.css', () => {
  it('scopes every rule to a Flow form container with ID-level specificity', () => {
    expect(rules.length).toBeGreaterThan(10)
    rules.forEach((selector) => {
      expect(selector).toContain('#dsf-frontend-app')
      expect(selector).toMatch(/\.gform_wrapper|\.dsf-form\b|\.dsf-form-wrap/)
    })
  })

  it('covers both Gravity Forms and native Flow form markup', () => {
    expect(css).toContain('.gchoice')
    expect(css).toContain('.dsf-form-option')
    expect(css).toContain('.ginput_container_consent')
  })

  it('neutralises theme-drawn control art on the choice label', () => {
    expect(css).toContain('content: none !important')
    expect(css).toContain('transform: none !important')
  })

  it('hides the native input without dropping it out of the tab order', () => {
    expect(css).toContain('clip-path: inset(50%) !important')
    expect(css).toContain('display: inline-block !important')
    expect(css).not.toContain('display: none !important;\n  appearance')
  })

  it('draws the control from theme tokens so it matches the page palette', () => {
    expect(css).toContain('--dsf-form-accent: var(--dsf-theme-primary')
    expect(css).toContain('--dsf-choice-accent: var(--dsf-form-accent)')
    expect(css).toContain('input[type="radio"]')
    expect(css).toContain('border-radius: 50% !important')
  })

  it('restores Gravity Forms buttons that a reset theme strips', () => {
    // A Tailwind-style preflight zeroes background, border and padding on every
    // <button>; with no Gravity Forms CSS loaded, each has to be restated.
    ;['.gform_button', '.gform_next_button', '.gform_previous_button'].forEach((selector) => {
      expect(css).toContain(selector)
    })
    expect(css).toContain('background-color: var(--dsf-btn-accent) !important')
    expect(css).toContain('min-height: var(--dsf-btn-min-height) !important')
    expect(css).toContain('padding: var(--dsf-btn-padding) !important')
    expect(css).toContain('appearance: none !important')
  })

  it('gives Previous a secondary treatment and lays the footer out as a row', () => {
    expect(css).toContain('background-color: var(--dsf-btn-bg) !important')
    expect(css).toContain('color: var(--dsf-btn-accent) !important')
    expect(css).toContain('.gform_page_footer')
    expect(css).toContain('flex-wrap: wrap !important')
  })

  it('draws the AJAX spinner rather than leaving a bare box', () => {
    // Gravity Forms appends <span class="gform-loader"> inside the clicked button
    // and draws the ring in its own CSS; sized but undrawn it reads as a square.
    expect(css).toContain('.gform-loader')
    expect(css).toContain('[id^="gform_ajax_spinner_"]')
    expect(css).toContain('border: 2px solid currentColor !important')
    expect(css).toContain('border-top-color: transparent !important')
    expect(css).toContain('animation: dsf-form-spin 0.6s linear infinite !important')
    expect(css).toContain('@keyframes dsf-form-spin')
    expect(css).toContain('@media (prefers-reduced-motion: reduce)')
  })

  it('only sizes a real spinner image, since it animates itself', () => {
    expect(css).toContain('img:is(.gform_ajax_spinner, [id^="gform_ajax_spinner_"])')
    // The drawn ring must not be applied to an <img>.
    expect(css).toContain(':is(.gform-loader, [id^="gform_ajax_spinner_"]):not(img)')
  })

  it('leaves native Flow form buttons to forms.css', () => {
    // Only the comment may name them; no rule may target them.
    rules.forEach((selector) => {
      expect(selector).not.toContain('.dsf-form-nav__btn')
    })
  })

  it('keeps a native control where there is no label to draw on', () => {
    expect(css).toContain('@supports selector(:has(*))')
    expect(css).toContain(':not(:has(+ :is(label, span)))')
    expect(css).toContain('appearance: auto !important')
  })
})
