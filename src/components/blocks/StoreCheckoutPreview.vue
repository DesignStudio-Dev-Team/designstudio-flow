<template>
  <section
    class="dsf-store-checkout"
    :class="`dsf-store-checkout--${layout}`"
    :style="blockStyle"
  >
    <div class="dsf-store-checkout__inner" :style="innerStyle">
      <!-- Editor / snapshot: a mock preview (checkout is per-visitor). -->
      <div v-if="showMock" class="dsf-store-checkout__mock" aria-hidden="true">
        <div class="dsf-store-checkout__mock-fields">
          <div class="dsf-store-checkout__mock-heading"></div>
          <div class="dsf-store-checkout__mock-grid">
            <span v-for="i in 6" :key="i"></span>
          </div>
          <div class="dsf-store-checkout__mock-heading"></div>
          <div class="dsf-store-checkout__mock-grid">
            <span v-for="i in 2" :key="i"></span>
          </div>
        </div>
        <div class="dsf-store-checkout__mock-summary">
          <i></i><i></i><i></i>
          <b></b>
        </div>
      </div>
      <p v-if="showMock && isEditor" class="dsf-store-checkout__note">
        WooCommerce's live checkout — billing, shipping, and payment — renders here. Place this block on your checkout page.
      </p>

      <!-- Frontend: the live Woo checkout fragment is adopted into this host. -->
      <div v-show="!showMock" ref="hostEl" class="dsf-store-checkout__host"></div>
      <p v-if="!showMock && missing" class="dsf-store-checkout__note">
        The checkout could not be loaded here. This block works on a DesignStudio Flow page with WooCommerce active.
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

const layout = computed(() => (props.settings?.layout === 'stacked' ? 'stacked' : 'split'))

const hostEl = ref(null)
const { missing } = useStoreFragment('checkout', hostEl, () => !showMock.value)

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
  const maxWidth = Number(props.settings?.maxWidth) || 1140
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-store-checkout {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-checkout__inner {
  margin: 0 auto;
}

/* ---- Editor mock ---- */
.dsf-store-checkout__mock {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 1.5rem;
  align-items: start;
}

.dsf-store-checkout--stacked .dsf-store-checkout__mock {
  grid-template-columns: 1fr;
}

.dsf-store-checkout__mock-fields {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-checkout__mock-heading {
  width: 40%;
  height: 14px;
  border-radius: 4px;
  background: var(--dsf-gray-200, #e5e7eb);
}

.dsf-store-checkout__mock-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.dsf-store-checkout__mock-grid span {
  height: 40px;
  border-radius: 10px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-checkout__mock-summary {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-checkout__mock-summary i {
  height: 10px;
  border-radius: 3px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-checkout__mock-summary b {
  height: 44px;
  border-radius: 999px;
  background: var(--dsf-store-accent);
  margin-top: 0.5rem;
}

.dsf-store-checkout__note {
  margin: 0.75rem 0 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

/* ---- Live Woo checkout restyle (adopted fragment) ---- */
/* Split layout: customer fields left, order review right. */
.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout) {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 1.75rem;
  align-items: start;
}

.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > .col2-set),
.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #customer_details) {
  grid-column: 1;
}

.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review_heading),
.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review) {
  grid-column: 2;
}

.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review_heading) {
  grid-row: 1;
}

.dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review) {
  grid-row: 2;
  position: sticky;
  top: 24px;
}

.dsf-store-checkout__host :deep(#customer_details .col-1),
.dsf-store-checkout__host :deep(#customer_details .col-2) {
  float: none;
  width: auto;
}

.dsf-store-checkout__host :deep(h3),
.dsf-store-checkout__host :deep(#order_review_heading) {
  margin: 0 0 0.75rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.3rem);
  font-weight: 800;
}

.dsf-store-checkout__host :deep(.form-row) {
  margin-bottom: 0.85rem;
}

.dsf-store-checkout__host :deep(.form-row label) {
  display: block;
  margin-bottom: 0.3rem;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
}

.dsf-store-checkout__host :deep(.input-text),
.dsf-store-checkout__host :deep(select),
.dsf-store-checkout__host :deep(textarea) {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 10px;
  background: #fff;
  font: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.dsf-store-checkout__host :deep(.input-text:focus),
.dsf-store-checkout__host :deep(select:focus) {
  outline: none;
  border-color: var(--dsf-store-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dsf-store-accent) 18%, transparent);
}

.dsf-store-checkout__host :deep(#order_review) {
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  background: color-mix(in srgb, var(--dsf-store-accent) 3%, #fff);
}

.dsf-store-checkout__host :deep(table.shop_table) {
  width: 100%;
  border-collapse: collapse;
  border: 0;
}

.dsf-store-checkout__host :deep(table.shop_table th),
.dsf-store-checkout__host :deep(table.shop_table td) {
  padding: 0.6rem 0.4rem;
  border: 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.07);
  text-align: left;
}

.dsf-store-checkout__host :deep(.wc_payment_methods) {
  list-style: none;
  margin: 1rem 0;
  padding: 0;
}

.dsf-store-checkout__host :deep(.wc_payment_method) {
  padding: 0.6rem 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.dsf-store-checkout__host :deep(.payment_box) {
  margin-top: 0.5rem;
  padding: 0.75rem 0.9rem;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.04);
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

.dsf-store-checkout__host :deep(#place_order) {
  display: block;
  width: 100%;
  background: var(--dsf-store-btn-bg, var(--dsf-store-accent)) !important;
  color: var(--dsf-store-btn-color, #fff) !important;
  border: 0 !important;
  border-radius: 999px;
  padding: 0.95rem 1.5rem;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dsf-store-checkout__host :deep(#place_order:hover) {
  opacity: 0.92;
  transform: translateY(-1px);
}

/* Order received (thank-you) — same block, post-purchase state. */
.dsf-store-checkout__host :deep(.woocommerce-order-overview) {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  list-style: none;
  margin: 0 0 1.5rem;
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-checkout__host :deep(.woocommerce-order-overview li) {
  border: 0;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

@media (max-width: 860px) {
  .dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout) {
    grid-template-columns: 1fr;
  }

  .dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review_heading),
  .dsf-store-checkout--split .dsf-store-checkout__host :deep(form.checkout > #order_review) {
    grid-column: 1;
    grid-row: auto;
    position: static;
  }

  .dsf-store-checkout__mock { grid-template-columns: 1fr; }
}
</style>
