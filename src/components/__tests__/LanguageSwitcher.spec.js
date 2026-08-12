import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import LanguageSwitcher from '../common/LanguageSwitcher.vue'

const resolvedItems = [
  {
    code: 'en-US',
    label: 'English (United States)',
    html_lang: 'en-US',
    direction: 'ltr',
    short: 'EN',
    url: 'https://example.test/about/',
    current: true,
  },
  {
    code: 'es-MX',
    label: 'Español (México)',
    html_lang: 'es-MX',
    direction: 'ltr',
    short: 'ES',
    url: 'https://example.test/es/acerca-de/',
    current: false,
  },
  {
    code: 'ar',
    label: 'العربية',
    html_lang: 'ar',
    direction: 'rtl',
    short: 'AR',
    url: 'https://example.test/ar/about/',
    current: false,
  },
]

function setServerData(data) {
  window.dsfFrontendData = data
}

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    setServerData({ languageSwitcher: resolvedItems })
  })

  afterEach(() => {
    delete window.dsfFrontendData
    delete window.dsfEditorData
  })

  it('renders only what the server resolved, never a synthesized URL', () => {
    const wrapper = mount(LanguageSwitcher)
    const links = wrapper.findAll('a')

    expect(links).toHaveLength(2)
    expect(links[0].attributes('href')).toBe('https://example.test/es/acerca-de/')
    expect(links[1].attributes('href')).toBe('https://example.test/ar/about/')
  })

  it('announces the current language instead of linking to the same page', () => {
    const wrapper = mount(LanguageSwitcher)
    const current = wrapper.find('.dsf-language-switcher__current')

    expect(current.exists()).toBe(true)
    expect(current.attributes('aria-current')).toBe('true')
    expect(current.attributes('lang')).toBe('en-US')
    expect(wrapper.find('a[href="https://example.test/about/"]').exists()).toBe(false)
  })

  it('carries language and direction on every target', () => {
    const wrapper = mount(LanguageSwitcher, { props: { variant: 'list' } })
    const arabic = wrapper.find('a[hreflang="ar"]')

    expect(arabic.attributes('dir')).toBe('rtl')
    expect(wrapper.find('a[hreflang="es-MX"]').attributes('dir')).toBe('ltr')
  })

  it('renders nothing when there is nowhere to switch to', () => {
    setServerData({ languageSwitcher: [resolvedItems[0]] })
    expect(mount(LanguageSwitcher).find('nav').exists()).toBe(false)

    setServerData({ languageSwitcher: [] })
    expect(mount(LanguageSwitcher).find('nav').exists()).toBe(false)

    setServerData({})
    expect(mount(LanguageSwitcher).find('nav').exists()).toBe(false)
  })

  it('opens and closes as a keyboard-operable disclosure', async () => {
    const wrapper = mount(LanguageSwitcher, { attachTo: document.body })
    const toggle = wrapper.find('.dsf-language-switcher__toggle')

    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('.dsf-language-switcher__list').isVisible()).toBe(false)

    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('.dsf-language-switcher__list').isVisible()).toBe(true)

    await toggle.trigger('keydown', { key: 'Escape' })
    await nextTick()
    expect(toggle.attributes('aria-expanded')).toBe('false')

    wrapper.unmount()
  })

  it('closes when a pointer lands outside it', async () => {
    const wrapper = mount(LanguageSwitcher, { attachTo: document.body })
    await wrapper.find('.dsf-language-switcher__toggle').trigger('click')
    expect(wrapper.find('.dsf-language-switcher__toggle').attributes('aria-expanded')).toBe('true')

    document.dispatchEvent(new window.MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.find('.dsf-language-switcher__toggle').attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('always shows the list style without a toggle', () => {
    const wrapper = mount(LanguageSwitcher, { props: { variant: 'list' } })

    expect(wrapper.find('.dsf-language-switcher__toggle').exists()).toBe(false)
    expect(wrapper.find('.dsf-language-switcher__list').isVisible()).toBe(true)
  })

  it('honours the label style and falls back for an unknown variant', () => {
    expect(mount(LanguageSwitcher, { props: { labels: 'code' } }).findAll('a')[0].text()).toBe('ES')
    expect(mount(LanguageSwitcher, { props: { labels: 'both' } }).findAll('a')[0].text()).toBe(
      'Español (México) (ES)',
    )
    expect(mount(LanguageSwitcher, { props: { variant: 'compact' } }).findAll('a')[0].text()).toBe('ES')

    const unknown = mount(LanguageSwitcher, { props: { variant: 'carousel' } })
    expect(unknown.find('nav').classes()).toContain('dsf-language-switcher--dropdown')
  })

  it('previews configured languages in the editor and keeps navigation inert', async () => {
    setServerData({
      languageSwitcher: [],
      language: {
        active: true,
        current: 'en-US',
        list: [
          { code: 'en-US', label: 'English (United States)', dir: 'ltr' },
          { code: 'es-MX', label: 'Español (México)', dir: 'ltr' },
        ],
      },
    })

    const wrapper = mount(LanguageSwitcher, { props: { isEditor: true, variant: 'list' } })
    const link = wrapper.find('a')

    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('#')

    const event = new window.MouseEvent('click', { bubbles: true, cancelable: true })
    link.element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })
})
