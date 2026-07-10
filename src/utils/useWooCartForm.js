import { onMounted, onBeforeUnmount } from 'vue'

/**
 * Initialize WooCommerce's variation form inside an element that Vue injected.
 *
 * Woo's add-to-cart-variation script binds to `.variations_form` on DOM ready,
 * but our blocks insert the (server-rendered, sanitized) form afterwards — so
 * each block that embeds the form calls this to bind it once jQuery + the Woo
 * plugin are available. Frontend only: pass `enabled: false` in the editor or
 * snapshot render. Retries briefly because the Woo script loads in the footer.
 *
 * @param {import('vue').Ref<HTMLElement|null>} rootEl Element containing the form.
 * @param {() => boolean} enabled Whether init should run (frontend with a form).
 */
export function useWooCartForm(rootEl, enabled) {
  let retryTimer = null
  let attempts = 0

  function init() {
    const $ = typeof window !== 'undefined' ? window.jQuery : null
    if (!$ || typeof $.fn?.wc_variation_form !== 'function') {
      if (attempts++ < 20) retryTimer = window.setTimeout(init, 150)
      return
    }
    const root = rootEl.value
    if (!root) return
    $(root)
      .find('.variations_form')
      .each(function initOne() {
        const $form = $(this)
        if (!$form.data('dsf-wc-init')) {
          $form.wc_variation_form()
          $form.data('dsf-wc-init', true)
        }
      })
  }

  onMounted(() => {
    if (enabled()) init()
  })

  onBeforeUnmount(() => {
    if (retryTimer) window.clearTimeout(retryTimer)
  })
}
