import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ShowcaseHeaderNavigationField from '../common/ShowcaseHeaderNavigationField.vue'

describe('ShowcaseHeaderNavigationField', () => {
  it('edits nested navigation without mutating the saved prop directly', async () => {
    const original = { utility: [{ label: 'Services', kind: 'link', url: '#', links: [], panel: {} }], menu: [], locations: [], calls: [] }
    const wrapper = mount(ShowcaseHeaderNavigationField, { props: { modelValue: original } })
    const input = wrapper.find('input')
    await input.setValue('Support')

    expect(original.utility[0].label).toBe('Services')
    expect(wrapper.emitted('update:modelValue').at(-1)[0].utility[0].label).toBe('Support')
  })

  it('caps every server-sensitive collection in the editor model', () => {
    const many = Array.from({ length: 20 }, (_, index) => ({ label: `${index}`, url: '#', links: [], panel: {} }))
    const wrapper = mount(ShowcaseHeaderNavigationField, { props: { modelValue: { utility: many, menu: many, locations: many, calls: many } } })
    expect(wrapper.vm.model.utility).toHaveLength(4)
    expect(wrapper.vm.model.menu).toHaveLength(8)
    expect(wrapper.vm.model.locations).toHaveLength(6)
    expect(wrapper.vm.model.calls).toHaveLength(8)
  })
})
