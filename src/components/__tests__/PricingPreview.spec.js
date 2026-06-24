import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PricingPreview from '../blocks/PricingPreview.vue'

describe('PricingPreview', () => {
  const settings = {
    eyebrow: 'Pricing',
    title: 'Pricing that grows with you',
    description: 'Choose the right plan.',
    showBillingToggle: true,
    monthlyLabel: 'Monthly',
    annualLabel: 'Annually',
    columns: '3',
    maxWidth: 1200,
    plans: [
      {
        name: 'Basic Plan',
        description: 'For getting started.',
        monthlyPrice: '19',
        annualPrice: '15',
        pricePrefix: '$',
        priceSuffix: '/month',
        buttonText: 'Choose plan',
        buttonUrl: '#',
        features: 'Feature one\nFeature two',
      },
      {
        name: 'Standard Plan',
        description: 'For growing teams.',
        monthlyPrice: '29',
        annualPrice: '24',
        pricePrefix: '$',
        priceSuffix: '/month',
        buttonText: 'Choose plan',
        buttonUrl: '#',
        popular: true,
        badgeText: 'Most popular',
        features: 'Everything in Basic\nPriority support',
      },
      {
        name: 'Premium Plan',
        description: 'For established businesses.',
        monthlyPrice: '59',
        annualPrice: '49',
        pricePrefix: '$',
        priceSuffix: '/month',
        buttonText: 'Choose plan',
        buttonUrl: '#',
        features: ['Unlimited feature', 'Dedicated support'],
      },
    ],
  }

  it('renders pricing cards, features, and the featured plan', () => {
    const wrapper = mount(PricingPreview, { props: { settings } })

    expect(wrapper.findAll('.dsf-pricing-preview__card')).toHaveLength(3)
    expect(wrapper.find('.dsf-pricing-preview__badge').text()).toBe('Most popular')
    expect(wrapper.findAll('.dsf-pricing-preview__features li')).toHaveLength(6)
    expect(wrapper.find('.dsf-pricing-preview__inner').attributes('style')).toContain('max-width: 1200px')
  })

  it('switches each card from monthly to annual pricing', async () => {
    const wrapper = mount(PricingPreview, { props: { settings } })

    expect(wrapper.findAll('.dsf-pricing-preview__price-value').map((node) => node.text()))
      .toEqual(['19', '29', '59'])

    await wrapper.findAll('.dsf-pricing-preview__billing-option').at(1).trigger('click')

    expect(wrapper.findAll('.dsf-pricing-preview__price-value').map((node) => node.text()))
      .toEqual(['15', '24', '49'])
  })

  it('hides billing controls when disabled', () => {
    const wrapper = mount(PricingPreview, {
      props: { settings: { ...settings, showBillingToggle: false } },
    })

    expect(wrapper.find('.dsf-pricing-preview__billing').exists()).toBe(false)
  })
})
