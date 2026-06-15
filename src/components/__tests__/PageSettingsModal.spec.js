import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PageSettingsModal from '../PageSettingsModal.vue'

describe('PageSettingsModal', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('emits sanitized page settings', async () => {
    const wrapper = mount(PageSettingsModal, {
      props: {
        visible: true,
        title: 'Summer Sale',
        slug: 'summer-sale',
        status: 'draft',
        parentId: 0,
        parentPages: [
          { id: 42, title: 'Landing Pages', depthLabel: '', depth: 0 },
        ],
      },
      attachTo: document.body,
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    await wrapper.find('#dsf-page-title').setValue('Beat The Heat')
    await wrapper.find('#dsf-page-slug').setValue('Beat The Heat 2026!')
    await wrapper.find('#dsf-page-status').setValue('publish')
    await wrapper.find('#dsf-page-parent').setValue('42')
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
      title: 'Beat The Heat',
      slug: 'beat-the-heat-2026',
      status: 'publish',
      parentId: 42,
    })
  })
})
