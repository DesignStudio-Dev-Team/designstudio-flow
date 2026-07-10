import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SettingField from '../SettingField.vue'

function mountSlider(props = {}) {
  return mount(SettingField, {
    props: {
      config: { type: 'slider', label: 'Height', min: 20, max: 100, ...(props.config || {}) },
      fieldKey: 'height',
      value: props.value ?? 50,
    },
  })
}

describe('SettingField slider (editable number + knob)', () => {
  it('renders both a range knob and a number input', () => {
    const w = mountSlider()
    expect(w.find('input[type="range"].dsf-slider').exists()).toBe(true)
    expect(w.find('input[type="number"].dsf-slider-input').exists()).toBe(true)
    expect(w.find('.dsf-slider-input').element.value).toBe('50')
  })

  it('typing a number emits it (upper bound clamped live)', async () => {
    const w = mountSlider()
    const num = w.find('.dsf-slider-input')
    await num.setValue('80')
    expect(w.emitted('update').at(-1)).toEqual([80])

    await num.setValue('999')
    expect(w.emitted('update').at(-1)).toEqual([100]) // clamped to max
  })

  it('allows typing below the minimum, then clamps up on blur', async () => {
    const w = mountSlider()
    const num = w.find('.dsf-slider-input')
    await num.setValue('5') // below min while typing → allowed
    expect(w.emitted('update').at(-1)).toEqual([5])

    await num.trigger('blur')
    expect(w.emitted('update').at(-1)).toEqual([20]) // clamped to min
  })

  it('empty field falls back to the minimum on blur', async () => {
    const w = mountSlider()
    const num = w.find('.dsf-slider-input')
    await num.setValue('')
    await num.trigger('blur')
    expect(w.emitted('update').at(-1)).toEqual([20])
  })

  it('dragging the knob always emits an in-range value', async () => {
    const w = mountSlider()
    const range = w.find('input[type="range"].dsf-slider')
    await range.setValue('75')
    expect(w.emitted('update').at(-1)).toEqual([75])
  })

  it('respects custom min/max bounds', async () => {
    const w = mountSlider({ config: { min: 0, max: 400 }, value: 100 })
    const num = w.find('.dsf-slider-input')
    await num.setValue('350')
    expect(w.emitted('update').at(-1)).toEqual([350])
    await num.setValue('900')
    expect(w.emitted('update').at(-1)).toEqual([400])
  })
})
