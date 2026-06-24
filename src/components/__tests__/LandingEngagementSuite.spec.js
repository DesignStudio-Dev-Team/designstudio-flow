import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import LandingEngagementSuitePreview from '../blocks/LandingEngagementSuitePreview.vue'

const settings = {
  eyebrow: 'Grow with Flow',
  title: 'From page to conversation.',
  description: 'Three connected tools.',
  formsTitle: 'Flexible forms',
  formsDescription: 'Build fields visually.',
  popupTitle: 'Timed popups',
  popupDescription: 'Control timing and repeat visits.',
  notificationTitle: 'Site-wide notifications',
  notificationDescription: 'Publish one message everywhere.',
}

describe('LandingEngagementSuitePreview', () => {
  it('renders all three engagement features and editor-faithful scenes', () => {
    const wrapper = mount(LandingEngagementSuitePreview, {
      props: { isEditor: true, settings },
    })

    expect(wrapper.attributes('id')).toBe('engagement')
    expect(wrapper.find('h2').text()).toBe('From page to conversation.')
    expect(wrapper.findAll('.dsf-engagement-card')).toHaveLength(3)
    expect(wrapper.text()).toContain('Flexible forms')
    expect(wrapper.text()).toContain('Timed popups')
    expect(wrapper.text()).toContain('Site-wide notifications')
    expect(wrapper.find('.dsf-form-builder').exists()).toBe(true)
    expect(wrapper.find('.dsf-popup-scene').exists()).toBe(true)
    expect(wrapper.find('.dsf-notification-scene').exists()).toBe(true)
  })

  it('escapes editable copy and remains stable with missing values', () => {
    const wrapper = mount(LandingEngagementSuitePreview, {
      props: {
        isEditor: true,
        settings: { ...settings, title: '<img src=x onerror=alert(1)>', popupDescription: undefined },
      },
    })

    expect(wrapper.find('h2').text()).toBe('<img src=x onerror=alert(1)>')
    expect(wrapper.find('h2 img').exists()).toBe(false)
    expect(wrapper.findAll('.dsf-engagement-card')).toHaveLength(3)
  })

  it('uses the renamed accent setting only for icon backgrounds', () => {
    const wrapper = mount(LandingEngagementSuitePreview, {
      props: { isEditor: true, settings: { ...settings, accentColor: '#cc5500' } },
    })

    const style = wrapper.attributes('style')
    expect(style).toContain('--dsf-theme-secondary: #cc5500')
    expect(style).not.toContain('--dsf-theme-primary: #cc5500')
  })
})
