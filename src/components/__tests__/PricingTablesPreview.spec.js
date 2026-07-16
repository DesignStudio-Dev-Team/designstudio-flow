import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PricingTablesPreview from '../blocks/PricingTablesPreview.vue'

describe('PricingTablesPreview', () => {
  it('renders three plans and keeps unsafe links inert', () => {
    const wrapper = mount(PricingTablesPreview, {
      props: {
        isEditor: false,
        settings: {
          plans: [
            { name: 'Starter', monthlyPrice: '19', buttonUrl: 'javascript:alert(1)', features: ['One'] },
            { name: 'Growth', monthlyPrice: '49', popular: true, features: ['Two'] },
            { name: 'Scale', monthlyPrice: '99', features: ['Three'] },
          ],
        },
      },
    })

    expect(wrapper.findAll('article')).toHaveLength(3)
    expect(wrapper.find('a').attributes('href')).toBe('#')
    expect(wrapper.find('.is-featured').text()).toContain('Growth')
  })
})
