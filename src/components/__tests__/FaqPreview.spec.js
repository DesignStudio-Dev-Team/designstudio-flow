import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FaqPreview from '../blocks/FaqPreview.vue'

describe('FaqPreview', () => {
  const settings = {
    title: 'Frequently asked questions',
    maxWidth: 860,
    items: [
      {
        question: 'What is the best thing about Switzerland?',
        answer: '<p>I do not know, but the flag is a big plus.</p>',
      },
      {
        question: 'How do you make holy water?',
        answer: '<p>You boil the hell out of it.</p>',
      },
    ],
  }

  it('renders the FAQ title, first answer, and width setting', () => {
    const wrapper = mount(FaqPreview, {
      props: { settings },
    })

    expect(wrapper.find('.dsf-faq-preview__title').text()).toBe('Frequently asked questions')
    expect(wrapper.find('.dsf-faq-preview__inner').attributes('style')).toContain('max-width: 860px;')
    expect(wrapper.find('.dsf-faq-preview__answer').html()).toContain('flag is a big plus')
    expect(wrapper.findAll('.dsf-faq-preview__icon').at(0).text()).toBe('−')
    expect(wrapper.findAll('.dsf-faq-preview__icon').at(1).text()).toBe('+')
  })

  it('toggles FAQ items open and closed', async () => {
    const wrapper = mount(FaqPreview, {
      props: { settings },
    })

    await wrapper.findAll('.dsf-faq-preview__question').at(1).trigger('click')

    expect(wrapper.findAll('.dsf-faq-preview__answer')).toHaveLength(2)
    expect(wrapper.findAll('.dsf-faq-preview__icon').at(1).text()).toBe('−')

    await wrapper.findAll('.dsf-faq-preview__question').at(0).trigger('click')

    expect(wrapper.findAll('.dsf-faq-preview__answer')).toHaveLength(1)
    expect(wrapper.findAll('.dsf-faq-preview__icon').at(0).text()).toBe('+')
  })
})
