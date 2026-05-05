import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FormWithContentPreview from '../blocks/FormWithContentPreview.vue'

describe('FormWithContentPreview', () => {
  it('renders the logo over image media', () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        settings: {
          mediaType: 'image',
          logo: 'https://example.test/logo.png',
          image: 'https://example.test/backyard.jpg',
        },
      },
    })

    const mediaWrap = wrapper.find('.dsf-form-with-content__media-wrap')
    const logo = mediaWrap.find('.dsf-form-with-content__logo')
    const image = mediaWrap.find('.dsf-form-with-content__image')

    expect(logo.exists()).toBe(true)
    expect(logo.attributes('src')).toBe('https://example.test/logo.png')
    expect(image.exists()).toBe(true)
    expect(mediaWrap.element.firstElementChild).toBe(logo.element)
  })

  it('renders the logo over video media', () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        settings: {
          mediaType: 'video',
          logo: 'https://example.test/logo.png',
          videoFile: 'https://example.test/intro.mp4',
        },
      },
    })

    const mediaWrap = wrapper.find('.dsf-form-with-content__media-wrap')
    const logo = mediaWrap.find('.dsf-form-with-content__logo')
    const video = mediaWrap.find('.dsf-form-with-content__video--file')

    expect(logo.exists()).toBe(true)
    expect(video.exists()).toBe(true)
    expect(mediaWrap.element.firstElementChild).toBe(logo.element)
  })

  it('renders custom shortcode or embed content instead of the DSF form placeholder', () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        isEditor: true,
        settings: {
          formSource: 'embed',
          embedCode: '<iframe src="https://example.test/embed"></iframe>',
        },
      },
    })

    expect(wrapper.find('.dsf-form-with-content__form-placeholder').exists()).toBe(false)
    expect(wrapper.find('iframe').attributes('src')).toBe('https://example.test/embed')
  })

  it('prefers server-rendered embed content on the frontend', () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          embedCode: '[contact-form-7 id="1"]',
          renderedEmbedHtml: '<form class="rendered-form"></form>',
        },
      },
    })

    expect(wrapper.find('.rendered-form').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('[contact-form-7 id="1"]')
  })
})
