<template>
  <section class="dsf-store-login" :style="blockStyle">
    <div class="dsf-store-login__inner" :style="innerStyle">
      <div class="dsf-store-login__card">
        <header class="dsf-store-login__header">
          <p class="dsf-store-login__eyebrow">Customer account</p>
          <h1 class="dsf-store-login__heading">{{ settings.heading || 'Welcome back' }}</h1>
          <p v-if="settings.subheading" class="dsf-store-login__subheading">{{ settings.subheading }}</p>
        </header>

        <div v-if="showMock" class="dsf-store-login__mock" aria-hidden="true">
          <span></span><span></span><b></b>
        </div>
        <p v-if="showMock && isEditor" class="dsf-store-login__note">
          WooCommerce’s secure customer login form renders here on the live page.
        </p>
        <div v-show="!showMock" ref="hostEl" class="dsf-store-login__host"></div>
        <p v-if="!showMock && missing" class="dsf-store-login__note">
          The login form could not be loaded. This block requires WooCommerce.
        </p>

        <a
          v-if="settings.showRegisterLink !== false && accountUrl"
          class="dsf-store-login__register"
          :href="accountUrl"
          @click="isEditor && $event.preventDefault()"
        >Create an account</a>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useStoreFragment } from '../../utils/useStoreFragment'
import { useStoreContext } from '../../utils/useStoreContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const renderMode = inject('dsfRenderMode', null)
const showMock = computed(() => props.isEditor || renderMode === 'snapshot')
const hostEl = ref(null)
const { missing } = useStoreFragment('login', hostEl, () => !showMock.value)
const { store } = useStoreContext()
const accountUrl = computed(() => store.value?.urls?.account || '')

const blockStyle = computed(() => {
  const padding = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 48
  const style = {
    paddingTop: `${padding}px`,
    paddingBottom: `${padding}px`,
    '--dsf-store-login-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
  if (props.settings?.buttonColor) style['--dsf-store-login-button'] = props.settings.buttonColor
  if (props.settings?.buttonTextColor) style['--dsf-store-login-button-text'] = props.settings.buttonTextColor
  return style
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 520}px` }))
</script>

<style scoped>
.dsf-store-login { width: 100%; font-family: var(--dsf-theme-body-font, inherit); }
.dsf-store-login__inner { margin: 0 auto; }
.dsf-store-login__card { padding: clamp(1.5rem, 5vw, 3rem); border: 1px solid color-mix(in srgb, var(--dsf-store-login-accent) 16%, #dbe1e8); border-radius: 28px; background: linear-gradient(145deg, #fff, color-mix(in srgb, var(--dsf-store-login-accent) 5%, #fff)); box-shadow: 0 24px 60px rgb(15 23 42 / 10%); }
.dsf-store-login__header { margin-bottom: 1.75rem; }
.dsf-store-login__eyebrow { margin: 0 0 .45rem; color: var(--dsf-store-login-accent); font-size: .75rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.dsf-store-login__heading { margin: 0; color: var(--dsf-theme-text, #172033); font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(1.75rem, 4vw, 2.45rem); line-height: 1.05; }
.dsf-store-login__subheading, .dsf-store-login__note { margin: .7rem 0 0; color: #64748b; line-height: 1.55; }
.dsf-store-login__mock { display: grid; gap: 1rem; }
.dsf-store-login__mock span { height: 48px; border: 1px solid #dbe1e8; border-radius: 12px; background: #fff; }
.dsf-store-login__mock b { height: 50px; border-radius: 12px; background: var(--dsf-store-login-button, var(--dsf-store-login-accent)); }
.dsf-store-login__register { display: inline-block; margin-top: 1.25rem; color: var(--dsf-store-login-accent); font-weight: 700; text-decoration: none; }
.dsf-store-login__host :deep(.woocommerce-form-login) { border: 0; padding: 0; margin: 0; }
.dsf-store-login__host :deep(input.input-text) { width: 100%; min-height: 48px; border: 1px solid #cbd5e1; border-radius: 12px; padding: .75rem .9rem; }
.dsf-store-login__host :deep(button.button) { border: 0; border-radius: 12px; padding: .85rem 1.25rem; background: var(--dsf-store-login-button, var(--dsf-store-login-accent)); color: var(--dsf-store-login-button-text, #fff); font-weight: 800; }
</style>
