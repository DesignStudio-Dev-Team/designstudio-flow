const ALLOWED_PROTOCOLS = new Set(['http:', 'https:', 'mailto:', 'tel:'])

export function safePublicUrl(value, fallback = '#') {
  if (typeof value !== 'string') return fallback
  const url = value.trim()
  if (!url) return fallback
  if (/^#[A-Za-z][A-Za-z0-9_:.-]*$/.test(url) || url === '#') return url
  if ((url.startsWith('/') && !url.startsWith('//')) || url.startsWith('./') || url.startsWith('../')) return url

  try {
    const parsed = new URL(url)
    return ALLOWED_PROTOCOLS.has(parsed.protocol) ? url : fallback
  } catch {
    return fallback
  }
}
