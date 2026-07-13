import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { normalizeAnchorId, blockAnchorId } from '../../utils/anchor.js'
import FrontendApp from '../../frontend/FrontendApp.vue'

describe('normalizeAnchorId', () => {
  it('slugifies spaces, case, and stray characters', () => {
    expect(normalizeAnchorId('Our Pricing!')).toBe('our-pricing')
    expect(normalizeAnchorId('  Contact_Us  ')).toBe('contact-us')
    expect(normalizeAnchorId('a---b')).toBe('a-b')
  })

  it('prefixes a leading digit so the id is a valid selector', () => {
    expect(normalizeAnchorId('2024 plans')).toBe('s-2024-plans')
  })

  it('returns empty when nothing usable remains', () => {
    expect(normalizeAnchorId('!!!')).toBe('')
    expect(normalizeAnchorId('')).toBe('')
    expect(normalizeAnchorId(null)).toBe('')
  })

  it('blockAnchorId returns undefined (not "") when unset, so no id attr renders', () => {
    expect(blockAnchorId({})).toBeUndefined()
    expect(blockAnchorId({ anchorId: 'Pricing' })).toBe('pricing')
  })
})

describe('FrontendApp anchor rendering', () => {
  it('renders the sanitized anchor as the block wrapper id, and none when unset', () => {
    const wrapper = mount(FrontendApp, {
      props: {
        blocks: [
          { id: 'block_1', type: 'content', settings: {}, anchorId: 'Our Pricing' },
          { id: 'block_2', type: 'content', settings: {} },
        ],
      },
    })
    const blocks = wrapper.findAll('.dsf-block')
    expect(blocks[0].attributes('id')).toBe('our-pricing')
    expect(blocks[1].attributes('id')).toBeUndefined()
  })
})
