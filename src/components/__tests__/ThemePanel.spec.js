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

  it('accepts typed container width values', async () => {
    const wrapper = shallowMount(ThemePanel, {
      props: { settings },
    })
    const input = wrapper.find('[aria-label="Container width in pixels"]')

    await input.setValue('1400')

    expect(wrapper.emitted('update:settings').at(-1)[0].layout.containerWidth).toBe(1400)
  })

  it('uses and preserves zero as the content padding default', async () => {
    const wrapper = shallowMount(ThemePanel, {
      props: { settings: { ...settings, layout: { contentPadding: 0 } } },
    })
    const input = wrapper.find('[aria-label="Content padding in pixels"]')
    const slider = wrapper.find('[aria-label="Content padding slider"]')

    expect(input.element.value).toBe('0')
    expect(slider.element.value).toBe('0')

    await input.setValue('0')
    expect(wrapper.emitted('update:settings').at(-1)[0].layout.contentPadding).toBe(0)
  })

  it('allows partial container-width typing before committing a valid value', async () => {
    const wrapper = shallowMount(ThemePanel, {
      props: { settings },
    })
    const input = wrapper.find('[aria-label="Container width in pixels"]')

    await input.setValue('14')
    expect(wrapper.emitted('update:settings')).toBeUndefined()

    await input.setValue('1400')
    expect(wrapper.emitted('update:settings').at(-1)[0].layout.containerWidth).toBe(1400)
  })
})
