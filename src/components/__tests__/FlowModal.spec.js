import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FlowModal from '../common/FlowModal.vue'

describe('FlowModal', () => {
  it('renders center layout by default', () => {
    const wrapper = mount(FlowModal, {
      props: {
        content: '<p>Hi</p>',
        layout: 'center',
      },
    })

    expect(wrapper.classes()).toContain('dsf-modal--center')
    expect(wrapper.find('.dsf-modal__content').html()).toContain('Hi')
  })

  it('renders drawer layout', () => {
    const wrapper = mount(FlowModal, {
      props: {
        content: '<p>Drawer</p>',
        layout: 'drawer',
      },
    })

    expect(wrapper.classes()).toContain('dsf-modal--drawer')
  })
})
