<template>
  <nav class="dsf-store-steps" :style="blockStyle" aria-label="Checkout progress">
    <ol class="dsf-store-steps__list" :style="innerStyle">
      <li
        v-for="(step, i) in steps"
        :key="step.key"
        class="dsf-store-steps__step"
        :class="{ 'is-done': i < currentIndex, 'is-current': i === currentIndex }"
        :aria-current="i === currentIndex ? 'step' : undefined"
      >
        <component
          :is="stepTag(step, i)"
          class="dsf-store-steps__label"
          v-bind="stepTag(step, i) === 'a' ? { href: step.url } : {}"
          @click="stepTag(step, i) === 'a' && isEditor && $event.preventDefault()"
        >
          <span class="dsf-store-steps__marker" aria-hidden="true">
            <svg v-if="i < currentIndex" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span class="dsf-store-steps__text">{{ step.label }}</span>
        </component>
        <span v-if="i < steps.length - 1" class="dsf-store-steps__line" aria-hidden="true"></span>
      </li>
    </ol>
  </nav>
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

const STEP_KEYS = ['cart', 'checkout', 'complete']

const storeContext = computed(() => {
  if (typeof window === 'undefined') return null
  const ctx = window.dsfFrontendData?.storeContext
  return ctx && typeof ctx === 'object' ? ctx : null
})

const steps = computed(() => {
  const urls = storeContext.value?.urls || {}
  return [
    { key: 'cart', label: props.settings?.labelCart || 'Cart', url: typeof urls.cart === 'string' ? urls.cart : '' },
    { key: 'checkout', label: props.settings?.labelCheckout || 'Checkout', url: typeof urls.checkout === 'string' ? urls.checkout : '' },
    { key: 'complete', label: props.settings?.labelComplete || 'Order Complete', url: '' },
  ]
})

const currentIndex = computed(() => {
  const manual = props.settings?.currentStep
  if (STEP_KEYS.includes(manual)) return STEP_KEYS.indexOf(manual)

  // Auto mode: the server detects the step on the frontend; the editor
  // previews the middle step so both states are visible.
  if (props.isEditor) return 1
  const detected = storeContext.value?.step
  return STEP_KEYS.includes(detected) ? STEP_KEYS.indexOf(detected) : 0
})

function stepTag(step, index) {
  // Completed steps link back (e.g. Checkout → Cart) when enabled.
  const linkable = props.settings?.linkSteps !== false && index < currentIndex.value && step.url
  return linkable ? 'a' : 'span'
}

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-steps-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 720
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-store-steps {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-store-steps__list {
  display: flex;
  align-items: center;
  margin: 0 auto;
  padding: 0;
  list-style: none;
}

.dsf-store-steps__step {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.dsf-store-steps__step:last-child {
  flex: 0 0 auto;
}

.dsf-store-steps__label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  opacity: 0.55;
  white-space: nowrap;
}

.dsf-store-steps__step.is-current .dsf-store-steps__label,
.dsf-store-steps__step.is-done .dsf-store-steps__label {
  opacity: 1;
}

a.dsf-store-steps__label:hover .dsf-store-steps__text {
  color: var(--dsf-steps-accent);
}

.dsf-store-steps__marker {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  border: 2px solid rgba(0, 0, 0, 0.15);
  font-size: 0.78rem;
  font-weight: 700;
  flex-shrink: 0;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.dsf-store-steps__step.is-done .dsf-store-steps__marker {
  background: var(--dsf-steps-accent);
  border-color: var(--dsf-steps-accent);
  color: #fff;
}

.dsf-store-steps__step.is-current .dsf-store-steps__marker {
  border-color: var(--dsf-steps-accent);
  color: var(--dsf-steps-accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--dsf-steps-accent) 15%, transparent);
}

.dsf-store-steps__line {
  flex: 1;
  height: 2px;
  margin: 0 0.75rem;
  border-radius: 2px;
  background: rgba(0, 0, 0, 0.12);
}

.dsf-store-steps__step.is-done .dsf-store-steps__line {
  background: var(--dsf-steps-accent);
}

@media (max-width: 560px) {
  .dsf-store-steps__text {
    font-size: 0.78rem;
  }

  .dsf-store-steps__line {
    margin: 0 0.4rem;
  }
}
</style>
