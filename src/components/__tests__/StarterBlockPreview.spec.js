import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StarterBlockPreview from '../blocks/StarterBlockPreview.vue'

describe('StarterBlockPreview', () => {
  it('renders settings and styles correctly', () => {
    const settings = {
      title: 'Starter Title',
      subtitle: 'Starter Subtitle',
      buttonText: 'Get Started',
      buttonUrl: '/start',
      buttonColor: 'rgb(10, 10, 10)',
      backgroundColor: 'rgb(1, 2, 3)',
      textColor: 'rgb(4, 5, 6)',
      padding: 80,
      paddingX: 40,
    }

    const wrapper = mount(StarterBlockPreview, {
      props: { settings, isEditor: false },
    })

    const section = wrapper.find('.dsf-starter-block')
    expect(section.exists()).toBe(true)
    expect(section.attributes('style')).toContain('padding: 80px 40px;')
    expect(section.attributes('style')).toContain('background-color: rgb(1, 2, 3);')
    expect(section.attributes('style')).toContain('color: rgb(4, 5, 6);')

    const button = wrapper.find('.dsf-starter-block__btn')
    expect(button.attributes('href')).toBe('/start')
    expect(button.attributes('style')).toContain('background-color: rgb(10, 10, 10);')
  })
})
