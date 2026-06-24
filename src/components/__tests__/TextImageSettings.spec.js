import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SidePanel from '../SidePanel.vue'

const settingFieldStub = {
  props: ['fieldKey', 'config'],
  template: '<div class="setting-stub" :data-type="config.type">{{ fieldKey }}</div>',
}

const definition = {
  id: 'text-image',
  name: 'Text & Image',
  settings: {
    title: { type: 'text', label: 'Title' },
    content: { type: 'textarea', label: 'Description' },
    descriptionSize: { type: 'select', label: 'Description Size', options: { 'Large Text': 'large', 'P Tag': 'normal' } },
    height: { type: 'slider', label: 'Block Height', min: 100, max: 800, default: 400 },
    padding: { type: 'slider', label: 'Vertical Padding', min: 0, max: 120, default: 60 },
  },
}

describe('Text & Image settings', () => {
  it('exposes description editing and sizing in the content expander', async () => {
    const wrapper = mount(SidePanel, {
      props: { block: { type: 'text-image', settings: {} }, blockDefinition: definition },
      global: { stubs: { SettingField: settingFieldStub } },
    })

    await wrapper.find('[data-section="settings"] .dsf-settings-expander__trigger').trigger('click')
    const fields = wrapper.findAll('.setting-stub')
    expect(fields.map((field) => field.text())).toEqual(['title', 'content', 'descriptionSize'])
    expect(fields.find((field) => field.text() === 'content').attributes('data-type')).toBe('textarea')
  })

  it('includes the 100px height control in responsive spacing', async () => {
    const wrapper = mount(SidePanel, {
      props: { block: { type: 'text-image', settings: {} }, blockDefinition: definition },
      global: { stubs: { SettingField: settingFieldStub } },
    })

    await wrapper.findAll('.dsf-segmented-btn').find((button) => button.text().includes('Style')).trigger('click')
    await wrapper.find('[data-style-section="spacing"] .dsf-settings-expander__trigger').trigger('click')
    expect(wrapper.findAll('.setting-stub').map((field) => field.text())).toContain('height')
  })
})
