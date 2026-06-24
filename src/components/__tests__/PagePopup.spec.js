import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PagePopup from '../common/PagePopup.vue'

describe('PagePopup', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    document.cookie = 'dsf_popup_dismissed_42=; Max-Age=0; Path=/'
    document.body.style.overflow = ''
  })

  afterEach(() => {
    vi.useRealTimers()
    document.cookie = 'dsf_popup_dismissed_42=; Max-Age=0; Path=/'
    document.body.innerHTML = ''
  })

  it('opens after the configured delay and stores dismissal in a page cookie', async () => {
    const wrapper = mount(PagePopup, {
      props: {
        postId: 42,
        settings: {
          enabled: true,
          delaySeconds: 2,
          headline: 'Summer offer',
          body: '<p>Save today.</p>',
          cookieDuration: 1,
          cookieUnit: 'hours',
          showClose: true,
        },
      },
      attachTo: document.body,
    })

    expect(document.querySelector('.dsf-page-popup')).toBeNull()
    await vi.advanceTimersByTimeAsync(2000)

    expect(document.querySelector('.dsf-page-popup__headline')?.textContent).toBe('Summer offer')
    expect(document.body.style.overflow).toBe('hidden')

    document.querySelector('.dsf-page-popup__close').click()
    await wrapper.vm.$nextTick()

    expect(document.cookie).toContain('dsf_popup_dismissed_42=1')
    expect(document.body.style.overflow).toBe('')
    wrapper.unmount()
  })

  it('does not render outside its scheduled date range', async () => {
    vi.setSystemTime(new Date('2026-06-18T12:00:00'))
    mount(PagePopup, {
      props: {
        postId: 42,
        settings: {
          enabled: true,
          delaySeconds: 0,
          startDate: '2026-06-19T00:00',
        },
      },
      attachTo: document.body,
    })

    await vi.runAllTimersAsync()
    expect(document.querySelector('.dsf-page-popup')).toBeNull()
  })

  it('renders an image-only popup as a linked creative', async () => {
    mount(PagePopup, {
      props: {
        postId: 42,
        settings: {
          enabled: true,
          type: 'image',
          image: 'https://example.com/offer.jpg',
          imageAlt: 'Seasonal offer',
          buttonText: 'Shop offer',
          buttonUrl: 'https://example.com/shop',
          delaySeconds: 0,
        },
      },
      attachTo: document.body,
    })

    await vi.runAllTimersAsync()
    expect(document.querySelector('.dsf-page-popup__full-image')?.getAttribute('alt')).toBe('Seasonal offer')
    expect(document.querySelector('.dsf-page-popup__image-link')?.getAttribute('href')).toBe('https://example.com/shop')
  })
})
