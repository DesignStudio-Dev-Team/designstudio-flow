<template>
  <section
    class="dsf-store-cart"
    :class="{ 'dsf-store-cart--hide-cross-sells': settings.showCrossSells === false }"
    :style="blockStyle"
  >
    <div class="dsf-store-cart__inner" :style="innerStyle">
      <!-- Editor / snapshot: a mock preview (a real cart is per-visitor). -->
      <div v-if="showMock" class="dsf-store-cart__mock" aria-hidden="true">
        <div class="dsf-store-cart__mock-items">
          <div v-for="i in 2" :key="i" class="dsf-store-cart__mock-row">
            <span class="dsf-store-cart__mock-img"></span>
            <span class="dsf-store-cart__mock-lines"><i></i><i></i></span>
            <span class="dsf-store-cart__mock-qty"></span>
            <span class="dsf-store-cart__mock-price"></span>
          </div>
        </div>
        <div class="dsf-store-cart__mock-totals">
          <i></i><i></i>
          <b></b>
        </div>
      </div>
      <p v-if="showMock && isEditor" class="dsf-store-cart__note">
        The visitor's live WooCommerce cart renders here. Place this block on your cart page.
      </p>

      <!-- Frontend: the live Woo cart fragment is adopted into this host. -->
      <div v-show="!showMock" ref="hostEl" class="dsf-store-cart__host"></div>
      <p v-if="!showMock && missing" class="dsf-store-cart__note">
        The cart could not be loaded here. This block works on a DesignStudio Flow page with WooCommerce active.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, inject } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useStoreFragment } from '../../utils/useStoreFragment'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const renderMode = inject('dsfRenderMode', null)
const showMock = computed(() => props.isEditor || renderMode === 'snapshot')

const hostEl = ref(null)
const { missing } = useStoreFragment('cart', hostEl, () => !showMock.value)

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 24
  const style = {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-store-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
  if (props.settings?.buttonColor) style['--dsf-store-btn-bg'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-store-btn-color'] = props.settings.buttonTextColor
  return style
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1100
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-store-cart {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-cart__inner {
  margin: 0 auto;
}

/* ---- Editor mock ---- */
.dsf-store-cart__mock {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr);
  gap: 1.5rem;
  align-items: start;
}

.dsf-store-cart__mock-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-store-cart__mock-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 14px;
}

.dsf-store-cart__mock-img {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  background: var(--dsf-gray-100, #f3f4f6);
  flex-shrink: 0;
}

.dsf-store-cart__mock-lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dsf-store-cart__mock-lines i {
  height: 10px;
  border-radius: 3px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-cart__mock-lines i:first-child { width: 60%; }
.dsf-store-cart__mock-lines i:last-child { width: 35%; }

.dsf-store-cart__mock-qty {
  width: 64px;
  height: 34px;
  border-radius: 999px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-cart__mock-price {
  width: 52px;
  height: 12px;
  border-radius: 3px;
  background: var(--dsf-gray-200, #e5e7eb);
}

.dsf-store-cart__mock-totals {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-cart__mock-totals i {
  height: 10px;
  border-radius: 3px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-cart__mock-totals b {
  height: 42px;
  border-radius: 999px;
  background: var(--dsf-store-accent);
  margin-top: 0.5rem;
}

.dsf-store-cart__note {
  margin: 0.75rem 0 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

/* ---- Live Woo cart restyle (adopted fragment) ---- */
.dsf-store-cart__host :deep(table.shop_table) {
  width: 100%;
  border-collapse: collapse;
  border: 0;
}

.dsf-store-cart__host :deep(table.shop_table th),
.dsf-store-cart__host :deep(table.shop_table td) {
  padding: 0.85rem 0.6rem;
  border: 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  text-align: left;
  vertical-align: middle;
}

.dsf-store-cart__host :deep(.product-thumbnail img) {
  width: 64px;
  height: 64px;
  object-fit: cover;
  border-radius: 12px;
}

.dsf-store-cart__host :deep(.product-name a) {
  color: inherit;
  font-weight: 600;
  text-decoration: none;
}

.dsf-store-cart__host :deep(.product-name a:hover) {
  color: var(--dsf-store-accent);
}

.dsf-store-cart__host :deep(a.remove) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 999px;
  color: #b91c1c !important;
  background: rgba(185, 28, 28, 0.08);
  text-decoration: none;
  font-size: 1.1rem;
  line-height: 1;
}

.dsf-store-cart__host :deep(input.qty) {
  width: 4.2rem;
  padding: 0.5rem 0.4rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  text-align: center;
  font: inherit;
}

.dsf-store-cart__host :deep(#coupon_code) {
  padding: 0.55rem 0.85rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  font: inherit;
  width: 11rem;
}

.dsf-store-cart__host :deep(.button),
.dsf-store-cart__host :deep(button[type='submit']) {
  padding: 0.6rem 1.3rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  background: #fff;
  color: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.dsf-store-cart__host :deep(.button:hover) {
  border-color: var(--dsf-store-accent);
  color: var(--dsf-store-accent);
}

.dsf-store-cart__host :deep(.cart_totals) {
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-cart__host :deep(.cart_totals h2) {
  margin: 0 0 0.75rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.35rem);
  font-weight: 800;
}

.dsf-store-cart__host :deep(.checkout-button) {
  display: block;
  width: 100%;
  text-align: center;
  background: var(--dsf-store-btn-bg, var(--dsf-store-accent)) !important;
  color: var(--dsf-store-btn-color, #fff) !important;
  border: 0 !important;
  border-radius: 999px;
  padding: 0.9rem 1.5rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dsf-store-cart__host :deep(.checkout-button:hover) {
  opacity: 0.92;
  transform: translateY(-1px);
}

.dsf-store-cart--hide-cross-sells .dsf-store-cart__host :deep(.cross-sells) {
  display: none;
}

.dsf-store-cart__host :deep(.cart-empty),
.dsf-store-cart__host :deep(.wc-empty-cart-message) {
  padding: 2rem 1rem;
  text-align: center;
  opacity: 0.75;
}

@media (max-width: 760px) {
  .dsf-store-cart__mock { grid-template-columns: 1fr; }
}
</style>
