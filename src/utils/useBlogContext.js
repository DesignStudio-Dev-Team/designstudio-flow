import { inject, computed, isRef } from 'vue'

// Shown in the blog-template editor before archive data loads, and as a safe
// fallback if a blog block ever renders without archive data (e.g. snapshots).
export const BLOG_ARCHIVE_PLACEHOLDER = Object.freeze({
  title: 'Blog',
  descriptionHtml: '',
  posts: [],
  total: 0,
  perPage: 9,
  currentPage: 1,
  totalPages: 1,
  pagination: [],
})

/**
 * Resolve the post-archive payload the blog blocks should render.
 *
 * Provided by the app root (a ref in the editor holding the sample archive, the
 * viewed archive on the frontend). Falls back to the localized window data (for
 * snapshot rendering) and finally to a safe placeholder so blocks never crash.
 *
 * @returns {{ archive: import('vue').ComputedRef<object> }}
 */
export function useBlogContext() {
  const injected = inject('dsfBlogContext', null)

  const archive = computed(() => {
    const fromInject = isRef(injected) ? injected.value : injected
    if (fromInject && typeof fromInject === 'object') return fromInject

    if (typeof window !== 'undefined') {
      const fromWindow =
        window.dsfFrontendData?.currentBlogArchive || window.dsfEditorData?.currentBlogArchive
      if (fromWindow && typeof fromWindow === 'object') return fromWindow
    }

    return BLOG_ARCHIVE_PLACEHOLDER
  })

  return { archive }
}
