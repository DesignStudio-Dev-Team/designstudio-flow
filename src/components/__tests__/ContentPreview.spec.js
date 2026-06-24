import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ContentPreview from '../blocks/ContentPreview.vue'

describe('ContentPreview', () => {
  it('renders WYSIWYG content HTML', () => {
    const wrapper = mount(ContentPreview, {
      props: {
        settings: {
          content: '<h2>Hello</h2><p>Body copy</p>',
        },
      },
    })

    expect(wrapper.html()).toContain('<h2>Hello</h2>')
    expect(wrapper.text()).toContain('Body copy')
  })

  it('applies the configured max width', () => {
    const wrapper = mount(ContentPreview, {
      props: {
        settings: {
          maxWidth: 720,
        },
      },
    })

    expect(wrapper.find('.dsf-content-preview__inner').attributes('style'))
      .toContain('max-width: 720px;')
  })
})
