import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WysiwygField from '../common/WysiwygField.vue'

describe('WysiwygField', () => {
  it('renders initial HTML content', async () => {
    const html = '<p><a href="https://example.com">Link</a></p>'
    const wrapper = mount(WysiwygField, {
      props: { modelValue: html },
    })

    const editor = wrapper.find('.dsf-wysiwyg__editor')
    expect(editor.exists()).toBe(true)
    expect(editor.html()).toContain('href="https://example.com"')
    expect(editor.text()).toContain('Link')
  })

  it('emits updates on input', async () => {
    const wrapper = mount(WysiwygField, {
      props: { modelValue: '' },
    })

    const editor = wrapper.find('.dsf-wysiwyg__editor')
    editor.element.innerHTML = '<p>Updated</p>'
    await editor.trigger('input')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toContain('Updated')
  })
})
