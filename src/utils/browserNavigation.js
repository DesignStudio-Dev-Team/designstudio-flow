export function navigateToUrl(url) {
  if (typeof window === 'undefined') return
  window.location.assign(url)
}
