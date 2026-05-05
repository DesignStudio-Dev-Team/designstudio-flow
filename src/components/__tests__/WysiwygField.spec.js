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

  it('allows raw HTML editing when enabled', async () => {
    const wrapper = mount(WysiwygField, {
      props: {
        allowRawHtml: true,
        modelValue: '<iframe src="https://example.com/embed"></iframe>',
      },
    })

    await wrapper.find('.dsf-wysiwyg__btn:last-child').trigger('click')

    const source = wrapper.find('.dsf-wysiwyg__source')
    expect(source.exists()).toBe(true)
    expect(source.element.value).toContain('<iframe')

    await source.setValue('<iframe src="https://example.com/updated"></iframe>')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted.at(-1)[0]).toContain('https://example.com/updated')

    await wrapper.setProps({ modelValue: '<iframe src="https://example.com/updated"></iframe>' })
    await wrapper.find('.dsf-wysiwyg__btn:last-child').trigger('click')
    expect(wrapper.find('.dsf-wysiwyg__editor').html()).toContain('https://example.com/updated')
  })
})
