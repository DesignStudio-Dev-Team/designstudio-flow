import { describe, it, expect, beforeAll, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LandingRedirectToolPreview from '../blocks/LandingRedirectToolPreview.vue'
import LandingMailToolPreview from '../blocks/LandingMailToolPreview.vue'

beforeAll(() => {
  vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} })))
})

describe('new landing blocks mount without crashing', () => {
  it('redirect tool renders', () => {
    const w = mount(LandingRedirectToolPreview, { props: { settings: { eyebrow: 'X', title: 'T', description: 'D', featureOne: 'a' }, isEditor: false } })
    expect(w.find('.dsf-redir__panel').exists()).toBe(true)
    expect(w.text()).toContain('/old-pricing')
  })
  it('mail tool renders', () => {
    const w = mount(LandingMailToolPreview, { props: { settings: { eyebrow: 'X', title: 'T', description: 'D', featureOne: 'a' }, isEditor: false } })
    expect(w.find('.dsf-mail__panel').exists()).toBe(true)
    expect(w.text()).toContain('Email log')
  })
})
