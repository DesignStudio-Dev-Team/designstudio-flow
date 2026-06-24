import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { useBlockMedia } from '../../utils/useBlockMedia'
import LandingHeroPreview from '../blocks/LandingHeroPreview.vue'
import LandingProductStoryPreview from '../blocks/LandingProductStoryPreview.vue'

describe('useBlockMedia', () => {
  it('defaults to mockup mode and resolves image/video sources', () => {
    expect(useBlockMedia(() => ({})).mode.value).toBe('mockup')

    const image = useBlockMedia(() => ({ mediaType: 'image', mediaImage: 'https://x/a.jpg' }))
    expect(image.mode.value).toBe('image')
    expect(image.hasMedia.value).toBe(true)

    const file = useBlockMedia(() => ({ mediaType: 'video', mediaVideo: 'https://x/a.mp4' }))
    expect(file.videoFileUrl.value).toBe('https://x/a.mp4')
    expect(file.videoEmbedUrl.value).toBe('')

    const youtube = useBlockMedia(() => ({ mediaType: 'video', mediaVideo: 'https://youtu.be/abc123' }))
    expect(youtube.videoFileUrl.value).toBe('')
    expect(youtube.videoEmbedUrl.value).toContain('youtube.com/embed/abc123')
  })

  it('honors custom key prefixes', () => {
    const media = useBlockMedia(() => ({ formsMediaType: 'image', formsImage: 'a.png' }), {
      typeKey: 'formsMediaType',
      imageKey: 'formsImage',
      videoKey: 'formsVideo',
    })
    expect(media.mode.value).toBe('image')
    expect(media.imageUrl.value).toBe('a.png')
  })
})

describe('landing blocks media overrides the built-in mockup', () => {
  it('hero shows an image instead of the studio mockup when set', () => {
    const wrapper = mount(LandingHeroPreview, {
      props: { isEditor: true, settings: { title: 'Hero', mediaType: 'image', mediaImage: 'https://x/hero.jpg' } },
    })
    expect(wrapper.find('img.dsf-block-media__el').exists()).toBe(true)
    expect(wrapper.find('.dsf-studio').exists()).toBe(false)
  })

  it('hero keeps the studio mockup when no media is set', () => {
    const wrapper = mount(LandingHeroPreview, {
      props: { isEditor: true, settings: { title: 'Hero' } },
    })
    expect(wrapper.find('.dsf-studio').exists()).toBe(true)
  })

  it('product story embeds a video when a YouTube url is provided', () => {
    const wrapper = mount(LandingProductStoryPreview, {
      props: { isEditor: true, settings: { variant: 'editor', title: 'Story', mediaType: 'video', mediaVideo: 'https://youtu.be/abc123' } },
    })
    expect(wrapper.find('iframe.dsf-block-media__el--embed').exists()).toBe(true)
    expect(wrapper.find('.dsf-story-ui--editor').exists()).toBe(false)
  })
})
