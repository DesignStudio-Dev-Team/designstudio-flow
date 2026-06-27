import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ShortcodeEmbedField from '../common/ShortcodeEmbedField.vue'

describe('ShortcodeEmbedField', () => {
  const originalEditorData = window.dsfEditorData

  beforeEach(() => {
    window.dsfEditorData = { gravityForms: [] }
  })

  afterEach(() => {
    window.dsfEditorData = originalEditorData
  })

  it('renders a visible code editor for empty shortcode/embed content', () => {
    const wrapper = mount(ShortcodeEmbedField, {
      props: { modelValue: '' },
    })

    expect(wrapper.find('.dsf-shortcode-embed__source').exists()).toBe(true)
    expect(wrapper.find('.dsf-shortcode-embed__quick-btn').exists()).toBe(false)
    expect(wrapper.text()).toContain('Empty')
  })

  it('inserts a Gravity Forms shortcode when forms are available', async () => {
    window.dsfEditorData = {
      gravityForms: [
        {
          id: 7,
          title: 'Lead Capture',
          shortcode: '[gravityform id="7" title="false" description="false" ajax="true"]',
        },
      ],
    }

    const wrapper = mount(ShortcodeEmbedField, {
      props: { modelValue: '<iframe src="https://example.test"></iframe>' },
    })

    expect(wrapper.find('.dsf-shortcode-embed__quick-btn').exists()).toBe(true)
    await wrapper.find('.dsf-shortcode-embed__quick-btn').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0]
    expect(emitted).toContain('<iframe')
    expect(emitted).toContain('\n[gravityform id="7"')
  })
})
