import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FooterCommercePreview from '../blocks/FooterCommercePreview.vue'

const settings = {
  showFeatures: true,
  features: [
    { icon: 'zap', title: 'Fast Delivery', description: 'Ships in 24h.' },
    { icon: 'shield-check', title: 'Guarantee', description: 'Money back.' },
  ],
  logoText: 'Acme',
  brandText: 'We make things.',
  socialLabel: 'Follow us',
  socialLinks: [{ label: 'Facebook', url: '#' }, { label: 'Twitter', url: '#' }],
  column1Heading: 'Shop',
  column1Links: [{ label: 'New Arrivals', url: '/new' }, { label: 'Best Sellers', url: '/best' }],
  column2Heading: 'Support',
  column2Links: [{ label: 'FAQs', url: '/faq' }],
  showNewsletter: true,
  newsletterHeading: 'Subscribe',
  newsletterButton: 'Join',
  showLocale: true,
  localeText: 'English',
  currencyText: 'USD',
  copyrightText: '© 2025 Acme.',
  showPayments: true,
  payments: [{ name: 'Visa', logo: '' }, { name: 'PayPal', logo: 'https://example.com/pp.png' }],
}

describe('Footer Commerce', () => {
  it('renders the features/trust bar', () => {
    const wrapper = mount(FooterCommercePreview, { props: { settings, isEditor: true } })
    const features = wrapper.findAll('.dsf-fcom__feature')
    expect(features).toHaveLength(2)
    expect(features[0].text()).toContain('Fast Delivery')
    expect(features[0].text()).toContain('Ships in 24h.')
  })

  it('renders brand, social icons, and the link columns', () => {
    const wrapper = mount(FooterCommercePreview, { props: { settings, isEditor: true } })
    expect(wrapper.find('.dsf-fcom__brand').text()).toContain('Acme')
    expect(wrapper.findAll('.dsf-fcom__social')).toHaveLength(2)
    const cols = wrapper.findAll('.dsf-fcom__col')
    expect(cols[0].text()).toContain('Shop')
    expect(cols[0].text()).toContain('New Arrivals')
    expect(cols[1].text()).toContain('FAQs')
  })

  it('renders the newsletter form and the bottom bar', () => {
    const wrapper = mount(FooterCommercePreview, { props: { settings, isEditor: true } })
    expect(wrapper.find('.dsf-fcom__news-form input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('.dsf-fcom__news-btn').text()).toBe('Join')
    expect(wrapper.find('.dsf-fcom__copyright').text()).toContain('© 2025 Acme.')
    expect(wrapper.findAll('.dsf-fcom__locale')).toHaveLength(2)
    const pays = wrapper.findAll('.dsf-fcom__pay')
    expect(pays[0].text()).toContain('Visa')
    expect(wrapper.find('.dsf-fcom__pay img').attributes('src')).toBe('https://example.com/pp.png')
  })

  it('hides sections when their toggles are off', () => {
    const wrapper = mount(FooterCommercePreview, { props: { settings: { ...settings, showFeatures: false, showNewsletter: false, showPayments: false }, isEditor: true } })
    expect(wrapper.find('.dsf-fcom__features').exists()).toBe(false)
    expect(wrapper.find('.dsf-fcom__news').exists()).toBe(false)
    expect(wrapper.find('.dsf-fcom__payments').exists()).toBe(false)
  })

  it('embeds a form instead of the email field when the newsletter source is a DSF form', () => {
    const wrapper = mount(FooterCommercePreview, {
      props: { settings: { ...settings, newsletterSource: 'dsf', newsletterFormId: '7' }, isEditor: true },
    })
    // The simple email field is replaced by the embedded form renderer.
    expect(wrapper.find('.dsf-fcom__news-form').exists()).toBe(false)
    expect(wrapper.find('.dsf-fcom__news-embed').exists()).toBe(true)
    expect(wrapper.find('.dsf-form-embed-preview').exists()).toBe(true)
  })

  it('leaves the newsletter form actionless in the editor but wires it on the frontend', () => {
    const editor = mount(FooterCommercePreview, { props: { settings: { ...settings, newsletterAction: 'https://example.com/subscribe' }, isEditor: true } })
    expect(editor.find('.dsf-fcom__news-form').attributes('action')).toBeUndefined()

    const front = mount(FooterCommercePreview, { props: { settings: { ...settings, newsletterAction: 'https://example.com/subscribe' }, isEditor: false } })
    expect(front.find('.dsf-fcom__news-form').attributes('action')).toBe('https://example.com/subscribe')
  })
})
