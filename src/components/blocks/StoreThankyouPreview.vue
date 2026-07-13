<template>
  <section v-if="visible" class="dsf-store-thankyou" :style="blockStyle">
    <div class="dsf-store-thankyou__card" :style="cardStyle">
      <span v-if="settings.showConfetti !== false" class="dsf-store-thankyou__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/></svg>
      </span>
      <h1 class="dsf-store-thankyou__heading">{{ settings.headingText || 'Thank you for your order!' }}</h1>
      <p v-if="settings.messageText" class="dsf-store-thankyou__message">{{ settings.messageText }}</p>
      <p v-if="isEditor" class="dsf-store-thankyou__hint">
        Shown only on the order-received page (place it above the Checkout block).
      </p>
    </div>
  </section>
  <!-- Off the order-received step: keep an inert root so the block wrapper
       (margins, height contract) always has a child. -->
  <span v-else class="dsf-store-thankyou__placeholder" hidden aria-hidden="true"></span>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const visible = computed(() => {
  if (props.isEditor) return true
  if (typeof window === 'undefined') return false
  return window.dsfFrontendData?.storeContext?.step === 'complete'
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 40
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-thankyou-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const cardStyle = computed(() => ({
  maxWidth: `${Number(props.settings?.maxWidth) || 900}px`,
  backgroundColor:
    props.settings?.backgroundColor ||
    'color-mix(in srgb, var(--dsf-thankyou-accent) 6%, var(--dsf-theme-surface, #fff))',
}))
</script>

<style scoped>
.dsf-store-thankyou {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-thankyou__card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  margin: 0 auto;
  padding: clamp(1.75rem, 4vw, 3rem);
  border-radius: 24px;
  border: 1px solid color-mix(in srgb, var(--dsf-thankyou-accent) 15%, transparent);
  text-align: center;
}

.dsf-store-thankyou__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: var(--dsf-thankyou-accent);
  color: #fff;
}

.dsf-store-thankyou__heading {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.6rem, 3vw, 2.3rem);
  font-weight: 800;
  letter-spacing: -0.02em;
}

.dsf-store-thankyou__message {
  margin: 0;
  max-width: 52ch;
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.65;
  opacity: 0.8;
}

.dsf-store-thankyou__hint {
  margin: 0.5rem 0 0;
  opacity: 0.55;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.8rem);
}
</style>
