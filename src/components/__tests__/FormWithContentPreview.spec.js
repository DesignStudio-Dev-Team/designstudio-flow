import { describe, it, expect, vi } from 'vitest'
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

  it('injects late Gravity Forms layout overrides for embedded forms', async () => {
    document.getElementById('dsf-form-with-content-gravity-overrides')?.remove()

    const wrapper = mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          renderedEmbedHtml: '<form class="gform_wrapper gravity-theme"><div class="gchoice"><label>Choice</label></div></form>',
        },
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 0))

    const style = document.getElementById('dsf-form-with-content-gravity-overrides')
    expect(style).not.toBeNull()
    expect(wrapper.find('[data-dsf-form-with-content-form]').exists()).toBe(true)
    expect(style.textContent).toContain('[data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme *')
    expect(style.textContent).toContain('[data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] legend')
    expect(style.textContent).toContain('.gform_wrapper.gravity-theme legend.gfield_label')
    expect(style.textContent).toContain('margin-bottom: 0 !important')
    expect(style.textContent).toContain('width: 16px !important')
    expect(style.textContent).not.toContain('font-size: 16px !important')
  })

  it('sets Gravity Forms legend spacing inline so theme label rules cannot win', async () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          renderedEmbedHtml: '<form class="gform_wrapper gravity-theme"><legend class="gfield_label">Options</legend></form>',
        },
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 0))

    const legend = wrapper.find('legend.gfield_label').element
    expect(['0', '0px']).toContain(legend.style.getPropertyValue('margin-bottom'))
    expect(legend.style.getPropertyPriority('margin-bottom')).toBe('important')
    expect(['0', '0px']).toContain(legend.style.getPropertyValue('margin-block-end'))
  })

  it('keeps bold markup in the rich text column', () => {
    const wrapper = mount(FormWithContentPreview, {
      props: {
        settings: {
          content: '<p><b>Your dream backyard starts here.</b></p><p><strong>We will be in touch.</strong></p>',
        },
      },
    })

    expect(wrapper.find('.dsf-form-with-content__content b').exists()).toBe(true)
    expect(wrapper.find('.dsf-form-with-content__content strong').exists()).toBe(true)
  })

  it('injects server-rendered embed scripts after frontend markup mounts', async () => {
    const appendSpy = vi.spyOn(document.body, 'appendChild').mockImplementation((node) => node)

    mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          renderedEmbedHtml: '<form class="rendered-form"></form>',
          renderedEmbedScripts: [{ code: 'window.__gravityInit = true;' }],
        },
      },
    })

    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(appendSpy).toHaveBeenCalledWith(expect.any(HTMLScriptElement))
    const script = appendSpy.mock.calls.find(([node]) => node instanceof HTMLScriptElement)?.[0]
    expect(script.text).toContain('window.__gravityInit = true;')

    appendSpy.mockRestore()
  })

  it('waits for Gravity Forms runtime before injecting Gravity scripts', async () => {
    vi.useFakeTimers()
    const originalGform = window.gform
    const originalGlobalGform = globalThis.gform
    delete window.gform
    delete globalThis.gform
    const appendSpy = vi.spyOn(document.body, 'appendChild').mockImplementation((node) => node)

    mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          renderedEmbedHtml: '<form class="gform_wrapper"></form>',
          renderedEmbedScripts: [{ code: 'window.gform.initializeOnLoaded(function(){});' }],
        },
      },
    })

    await vi.runOnlyPendingTimersAsync()
    expect(appendSpy).not.toHaveBeenCalled()

    window.gform = { initializeOnLoaded: vi.fn() }
    globalThis.gform = window.gform
    await vi.runOnlyPendingTimersAsync()

    expect(appendSpy).toHaveBeenCalledWith(expect.any(HTMLScriptElement))

    appendSpy.mockRestore()
    if (originalGform === undefined) {
      delete window.gform
    } else {
      window.gform = originalGform
    }
    if (originalGlobalGform === undefined) {
      delete globalThis.gform
    } else {
      globalThis.gform = originalGlobalGform
    }
    vi.useRealTimers()
  })

  it('scrolls to the embedded Gravity form top when a page loads', async () => {
    const scrollSpy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})

    const wrapper = mount(FormWithContentPreview, {
      props: {
        isEditor: false,
        settings: {
          formSource: 'embed',
          renderedEmbedHtml: '<form id="gform_1" class="gform_wrapper"></form>',
        },
      },
      attachTo: document.body,
    })

    await new Promise((resolve) => setTimeout(resolve, 0))

    wrapper.find('.dsf-form-with-content__form-frontend').element.getBoundingClientRect = () => ({
      top: 240,
      left: 0,
      right: 0,
      bottom: 640,
      width: 400,
      height: 400,
      x: 0,
      y: 240,
      toJSON: () => {},
    })

    document.dispatchEvent(new CustomEvent('gform/ajax/post_page_change', {
      detail: { formId: 1 },
    }))

    expect(scrollSpy).toHaveBeenCalledWith({
      top: 216,
      behavior: 'smooth',
    })

    wrapper.unmount()
    scrollSpy.mockRestore()
  })
})
