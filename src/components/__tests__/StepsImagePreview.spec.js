import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StepsImagePreview from '../blocks/StepsImagePreview.vue'

describe('StepsImagePreview', () => {
  const settings = {
    eyebrow: 'Process',
    title: 'A clear path forward',
    description: 'A generic description for a focused section.',
    image: 'https://cdn.example.test/process.jpg',
    step1Title: 'First', step1Text: 'Start here.',
    step2Title: 'Next', step2Text: 'Keep moving.',
    step3Title: 'Finish', step3Text: 'Complete the journey.',
  }

  it('renders editable copy, steps, and a safe image', () => {
    const wrapper = mount(StepsImagePreview, { props: { isEditor: true, settings } })
    expect(wrapper.classes()).toContain('dsf-steps-image--image-right')
    expect(wrapper.findAll('.dsf-steps-image__steps li')).toHaveLength(3)
    expect(wrapper.find('.dsf-steps-image__visual img').attributes('src')).toBe(settings.image)
    expect(wrapper.find('h2').attributes('contenteditable')).toBe('true')
  })

  it('supports image-left and stacked layouts and can hide steps', () => {
    const left = mount(StepsImagePreview, { props: { settings: { ...settings, layout: 'image-left' } } })
    const stacked = mount(StepsImagePreview, { props: { settings: { ...settings, layout: 'stacked', showSteps: false } } })
    expect(left.classes()).toContain('dsf-steps-image--image-left')
    expect(stacked.classes()).toContain('dsf-steps-image--stacked')
    expect(stacked.find('.dsf-steps-image__steps').exists()).toBe(false)
  })

  it('rejects unsafe image URLs', () => {
    const wrapper = mount(StepsImagePreview, { props: { settings: { ...settings, image: 'javascript:alert(1)' } } })
    expect(wrapper.find('.dsf-steps-image__visual img').exists()).toBe(false)
    expect(wrapper.find('.dsf-steps-image__placeholder').exists()).toBe(true)
  })
})
