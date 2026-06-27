import { describe, it, expect, vi } from 'vitest'
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

  it('allows HTML editing in every WYSIWYG field', async () => {
    const wrapper = mount(WysiwygField, {
      props: {
        modelValue: '<iframe src="https://example.com/embed"></iframe>',
      },
    })

    await wrapper.find('[title="Edit HTML"]').trigger('click')

    const source = wrapper.find('.dsf-wysiwyg__source')
    expect(source.exists()).toBe(true)
    expect(source.element.value).toContain('<iframe')

    await source.setValue('<iframe src="https://example.com/updated"></iframe>')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted.at(-1)[0]).toContain('https://example.com/updated')

    await wrapper.setProps({ modelValue: '<iframe src="https://example.com/updated"></iframe>' })
    await wrapper.find('[title="Edit HTML"]').trigger('click')
    expect(wrapper.find('.dsf-wysiwyg__editor').html()).toContain('https://example.com/updated')
  })

  it('renders heading, list, rule, and html toolbar controls', () => {
    const wrapper = mount(WysiwygField, {
      props: { modelValue: '' },
    })

    expect(wrapper.find('[title="Heading 3"]').exists()).toBe(true)
    expect(wrapper.find('[title="Heading 4"]').exists()).toBe(true)
    expect(wrapper.find('[title="Heading 1"]').text()).toBe('H1')
    expect(wrapper.find('[title="Heading 2"]').text()).toBe('H2')
    expect(wrapper.find('[title="Heading 3"]').text()).toBe('H3')
    expect(wrapper.find('[title="Heading 4"]').text()).toBe('H4')
    expect(wrapper.find('[title="Bullet List"]').exists()).toBe(true)
    expect(wrapper.find('[title="Numbered List"]').exists()).toBe(true)
    expect(wrapper.find('[title="Horizontal Rule"]').exists()).toBe(true)
    expect(wrapper.find('[title="Edit HTML"]').exists()).toBe(true)
  })

  it('shows the Gravity Forms inserter and inserts the shortcode when forms exist', async () => {
    const originalEditorData = window.dsfEditorData
    const shortcode = '[gravityform id="7" title="false" description="false" ajax="true"]'
    window.dsfEditorData = {
      gravityForms: [{ id: 7, title: 'Contact Form', shortcode }],
    }

    try {
      const wrapper = mount(WysiwygField, {
        props: { modelValue: '<p>Lead text</p>' },
        attachTo: document.body,
      })

      expect(wrapper.find('.dsf-wysiwyg__gf-select').exists()).toBe(true)
      const insertBtn = wrapper.find('.dsf-wysiwyg__gf-insert')
      expect(insertBtn.exists()).toBe(true)

      // Use source mode so insertion is deterministic without execCommand.
      await wrapper.find('[title="Edit HTML"]').trigger('click')
      await insertBtn.trigger('click')

      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted.at(-1)[0]).toContain('gravityform id="7"')
    } finally {
      window.dsfEditorData = originalEditorData
    }
  })

  it('hides the Gravity Forms inserter when no forms are available', () => {
    const originalEditorData = window.dsfEditorData
    window.dsfEditorData = { gravityForms: [] }

    try {
      const wrapper = mount(WysiwygField, { props: { modelValue: '' } })
      expect(wrapper.find('.dsf-wysiwyg__gf-select').exists()).toBe(false)
      expect(wrapper.find('.dsf-wysiwyg__gf-insert').exists()).toBe(false)
    } finally {
      window.dsfEditorData = originalEditorData
    }
  })

  it('executes the new formatting commands from the toolbar', async () => {
    document.execCommand = document.execCommand || vi.fn()
    const execSpy = vi.spyOn(document, 'execCommand').mockImplementation(() => true)
    const wrapper = mount(WysiwygField, {
      props: { modelValue: '<p>Text</p>' },
      attachTo: document.body,
    })

    await wrapper.find('[title="Heading 3"]').trigger('click')
    await wrapper.find('[title="Heading 4"]').trigger('click')
    await wrapper.find('[title="Bullet List"]').trigger('click')
    await wrapper.find('[title="Numbered List"]').trigger('click')
    await wrapper.find('[title="Horizontal Rule"]').trigger('click')

    expect(execSpy).toHaveBeenCalledWith('formatBlock', false, '<h3>')
    expect(execSpy).toHaveBeenCalledWith('formatBlock', false, '<h4>')
    expect(execSpy).toHaveBeenCalledWith('insertUnorderedList', false, null)
    expect(execSpy).toHaveBeenCalledWith('insertOrderedList', false, null)
    expect(execSpy).toHaveBeenCalledWith('insertHorizontalRule', false, null)

    execSpy.mockRestore()
  })
})
