import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EditorHelp from '../EditorHelp.vue'

describe('EditorHelp', () => {
  it('explains the dock as the first step of the quick tour', () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' } })

    expect(wrapper.text()).toContain('Click here first')
    expect(wrapper.text()).toContain('Quick tour · 1 of 10')
    expect(wrapper.text()).toContain('highlighted Page Settings button')
    expect(wrapper.text()).toContain('flashing CLICK HERE arrow')
  })

  it('puts a flashing CLICK HERE pointer over the first tour target', async () => {
    const target = document.createElement('button')
    target.dataset.dsfHelp = 'dock-page-settings'
    document.body.appendChild(target)

    const wrapper = mount(EditorHelp, { attachTo: document.body, props: { visible: true, step: 'dock' } })
    await new Promise((resolve) => setTimeout(resolve, 40))

    expect(wrapper.get('.dsf-editor-help__pointer').text()).toContain('CLICK HERE')
    wrapper.unmount()
    target.remove()
  })

  it('keeps the flashing pointer on every action-oriented stop through the end of the tour', async () => {
    const steps = [
      ['theme', 'dock-theme'],
      ['preview', 'dock-preview'],
      ['history', 'dock-history'],
      ['structure', 'dock-structure'],
      ['add', 'dock-add-block'],
      ['settings', 'customize-block'],
      ['background', 'background-color'],
    ]

    for (const [step, targetName] of steps) {
      const target = document.createElement('button')
      target.setAttribute('data-dsf-help', targetName)
      document.body.appendChild(target)
      const wrapper = mount(EditorHelp, { attachTo: document.body, props: { visible: true, step } })
      await new Promise((resolve) => setTimeout(resolve, 40))

      expect(wrapper.get('.dsf-editor-help__pointer').text()).toContain('CLICK HERE')
      wrapper.unmount()
      target.remove()
    }
  })

  it('uses the compact layout when browser chrome reduces the available width', async () => {
    const originalWidth = window.innerWidth
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 800 })
    const target = document.createElement('button')
    target.dataset.dsfHelp = 'dock-page-settings'
    document.body.appendChild(target)

    const wrapper = mount(EditorHelp, { attachTo: document.body, props: { visible: true, step: 'dock' } })
    await new Promise((resolve) => setTimeout(resolve, 40))

    expect(wrapper.get('.dsf-editor-help__card').attributes('style')).toContain('top: 16px')
    wrapper.unmount()
    target.remove()
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: originalWidth })
  })

  it('keeps the first-step pointer inside a narrow viewport', async () => {
    const originalWidth = window.innerWidth
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 375 })
    const target = document.createElement('button')
    target.setAttribute('aria-label', 'Open editor actions')
    target.getBoundingClientRect = () => ({ left: 0, top: 120, width: 30, height: 30 })
    document.body.appendChild(target)

    const wrapper = mount(EditorHelp, { attachTo: document.body, props: { visible: true, step: 'dock' } })
    await new Promise((resolve) => setTimeout(resolve, 80))

    expect(wrapper.get('.dsf-editor-help__pointer').attributes('style')).toContain('left: 62px')
    wrapper.unmount()
    target.remove()
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: originalWidth })
  })

  it('places Page Settings guidance beside the modal instead of covering it', async () => {
    const originalWidth = window.innerWidth
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 1296 })
    const target = document.createElement('section')
    target.setAttribute('data-dsf-help', 'page-settings-panel')
    target.getBoundingClientRect = () => ({ left: 18, top: 122, width: 952, height: 690 })
    document.body.appendChild(target)

    const wrapper = mount(EditorHelp, { attachTo: document.body, props: { visible: true, step: 'page-settings-detail' } })
    await new Promise((resolve) => setTimeout(resolve, 40))

    const style = wrapper.get('.dsf-editor-help__card').element.style
    expect(Number.parseFloat(style.left)).toBeGreaterThanOrEqual(982)
    expect(Number.parseFloat(style.width)).toBeLessThanOrEqual(314)
    expect(wrapper.classes()).toContain('dsf-editor-help--keep-target-readable')
    wrapper.unmount()
    target.remove()
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: originalWidth })
  })

  it('keeps the Block Library bright while the add-block tour step is active', () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'add', blockLibraryOpen: true } })

    expect(wrapper.classes()).toContain('dsf-editor-help--keep-target-readable')
  })

  it('keeps History and Structure bright when their panels are open during the tour', () => {
    for (const props of [
      { visible: true, step: 'history', historyOpen: true },
      { visible: true, step: 'structure', structureOpen: true },
    ]) {
      const wrapper = mount(EditorHelp, { props })
      expect(wrapper.classes()).toContain('dsf-editor-help--keep-target-readable')
      wrapper.unmount()
    }
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
