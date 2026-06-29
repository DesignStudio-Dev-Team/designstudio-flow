import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SaveBlockModal from '../SaveBlockModal.vue'

function mountOpen(props = {}) {
  return mount(SaveBlockModal, {
    props: { visible: false, suggestedName: 'Hero', existing: [], ...props },
    global: { stubs: { teleport: true } },
  })
}

describe('SaveBlockModal', () => {
  it('emits save with the suggested name and a null id when creating new', async () => {
    const wrapper = mountOpen()
    await wrapper.setProps({ visible: true }) // triggers the open watcher

    await wrapper.find('.dsf-savemodal__btn--save').trigger('click')

    const events = wrapper.emitted('save')
    expect(events).toHaveLength(1)
    expect(events[0][0]).toEqual({ name: 'Hero', id: null, category: '' })
  })

  it('offers update mode and emits the chosen existing id', async () => {
    const wrapper = mountOpen({
      existing: [
        { id: 7, name: 'Marketing hero', type: 'hero' },
        { id: 9, name: 'Launch hero', type: 'hero' },
      ],
    })
    await wrapper.setProps({ visible: true })

    // Switch to "Update an existing one".
    const radios = wrapper.findAll('input[type="radio"]')
    await radios[1].setValue()

    await wrapper.find('.dsf-savemodal__btn--save').trigger('click')

    const payload = wrapper.emitted('save')[0][0]
    expect(payload.id).toBe(7) // defaults to the first existing block
  })

  it('does not show update mode when there are no existing blocks of this type', async () => {
    const wrapper = mountOpen({ existing: [] })
    await wrapper.setProps({ visible: true })
    expect(wrapper.find('.dsf-savemodal__modes').exists()).toBe(false)
  })

  it('shows a folder field only when enabled, and emits the folder as category', async () => {
    const hidden = mountOpen()
    await hidden.setProps({ visible: true })
    expect(hidden.find('#dsf-savemodal-folder').exists()).toBe(false)

    const wrapper = mountOpen({ showFolder: true, folders: ['Heroes'] })
    await wrapper.setProps({ visible: true })
    await wrapper.find('#dsf-savemodal-folder').setValue('Footers')
    await wrapper.find('.dsf-savemodal__btn--save').trigger('click')

    expect(wrapper.emitted('save')[0][0].category).toBe('Footers')
  })
})
