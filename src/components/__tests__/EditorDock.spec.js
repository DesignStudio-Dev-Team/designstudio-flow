import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EditorDock from '../EditorDock.vue'

function mountDock(props = {}) {
  window.dsfEditorData = { pluginUrl: '', adminUrl: '' }
  return mount(EditorDock, {
    attachTo: document.body,
    props: { previewMode: 'desktop', ...props },
  })
}

describe('EditorDock preview picker', () => {
  it('uses one button to open the responsive preview picker', async () => {
    const wrapper = mountDock()

    expect(wrapper.findAll('[aria-label="Choose preview size"]')).toHaveLength(1)
    expect(wrapper.find('.dsf-dock__preview-menu').exists()).toBe(false)

    await wrapper.get('[aria-label="Choose preview size"]').trigger('click')
    expect(wrapper.get('.dsf-dock__preview-menu').text()).toContain('Desktop')
    expect(wrapper.get('.dsf-dock__preview-menu').text()).toContain('Tablet')
    expect(wrapper.get('.dsf-dock__preview-menu').text()).toContain('Mobile')
  })

  it('emits the selected responsive mode and closes the picker', async () => {
    const wrapper = mountDock()
    await wrapper.get('[aria-label="Choose preview size"]').trigger('click')
    await wrapper.get('[role="menuitemradio"][aria-checked="false"]').trigger('click')

    expect(wrapper.emitted('set-preview-mode')).toHaveLength(1)
    expect(wrapper.find('.dsf-dock__preview-menu').exists()).toBe(false)
  })

  it('uses an ellipsis trigger for the full action panel on mobile', async () => {
    const originalMatchMedia = window.matchMedia
    window.matchMedia = () => ({ matches: true, addEventListener: () => {}, removeEventListener: () => {} })
    const wrapper = mountDock()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[aria-label="Open editor actions"]').exists()).toBe(true)
    expect(wrapper.find('.dsf-dock__body').isVisible()).toBe(false)

    await wrapper.get('[aria-label="Open editor actions"]').trigger('click')
    expect(wrapper.find('.dsf-dock__body').isVisible()).toBe(true)

    wrapper.unmount()
    window.matchMedia = originalMatchMedia
  })
})
