import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CardColumnsPreview from '../blocks/CardColumnsPreview.vue'
import InlineText from '../common/InlineText.vue'

function mountBlock(settings = {}, isEditor = false, previewMode = 'desktop') {
  return mount(CardColumnsPreview, {
    props: { settings, isEditor, previewMode },
  })
}

describe('CardColumnsPreview', () => {
  it('renders fallback cards when no cards setting exists', () => {
    const wrapper = mountBlock({})
    expect(wrapper.findAll('.dsf-card-columns__card')).toHaveLength(3)
    expect(wrapper.text()).toContain('First Benefit')
  })

  it('renders nothing in the grid for an explicit empty cards array', () => {
    const wrapper = mountBlock({ cards: [] })
    expect(wrapper.findAll('.dsf-card-columns__card')).toHaveLength(0)
  })

  it('drops malformed card entries without crashing', () => {
    const wrapper = mountBlock({ cards: [null, 'nope', 7, { title: 'Real Card' }] })
    const cards = wrapper.findAll('.dsf-card-columns__card')
    expect(cards).toHaveLength(1)
    expect(cards[0].text()).toContain('Real Card')
  })

  it('falls back to sample cards when cards is not an array', () => {
    const wrapper = mountBlock({ cards: 'corrupt' })
    expect(wrapper.findAll('.dsf-card-columns__card')).toHaveLength(3)
  })

  it('renders an icon next to the card title when an icon is set', () => {
    const wrapper = mountBlock({ cards: [{ icon: 'star', title: 'Better Sleep' }] })
    expect(wrapper.find('.dsf-card-columns__card-icon svg').exists()).toBe(true)
    expect(wrapper.find('.dsf-card-columns__card-title').text()).toBe('Better Sleep')
  })

  it('omits the icon wrapper when no icon is chosen', () => {
    const wrapper = mountBlock({ cards: [{ icon: '', title: 'No Icon' }] })
    expect(wrapper.find('.dsf-card-columns__card-icon').exists()).toBe(false)
  })

  it('applies solid card background color', () => {
    const wrapper = mountBlock({
      cards: [{ title: 'Solid', backgroundType: 'solid', backgroundColor: '#EFF6FF' }],
    })
    const style = wrapper.find('.dsf-card-columns__card').attributes('style') || ''
    expect(style).toContain('background: rgb(239, 246, 255)')
  })

  it('applies per-card gradient background', () => {
    const wrapper = mountBlock({
      cards: [{ title: 'Grad', backgroundType: 'gradient', gradientStart: '#111111', gradientEnd: '#222222', gradientDirection: 'left-right' }],
    })
    const style = wrapper.find('.dsf-card-columns__card').attributes('style') || ''
    expect(style).toContain('linear-gradient(to right, rgb(17, 17, 17), rgb(34, 34, 34))')
  })

  it('supports a transparent card background', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Clear', backgroundType: 'transparent', backgroundColor: '#FF0000' }] })
    const style = wrapper.find('.dsf-card-columns__card').attributes('style') || ''
    expect(style).toContain('background: transparent')
  })

  it('applies a section gradient background', () => {
    const wrapper = mountBlock({
      backgroundType: 'gradient',
      gradientStart: '#FFFFFF',
      gradientEnd: '#EEEEEE',
      gradientDirection: 'radial',
    })
    const style = wrapper.find('.dsf-card-columns').attributes('style') || ''
    expect(style).toContain('radial-gradient(circle, rgb(255, 255, 255), rgb(238, 238, 238))')
  })

  it('uses the split header layout class when selected', () => {
    const wrapper = mountBlock({ headerLayout: 'split' })
    expect(wrapper.find('.dsf-card-columns__header--split').exists()).toBe(true)
  })

  it('defaults to centered header for unknown layout values', () => {
    const wrapper = mountBlock({ headerLayout: 'bogus' })
    expect(wrapper.find('.dsf-card-columns__header--split').exists()).toBe(false)
  })

  it('renders the bottom image with the configured height and fit', () => {
    const wrapper = mountBlock({
      imageHeight: 180,
      imageFit: 'contain',
      cards: [{ title: 'Pic', image: 'https://example.com/tub.png' }],
    })
    const media = wrapper.find('.dsf-card-columns__card-media')
    expect(media.exists()).toBe(true)
    expect(media.attributes('style')).toContain('height: 180px')
    expect(media.find('img').attributes('src')).toBe('https://example.com/tub.png')
  })

  it('rejects dangerous image URLs', () => {
    // eslint-disable-next-line no-script-url
    const wrapper = mountBlock({ cards: [{ title: 'XSS', image: 'javascript:alert(1)' }] })
    expect(wrapper.find('.dsf-card-columns__card-media').exists()).toBe(false)
  })

  it('renders an arrow-circle button with an accessible name', () => {
    const wrapper = mountBlock({
      buttonStyle: 'arrow',
      cards: [{ title: 'Hot Spring Spas', showButton: true, buttonUrl: '/hot-spring' }],
    })
    const button = wrapper.find('.dsf-card-columns__card-btn--arrow')
    expect(button.exists()).toBe(true)
    expect(button.attributes('href')).toBe('/hot-spring')
    expect(button.attributes('aria-label')).toBe('Hot Spring Spas')
    expect(button.find('svg').exists()).toBe(true)
  })

  it('renders a text button with optional trailing arrow', () => {
    const wrapper = mountBlock({
      buttonStyle: 'text-arrow',
      cards: [{ title: 'Card', showButton: true, buttonText: 'Explore', buttonUrl: '/explore' }],
    })
    const button = wrapper.find('.dsf-card-columns__card-btn--text')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('Explore')
    expect(button.find('svg').exists()).toBe(true)
    expect(wrapper.find('.dsf-card-columns__card-btn--arrow').exists()).toBe(false)
  })

  it('hides the button when showButton is off', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Quiet', showButton: false, buttonUrl: '/x' }] })
    expect(wrapper.find('.dsf-card-columns__card-btn').exists()).toBe(false)
  })

  it('sanitizes dangerous button URLs to #', () => {
    // eslint-disable-next-line no-script-url
    const wrapper = mountBlock({ cards: [{ title: 'Bad', showButton: true, buttonUrl: 'javascript:alert(1)' }] })
    expect(wrapper.find('.dsf-card-columns__card-btn--arrow').attributes('href')).toBe('#')
  })

  it('prevents navigation for button clicks in the editor', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Card', showButton: true, buttonUrl: '/away' }] }, true)
    const anchor = wrapper.find('.dsf-card-columns__card-btn--arrow').element
    // dispatchEvent returns false when a handler called preventDefault().
    const notPrevented = anchor.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    expect(notPrevented).toBe(false)
  })

  it('does not block button clicks on the frontend', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Card', showButton: true, buttonUrl: '#next-section' }] }, false)
    const anchor = wrapper.find('.dsf-card-columns__card-btn--arrow').element
    const notPrevented = anchor.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    expect(notPrevented).toBe(true)
  })

  it('applies responsive padding for the active preview mode', () => {
    const settings = {
      padding: 60,
      paddingX: 24,
      responsive: { desktop: { padding: 60, paddingX: 24 }, mobile: { padding: 20, paddingX: 10 } },
    }
    const desktop = mountBlock(settings, false, 'desktop')
    expect(desktop.find('.dsf-card-columns').attributes('style')).toContain('padding: 60px 24px')
    const mobile = mountBlock(settings, false, 'mobile')
    expect(mobile.find('.dsf-card-columns').attributes('style')).toContain('padding: 20px 10px')
  })

  it('applies the responsive gap and column count to the grid', () => {
    const wrapper = mountBlock({ columns: '5', gap: 32 })
    const style = wrapper.find('.dsf-card-columns__grid').attributes('style') || ''
    expect(style).toContain('--columns: 5')
    expect(style).toContain('gap: 32px')
  })

  it('clamps malformed card dimension values to safe defaults', () => {
    const wrapper = mountBlock({ cardMinHeight: 'huge', cardPadding: 9999, cardRadius: -5, cards: [{ title: 'C' }] })
    const style = wrapper.find('.dsf-card-columns__card').attributes('style') || ''
    expect(style).toContain('min-height: 380px')
    expect(style).toContain('padding: 48px')
    expect(style).toContain('border-radius: 0px')
  })

  it('applies the section-level color settings', () => {
    const wrapper = mountBlock({
      titleColor: '#101010',
      cardTitleColor: '#202020',
      buttonColor: '#303030',
      buttonTextColor: '#404040',
      cards: [{ title: 'Card', showButton: true, buttonUrl: '/go' }],
    })
    expect(wrapper.find('.dsf-card-columns__title').attributes('style')).toContain('color: rgb(16, 16, 16)')
    expect(wrapper.find('.dsf-card-columns__card-title').attributes('style')).toContain('color: rgb(32, 32, 32)')
    const buttonStyle = wrapper.find('.dsf-card-columns__card-btn--arrow').attributes('style') || ''
    expect(buttonStyle).toContain('background-color: rgb(48, 48, 48)')
    expect(buttonStyle).toContain('color: rgb(64, 64, 64)')
  })

  it('left-aligns card content when contentAlign is left', () => {
    const wrapper = mountBlock({ contentAlign: 'left', cards: [{ title: 'C' }] })
    expect(wrapper.find('.dsf-card-columns__card--left').exists()).toBe(true)
  })

  it('supports six columns', () => {
    const wrapper = mountBlock({ columns: '6' })
    expect(wrapper.find('.dsf-card-columns__grid').attributes('style')).toContain('--columns: 6')
  })

  it('renders the overlay layout with full background image and scrim', () => {
    const wrapper = mountBlock({
      cardLayout: 'overlay',
      cards: [{ title: 'Accessories', image: 'https://example.com/deck.jpg' }],
    })
    const card = wrapper.find('.dsf-card-columns__card')
    expect(card.classes()).toContain('dsf-card-columns__card--overlay')
    expect(card.find('.dsf-card-columns__card-bg img').attributes('src')).toBe('https://example.com/deck.jpg')
    expect(card.find('.dsf-card-columns__card-scrim').exists()).toBe(true)
    // The bottom-image element must not render in overlay mode.
    expect(card.find('.dsf-card-columns__card-media').exists()).toBe(false)
  })

  it('builds the scrim gradient from strength and height settings', () => {
    const wrapper = mountBlock({
      cardLayout: 'overlay',
      overlayStrength: 80,
      overlayHeight: 40,
      cards: [{ title: 'Services', image: 'https://example.com/van.jpg' }],
    })
    const style = wrapper.find('.dsf-card-columns__card-scrim').attributes('style') || ''
    expect(style).toContain('rgba(0, 0, 0, 0.8) 0%')
    expect(style).toContain('rgba(0, 0, 0, 0) 40%')
  })

  it('uses the overlay text color for the card title in overlay mode', () => {
    const wrapper = mountBlock({
      cardLayout: 'overlay',
      overlayTextColor: '#FAFAFA',
      cardTitleColor: '#111111',
      cards: [{ title: 'Overlaid' }],
    })
    expect(wrapper.find('.dsf-card-columns__card-title').attributes('style')).toContain('color: rgb(250, 250, 250)')
  })

  it('omits the background layer for overlay cards without an image', () => {
    const wrapper = mountBlock({ cardLayout: 'overlay', cards: [{ title: 'No Image' }] })
    expect(wrapper.find('.dsf-card-columns__card-bg').exists()).toBe(false)
    expect(wrapper.find('.dsf-card-columns__card-scrim').exists()).toBe(true)
  })

  it('keeps the standard layout when cardLayout is unknown', () => {
    const wrapper = mountBlock({ cardLayout: 'bogus', cards: [{ title: 'C', image: 'https://example.com/a.png' }] })
    expect(wrapper.find('.dsf-card-columns__card--overlay').exists()).toBe(false)
    expect(wrapper.find('.dsf-card-columns__card-media').exists()).toBe(true)
  })

  it('renders a custom icon image when iconType is custom', () => {
    const wrapper = mountBlock({
      cards: [{ iconType: 'custom', customIcon: 'https://example.com/icon.svg', title: 'Custom' }],
    })
    const img = wrapper.find('.dsf-card-columns__card-icon-img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/icon.svg')
  })

  it('rejects dangerous custom icon URLs and hides the icon', () => {
    // eslint-disable-next-line no-script-url
    const wrapper = mountBlock({ cards: [{ iconType: 'custom', customIcon: 'javascript:alert(1)', title: 'Bad' }] })
    expect(wrapper.find('.dsf-card-columns__card-icon').exists()).toBe(false)
  })

  it('hides the icon when iconType is none even if a preset name is set', () => {
    const wrapper = mountBlock({ cards: [{ iconType: 'none', icon: 'star', title: 'Hidden' }] })
    expect(wrapper.find('.dsf-card-columns__card-icon').exists()).toBe(false)
  })

  it('still renders legacy cards (icon set, no iconType) as preset icons', () => {
    const wrapper = mountBlock({ cards: [{ icon: 'star', title: 'Legacy' }] })
    expect(wrapper.find('.dsf-card-columns__card-icon svg').exists()).toBe(true)
  })

  it('makes card title and description contenteditable in the editor', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Editable', description: 'Text' }] }, true)
    expect(wrapper.find('.dsf-card-columns__card-title').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-card-columns__card-description').attributes('contenteditable')).toBe('true')
  })

  it('does not make card text editable on the frontend', () => {
    const wrapper = mountBlock({ cards: [{ title: 'Static', description: 'Text' }] }, false)
    expect(wrapper.find('.dsf-card-columns__card-title').attributes('contenteditable')).toBe('false')
  })

  it('writes inline card title edits back into the settings object', async () => {
    const settings = { cards: [{ title: 'Old Title', description: 'Old Desc' }] }
    const wrapper = mountBlock(settings, true)
    const inlineFields = wrapper
      .findAllComponents(InlineText)
      .filter((field) => field.classes().includes('dsf-card-columns__card-title'))
    expect(inlineFields).toHaveLength(1)
    await inlineFields[0].vm.$emit('update:modelValue', 'New Title')
    expect(settings.cards[0].title).toBe('New Title')
  })

  it('shows empty-card text placeholders only in the editor', () => {
    const editor = mountBlock({ cards: [{ title: '', description: '' }] }, true)
    expect(editor.find('.dsf-card-columns__card-title').exists()).toBe(true)
    expect(editor.find('.dsf-card-columns__card-description').exists()).toBe(true)

    const frontend = mountBlock({ cards: [{ title: '', description: '' }] }, false)
    expect(frontend.find('.dsf-card-columns__card-title').exists()).toBe(false)
    expect(frontend.find('.dsf-card-columns__card-description').exists()).toBe(false)
  })
})
