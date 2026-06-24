import { describe, expect, it } from 'vitest'
import { safePublicUrl } from '../../utils/safeUrl'

describe('safePublicUrl', () => {
  it('allows public and contact URLs', () => {
    expect(safePublicUrl('/shop/')).toBe('/shop/')
    expect(safePublicUrl('#blocks')).toBe('#blocks')
    expect(safePublicUrl('https://example.com')).toBe('https://example.com')
    expect(safePublicUrl('tel:+15550100')).toBe('tel:+15550100')
    expect(safePublicUrl('mailto:hello@example.com')).toBe('mailto:hello@example.com')
  })

  it('rejects executable and malformed protocols', () => {
    expect(safePublicUrl('javascript:alert(1)')).toBe('#')
    expect(safePublicUrl('data:text/html,test')).toBe('#')
    expect(safePublicUrl('//untrusted.example/path')).toBe('#')
    expect(safePublicUrl('not a url')).toBe('#')
  })
})
