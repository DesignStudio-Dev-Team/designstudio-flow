import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RepeaterField from '../common/RepeaterField.vue'

const DraggableStub = {
  props: ['modelValue'],
  template:
    '<div><slot name="item" v-for="(element, index) in modelValue" :element="element" :index="index" /></div>',
}

const mountRepeater = (items) =>
  mount(RepeaterField, {
    props: { modelValue: items },
    global: {
      stubs: {
        draggable: DraggableStub,
      },
    },
  })

describe('RepeaterField', () => {
  it('shows button URL when action is link', async () => {
    const wrapper = mountRepeater([
      {
        title: 'Item',
        description: 'Desc',
        buttonText: 'Go',
        buttonUrl: '/path',
        buttonAction: 'link',
      },
    ])

    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Button URL')
  })

  it('hides button URL and shows modal fields when action is modal', async () => {
    const wrapper = mountRepeater([
      {
        title: 'Item',
        description: 'Desc',
        buttonText: 'Open',
        buttonAction: 'modal',
        buttonModalContentType: 'wysiwyg',
      },
    ])

    await wrapper.vm.$nextTick()
    expect(wrapper.text()).not.toContain('Button URL')
    expect(wrapper.text()).toContain('Modal Content Type')
  })
})
