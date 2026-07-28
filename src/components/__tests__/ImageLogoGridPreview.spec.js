import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ImageLogoGridPreview from '../blocks/ImageLogoGridPreview.vue'

describe('ImageLogoGridPreview', () => {
  const settings = {
    title: 'The hottest brands',
    description: 'Models for every budget.',
    items: [
      { image: 'https://cdn.example.test/tub.jpg', logo: 'https://cdn.example.test/logo.png', url: '/tubs' },
      { image: 'https://cdn.example.test/tub-2.jpg', logo: 'https://cdn.example.test/logo-2.png' },
    ],
  }

  it('renders image and logo panels for each card', () => {
    const wrapper = mount(ImageLogoGridPreview, { props: { settings } })
    expect(wrapper.findAll('.dsf-image-logo-grid__card')).toHaveLength(2)
    expect(wrapper.findAll('.dsf-image-logo-grid__image')).toHaveLength(2)
    expect(wrapper.findAll('.dsf-image-logo-grid__logo')).toHaveLength(2)
  })

  it('supports a second row by rendering up to eight cards', () => {
    const wrapper = mount(ImageLogoGridPreview, { props: { settings: { ...settings, items: Array.from({ length: 10 }, (_, index) => ({ image: `https://cdn.example.test/${index}.jpg`, logo: `https://cdn.example.test/${index}.png` })) } } })
    expect(wrapper.findAll('.dsf-image-logo-grid__card')).toHaveLength(8)
  })

  it('uses responsive grid patterns with balanced final rows', () => {
    for (const [count, columns] of [[1, 1], [2, 2], [3, 3], [4, 4], [5, 4], [6, 4], [8, 4]]) {
      const wrapper = mount(ImageLogoGridPreview, { props: { settings: { ...settings, items: Array.from({ length: count }, () => ({ image: '', logo: '' })) } } })
      expect(wrapper.attributes('style')).toContain(`--dsf-image-logo-columns: ${columns}`)
    }

    const sixCardWrapper = mount(ImageLogoGridPreview, { props: { settings: { ...settings, items: Array.from({ length: 6 }, () => ({ image: '', logo: '' })) } } })
    expect(sixCardWrapper.classes()).toContain('dsf-image-logo-grid--count-6')
    const sevenCardWrapper = mount(ImageLogoGridPreview, { props: { settings: { ...settings, items: Array.from({ length: 7 }, () => ({ image: '', logo: '' })) } } })
    expect(sevenCardWrapper.classes()).toContain('dsf-image-logo-grid--count-7')
  })

  it('makes builder text inline-editable and keeps card links inert', async () => {
    const wrapper = mount(ImageLogoGridPreview, { props: { isEditor: true, settings } })
    expect(wrapper.find('.dsf-image-logo-grid__title').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-image-logo-grid__description').attributes('contenteditable')).toBe('true')
    await wrapper.find('.dsf-image-logo-grid__card').trigger('click')
    expect(wrapper.find('.dsf-image-logo-grid__card').attributes('href')).toBe('/tubs')
  })

  it('does not render dangerous URLs', () => {
    const wrapper = mount(ImageLogoGridPreview, { props: { settings: { ...settings, items: [{ image: 'javascript:alert(1)', logo: 'javascript:alert(2)', url: 'javascript:alert(3)' }] } } })
    expect(wrapper.find('.dsf-image-logo-grid__image').exists()).toBe(false)
    expect(wrapper.find('.dsf-image-logo-grid__logo').exists()).toBe(false)
  })
})
