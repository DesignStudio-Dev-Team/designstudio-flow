import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import ProductSpecsPreview from '../blocks/ProductSpecsPreview.vue'

describe('ProductSpecsPreview custom product fields', () => {
  it('renders only custom fields explicitly configured for the block', () => {
    const wrapper = mount(ProductSpecsPreview, {
      props: { settings: { customFieldKeys: 'material, care_instructions' } },
      global: {
        provide: {
          dsfProductContext: ref({
            specs: [],
            customFields: { material: 'Recycled aluminum', care_instructions: 'Hand wash', private_note: 'Never show' },
          }),
        },
      },
    })

    expect(wrapper.text()).toContain('Recycled aluminum')
    expect(wrapper.text()).toContain('Hand wash')
    expect(wrapper.text()).not.toContain('Never show')
  })
})
