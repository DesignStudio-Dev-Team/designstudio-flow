import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CardColumnItemsField from '../common/CardColumnItemsField.vue'

describe('CardColumnItemsField', () => {
  function linkSelect(wrapper) {
    return wrapper.findAll('select').find((select) => select.text().includes('Link whole card'))
  }

  it('switches a card from no link to a whole-card URL', async () => {
    const wrapper = mount(CardColumnItemsField, {
      props: { modelValue: [{ title: 'Explore', linkMode: 'none', buttonUrl: '' }] },
    })

    const select = linkSelect(wrapper)
    expect(select.element.value).toBe('none')

    await select.setValue('card')

    expect(wrapper.text()).toContain('Card URL')
    expect(wrapper.text()).not.toContain('Button Text')
    const updates = wrapper.emitted('update:modelValue')
    expect(updates.at(-1)[0][0]).toMatchObject({ linkMode: 'card', showButton: false })
  })

  it('maps legacy showButton cards to button mode', () => {
    const wrapper = mount(CardColumnItemsField, {
      props: { modelValue: [{ title: 'Legacy', showButton: true, buttonText: 'Learn more' }] },
    })

    expect(linkSelect(wrapper).element.value).toBe('button')
    expect(wrapper.text()).toContain('Button Text')
  })
})
