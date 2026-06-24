<template>
  <header ref="root" class="dsf-landing-header" :class="[{ 'is-editor': isEditor }, `is-${variant}`]" :style="blockStyle">
    <div v-if="settings.showAnnouncement" class="dsf-landing-header__announcement">
      <span>{{ settings.announcementText }}</span>
      <a :href="safePublicUrl(settings.announcementUrl)" @click="guardEditor">{{ settings.announcementLinkText }}</a>
    </div>

    <div class="dsf-landing-header__bar">
      <a class="dsf-landing-header__brand" :href="safePublicUrl(settings.homeUrl)" aria-label="Home" @click="guardEditor">
        <img class="dsf-landing-header__mark" :src="logoUrl" alt="" aria-hidden="true" />
        <span v-if="settings.brandText">{{ settings.brandText }}</span>
        <span v-else>DesignStudio <strong>Flow</strong></span>
      </a>

      <nav class="dsf-landing-header__nav" aria-label="Landing page">
        <a
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          :class="{ 'is-current': activeSection === item.href }"
          :aria-current="activeSection === item.href ? 'true' : undefined"
          @click="guardEditor"
        >{{ item.label }}</a>
      </nav>

      <div class="dsf-landing-header__actions">
        <a class="dsf-button dsf-button--ghost" :href="safePublicUrl(settings.docsUrl)" @click="guardEditor">{{ settings.docsText }}</a>
        <a class="dsf-button dsf-button--primary" :href="safePublicUrl(settings.ctaUrl)" @click="guardEditor">{{ settings.ctaText }}</a>
      </div>

      <button
        class="dsf-landing-header__menu"
        type="button"
        :aria-expanded="mobileOpen"
        aria-label="Toggle navigation"
        @click="mobileOpen = !mobileOpen"
      >
        <span></span><span></span>
      </button>
    </div>

    <div v-if="mobileOpen" class="dsf-landing-header__mobile">
      <a v-for="item in navItems" :key="item.href" :href="item.href" @click="onMobileLink">{{ item.label }}</a>
      <a :href="safePublicUrl(settings.docsUrl)" @click="onMobileLink">{{ settings.docsText }}</a>
      <a class="dsf-button dsf-button--primary" :href="safePublicUrl(settings.ctaUrl)" @click="onMobileLink">{{ settings.ctaText }}</a>
    </div>

    <span v-if="variant === 'progress'" class="dsf-landing-header__progress" :style="{ transform: `scaleX(${progress})` }" aria-hidden="true"></span>
  </header>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { ensureGsap, gsap, ScrollTrigger } from '../../utils/gsapSetup'
import { safePublicUrl } from '../../utils/safeUrl'
import { landingBlockStyle } from '../../utils/landingStyle'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

const defaultNav = [
  { href: '#why-dsflow', label: 'Why DSFlow' },
  { href: '#blocks', label: 'Blocks' },
  { href: '#woocommerce', label: 'WooCommerce' },
  { href: '#engagement', label: 'Forms & Growth' },
  { href: '#security', label: 'Security' },
  { href: '#audience', label: 'For Agencies' },
]

const variant = computed(() => props.settings.variant || 'progress')
const blockStyle = computed(() => landingBlockStyle(props.settings))
const navItems = computed(() => {
  const links = Array.isArray(props.settings.navLinks) ? props.settings.navLinks : []
  const mapped = links
    .filter((link) => link && (link.label || link.url))
    .map((link) => ({ href: safePublicUrl(link.url || '#'), label: link.label || link.url }))
  return mapped.length ? mapped : defaultNav
})

const root = ref(null)
const mobileOpen = ref(false)
const progress = ref(0)
const activeSection = ref('#why-dsflow')
let updateProgress = null
let headerContext = null
let sectionSpies = []
let spyTimer = null

const logoUrl = computed(() => {
  if (props.settings.logoImage) return props.settings.logoImage
  const baseUrl = window.dsfEditorData?.pluginUrl || window.dsfFrontendData?.pluginUrl || ''
  return `${baseUrl}assets/images/dsflow-logo.png`
})

function guardEditor(event) {
  if (props.isEditor) event.preventDefault()
}

function onMobileLink(event) {
  guardEditor(event)
  mobileOpen.value = false
}

function closeOnEscape(event) {
  if (event.key === 'Escape') mobileOpen.value = false
}

watch(mobileOpen, (isOpen) => {
  if (!props.isEditor) document.body.classList.toggle('dsf-nav-open', isOpen)
  if (isOpen && !props.isEditor && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    nextTick(() => {
      gsap.fromTo(
        root.value?.querySelectorAll('.dsf-landing-header__mobile a') || [],
        { autoAlpha: 0, x: 18 },
        { autoAlpha: 1, x: 0, duration: 0.38, stagger: 0.055, ease: 'power3.out', overwrite: true }
      )
    })
  }
})

onMounted(() => {
  if (props.isEditor) return

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    headerContext = gsap.context(() => {
      gsap.from('.dsf-landing-header__brand', { autoAlpha: 0, x: -18, duration: 0.65, ease: 'power3.out' })
      gsap.from('.dsf-landing-header__nav a', { autoAlpha: 0, y: -10, duration: 0.55, stagger: 0.07, ease: 'power3.out' })
      gsap.from('.dsf-landing-header__actions > *', { autoAlpha: 0, x: 12, duration: 0.55, stagger: 0.08, ease: 'power3.out' })
    }, root.value)
  }

  const setProgress = gsap.quickTo(progress, 'value', { duration: 0.18, ease: 'power1.out' })
  updateProgress = () => {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight
    setProgress(scrollable > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollable)) : 0)
  }

  window.addEventListener('scroll', updateProgress, { passive: true })
  window.addEventListener('resize', updateProgress)
  window.addEventListener('keydown', closeOnEscape)
  updateProgress()

  // Highlight the nav item for whichever section currently owns the viewport.
  // Sections live in sibling blocks, so wait a tick for the full page DOM.
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    ensureGsap()
    spyTimer = window.setTimeout(() => {
      sectionSpies = navItems.value
        .map((item) => {
          const target = item.href && item.href.startsWith('#') ? document.querySelector(item.href) : null
          if (!target) return null
          return ScrollTrigger.create({
            trigger: target,
            start: 'top 55%',
            end: 'bottom 55%',
            onToggle: (self) => {
              if (self.isActive) activeSection.value = item.href
            },
          })
        })
        .filter(Boolean)
      ScrollTrigger.refresh()
    }, 320)
  }
})

onUnmounted(() => {
  document.body.classList.remove('dsf-nav-open')
  headerContext?.revert()
  gsap.killTweensOf(root.value?.querySelectorAll('.dsf-landing-header__mobile a') || [])
  if (spyTimer) window.clearTimeout(spyTimer)
  sectionSpies.forEach((spy) => spy.kill())
  sectionSpies = []
  if (updateProgress) {
    window.removeEventListener('scroll', updateProgress)
    window.removeEventListener('resize', updateProgress)
  }
  window.removeEventListener('keydown', closeOnEscape)
})
</script>

<style scoped>
.dsf-landing-header {
  --blue: var(--dsf-theme-primary, #0091ff);
  --ink: var(--dsf-theme-text, #111827);
  position: relative;
  z-index: 100;
  width: 100%;
  color: var(--ink);
  background: rgba(247, 244, 237, 0.96);
  border-bottom: 1px solid rgba(17, 24, 39, 0.09);
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
  backdrop-filter: blur(16px);
}

.dsf-landing-header__announcement {
  display: flex;
  justify-content: center;
  gap: 8px;
  padding: 7px 20px;
  color: #071b2f;
  background: #a4d2f6;
  font-size: 14px;
}

.dsf-landing-header__announcement a { color: #071b2f; font-weight: 800; text-underline-offset: 3px; }

.dsf-landing-header__bar {
  display: flex;
  align-items: center;
  width: min(1120px, calc(100% - 40px));
  min-height: 66px;
  margin: 0 auto;
  gap: 28px;
}

.dsf-landing-header__brand {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 10px;
  color: var(--ink);
  font-family: var(--dsf-theme-heading-font, 'Manrope', sans-serif);
  font-size: 16px;
  font-weight: 700;
  text-decoration: none;
}

.dsf-landing-header__brand strong { color: var(--blue); }
.dsf-landing-header__mark { display: block; width: 25px; height: 25px; object-fit: contain; }

.dsf-landing-header__nav { display: flex; align-items: center; justify-content: center; flex: 1; gap: clamp(16px, 2.2vw, 30px); }
.dsf-landing-header__nav a,
.dsf-landing-header__mobile a { color: #46515f; font-size: 14px; font-weight: 650; text-decoration: none; transition: color 180ms ease; }
.dsf-landing-header__nav a { position: relative; }
.dsf-landing-header__nav a:hover { color: var(--blue); }
.dsf-landing-header__nav a.is-current { color: var(--ink); }
.dsf-landing-header__nav a.is-current::after { content: ''; position: absolute; left: 0; right: 0; bottom: -6px; height: 2px; border-radius: 2px; background: var(--blue); }
.dsf-landing-header__actions { display: flex; align-items: center; gap: 9px; }
.dsf-button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 16px; border: 1px solid transparent; border-radius: 7px; font-size: 14px; font-weight: 750; text-decoration: none; }
.dsf-button--ghost { color: var(--blue); border-color: rgba(17, 24, 39, 0.12); background: #fff; }
.dsf-button--primary,
.dsf-button--primary:hover,
.dsf-button--primary:focus-visible { color: #fff !important; background: var(--blue); box-shadow: 0 8px 20px rgba(12, 95, 168, 0.18); }
.dsf-landing-header__menu { display: none; width: 42px; height: 42px; padding: 0; border: 0; background: transparent; }
.dsf-landing-header__menu span { display: block; width: 21px; height: 2px; margin: 5px auto; background: var(--ink); }
.dsf-landing-header__mobile { display: none; }
.dsf-landing-header__progress { position: absolute; right: 0; bottom: -1px; left: 0; height: 3px; background: var(--blue); transform-origin: left center; will-change: transform; }

/* Variants */
.dsf-landing-header.is-minimal .dsf-landing-header__nav { display: none; }
.dsf-landing-header.is-minimal .dsf-landing-header__bar { justify-content: space-between; }
.dsf-landing-header.is-centered .dsf-landing-header__bar { display: grid; grid-template-columns: 1fr auto 1fr; }
.dsf-landing-header.is-centered .dsf-landing-header__nav { justify-content: center; }
.dsf-landing-header.is-centered .dsf-landing-header__actions { justify-content: flex-end; }
.dsf-landing-header.is-transparent { background: transparent; border-bottom-color: transparent; backdrop-filter: none; }
.dsf-landing-header.is-transparent .dsf-landing-header__nav a { color: var(--ink); }

@media (max-width: 900px) {
  .dsf-landing-header__bar { width: min(100% - 28px, 720px); }
  .dsf-landing-header__nav, .dsf-landing-header__actions { display: none; }
  .dsf-landing-header__brand { flex: 1; }
  .dsf-landing-header__menu { display: block; }
  .dsf-landing-header__mobile { display: grid; gap: 0; padding: 8px 20px 22px; border-top: 1px solid rgba(17, 24, 39, 0.08); }
  .dsf-landing-header__mobile a { padding: 13px 4px; border-bottom: 1px solid rgba(17, 24, 39, 0.07); }
  .dsf-landing-header__mobile .dsf-button { margin-top: 12px; color: #fff; border-bottom: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .dsf-landing-header__nav a { transition: none; }
}
</style>

<style>
.dsf-frontend-blocks > .dsf-block:has(.dsf-landing-header:not(.is-editor)) {
  position: sticky;
  top: 0;
  z-index: 999;
}

body.dsf-nav-open { overflow: hidden; }

html:has(.dsf-landing-header:not(.is-editor)) {
  scroll-behavior: smooth;
  scroll-padding-top: 72px;
}

@media (prefers-reduced-motion: reduce) {
  html:has(.dsf-landing-header:not(.is-editor)) { scroll-behavior: auto; }
}
</style>
