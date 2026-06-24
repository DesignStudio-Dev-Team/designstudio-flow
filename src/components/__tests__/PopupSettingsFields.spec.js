import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PopupSettingsFields from '../common/PopupSettingsFields.vue'

describe('PopupSettingsFields', () => {
  it('enables a popup and exposes content, layout, and display settings', async () => {
    const wrapper = mount(PopupSettingsFields, {
      props: { modelValue: { enabled: false } },
    })

    await wrapper.find('.dsf-popup-settings-fields__enable button').trigger('click')
    const enabled = wrapper.emitted('update:modelValue')?.at(-1)?.[0]
    expect(enabled.enabled).toBe(true)

    await wrapper.setProps({ modelValue: enabled })
    expect(wrapper.find('#dsf-popup-type').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-delay').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-cookie-duration').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-start').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-end').exists()).toBe(true)
  })

  it('shows image-specific fields without the content editor in image mode', async () => {
    const wrapper = mount(PopupSettingsFields, {
      props: {
        modelValue: {
          enabled: true,
          type: 'image',
          image: 'https://example.com/offer.jpg',
        },
      },
    })

    expect(wrapper.find('#dsf-popup-image-alt').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-headline').exists()).toBe(false)
    expect(wrapper.find('.dsf-wysiwyg').exists()).toBe(false)
    expect(wrapper.text()).toContain('Image Link URL')
  })

  it('hides the per-page enable row and always shows fields when showEnable is false', () => {
    const wrapper = mount(PopupSettingsFields, {
      props: { modelValue: {}, showEnable: false },
    })

    expect(wrapper.find('.dsf-popup-settings-fields__enable').exists()).toBe(false)
    // Fields render even though `enabled` is not set, because the CPT editor manages enable per page.
    expect(wrapper.find('#dsf-popup-type').exists()).toBe(true)
    expect(wrapper.find('#dsf-popup-delay').exists()).toBe(true)
  })
})
