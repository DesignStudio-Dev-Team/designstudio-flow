<template>
  <div
    class="dsf-store-mini-cart"
    :class="[
      `dsf-store-mini-cart--${placement}`,
      `dsf-store-mini-cart--${corner}`,
      { 'dsf-store-mini-cart--editor': isEditor },
    ]"
    :style="blockStyle"
  >
    <a
      v-show="visible"
      :href="cartUrl"
      class="dsf-store-mini-cart__pill"
      aria-label="View cart"
      @click="isEditor && $event.preventDefault()"
    >
      <svg class="dsf-store-mini-cart__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
      <!-- The count/subtotal class names are WooCommerce cart-fragment selectors:
           add_mini_cart_fragments() replaces them after AJAX add-to-cart. -->
      <span class="dsf-store-mini-cart__count">{{ count }}</span>
      <!-- subtotalHtml is Woo price markup, kses'd server-side (build_mini_cart_state). -->
      <span
        v-if="settings.showSubtotal !== false"
        class="dsf-store-mini-cart__subtotal"
        v-html="subtotalHtml"
      ></span>
    </a>
    <p v-if="isEditor && placement === 'floating'" class="dsf-store-mini-cart__hint">
      Floats in the {{ corner === 'bottom-left' ? 'bottom-left' : 'bottom-right' }} corner on the live page.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const placement = computed(() => (props.settings?.placement === 'inline' ? 'inline' : 'floating'))
const corner = computed(() => (props.settings?.corner === 'bottom-left' ? 'bottom-left' : 'bottom-right'))

const storeContext = computed(() => {
  if (typeof window === 'undefined') return null
  const ctx = window.dsfFrontendData?.storeContext
  return ctx && typeof ctx === 'object' ? ctx : null
})

const count = computed(() => {
  if (props.isEditor) return 2
  return Math.max(0, Number(storeContext.value?.miniCart?.count) || 0)
})

const subtotalHtml = computed(() => {
  if (props.isEditor) return '$128.00'
  return typeof storeContext.value?.miniCart?.subtotalHtml === 'string'
    ? storeContext.value.miniCart.subtotalHtml
    : ''
})

const cartUrl = computed(() => {
  const url = storeContext.value?.urls?.cart
  return typeof url === 'string' && url ? url : '#'
})

const visible = computed(() => {
  if (props.isEditor) return true
  if (props.settings?.hideWhenEmpty === false) return true
  return count.value > 0
})

const blockStyle = computed(() => {
  const style = {
    '--dsf-minicart-bg': props.settings?.buttonColor || 'var(--dsf-theme-primary, #2c5f5d)',
    '--dsf-minicart-color': props.settings?.buttonTextColor || '#ffffff',
  }
  return style
})
</script>

<style scoped>
.dsf-store-mini-cart {
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-mini-cart--inline {
  display: flex;
}

.dsf-store-mini-cart__pill {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.65rem 1.1rem;
  border-radius: 999px;
  background: var(--dsf-minicart-bg);
  color: var(--dsf-minicart-color);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 700;
  box-shadow: 0 10px 26px -10px color-mix(in srgb, var(--dsf-minicart-bg) 70%, rgba(0, 0, 0, 0.4));
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.dsf-store-mini-cart__pill:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px -10px color-mix(in srgb, var(--dsf-minicart-bg) 80%, rgba(0, 0, 0, 0.4));
}

.dsf-store-mini-cart__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.25);
  font-size: 0.75rem;
  line-height: 1;
}

.dsf-store-mini-cart__subtotal {
  font-weight: 600;
  opacity: 0.9;
}

.dsf-store-mini-cart__subtotal :deep(.woocommerce-Price-amount) {
  color: inherit;
}

/* Floating bubble: fixed on the live page, inline preview in the editor. */
.dsf-store-mini-cart--floating:not(.dsf-store-mini-cart--editor) {
  position: fixed;
  bottom: 22px;
  z-index: 990;
}

.dsf-store-mini-cart--floating.dsf-store-mini-cart--bottom-right:not(.dsf-store-mini-cart--editor) {
  right: 22px;
}

.dsf-store-mini-cart--floating.dsf-store-mini-cart--bottom-left:not(.dsf-store-mini-cart--editor) {
  left: 22px;
}

.dsf-store-mini-cart--editor {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0;
}

.dsf-store-mini-cart__hint {
  margin: 0;
  opacity: 0.55;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.8rem);
}
</style>
