import { describe, expect, it } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import ThemePanel from '../ThemePanel.vue'

const settings = {
  theme: {
    primaryColor: '#0C5FA8',
    secondaryColor: '#E86A45',
    textColor: '#171C23',
    backgroundColor: '#FCFBF7',
    headingFont: "'Manrope', sans-serif",
    bodyFont: "'Source Sans 3', sans-serif",
  },
  layout: {},
}

describe('ThemePanel', () => {
  it('emits an undo request when theme history is available', async () => {
    const wrapper = shallowMount(ThemePanel, {
      props: { settings, canUndo: true },
    })

    await wrapper.find('.dsf-theme-panel__undo').trigger('click')
    expect(wrapper.emitted('undo-theme')).toHaveLength(1)
  })

  it('can restore the backend site defaults', async () => {
    const wrapper = shallowMount(ThemePanel, {
      props: { settings },
    })

    await wrapper.find('.dsf-theme-panel__site-defaults button').trigger('click')
    expect(wrapper.emitted('restore-defaults')).toHaveLength(1)
  })
})
