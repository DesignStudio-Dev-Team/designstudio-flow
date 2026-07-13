import { ref, onMounted } from 'vue'

/**
 * Adopt a server-rendered WooCommerce store fragment (cart / checkout / account)
 * into a Vue store block.
 *
 * The fragment is printed by PHP in a hidden container OUTSIDE the Vue mount
 * root (mounting replaces the root's children, which would destroy it). On
 * mount, the block MOVES the live DOM node into its own container — moved, not
 * re-rendered — so event bindings made by WooCommerce's scripts (checkout,
 * payment gateways) survive. Frontend only: pass `enabled: false` in the editor
 * and snapshot renders, which show a mock preview instead.
 *
 * A fragment is adopted at most once; a second block of the same type on the
 * page reports `missing` rather than stealing the node.
 *
 * @param {string} key Fragment key ('cart' | 'checkout' | 'account').
 * @param {import('vue').Ref<HTMLElement|null>} hostEl Element to adopt into.
 * @param {() => boolean} enabled Whether adoption should run.
 * @returns {{ adopted: import('vue').Ref<boolean>, missing: import('vue').Ref<boolean> }}
 */
export function useStoreFragment(key, hostEl, enabled) {
  const adopted = ref(false)
  const missing = ref(false)

  onMounted(() => {
    if (!enabled() || typeof document === 'undefined') return

    const fragment = document.querySelector(
      `[data-dsf-store-fragment="${key}"]:not([data-dsf-adopted])`
    )
    const host = hostEl.value

    if (!fragment || !host) {
      missing.value = true
      return
    }

    // Moving the node out of the hidden PHP container makes it visible in place;
    // the container itself stays hidden for any fragment no block adopts.
    fragment.setAttribute('data-dsf-adopted', 'true')
    host.appendChild(fragment)
    adopted.value = true
  })

  return { adopted, missing }
}
