import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EditorHelp from '../EditorHelp.vue'

describe('EditorHelp', () => {
  it('explains the dock as the first step of the quick tour', () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' } })

    expect(wrapper.text()).toContain('Start with Page Settings')
    expect(wrapper.text()).toContain('Quick tour · 1 of 9')
    expect(wrapper.text()).toContain('Click Page Settings')
    expect(wrapper.text()).toContain('Click the glowing Page Settings icon to continue.')
  })

  it('emits the appropriate actions for tour progress and dismissal', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'settings' } })

    await wrapper.get('.dsf-editor-help__button').trigger('click')
    expect(wrapper.emitted('next')).toHaveLength(1)

    await wrapper.get('.dsf-editor-help__close').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('uses the current editor context outside the tour', () => {
    const wrapper = mount(EditorHelp, {
      props: { visible: true, context: { title: 'Customize this block', tip: 'Use Style for colors.' } },
    })

    expect(wrapper.text()).toContain('Customize this block')
    expect(wrapper.text()).toContain('Restart quick tour')
  })
})
