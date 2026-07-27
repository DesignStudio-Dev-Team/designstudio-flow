import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MediaVisual from '../common/MediaVisual.vue'

describe('MediaVisual', () => {
  it('renders direct MP4 media muted, autoplaying, looping, and inline', () => {
    const wrapper = mount(MediaVisual, {
      props: { mode: 'video', video: 'https://cdn.example.test/hero.mp4' },
    })

    const video = wrapper.find('video')
    expect(video.exists()).toBe(true)
    expect(video.find('source').attributes('src')).toBe('https://cdn.example.test/hero.mp4')
    expect(video.attributes('autoplay')).toBeDefined()
    expect(video.element.muted).toBe(true)
    expect(video.element.loop).toBe(true)
    expect(video.element.playsInline).toBe(true)
  })

  it('rejects dangerous and non-file video URLs', () => {
    const wrapper = mount(MediaVisual, {
      props: { mode: 'video', video: 'javascript:alert(1)' },
    })

    expect(wrapper.find('video').exists()).toBe(false)
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('renders image mode safely', () => {
    const wrapper = mount(MediaVisual, {
      props: { mode: 'image', image: 'https://cdn.example.test/image.jpg', alt: 'Example' },
    })

    expect(wrapper.find('img').attributes('src')).toBe('https://cdn.example.test/image.jpg')
    expect(wrapper.find('img').attributes('alt')).toBe('Example')
  })
})
