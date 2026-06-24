import { describe, expect, it } from 'vitest'
import { canAddTemplateBlock, isSingleBlockTemplate, normalizeTemplateBlocks } from '../../utils/templateBlockRules'

describe('template block rules', () => {
  const blocks = [{ id: 'first' }, { id: 'second' }]

  it('limits header templates to the first block', () => {
    expect(isSingleBlockTemplate('dsf_layout', 'header')).toBe(true)
    expect(normalizeTemplateBlocks(blocks, 'dsf_layout', 'header')).toEqual([{ id: 'first' }])
    expect(canAddTemplateBlock(blocks, 'dsf_layout', 'header')).toBe(false)
  })

  it('allows a header to be added only while the template is empty', () => {
    expect(canAddTemplateBlock([], 'dsf_layout', 'header')).toBe(true)
  })

  it('does not limit pages or footer templates', () => {
    expect(normalizeTemplateBlocks(blocks, 'page', 'header')).toHaveLength(2)
    expect(normalizeTemplateBlocks(blocks, 'dsf_layout', 'footer')).toHaveLength(2)
    expect(canAddTemplateBlock(blocks, 'dsf_layout', 'footer')).toBe(true)
  })
})
