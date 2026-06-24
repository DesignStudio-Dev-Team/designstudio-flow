import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(
  resolve(process.cwd(), 'src/components/blocks/FormEmbedPreview.vue'),
  'utf8',
)

describe('FormEmbedPreview', () => {
  it('keeps Gravity Forms choice inputs visible alongside theme pseudo controls', () => {
    expect(source).toContain('.gchoice > input[type="checkbox"]')
    expect(source).toContain('width: 25px !important')
    expect(source).toContain('appearance: auto !important')
    expect(source).toContain('accent-color: #aaa !important')
    expect(source).toContain('flex: 0 0 25px !important')
  })
})
