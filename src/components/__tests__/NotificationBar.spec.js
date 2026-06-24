import { beforeEach, describe, expect, it, vi } from 'vitest'
import { initNotificationBar } from '../../frontend/notificationBar.js'

function clearDismissalCookie() {
  document.cookie = 'dsf_notification_dismissed=; Max-Age=0; Path=/'
  document.cookie = 'dsf_notification_campaign1=; Max-Age=0; Path=/'
}

describe('site-wide notification bar', () => {
  beforeEach(() => {
    vi.useRealTimers()
    clearDismissalCookie()
    document.body.innerHTML = ''
  })

  it('moves the footer fallback to the beginning of the page', () => {
    document.body.innerHTML = `
      <main id="content"></main>
      <aside data-dsf-notification-bar data-footer-fallback="true"></aside>
    `

    const bar = initNotificationBar()

    expect(bar).not.toBeNull()
    expect(document.body.firstElementChild).toBe(bar)
  })

  it('remembers dismissal and removes the bar', () => {
    vi.useFakeTimers()
    document.body.innerHTML = `
      <aside data-dsf-notification-bar data-cookie-name="dsf_notification_campaign1" data-cookie-hours="24">
        <button data-dsf-notification-close type="button">Close</button>
      </aside>
    `

    const bar = initNotificationBar()
    bar.querySelector('button').click()

    expect(document.cookie).toContain('dsf_notification_campaign1=1')
    expect(bar.classList.contains('dsf-notification-bar--closing')).toBe(true)
    vi.advanceTimersByTime(220)
    expect(document.querySelector('[data-dsf-notification-bar]')).toBeNull()
  })

  it('does not show a bar when the visitor already dismissed it', () => {
    document.cookie = 'dsf_notification_campaign1=1; Path=/; SameSite=Lax'
    document.body.innerHTML = '<aside data-dsf-notification-bar data-cookie-name="dsf_notification_campaign1"></aside>'

    expect(initNotificationBar()).toBeNull()
    expect(document.querySelector('[data-dsf-notification-bar]')).toBeNull()
  })
})
