import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(
  resolve(process.cwd(), 'src/components/blocks/FormEmbedPreview.vue'),
  'utf8',
)

describe('FormEmbedPreview', () => {
  it('leaves choice control styling to form-controls.css so the two cannot fight', () => {
    expect(source).not.toContain('.gchoice > input[type="checkbox"]')
    expect(source).not.toContain('accent-color')
    expect(source).toContain('form-controls.css')
  })
})
