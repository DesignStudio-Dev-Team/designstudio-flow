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
    expect(css).toContain('--dsf-choice-accent: var(--dsf-theme-primary')
    expect(css).toContain('input[type="radio"]')
    expect(css).toContain('border-radius: 50% !important')
  })

  it('keeps a native control where there is no label to draw on', () => {
    expect(css).toContain('@supports selector(:has(*))')
    expect(css).toContain(':not(:has(+ :is(label, span)))')
    expect(css).toContain('appearance: auto !important')
  })
})
