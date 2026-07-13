import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

vi.mock('../../utils/gsapSetup', () => ({
  ensureGsap: vi.fn(),
  gsap: {
    from: vi.fn(),
    fromTo: vi.fn(),
    to: vi.fn(),
    killTweensOf: vi.fn(),
  },
  ScrollTrigger: {
    create: vi.fn(() => ({ kill: vi.fn() })),
    refresh: vi.fn(),
  },
}))

import LandingDockHeaderPreview from '../blocks/LandingDockHeaderPreview.vue'
import { DSFLOW_DOCK_ICON_NAMES } from '../../utils/dsflowDockIcons'

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('LandingDockHeaderPreview', () => {
  it('renders a bespoke accessible icon for all 16 landing sections', () => {
    const wrapper = mount(LandingDockHeaderPreview, {
      props: { isEditor: true, settings: {} },
    })

    const links = wrapper.findAll('.dsf-dockhdr__link')
    const targets = links.map((link) => link.attributes('href'))
    const icons = wrapper.findAll('.dsf-dockhdr__link [data-dsf-icon]')

    expect(links).toHaveLength(16)
    expect(icons).toHaveLength(16)
    expect(DSFLOW_DOCK_ICON_NAMES).toHaveLength(16)
    expect(targets).toEqual([
      '#why-dsflow', '#blocks', '#ready', '#editor', '#theme', '#woocommerce',
      '#layouts', '#campaigns', '#engagement', '#seo', '#security', '#audience',
      '#workflow', '#redirects', '#mail', '#get-dsflow',
    ])
    expect(wrapper.find('a[href="#redirects"]').attributes('aria-label')).toBe('Redirects')
    expect(wrapper.find('a[href="#mail"]').attributes('aria-label')).toBe('Email delivery')
    expect(icons.every((icon) => icon.attributes('aria-hidden') === 'true')).toBe(true)
    expect(wrapper.find('.dsf-dockhdr__mark .dsf-dockhdr__brand-logo').attributes('src')).toContain('assets/images/dsflow-logo.png')
    expect(wrapper.find('.dsf-dockhdr__mark svg').exists()).toBe(false)
    expect(wrapper.find('.dsf-dockhdr__mobile-current').attributes('href')).toBe('#why-dsflow')
    expect(wrapper.find('.dsf-dockhdr__mobile-current [data-dsf-icon]').attributes('data-dsf-icon')).toBe('dsflow-why')
    expect(wrapper.find('.dsf-dockhdr__mobile-more').attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('.dsf-dockhdr__mobile-more').attributes('aria-controls')).toMatch(/^dsf-dockhdr-menu-/)

    const paintValues = icons.flatMap((icon) => [
      icon.attributes('fill'),
      icon.attributes('stroke'),
      ...icon.findAll('[fill], [stroke]').flatMap((node) => [
        node.attributes('fill'),
        node.attributes('stroke'),
      ]),
    ]).filter(Boolean)
    expect(paintValues.every((value) => ['none', 'currentColor'].includes(value))).toBe(true)
    expect(icons.map((icon) => icon.html()).join('')).not.toMatch(/dsf-icon-accent|#f47c2c/i)
  })

  it('upgrades legacy section presets without overriding deliberate choices', () => {
    const wrapper = mount(LandingDockHeaderPreview, {
      props: {
        isEditor: true,
        settings: {
          navLinks: [
            { label: 'Blocks', url: '#blocks', icon: 'boxes', iconImage: '' },
            { label: 'Security', url: '#security', icon: 'heart', iconImage: '' },
            { label: 'Unsafe', url: 'javascript:alert(1)', icon: 'dsflow-mail', iconImage: 'javascript:alert(2)' },
            { label: 'Image', url: '#mail', icon: 'dsflow-mail', iconImage: 'https://example.com/mail.png' },
          ],
        },
      },
    })

    expect(wrapper.find('a[href="#blocks"] [data-dsf-icon]').attributes('data-dsf-icon')).toBe('dsflow-blocks')
    expect(wrapper.find('a[href="#security"] svg').classes()).toContain('lucide-heart-icon')
    expect(wrapper.find('a[href="#"] [data-dsf-icon]').attributes('data-dsf-icon')).toBe('dsflow-mail')
    expect(wrapper.find('a[href="#"] img').exists()).toBe(false)
    expect(wrapper.find('a[href="#mail"] img').attributes('src')).toBe('https://example.com/mail.png')
  })

  it('shows the current icon while scrolling, then restores the DS Flow logo after idle', async () => {
    vi.useFakeTimers()
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false })))
    Object.defineProperty(window, 'scrollY', { value: 500, configurable: true })

    const editor = mount(LandingDockHeaderPreview, {
      props: {
        isEditor: true,
        settings: { navLinks: [{ label: 'Blocks', url: '#blocks', icon: 'boxes', iconImage: '' }] },
      },
    })
    const editorClick = new MouseEvent('click', { bubbles: true, cancelable: true })
    expect(editor.find('a[href="#blocks"]').element.dispatchEvent(editorClick)).toBe(false)
    editor.unmount()

    const removeListener = vi.spyOn(window, 'removeEventListener')
    const wrapper = mount(LandingDockHeaderPreview, {
      attachTo: document.body,
      props: {
        settings: {
          logoImage: 'https://example.com/dsflow-logo.png',
          navLinks: [
            { label: 'Blocks', url: '#blocks', icon: 'boxes', iconImage: 'https://example.com/blocks.png' },
          ],
        },
      },
    })

    const blocksLink = wrapper.find('a[href="#blocks"]')
    await blocksLink.trigger('click')

    window.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.classes()).toContain('is-collapsed')
    expect(wrapper.find('.dsf-dockhdr__mark').attributes('aria-label')).toBe('Current section: Blocks — Go to Top')
    expect(wrapper.find('.dsf-dockhdr__mark .dsf-dockhdr__brand-logo').exists()).toBe(false)
    expect(wrapper.find('.dsf-dockhdr__mark .dsf-dockhdr__context-img').attributes('src')).toBe('https://example.com/blocks.png')
    expect(wrapper.find('.dsf-dockhdr__body').attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.dsf-dockhdr__body').attributes()).toHaveProperty('inert')

    vi.advanceTimersByTime(300)
    await nextTick()

    expect(wrapper.classes()).not.toContain('is-collapsed')
    expect(wrapper.find('.dsf-dockhdr__body').attributes('aria-hidden')).toBeUndefined()
    expect(wrapper.find('.dsf-dockhdr__body').attributes('inert')).toBeUndefined()
    expect(blocksLink.classes()).toContain('is-current')
    expect(blocksLink.attributes('aria-current')).toBe('true')
    expect(wrapper.find('.dsf-dockhdr__mark').attributes('href')).toBe('#top')
    expect(wrapper.find('.dsf-dockhdr__mark').attributes('aria-label')).toBe('DesignStudio Flow — Go to Top')
    expect(wrapper.find('.dsf-dockhdr__mark .dsf-dockhdr__brand-logo').attributes('src')).toBe('https://example.com/dsflow-logo.png')
    expect(wrapper.find('.dsf-dockhdr__mark svg').exists()).toBe(false)
    expect(wrapper.find('.dsf-dockhdr__mark img[src="https://example.com/blocks.png"]').exists()).toBe(false)

    wrapper.unmount()
    expect(removeListener).toHaveBeenCalledWith('scroll', expect.any(Function))
    expect(removeListener).toHaveBeenCalledWith('resize', expect.any(Function))
  })

  it('falls back to the built-in DS Flow logo for an unsafe configured logo URL', () => {
    const wrapper = mount(LandingDockHeaderPreview, {
      props: {
        isEditor: true,
        settings: { logoImage: 'javascript:alert(1)' },
      },
    })

    expect(wrapper.find('.dsf-dockhdr__brand-logo').attributes('src')).toContain('assets/images/dsflow-logo.png')
  })

  it('shows the current mobile section and discloses every other section', async () => {
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })))
    const wrapper = mount(LandingDockHeaderPreview, {
      attachTo: document.body,
      props: {
        settings: {
          navLinks: [
            { label: 'Why DSFlow', url: '#why-dsflow', icon: 'dsflow-why', iconImage: 'https://example.com/why.png' },
            { label: 'Blocks', url: '#blocks', icon: 'dsflow-blocks', iconImage: 'https://example.com/blocks.png' },
            { label: 'Email delivery', url: '#mail', icon: 'dsflow-mail', iconImage: '' },
          ],
        },
      },
    })

    const more = wrapper.find('.dsf-dockhdr__mobile-more')
    expect(wrapper.find('.dsf-dockhdr__mobile-current img').attributes('src')).toBe('https://example.com/why.png')

    await more.trigger('click')
    expect(more.attributes('aria-expanded')).toBe('true')
    expect(wrapper.findAll('.dsf-dockhdr__mobile-menu-link')).toHaveLength(2)
    expect(wrapper.find('.dsf-dockhdr__mobile-menu-link[href="#why-dsflow"]').exists()).toBe(false)
    expect(wrapper.find('.dsf-dockhdr__mobile-menu-link[href="#blocks"] img').attributes('src')).toBe('https://example.com/blocks.png')

    await wrapper.find('.dsf-dockhdr__mobile-menu-link[href="#blocks"]').trigger('click')
    await nextTick()

    const current = wrapper.find('.dsf-dockhdr__mobile-current')
    expect(more.attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('.dsf-dockhdr__mobile-menu').exists()).toBe(false)
    expect(current.attributes('href')).toBe('#blocks')
    expect(current.attributes('aria-current')).toBe('location')
    expect(current.find('img').attributes('src')).toBe('https://example.com/blocks.png')
    expect(document.activeElement).toBe(current.element)

    await more.trigger('click')
    expect(wrapper.find('.dsf-dockhdr__mobile-menu-link[href="#blocks"]').exists()).toBe(false)
    expect(wrapper.find('.dsf-dockhdr__mobile-menu-link[href="#why-dsflow"] img').attributes('src')).toBe('https://example.com/why.png')

    wrapper.unmount()
  })

  it('closes the mobile section disclosure on Escape and outside pointer input, then cleans up listeners', async () => {
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })))
    const addListener = vi.spyOn(document, 'addEventListener')
    const removeListener = vi.spyOn(document, 'removeEventListener')
    const wrapper = mount(LandingDockHeaderPreview, {
      attachTo: document.body,
      props: { settings: {} },
    })
    const more = wrapper.find('.dsf-dockhdr__mobile-more')
    const pointerHandler = addListener.mock.calls.find(([type, , capture]) => type === 'pointerdown' && capture === true)?.[1]
    const keyHandler = addListener.mock.calls.find(([type]) => type === 'keydown')?.[1]

    await more.trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(more.attributes('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(more.element)

    await more.trigger('click')
    document.body.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }))
    await nextTick()
    expect(more.attributes('aria-expanded')).toBe('false')

    wrapper.unmount()
    expect(pointerHandler).toBeTypeOf('function')
    expect(keyHandler).toBeTypeOf('function')
    expect(removeListener).toHaveBeenCalledWith('pointerdown', pointerHandler, true)
    expect(removeListener).toHaveBeenCalledWith('keydown', keyHandler)
  })
})
