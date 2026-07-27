import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandShowcaseGridPreview from '../blocks/BrandShowcaseGridPreview.vue'

describe('BrandShowcaseGridPreview', () => {
  it('renders a card with its image anchored in the card', () => {
    const wrapper = mount(BrandShowcaseGridPreview, { props: { settings: { cards: [{ title: 'Hot Springs', subtitle: 'Unleash your best self', image: 'https://example.test/spa.png', backgroundColor: '#E8F4FE', textColor: '#111111' }] } } })
    expect(wrapper.find('.dsf-brand-showcase-grid__card').text()).toContain('Hot Springs')
    expect(wrapper.find('.dsf-brand-showcase-grid__image').attributes('src')).toBe('https://example.test/spa.png')
  })

  it('uses an H2 heading with a paragraph for the split intro', () => {
    const wrapper = mount(BrandShowcaseGridPreview, { props: { settings: { title: 'Heading', description: 'Paragraph copy' } } })
    expect(wrapper.find('.dsf-brand-showcase-grid__title').element.tagName).toBe('H2')
    expect(wrapper.find('.dsf-brand-showcase-grid__description').element.tagName).toBe('P')
  })

  it('uses safe color fallbacks and rejects executable links', () => {
    const wrapper = mount(BrandShowcaseGridPreview, { props: { settings: { cards: [{ title: 'Safe', url: 'javascript:alert(1)', backgroundColor: 'url(javascript:alert(1))', textColor: 'red' }] } } })
    const card = wrapper.find('.dsf-brand-showcase-grid__card')
    expect(card.element.tagName).toBe('ARTICLE')
    expect(card.attributes('style')).toContain('background-color: rgb(243, 244, 246)')
  })

  it('applies the card text color directly to titles and subtitles', () => {
    const wrapper = mount(BrandShowcaseGridPreview, { props: { settings: { cards: [{ title: 'Dark card', subtitle: 'Readable copy', backgroundColor: '#1B1B1B', textColor: '#FFFFFF' }] } } })
    expect(wrapper.find('.dsf-brand-showcase-grid__copy h3').attributes('style')).toContain('color: rgb(255, 255, 255)')
    expect(wrapper.find('.dsf-brand-showcase-grid__copy p').attributes('style')).toContain('color: rgb(255, 255, 255)')
  })

  it('prevents card navigation in editor mode', () => {
    const wrapper = mount(BrandShowcaseGridPreview, { props: { isEditor: true, settings: { cards: [{ title: 'Preview', url: '/shop' }] } } })
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    wrapper.find('.dsf-brand-showcase-grid__card').element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })
})
