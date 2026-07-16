import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import StoreLoginPreview from '../blocks/StoreLoginPreview.vue'

describe('StoreLoginPreview', () => {
  it('renders a safe editor preview and its configured copy', () => {
    const wrapper = mount(StoreLoginPreview, {
      props: {
        isEditor: true,
        settings: { heading: 'Sign in', subheading: 'Access your orders.' },
      },
    })

    expect(wrapper.text()).toContain('Sign in')
    expect(wrapper.text()).toContain('Access your orders.')
    expect(wrapper.find('.dsf-store-login__mock').exists()).toBe(true)
  })

  it('does not render HTML supplied in plain-text settings', () => {
    const wrapper = mount(StoreLoginPreview, {
      props: { isEditor: true, settings: { heading: '<img src=x onerror=alert(1)>' } },
    })

    expect(wrapper.html()).not.toContain('<img src="x"')
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>')
  })
})
