import '../styles/notification-bar.css'

const DEFAULT_COOKIE_NAME = 'dsf_notification_dismissed'

function safeCookieName(value) {
  return typeof value === 'string' && /^dsf_notification_[a-zA-Z0-9_-]+$/.test(value)
    ? value
    : DEFAULT_COOKIE_NAME
}

function hasDismissalCookie(cookieName) {
  return document.cookie
    .split(';')
    .some((value) => value.trim().startsWith(`${cookieName}=`))
}

function setDismissalCookie(cookieName, hours) {
  const duration = Number.parseInt(hours, 10)
  const maxAge = Number.isFinite(duration) && duration > 0
    ? `; Max-Age=${duration * 60 * 60}`
    : ''
  const secure = window.location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${cookieName}=1; Path=/; SameSite=Lax${maxAge}${secure}`
}

export function initNotificationBar(root = document) {
  const bar = root.querySelector('[data-dsf-notification-bar]')
  if (!bar) return null
  const cookieName = safeCookieName(bar.dataset.cookieName)

  if (hasDismissalCookie(cookieName)) {
    bar.remove()
    return null
  }

  if (bar.dataset.footerFallback === 'true' && document.body.firstElementChild !== bar) {
    document.body.prepend(bar)
  }

  const close = bar.querySelector('[data-dsf-notification-close]')
  close?.addEventListener('click', () => {
    setDismissalCookie(cookieName, bar.dataset.cookieHours)
    bar.classList.add('dsf-notification-bar--closing')
    window.setTimeout(() => bar.remove(), 220)
  })

  return bar
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initNotificationBar(), { once: true })
} else {
  initNotificationBar()
}
