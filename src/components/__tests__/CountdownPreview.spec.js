import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CountdownPreview from '../blocks/CountdownPreview.vue'
import SettingField from '../SettingField.vue'

const openModalMock = vi.hoisted(() => vi.fn())

vi.mock('../common/useFlowModal', () => ({
  useFlowModal: () => ({ openModal: openModalMock }),
}))

describe('CountdownPreview', () => {
  afterEach(() => {
    vi.useRealTimers()
    openModalMock.mockReset()
  })

  it('renders countdown units from the target date', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-01-01T00:00:00Z'))

    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          title: 'Default title here',
          targetDate: '2026-01-03T03:04:05Z',
        },
      },
    })

    const numbers = wrapper.findAll('.dsf-countdown-preview__number').map((node) => node.text())
    expect(numbers).toEqual(['02', '03', '04', '05'])
    expect(wrapper.text()).toContain('Default title here')
  })

  it('shows the expired message after the countdown ends', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-01-03T00:00:00Z'))

    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          targetDate: '2026-01-01T00:00:00Z',
          expiredMessage: 'This is out now.',
        },
      },
    })

    expect(wrapper.find('.dsf-countdown-preview__expired').text()).toBe('This is out now.')
    expect(wrapper.find('.dsf-countdown-preview__timer').exists()).toBe(false)
  })

  it('can flip the media to the left', () => {
    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          mediaPosition: 'left',
        },
      },
    })

    expect(wrapper.find('.dsf-countdown-preview__inner').classes())
      .toContain('dsf-countdown-preview__inner--media-left')
  })

  it('renders direct video files in video mode', () => {
    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          mediaType: 'video',
          video: 'https://example.com/countdown.webm',
          image: 'https://example.com/poster.jpg',
        },
      },
    })

    expect(wrapper.find('video').exists()).toBe(true)
    expect(wrapper.find('video').attributes('poster')).toBe('https://example.com/poster.jpg')
    expect(wrapper.find('video source').attributes('type')).toBe('video/webm')
  })

  it('uses a calendar and time picker for the target date setting', () => {
    const wrapper = mount(SettingField, {
      props: {
        config: { type: 'datetime', label: 'Countdown Target Date', step: 60 },
        fieldKey: 'targetDate',
        value: '2026-08-15T14:30',
      },
    })

    const input = wrapper.find('input[type="datetime-local"]')
    expect(input.exists()).toBe(true)
    expect(input.attributes('step')).toBe('60')
    expect(input.element.value).toBe('2026-08-15T14:30')
  })

  it('opens configured modal content from the CTA', async () => {
    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          buttonText: 'Default button text',
          buttonAction: 'modal',
          buttonModalLayout: 'drawer',
          buttonModalContentType: 'wysiwyg',
          buttonModalContent: '<p>Default modal content.</p>',
        },
      },
    })

    await wrapper.find('.dsf-countdown-preview__button').trigger('click')
    expect(openModalMock).toHaveBeenCalledWith({
      layout: 'drawer',
      contentType: 'wysiwyg',
      content: '<p>Default modal content.</p>',
    })
  })

  it('rejects executable CTA and media URLs', () => {
    const wrapper = mount(CountdownPreview, {
      props: {
        settings: {
          buttonText: 'Default button text',
          buttonAction: 'link',
          buttonUrl: 'javascript:alert(1)',
          mediaType: 'image',
          image: 'javascript:alert(2)',
        },
      },
    })

    expect(wrapper.find('.dsf-countdown-preview__button').attributes('href')).toBe('#')
    expect(wrapper.find('.dsf-countdown-preview__media img').exists()).toBe(false)
  })
})
