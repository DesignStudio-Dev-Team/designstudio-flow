/**
 * Normalize a user-typed value into a safe HTML anchor id.
 *
 * Mirrors the server-side DSF_Ajax::sanitize_anchor_id() so the id the editor
 * shows and the id stored on save agree: lowercase, spaces/underscores → hyphen,
 * only [a-z0-9-] kept, collapsed and trimmed hyphens. A leading digit is
 * prefixed so the result is always a valid id/selector. Returns '' when nothing
 * usable remains.
 *
 * @param {string} raw
 * @returns {string}
 */
export function normalizeAnchorId(raw) {
  if (typeof raw !== 'string') return ''
  let id = raw
    .toLowerCase()
    .trim()
    .replace(/[\s_]+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '')
  if (id === '') return ''
  if (/^[0-9]/.test(id)) id = `s-${id}`
  return id
}

/**
 * The anchor id to render on a block wrapper, or undefined when none is set
 * (so Vue omits the attribute rather than emitting id="").
 *
 * @param {object} block
 * @returns {string|undefined}
 */
export function blockAnchorId(block) {
  const id = normalizeAnchorId(block?.anchorId || '')
  return id === '' ? undefined : id
}
