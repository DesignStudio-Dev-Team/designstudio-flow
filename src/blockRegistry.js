/**
 * DesignStudio Flow — third-party block registry.
 *
 * The block preview components that ship with the plugin are compiled into the
 * editor/frontend bundle, so an add-on plugin cannot inject an SFC into it.
 * Instead, an add-on enqueues a small script (declaring `dsf-editor` or
 * `dsf-frontend-app` as a dependency) and calls `window.dsfFlow.registerBlock()`
 * to hand us a runtime Vue component keyed by the block `type`.
 *
 * The registry is a shallowReactive map: `getPreviewComponent()` reads from it
 * inside a render, so a registration that arrives *after* the app has mounted
 * still triggers a re-render and the block swaps out of the generic placeholder.
 * That removes any load-order coupling between our bundle and the add-on script.
 *
 * We also re-export the plugin's own Vue instance (`h`, `defineComponent`, the
 * reactivity helpers, …) on `window.dsfFlow.vue` so an add-on builds components
 * against the *same* Vue runtime — mixing two Vue copies breaks provide/inject
 * and reactivity.
 */
import {
  shallowReactive,
  h,
  defineComponent,
  ref,
  reactive,
  computed,
  watch,
  onMounted,
  onUnmounted,
  inject,
} from 'vue'

// type -> Vue component. shallowReactive so assigning a component is tracked
// (we don't want deep reactivity walking into the component internals).
const customBlocks = shallowReactive({})

/**
 * Register (or replace) the preview component for a block type.
 *
 * @param {string} type      Block id, e.g. "acme-quote". Must match the id used
 *                           in the PHP `register_block()` schema.
 * @param {object} component A Vue component. It receives the same props every
 *                           built-in preview does: `settings` (Object),
 *                           `isEditor` (Boolean), `blockId` (String),
 *                           `previewMode` (String breakpoint).
 * @returns {boolean} True when registered.
 */
export function registerBlock(type, component) {
  if (typeof type !== 'string' || type === '' || !component) {
    if (typeof console !== 'undefined') {
      console.warn('[dsfFlow] registerBlock requires a non-empty type and a component.')
    }
    return false
  }
  customBlocks[type] = component
  return true
}

/**
 * Resolve a registered custom component for a type, or null.
 * @param {string} type
 * @returns {object|null}
 */
export function getCustomBlock(type) {
  return customBlocks[type] || null
}

/** @returns {string[]} All registered custom block types. */
export function getRegisteredBlockTypes() {
  return Object.keys(customBlocks)
}

// Expose the public API on the global exactly once, sharing this bundle's Vue
// runtime so add-ons don't ship their own copy.
if (typeof window !== 'undefined') {
  const api = window.dsfFlow || {}
  api.registerBlock = registerBlock
  api.getCustomBlock = getCustomBlock
  api.getRegisteredBlockTypes = getRegisteredBlockTypes
  api.version = 1
  api.vue = { h, defineComponent, ref, reactive, computed, watch, onMounted, onUnmounted, inject }
  window.dsfFlow = api

  // Let add-ons that loaded *before* this module run their registrations now.
  const queued = Array.isArray(api._queue) ? api._queue : []
  api._queue = []
  queued.forEach((entry) => {
    if (entry && typeof entry === 'object') {
      registerBlock(entry.type, entry.component)
    }
  })
}
