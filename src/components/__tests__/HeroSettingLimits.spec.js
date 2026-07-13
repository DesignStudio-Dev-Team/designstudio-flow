import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SettingField from '../SettingField.vue'
import DockNavLinksField from '../common/DockNavLinksField.vue'

const draggableStub = {
  props: ['modelValue'],
  template: `
    <div>
      <div v-for="(element, index) in modelValue" :key="index">
        <slot name="item" :element="element" :index="index" />
      </div>
    </div>
  `,
}

describe('Showcase Hero setting limits', () => {
  it('exposes the registered text length limit in the editor input', () => {
    const wrapper = mount(SettingField, {
      props: {
        config: { type: 'text', label: 'Rotating words', maxLength: 390 },
        fieldKey: 'rotatingWords',
        value: 'whole WordPress site',
      },
    })

    expect(wrapper.find('input').attributes('maxlength')).toBe('390')
  })

  it('caps the tile editor at the block-specific six-item limit', () => {
    const items = Array.from({ length: 8 }, (_, index) => ({
      label: `Tile ${index + 1}`,
      url: `#tile-${index + 1}`,
      icon: 'star',
      iconImage: '',
    }))
    const wrapper = mount(DockNavLinksField, {
      props: { modelValue: items, maxItems: 6 },
      global: {
        stubs: {
          draggable: draggableStub,
          MediaPicker: true,
        },
      },
    })

    expect(wrapper.findAll('.dsf-docknav-field__item')).toHaveLength(6)
    expect(wrapper.find('.dsf-docknav-field__add').text()).toContain('Maximum 6 links')
    expect(wrapper.find('.dsf-docknav-field__add').attributes('disabled')).toBeDefined()
  })
})
