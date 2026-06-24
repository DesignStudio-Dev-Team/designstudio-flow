<template>
  <section class="dsf-pricing-preview" :style="sectionStyle">
    <div class="dsf-pricing-preview__inner" :style="innerStyle">
      <header class="dsf-pricing-preview__header">
        <InlineText
          v-if="settings.eyebrow"
          tagName="p"
          class="dsf-pricing-preview__eyebrow"
          :style="{ color: accentColor }"
          v-model="settings.eyebrow"
          :is-editor="isEditor"
          placeholder="Pricing"
        />
        <InlineText
          tagName="h2"
          class="dsf-pricing-preview__title"
          v-model="settings.title"
          :is-editor="isEditor"
          placeholder="Pricing title"
        />
        <InlineText
          v-if="settings.description"
          tagName="p"
          class="dsf-pricing-preview__description"
          :style="{ color: mutedColor }"
          v-model="settings.description"
          :is-editor="isEditor"
          :multiline="true"
          placeholder="Describe your pricing options..."
        />
      </header>

      <div
        v-if="settings.showBillingToggle !== false"
        class="dsf-pricing-preview__billing"
        :style="{ borderColor: borderColor }"
        aria-label="Billing frequency"
      >
        <button
          v-for="option in billingOptions"
          :key="option.value"
          type="button"
          class="dsf-pricing-preview__billing-option"
          :class="{ 'dsf-pricing-preview__billing-option--active': billingCycle === option.value }"
          :style="billingCycle === option.value ? activeBillingStyle : null"
          :aria-pressed="billingCycle === option.value"
          @click="billingCycle = option.value"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="dsf-pricing-preview__grid" :style="gridStyle">
        <article
          v-for="(plan, index) in plans"
          :key="`${plan.name}-${index}`"
          class="dsf-pricing-preview__card"
          :class="{ 'dsf-pricing-preview__card--popular': plan.popular }"
          :style="cardStyle(plan)"
        >
          <div class="dsf-pricing-preview__plan-heading">
            <h3 :style="plan.popular ? { color: accentColor } : null">{{ plan.name }}</h3>
            <span
              v-if="plan.popular && plan.badgeText"
              class="dsf-pricing-preview__badge"
              :style="badgeStyle"
            >
              {{ plan.badgeText }}
            </span>
          </div>
          <p class="dsf-pricing-preview__plan-description" :style="{ color: mutedColor }">
            {{ plan.description }}
          </p>

          <div class="dsf-pricing-preview__price">
            <span class="dsf-pricing-preview__price-prefix">{{ plan.pricePrefix }}</span>
            <span class="dsf-pricing-preview__price-value">{{ displayedPrice(plan) }}</span>
            <span class="dsf-pricing-preview__price-suffix" :style="{ color: mutedColor }">{{ plan.priceSuffix }}</span>
          </div>

          <a
            v-if="plan.buttonText"
            class="dsf-pricing-preview__button"
            :class="{ 'dsf-pricing-preview__button--primary': plan.popular }"
            :href="plan.buttonUrl || '#'"
            :style="buttonStyle(plan)"
            @click="handleLinkClick($event, plan.buttonUrl)"
          >
            {{ plan.buttonText }}
          </a>

          <ul class="dsf-pricing-preview__features">
            <li v-for="feature in planFeatures(plan)" :key="feature">
              <span class="dsf-pricing-preview__check" :style="{ color: accentColor }" aria-hidden="true">✓</span>
              <span>{{ feature }}</span>
            </li>
          </ul>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const billingCycle = ref('monthly')
const accentColor = computed(() => props.settings.accentColor || '#4F36F5')
const mutedColor = computed(() => props.settings.mutedColor || '#4B5563')
const borderColor = computed(() => `${accentColor.value}2E`)
const plans = computed(() => Array.isArray(props.settings.plans) ? props.settings.plans.slice(0, 4) : [])

const billingOptions = computed(() => [
  { value: 'monthly', label: props.settings.monthlyLabel || 'Monthly' },
  { value: 'annual', label: props.settings.annualLabel || 'Annually' },
])

const sectionStyle = computed(() => ({
  backgroundColor: props.settings.backgroundColor || '#FFFFFF',
  color: props.settings.textColor || '#111827',
  paddingTop: `${getResponsiveValue(props.settings, props.previewMode, 'padding') ?? 80}px`,
  paddingBottom: `${getResponsiveValue(props.settings, props.previewMode, 'padding') ?? 80}px`,
}))

const innerStyle = computed(() => ({
  maxWidth: `${props.settings.maxWidth || 1200}px`,
}))

const gridStyle = computed(() => ({
  '--dsf-pricing-columns': Math.min(Math.max(Number(props.settings.columns) || 3, 2), 4),
}))

const activeBillingStyle = computed(() => ({
  backgroundColor: accentColor.value,
  color: '#FFFFFF',
}))

const badgeStyle = computed(() => ({
  backgroundColor: `${accentColor.value}12`,
  color: accentColor.value,
}))

function displayedPrice(plan) {
  return billingCycle.value === 'annual'
    ? (plan.annualPrice ?? plan.monthlyPrice ?? '')
    : (plan.monthlyPrice ?? '')
}

function planFeatures(plan) {
  const source = Array.isArray(plan.features) ? plan.features : String(plan.features || '').split(/\r?\n/)
  return source.map((feature) => String(feature).trim()).filter(Boolean)
}

function cardStyle(plan) {
  return {
    backgroundColor: props.settings.cardColor || '#FFFFFF',
    borderColor: plan.popular ? accentColor.value : borderColor.value,
  }
}

function buttonStyle(plan) {
  return plan.popular
    ? { backgroundColor: accentColor.value, borderColor: accentColor.value, color: '#FFFFFF' }
    : { borderColor: `${accentColor.value}55`, color: accentColor.value }
}

function handleLinkClick(event, url) {
  if (props.isEditor || !url || url === '#') event.preventDefault()
}
</script>

<style scoped>
.dsf-pricing-preview {
  width: 100%;
  box-sizing: border-box;
}

.dsf-pricing-preview__inner {
  width: 100%;
  margin: 0 auto;
}

.dsf-pricing-preview__header {
  max-width: 760px;
  margin: 0 auto;
  text-align: center;
}

.dsf-pricing-preview__eyebrow,
.dsf-pricing-preview__description,
.dsf-pricing-preview__plan-description,
.dsf-pricing-preview__price-suffix,
.dsf-pricing-preview__button,
.dsf-pricing-preview__features,
.dsf-pricing-preview__billing-option,
.dsf-pricing-preview__badge {
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-pricing-preview__eyebrow {
  margin: 0 0 12px;
  font-size: var(--dsf-theme-p-size, 16px);
  font-weight: 700;
  line-height: 1.4;
}

.dsf-pricing-preview__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h2-size, clamp(2rem, 4vw, 3.5rem));
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.035em;
}

.dsf-pricing-preview__description {
  max-width: 680px;
  margin: 20px auto 0;
  font-size: var(--dsf-theme-p-size, 16px);
  line-height: 1.65;
}

.dsf-pricing-preview__billing {
  display: flex;
  width: fit-content;
  margin: 54px auto 38px;
  padding: 3px;
  border: 1px solid;
  border-radius: 999px;
}

.dsf-pricing-preview__billing-option {
  padding: 7px 15px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.dsf-pricing-preview__grid {
  display: grid;
  grid-template-columns: repeat(var(--dsf-pricing-columns), minmax(0, 1fr));
  gap: 28px;
}

.dsf-pricing-preview__card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: 40px;
  border: 1px solid;
  border-radius: 24px;
  box-sizing: border-box;
}

.dsf-pricing-preview__card--popular {
  border-width: 2px;
  padding: 39px;
}

.dsf-pricing-preview__plan-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.dsf-pricing-preview__plan-heading h3 {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3-size, 20px);
  font-weight: 700;
  line-height: 1.3;
}

.dsf-pricing-preview__badge {
  flex: 0 0 auto;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.dsf-pricing-preview__plan-description {
  min-height: 3.2em;
  margin: 24px 0 20px;
  font-size: var(--dsf-theme-p-size, 16px);
  line-height: 1.6;
}

.dsf-pricing-preview__price {
  display: flex;
  align-items: baseline;
  margin-bottom: 24px;
  font-family: var(--dsf-theme-heading-font, inherit);
}

.dsf-pricing-preview__price-prefix,
.dsf-pricing-preview__price-value {
  font-size: clamp(2.25rem, 4vw, 3rem);
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.04em;
}

.dsf-pricing-preview__price-suffix {
  margin-left: 5px;
  font-size: 13px;
  font-weight: 600;
}

.dsf-pricing-preview__button {
  display: block;
  padding: 13px 18px;
  border: 1px solid;
  border-radius: 6px;
  text-align: center;
  text-decoration: none;
  font-size: var(--dsf-theme-p-size, 16px);
  font-weight: 700;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dsf-pricing-preview__button:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(17, 24, 39, 0.1);
}

.dsf-pricing-preview__features {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin: 34px 0 0;
  padding: 0;
  list-style: none;
  font-size: var(--dsf-theme-p-size, 16px);
  line-height: 1.45;
}

.dsf-pricing-preview__features li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.dsf-pricing-preview__check {
  flex: 0 0 auto;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;
}

@media (max-width: 1024px) {
  .dsf-pricing-preview__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 680px) {
  .dsf-pricing-preview__billing { margin-top: 36px; margin-bottom: 28px; }
  .dsf-pricing-preview__grid { grid-template-columns: 1fr; gap: 18px; }
  .dsf-pricing-preview__card { padding: 28px; border-radius: 18px; }
  .dsf-pricing-preview__card--popular { padding: 27px; }
  .dsf-pricing-preview__plan-description { min-height: 0; }
}
</style>
