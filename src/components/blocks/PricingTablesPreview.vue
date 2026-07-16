<template>
  <section class="dsf-pricing-tables" :style="sectionStyle">
    <div class="dsf-pricing-tables__inner" :style="innerStyle">
      <header><p>{{ settings.eyebrow || 'Plans for every stage' }}</p><h2>{{ settings.title || 'Straightforward pricing' }}</h2><div>{{ settings.description || '' }}</div></header>
      <div class="dsf-pricing-tables__grid">
        <article v-for="(plan, index) in plans" :key="index" :class="{ 'is-featured': plan.popular }">
          <span v-if="plan.popular" class="dsf-pricing-tables__badge">{{ plan.badgeText || 'Most popular' }}</span>
          <h3>{{ plan.name }}</h3><p class="dsf-pricing-tables__desc">{{ plan.description }}</p>
          <p class="dsf-pricing-tables__price"><small>{{ plan.pricePrefix }}</small>{{ plan.monthlyPrice }}<em>{{ plan.priceSuffix }}</em></p>
          <a :href="safeUrl(plan.buttonUrl)" @click="handleLink($event, plan.buttonUrl)">{{ plan.buttonText || 'Choose plan' }}</a>
          <ul><li v-for="feature in features(plan)" :key="feature"><b>✓</b>{{ feature }}</li></ul>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const plans = computed(() => Array.isArray(props.settings?.plans) ? props.settings.plans.slice(0, 3) : [])
const sectionStyle = computed(() => ({ backgroundColor: props.settings?.backgroundColor || '#F7F7FC', '--dsf-pricing-accent': props.settings?.accentColor || '#5B3DF5', paddingTop: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 80}px`, paddingBottom: `${getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 80}px` }))
const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1200}px` }))
function features(plan) { return (Array.isArray(plan?.features) ? plan.features : String(plan?.features || '').split(/\r?\n/)).map((value) => String(value).trim()).filter(Boolean).slice(0, 12) }
function safeUrl(url) { return /^(https?:|mailto:|tel:|\/|#)/i.test(String(url || '')) ? url : '#' }
function handleLink(event, url) { if (props.isEditor || !safeUrl(url) || safeUrl(url) === '#') event.preventDefault() }
</script>

<style scoped>
.dsf-pricing-tables { width: 100%; color: #172033; }.dsf-pricing-tables__inner { margin: 0 auto; padding: 0 1.25rem; }.dsf-pricing-tables header { max-width: 720px; margin: 0 auto 3rem; text-align: center; }.dsf-pricing-tables header p { margin: 0 0 .7rem; color: var(--dsf-pricing-accent); font: 800 .75rem/1 var(--dsf-theme-body-font, sans-serif); letter-spacing: .13em; text-transform: uppercase; }.dsf-pricing-tables h2 { margin: 0; font: 800 clamp(2.2rem, 5vw, 4rem)/.98 var(--dsf-theme-heading-font, sans-serif); letter-spacing: -.055em; }.dsf-pricing-tables header div { margin-top: 1rem; color: #64748b; line-height: 1.65; }.dsf-pricing-tables__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.25rem; align-items: stretch; }.dsf-pricing-tables article { position: relative; display: flex; flex-direction: column; min-width: 0; padding: 2rem; border: 1px solid #e3e8ef; border-radius: 26px; background: #fff; box-shadow: 0 10px 28px rgb(15 23 42 / 6%); }.dsf-pricing-tables article.is-featured { border-color: var(--dsf-pricing-accent); box-shadow: 0 22px 50px color-mix(in srgb, var(--dsf-pricing-accent) 22%, transparent); transform: translateY(-12px); }.dsf-pricing-tables__badge { position: absolute; top: -13px; left: 50%; padding: .45rem .85rem; border-radius: 999px; background: var(--dsf-pricing-accent); color: #fff; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; transform: translateX(-50%); white-space: nowrap; }.dsf-pricing-tables h3 { margin: .3rem 0 .75rem; font: 800 1.35rem/1.2 var(--dsf-theme-heading-font, sans-serif); }.dsf-pricing-tables__desc { min-height: 3.2em; margin: 0; color: #64748b; line-height: 1.55; }.dsf-pricing-tables__price { margin: 1.8rem 0; font: 800 clamp(2.7rem, 5vw, 4.25rem)/.85 var(--dsf-theme-heading-font, sans-serif); letter-spacing: -.07em; }.dsf-pricing-tables__price small { vertical-align: top; font-size: 1.25rem; letter-spacing: 0; }.dsf-pricing-tables__price em { margin-left: .35rem; color: #64748b; font: 600 .85rem/1 var(--dsf-theme-body-font, sans-serif); letter-spacing: 0; }.dsf-pricing-tables a { display: block; padding: .9rem 1rem; border: 1px solid var(--dsf-pricing-accent); border-radius: 12px; color: var(--dsf-pricing-accent); font-weight: 800; text-align: center; text-decoration: none; }.dsf-pricing-tables article.is-featured a { background: var(--dsf-pricing-accent); color: #fff; }.dsf-pricing-tables ul { display: grid; gap: .75rem; margin: 1.65rem 0 0; padding: 1.35rem 0 0; border-top: 1px solid #edf0f4; list-style: none; }.dsf-pricing-tables li { display: flex; gap: .65rem; color: #475569; line-height: 1.4; }.dsf-pricing-tables li b { color: var(--dsf-pricing-accent); }@media (max-width: 800px) { .dsf-pricing-tables__grid { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }.dsf-pricing-tables article.is-featured { transform: none; } }
</style>
