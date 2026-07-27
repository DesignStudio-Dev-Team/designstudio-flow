import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(
  resolve(process.cwd(), 'assets/js/forms-builder.js'),
  'utf8',
)

describe('forms builder admin routing', () => {
  it('routes View Entries to the dedicated entries URL', () => {
    const handler = source.slice(
      source.indexOf('function bindEntriesEvents()'),
      source.indexOf('function bindNotificationEvents()'),
    )

    expect(handler).toContain('wpData.entriesUrl')
    expect(handler).not.toContain('wpData.adminListUrl')
  })

  it('keeps the Back button routed to the forms list', () => {
    const handler = source.slice(
      source.indexOf('function bindBackButton()'),
      source.indexOf('function renderShortcode()'),
    )

    expect(handler).toContain('wpData.adminListUrl')
    expect(handler).not.toContain('wpData.entriesUrl')
  })
})
