import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'

const { useLandingMotionSpy } = vi.hoisted(() => ({ useLandingMotionSpy: vi.fn() }))

vi.mock('../../utils/useLandingMotion', () => ({
  useLandingMotion: useLandingMotionSpy,
}))

import LandingShowcaseHeroPreview from '../blocks/LandingShowcaseHeroPreview.vue'

const WORDS = ['WordPress site', 'page visually', 'online store', 'next campaign', 'site securely', 'client site']

const TILES = [
  { label: 'Design included', url: '#ready', icon: 'wand', iconImage: '' },
  { label: 'Visual editor', url: '#editor', icon: 'mouse-pointer', iconImage: '' },
  { label: 'WooCommerce', url: '#woocommerce', icon: 'store', iconImage: '' },
  { label: 'Forms & growth', url: '#engagement', icon: 'mail', iconImage: '' },
  { label: 'Security', url: '#security', icon: 'shield-check', iconImage: '' },
  { label: 'For agencies', url: '#audience', icon: 'briefcase', iconImage: '' },
]

const DEFAULT_SETTINGS = {
  eyebrow: 'THE VISUAL WORDPRESS SYSTEM',
  title: 'Design your',
  rotatingWords: WORDS.join(', '),
  tagline: 'Design pages, theme styles, WooCommerce stores, layouts, campaigns, and forms in one visual builder.',
  primaryText: 'Experience DSFlow',
  primaryUrl: '#get-dsflow',
  secondaryText: 'Browse 40+ blocks',
  secondaryUrl: '#blocks',
  chip1: '40+ designed blocks',
  chip2: 'WooCommerce ready',
  chip3: 'Built with security in mind',
  tiles: TILES,
}

const mountedWrappers = []

function installMatchMedia(initialMatches) {
  const listeners = new Set()
  const query = {
    matches: initialMatches,
    media: '(prefers-reduced-motion: reduce)',
    addEventListener: vi.fn((event, listener) => event === 'change' && listeners.add(listener)),
    removeEventListener: vi.fn((event, listener) => event === 'change' && listeners.delete(listener)),
    addListener: vi.fn((listener) => listeners.add(listener)),
    removeListener: vi.fn((listener) => listeners.delete(listener)),
  }
  vi.stubGlobal('matchMedia', vi.fn(() => query))

  return {
    query,
    setMatches(matches) {
      query.matches = matches
      listeners.forEach((listener) => listener({ matches, media: query.media }))
    },
  }
}

function mountHero(settings = {}, options = {}) {
  const wrapper = mount(LandingShowcaseHeroPreview, {
    props: {
      settings: { ...DEFAULT_SETTINGS, ...settings },
      isEditor: options.isEditor || false,
      blockId: 'sh1',
      previewMode: 'desktop',
    },
    global: {
      provide: options.renderMode ? { dsfRenderMode: options.renderMode } : {},
    },
  })
  mountedWrappers.push(wrapper)
  return wrapper
}

function activeTileIndex(wrapper) {
  return wrapper.findAll('.dsf-showcase-tile').findIndex((tile) => tile.classes().includes('is-active'))
}

function activeWordText(wrapper) {
  return wrapper.findAll('.dsf-showcase-hero__word-line').map((line) => line.text()).join(' ')
}

beforeEach(() => {
  vi.useRealTimers()
  installMatchMedia(true)
  useLandingMotionSpy.mockClear()
})

afterEach(() => {
  while (mountedWrappers.length) mountedWrappers.pop().unmount()
  vi.useRealTimers()
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('LandingShowcaseHeroPreview', () => {
  it('states the product clearly before the user scrolls', () => {
    const wrapper = mountHero()
    expect(wrapper.text()).toContain('THE VISUAL WORDPRESS SYSTEM')
    expect(wrapper.find('.dsf-showcase-hero__lead').text()).toBe('Design your')
    expect(wrapper.find('.dsf-showcase-hero__tagline').text()).toContain('WooCommerce stores')
    expect(wrapper.find('.dsf-showcase-hero__tagline').text()).toContain('one visual builder')
    expect(wrapper.find('.dsf-hero-button--primary').attributes('href')).toBe('#get-dsflow')
    expect(wrapper.findAll('.dsf-showcase-hero__chip')).toHaveLength(3)
  })

  it('keeps a stable, complete accessible headline without a repeating live region', () => {
    const wrapper = mountHero()
    const accessible = wrapper.find('.dsf-showcase-hero__sr-only').text()
    WORDS.forEach((word) => expect(accessible).toContain(word))
    expect(wrapper.find('[aria-live]').exists()).toBe(false)
    expect(wrapper.find('.dsf-showcase-hero__visual-title').attributes('aria-hidden')).toBe('true')
  })

  it('shows the first of six unique phrases statically under reduced motion', () => {
    const wrapper = mountHero()
    expect(activeWordText(wrapper)).toBe(`${WORDS[0]}.`)
    expect(wrapper.findAll('.dsf-showcase-tile__label small').map((item) => item.text())).toEqual(WORDS)
    expect(wrapper.find('.dsf-showcase-hero__cycle-toggle').exists()).toBe(false)
    expect(WORDS.every((word) => word.trim().split(/\s+/).length === 2)).toBe(true)
    expect(wrapper.findAll('.dsf-showcase-hero__word-line').map((item) => item.text())).toEqual(['WordPress', 'site.'])
  })

  it('repairs legacy five-phrase data with a deterministic six-tile mapping', () => {
    const wrapper = mountHero({ rotatingWords: 'storefront, blog, checkout, landing page, whole site' })
    expect(wrapper.findAll('.dsf-showcase-tile__label small').map((item) => item.text())).toEqual(WORDS)
    expect(activeWordText(wrapper)).toBe(`${WORDS[0]}.`)
  })

  it('repairs blank and duplicate phrases instead of allowing the tile states to drift', () => {
    const wrapper = mountHero({ rotatingWords: 'shopping, shopping, , page' })
    const labels = wrapper.findAll('.dsf-showcase-tile__label small').map((item) => item.text())
    expect(labels).toEqual(WORDS)
    expect(new Set(labels.map((label) => label.toLowerCase())).size).toBe(6)
  })

  it('repairs a six-item list when any phrase is not exactly two words', () => {
    const wrapper = mountHero({ rotatingWords: 'shopping, brand typography, image gallery, next campaign, secure publishing, agency workflow' })
    expect(wrapper.findAll('.dsf-showcase-tile__label small').map((item) => item.text())).toEqual(WORDS)
  })

  it('honors an exact one-to-one list of six custom phrases', () => {
    const custom = ['shopping experience', 'brand typography', 'image gallery', 'next campaign', 'secure publishing', 'agency workflow']
    const wrapper = mountHero({ rotatingWords: custom.join(', ') })
    expect(wrapper.findAll('.dsf-showcase-tile__label small').map((item) => item.text())).toEqual(custom)
  })

  it('drives all six words and matching tiles from the same timed index', async () => {
    vi.useFakeTimers()
    installMatchMedia(false)
    const wrapper = mountHero()

    for (let index = 0; index < WORDS.length; index += 1) {
      expect(activeWordText(wrapper)).toBe(`${WORDS[index]}.`)
      expect(activeTileIndex(wrapper)).toBe(index)
      expect(wrapper.findAll('.dsf-showcase-tile.is-active')).toHaveLength(1)
      await vi.advanceTimersByTimeAsync(2800)
      await nextTick()
    }

    expect(activeWordText(wrapper)).toBe(`${WORDS[0]}.`)
    expect(activeTileIndex(wrapper)).toBe(0)
  })

  it('keeps the automatic rotation and progress dots without rendering a pause button', async () => {
    vi.useFakeTimers()
    installMatchMedia(false)
    const wrapper = mountHero()
    await nextTick()
    expect(wrapper.find('.dsf-showcase-hero__cycle-toggle').exists()).toBe(false)
    expect(wrapper.findAll('.dsf-showcase-hero__cycle-dots i')).toHaveLength(6)
    await vi.advanceTimersByTimeAsync(2800)
    await nextTick()
    expect(activeTileIndex(wrapper)).toBe(1)
  })

  it('responds when the operating-system motion preference changes', async () => {
    vi.useFakeTimers()
    const media = installMatchMedia(false)
    const wrapper = mountHero()

    await vi.advanceTimersByTimeAsync(2800)
    await nextTick()
    expect(activeTileIndex(wrapper)).toBe(1)

    media.setMatches(true)
    await nextTick()
    expect(activeTileIndex(wrapper)).toBe(0)
    expect(wrapper.find('.dsf-showcase-hero__cycle').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(5600)
    expect(activeTileIndex(wrapper)).toBe(0)

    media.setMatches(false)
    await nextTick()
    expect(wrapper.find('.dsf-showcase-hero__cycle').exists()).toBe(true)
    await vi.advanceTimersByTimeAsync(2800)
    await nextTick()
    expect(activeTileIndex(wrapper)).toBe(1)
  })

  it('renders a dedicated product scene for every one of the six hub destinations', () => {
    const wrapper = mountHero()
    const tiles = wrapper.findAll('.dsf-showcase-tile')
    const scenes = ['design', 'builder', 'commerce', 'forms', 'security', 'agency']
    scenes.forEach((scene, index) => expect(tiles[index].classes()).toContain(`dsf-showcase-tile--${scene}`))
    expect(tiles[0].find('.dsf-scene-design__spacing').exists()).toBe(true)
    expect(tiles[1].find('.dsf-scene-builder__dock').exists()).toBe(true)
    expect(tiles[2].find('.dsf-scene-commerce__buy').text()).toBe('Add to cart')
    expect(tiles[3].find('.dsf-scene-forms__success').text()).toContain('Message sent')
    expect(tiles[4].find('.dsf-scene-security__checks').exists()).toBe(true)
    expect(tiles[5].find('.dsf-scene-agency__reusable').exists()).toBe(true)
  })

  it('allows only safe custom scene images and falls back to the built-in scene otherwise', () => {
    const safe = mountHero({ tiles: [{ label: 'Shots', url: '#x', icon: 'store', iconImage: 'https://example.com/shot.png' }] })
    expect(safe.find('.dsf-showcase-tile__image img').attributes('src')).toBe('https://example.com/shot.png')
    expect(safe.find('.dsf-showcase-tile__scene').exists()).toBe(false)

    const unsafe = mountHero({ tiles: [{ label: 'Unsafe', url: '#x', icon: 'store', iconImage: 'javascript:alert(1)' }] })
    expect(unsafe.find('.dsf-showcase-tile__image').exists()).toBe(false)
    expect(unsafe.find('.dsf-showcase-tile__scene').exists()).toBe(true)
  })

  it('filters malformed tiles, caps the hub at six, and hides empty chips', () => {
    const many = Array.from({ length: 12 }, (_, index) => ({ label: `T${index}`, url: '#a', icon: 'star', iconImage: '' }))
    const wrapper = mountHero({ tiles: ['bad', null, {}, ...many], chip2: '', chip3: '   ' })
    expect(wrapper.findAll('.dsf-showcase-tile')).toHaveLength(6)
    expect(wrapper.findAll('.dsf-showcase-hero__chip')).toHaveLength(1)
  })

  it('rejects dangerous destination URLs', () => {
    const wrapper = mountHero({ tiles: [{ label: 'Unsafe', url: 'javascript:alert(1)', icon: 'star', iconImage: '' }] })
    expect(wrapper.find('.dsf-showcase-tile').attributes('href')).toBe('#')
  })

  it('prevents every hero navigation action in the editor', () => {
    installMatchMedia(false)
    const wrapper = mountHero({}, { isEditor: true })
    ;['.dsf-showcase-tile', '.dsf-hero-button--primary', '.dsf-hero-button--secondary'].forEach((selector) => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true })
      wrapper.find(selector).element.dispatchEvent(event)
      expect(event.defaultPrevented).toBe(true)
    })
  })

  it('keeps frontend destination links interactive', () => {
    const wrapper = mountHero()
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    wrapper.find('.dsf-showcase-tile').element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
  })

  it('keeps the why-dsflow anchor id for the floating page navigation', () => {
    const wrapper = mountHero()
    expect(wrapper.find('section').attributes('id')).toBe('why-dsflow')
    expect(wrapper.find('.dsf-showcase-hero__mosaic').attributes('aria-label')).toBe('Page sections')
  })

  it('does not create a rotation timer in reduced-motion, editor, or snapshot modes', () => {
    const intervalSpy = vi.spyOn(window, 'setInterval')
    mountHero()
    expect(intervalSpy).not.toHaveBeenCalled()

    installMatchMedia(false)
    mountHero({}, { isEditor: true })
    mountHero({}, { renderMode: 'snapshot' })
    expect(intervalSpy).not.toHaveBeenCalled()
    expect(useLandingMotionSpy.mock.calls.some(([, disabled]) => disabled === true)).toBe(true)
  })

  it('keeps snapshot output stable even when time advances', async () => {
    vi.useFakeTimers()
    installMatchMedia(false)
    const wrapper = mountHero({}, { renderMode: 'snapshot' })
    const before = wrapper.html()
    await vi.advanceTimersByTimeAsync(20000)
    await nextTick()
    expect(wrapper.html()).toBe(before)
    expect(useLandingMotionSpy).toHaveBeenLastCalledWith(expect.anything(), true)
  })

  it('clears the exact live interval and motion listener on unmount', () => {
    installMatchMedia(false)
    const intervalSpy = vi.spyOn(window, 'setInterval')
    const clearSpy = vi.spyOn(window, 'clearInterval')
    const wrapper = mountHero()
    const intervalId = intervalSpy.mock.results[0].value
    const mediaQuery = window.matchMedia.mock.results[0].value

    wrapper.unmount()
    expect(clearSpy).toHaveBeenCalledWith(intervalId)
    expect(mediaQuery.removeEventListener).toHaveBeenCalledWith('change', expect.any(Function))
  })
})
