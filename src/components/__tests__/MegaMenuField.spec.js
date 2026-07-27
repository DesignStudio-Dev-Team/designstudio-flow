import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MegaMenuField from '../common/MegaMenuField.vue'

const menu = [
  {
    label: 'Products',
    url: '#',
    hasMega: true,
    menuType: 'mega',
    columns: [
      {
        heading: 'Categories',
        layout: 'links',
        links: [{ kind: 'link', label: 'New', url: '/new' }],
      },
    ],
    banner: {},
  },
]

describe('MegaMenuField', () => {
  it('keeps the active input mounted across parent model updates', async () => {
    const wrapper = mount(MegaMenuField, {
      attachTo: document.body,
      props: {
        modelValue: menu,
        pro: true,
        'onUpdate:modelValue': async (value) => wrapper.setProps({ modelValue: value }),
      },
    })
    const label = wrapper.find('input').element
    label.focus()

    label.value = 'P'
    label.dispatchEvent(new Event('input', { bubbles: true }))
    await wrapper.vm.$nextTick()
    label.value = 'Products and Services'
    label.dispatchEvent(new Event('input', { bubbles: true }))
    await wrapper.vm.$nextTick()

    expect(document.activeElement).toBe(label)
    expect(wrapper.find('input').element).toBe(label)
    expect(wrapper.emitted('update:modelValue').at(-1)[0][0].label).toBe('Products and Services')
    wrapper.unmount()
  })

  it('supports direct links, single-column dropdowns, and mega menus', async () => {
    const wrapper = mount(MegaMenuField, { props: { modelValue: menu, pro: true } })
    const type = wrapper.find('select')
    await type.setValue('dropdown')
    const emitted = wrapper.emitted('update:modelValue').at(-1)[0][0]

    expect(emitted.menuType).toBe('dropdown')
    expect(emitted.hasMega).toBe(true)
    expect(emitted.columns).toHaveLength(1)
  })

  it('adds section headings and preserves intentionally empty labels', async () => {
    const wrapper = mount(MegaMenuField, { props: { modelValue: menu, pro: true } })
    const addHeading = wrapper.findAll('button').find((button) => button.text() === 'Add Section Heading')
    await addHeading.trigger('click')
    let emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(emitted[0].columns[0].links.at(-1)).toMatchObject({ kind: 'heading', label: 'Section Heading', url: '' })

    const label = wrapper.find('input')
    await label.setValue('')
    emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    await wrapper.setProps({ modelValue: emitted })
    expect(wrapper.find('input').element.value).toBe('')
  })
})
