import { computed } from 'vue'

/**
 * The per-request site payload (login endpoints, current user, search results)
 * localized by DSF_Site_Pages for pages that use site blocks. Editor and
 * snapshot renders have none — blocks show their mock states instead.
 *
 * @returns {{ site: import('vue').ComputedRef<object|null> }}
 */
export function useSiteContext() {
  const site = computed(() => {
    if (typeof window === 'undefined') return null
    const ctx = window.dsfFrontendData?.siteContext
    return ctx && typeof ctx === 'object' ? ctx : null
  })

  return { site }
}
