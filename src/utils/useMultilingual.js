import { computed } from 'vue'

/**
 * Read the server-resolved language state.
 *
 * The server is the only place that decides which languages exist and where
 * they point. Components read that decision; they never infer a language from
 * the URL or build a sibling link themselves.
 */
export function useMultilingual() {
  const data = () =>
    (typeof window !== 'undefined' && (window.dsfFrontendData || window.dsfEditorData)) || {}

  const language = computed(() => data().language || {})

  const switcherItems = computed(() => {
    const items = data().languageSwitcher
    return Array.isArray(items) ? items : []
  })

  /**
   * Whether a header should render the switcher.
   *
   * On the frontend it takes at least two resolved targets to be useful. In the
   * editor the control appears as soon as multilingual mode is on, so the
   * layout can be designed before any translation exists.
   */
  const multilingualActive = computed(() => {
    const state = language.value
    if (!state || !state.active) {
      return false
    }
    if (switcherItems.value.length > 1) {
      return true
    }
    return Array.isArray(state.list) && state.list.length > 1
  })

  return { language, switcherItems, multilingualActive }
}

export default useMultilingual
