<template>
  <section
    class="dsf-store-account"
    :class="`dsf-store-account--nav-${navStyle}`"
    :style="blockStyle"
  >
    <div class="dsf-store-account__inner" :style="innerStyle">
      <!-- Editor / snapshot: a mock preview (the account area is per-visitor). -->
      <div v-if="showMock" class="dsf-store-account__mock" aria-hidden="true">
        <div class="dsf-store-account__mock-nav">
          <span v-for="i in 5" :key="i" :class="{ 'is-active': i === 1 }"></span>
        </div>
        <div class="dsf-store-account__mock-content">
          <div class="dsf-store-account__mock-heading"></div>
          <i></i><i></i><i></i>
        </div>
      </div>
      <p v-if="showMock && isEditor" class="dsf-store-account__note">
        WooCommerce's My Account area renders here — the dashboard for customers, the login form for guests.
      </p>

      <!-- Frontend: the live Woo account fragment is adopted into this host. -->
      <div v-show="!showMock" ref="hostEl" class="dsf-store-account__host"></div>
      <p v-if="!showMock && missing" class="dsf-store-account__note">
        The account area could not be loaded here. This block works on a DesignStudio Flow page with WooCommerce active.
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

const navStyle = computed(() => (props.settings?.navStyle === 'top' ? 'top' : 'side'))

const hostEl = ref(null)
const { missing } = useStoreFragment('account', hostEl, () => !showMock.value)

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 24
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-store-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1100
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-store-account {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-account__inner {
  margin: 0 auto;
}

/* ---- Editor mock ---- */
.dsf-store-account__mock {
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr);
  gap: 1.5rem;
}

.dsf-store-account--nav-top .dsf-store-account__mock {
  grid-template-columns: 1fr;
}

.dsf-store-account__mock-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dsf-store-account--nav-top .dsf-store-account__mock-nav {
  flex-direction: row;
}

.dsf-store-account__mock-nav span {
  height: 34px;
  border-radius: 10px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-account--nav-top .dsf-store-account__mock-nav span {
  flex: 1;
}

.dsf-store-account__mock-nav span.is-active {
  background: var(--dsf-store-accent);
}

.dsf-store-account__mock-content {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 1.25rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

.dsf-store-account__mock-heading {
  width: 35%;
  height: 14px;
  border-radius: 4px;
  background: var(--dsf-gray-200, #e5e7eb);
}

.dsf-store-account__mock-content i {
  height: 10px;
  border-radius: 3px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-store-account__note {
  margin: 0.75rem 0 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

/* ---- Live Woo account restyle (adopted fragment) ---- */
.dsf-store-account__host :deep(.woocommerce-MyAccount-navigation) {
  float: none;
  width: auto;
}

.dsf-store-account--nav-side .dsf-store-account__host :deep(.woocommerce) {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr);
  gap: 1.75rem;
  align-items: start;
}

.dsf-store-account__host :deep(.woocommerce-MyAccount-navigation ul) {
  display: flex;
  flex-direction: column;
  gap: 4px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.dsf-store-account--nav-top .dsf-store-account__host :deep(.woocommerce-MyAccount-navigation ul) {
  flex-direction: row;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
}

.dsf-store-account__host :deep(.woocommerce-MyAccount-navigation a) {
  display: block;
  padding: 0.6rem 0.9rem;
  border-radius: 10px;
  color: inherit;
  text-decoration: none;
  font-weight: 600;
  transition: background 0.15s ease, color 0.15s ease;
}

.dsf-store-account__host :deep(.woocommerce-MyAccount-navigation a:hover) {
  background: rgba(0, 0, 0, 0.05);
}

.dsf-store-account__host :deep(.woocommerce-MyAccount-navigation li.is-active a) {
  background: var(--dsf-store-accent);
  color: #fff;
}

.dsf-store-account__host :deep(.woocommerce-MyAccount-content) {
  float: none;
  width: auto;
  min-width: 0;
}

.dsf-store-account__host :deep(table.shop_table) {
  width: 100%;
  border-collapse: collapse;
  border: 0;
}

.dsf-store-account__host :deep(table.shop_table th),
.dsf-store-account__host :deep(table.shop_table td) {
  padding: 0.7rem 0.5rem;
  border: 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.07);
  text-align: left;
}

.dsf-store-account__host :deep(.input-text) {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 10px;
  font: inherit;
}

.dsf-store-account__host :deep(.button),
.dsf-store-account__host :deep(button[type='submit']) {
  padding: 0.7rem 1.5rem;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-store-accent);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.dsf-store-account__host :deep(.button:hover) {
  opacity: 0.92;
}

@media (max-width: 760px) {
  .dsf-store-account--nav-side .dsf-store-account__host :deep(.woocommerce) {
    grid-template-columns: 1fr;
  }

  .dsf-store-account__mock { grid-template-columns: 1fr; }
}
</style>
