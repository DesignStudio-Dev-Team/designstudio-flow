<template>
  <header
    ref="root"
    class="dsf-dockhdr"
    :class="{ 'is-editor': isEditor, 'is-collapsed': collapsed }"
    :style="rootStyle"
  >
    <nav class="dsf-dockhdr__bar" aria-label="Primary">
      <!-- Reading-progress bar that rides the top edge of the dock. -->
      <span class="dsf-dockhdr__progress" aria-hidden="true">
        <span class="dsf-dockhdr__progress-fill" :style="{ transform: `scaleX(${progress})` }"></span>
      </span>

      <!-- Expanded: permanent DS Flow brand mark. While the desktop dock is
           actively collapsed during scroll, this same centered control briefly
           shows the current section icon. -->
      <a
        class="dsf-dockhdr__mark"
        href="#top"
        :aria-label="markLabel"
        @click="goToTop"
      >
        <span class="dsf-dockhdr__mark-media">
          <img
            v-if="!collapsed || !currentNavItem"
            class="dsf-dockhdr__brand-logo"
            :src="logoUrl"
            alt=""
            aria-hidden="true"
          />
          <img
            v-else-if="currentNavItem.iconImage"
            class="dsf-dockhdr__context-img"
            :src="currentNavItem.iconImage"
            alt=""
            aria-hidden="true"
          />
          <component :is="dockIconFor(currentNavItem.icon)" v-else :size="24" :stroke-width="2" />
        </span>
        <span class="dsf-dockhdr__tip">{{ markLabel }}</span>
      </a>

      <!-- Icon-only nav. Retracts into the mark while scrolling; each icon
           expands to reveal its label on hover. -->
      <div
        ref="dockBody"
        class="dsf-dockhdr__body"
        :inert="collapsed || undefined"
        :aria-hidden="collapsed ? 'true' : undefined"
      >
        <span class="dsf-dockhdr__divider" aria-hidden="true"></span>
        <ul class="dsf-dockhdr__nav">
          <li v-for="item in navItems" :key="item.href">
            <a
              :href="item.href"
              class="dsf-dockhdr__link"
              :class="{ 'is-current': activeSection === item.href }"
              :aria-current="activeSection === item.href ? 'true' : undefined"
              :aria-label="item.label"
              @click="onNav($event, item.href)"
            >
              <img v-if="item.iconImage" :src="item.iconImage" class="dsf-dockhdr__link-img" alt="" />
              <component :is="dockIconFor(item.icon)" v-else :size="18" :stroke-width="2" />
              <span class="dsf-dockhdr__label">{{ item.label }}</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Compact navigation: brand, current section, then a disclosure for
           every other section. CSS swaps this in at the mobile breakpoint. -->
      <div class="dsf-dockhdr__mobile">
        <span class="dsf-dockhdr__mobile-divider" aria-hidden="true"></span>
        <a
          v-if="currentNavItem"
          ref="mobileCurrentLink"
          :href="currentNavItem.href"
          class="dsf-dockhdr__mobile-current"
          :aria-current="activeSection ? 'location' : undefined"
          :aria-label="`Current section: ${currentNavItem.label}`"
          @click="onMobileNav($event, currentNavItem.href)"
        >
          <img
            v-if="currentNavItem.iconImage"
            :src="currentNavItem.iconImage"
            class="dsf-dockhdr__mobile-img"
            alt=""
          />
          <component :is="dockIconFor(currentNavItem.icon)" v-else :size="20" :stroke-width="2" />
        </a>
        <button
          ref="mobileMenuButton"
          class="dsf-dockhdr__mobile-more"
          type="button"
          :aria-expanded="mobileMenuOpen"
          :aria-controls="mobileMenuId"
          :aria-label="mobileMenuOpen ? 'Hide page sections' : 'Show page sections'"
          :disabled="mobileMenuItems.length === 0"
          @click="toggleMobileMenu"
        >
          <span class="dsf-dockhdr__mobile-dots" aria-hidden="true">•••</span>
        </button>
      </div>

      <div
        v-if="mobileMenuOpen"
        :id="mobileMenuId"
        class="dsf-dockhdr__mobile-menu"
      >
        <ul class="dsf-dockhdr__mobile-menu-list" aria-label="Other page sections">
          <li v-for="item in mobileMenuItems" :key="`mobile-${item.href}`">
            <a
              :href="item.href"
              class="dsf-dockhdr__mobile-menu-link"
              :aria-label="item.label"
              @click="onMobileNav($event, item.href)"
            >
              <img v-if="item.iconImage" :src="item.iconImage" class="dsf-dockhdr__mobile-img" alt="" />
              <component :is="dockIconFor(item.icon)" v-else :size="20" :stroke-width="2" />
              <span>{{ item.label }}</span>
            </a>
          </li>
        </ul>
      </div>
    </nav>
  </header>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useId } from 'vue'
import { ensureGsap, gsap, ScrollTrigger } from '../../utils/gsapSetup'
import { safePublicUrl } from '../../utils/safeUrl'
import { landingBlockStyle } from '../../utils/landingStyle'
import { dockIconFor } from '../../utils/dsflowDockIcons'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

// Icon per landing-page section, so each nav item picks up a meaningful glyph
// automatically from the anchor it points at.
const SECTION_ICONS = {
  '#why-dsflow': 'dsflow-why',
  '#blocks': 'dsflow-blocks',
  '#ready': 'dsflow-ready',
  '#editor': 'dsflow-editor',
  '#theme': 'dsflow-theme',
  '#woocommerce': 'dsflow-commerce',
  '#layouts': 'dsflow-layouts',
  '#campaigns': 'dsflow-campaigns',
  '#engagement': 'dsflow-engagement',
  '#seo': 'dsflow-seo',
  '#security': 'dsflow-security',
  '#audience': 'dsflow-agencies',
  '#workflow': 'dsflow-workflow',
  '#redirects': 'dsflow-redirects',
  '#mail': 'dsflow-mail',
  '#get-dsflow': 'dsflow-launch',
}

// Existing pages saved the former generic preset names. Upgrade only that
// exact legacy pairing so a deliberately selected alternate icon still wins.
const LEGACY_SECTION_ICONS = {
  '#why-dsflow': 'sparkles',
  '#blocks': 'boxes',
  '#ready': 'wand',
  '#editor': 'mouse-pointer',
  '#theme': 'palette',
  '#woocommerce': 'store',
  '#layouts': 'layout',
  '#campaigns': 'megaphone',
  '#engagement': 'mail',
  '#seo': 'search',
  '#security': 'shield-check',
  '#audience': 'briefcase',
  '#workflow': 'gauge',
  '#redirects': 'zap',
  '#mail': 'bell',
  '#get-dsflow': 'rocket',
}
const FALLBACK_ICONS = ['sparkles', 'boxes', 'store', 'mail', 'shield-check', 'briefcase', 'layers', 'palette']

// One entry per section of the landing page, in scroll order.
const defaultNav = [
  { href: '#why-dsflow', label: 'Why DSFlow', icon: 'dsflow-why' },
  { href: '#blocks', label: 'Blocks', icon: 'dsflow-blocks' },
  { href: '#ready', label: 'Design included', icon: 'dsflow-ready' },
  { href: '#editor', label: 'Visual editor', icon: 'dsflow-editor' },
  { href: '#theme', label: 'Theme controls', icon: 'dsflow-theme' },
  { href: '#woocommerce', label: 'WooCommerce', icon: 'dsflow-commerce' },
  { href: '#layouts', label: 'Headers & footers', icon: 'dsflow-layouts' },
  { href: '#campaigns', label: 'Campaigns', icon: 'dsflow-campaigns' },
  { href: '#engagement', label: 'Forms & growth', icon: 'dsflow-engagement' },
  { href: '#seo', label: 'SEO', icon: 'dsflow-seo' },
  { href: '#security', label: 'Security', icon: 'dsflow-security' },
  { href: '#audience', label: 'For agencies', icon: 'dsflow-agencies' },
  { href: '#workflow', label: 'Workflow', icon: 'dsflow-workflow' },
  { href: '#redirects', label: 'Redirects', icon: 'dsflow-redirects' },
  { href: '#mail', label: 'Email delivery', icon: 'dsflow-mail' },
  { href: '#get-dsflow', label: 'Get DSFlow', icon: 'dsflow-launch' },
]

// Keep the block's theme vars (accent/text) but never paint a background on the
// root — only the pill should be visible, so the sides stay transparent.
const rootStyle = computed(() => {
  const style = landingBlockStyle(props.settings)
  delete style.backgroundColor
  delete style.background
  delete style['--dsf-theme-background']
  delete style['--dsf-landing-background']
  return style
})

const navItems = computed(() => {
  const links = Array.isArray(props.settings.navLinks) ? props.settings.navLinks : []
  const mapped = links
    .filter((link) => link && (link.label || link.url))
    .map((link, index) => {
      const href = safePublicUrl(link.url || '#')
      const inferredIcon = SECTION_ICONS[href]
      const savedIcon = link.icon || ''
      const shouldUpgradeLegacyIcon = inferredIcon
        && (!savedIcon || savedIcon === LEGACY_SECTION_ICONS[href])
      return {
        href,
        label: link.label || link.url,
        icon: shouldUpgradeLegacyIcon
          ? inferredIcon
          : (savedIcon || inferredIcon || FALLBACK_ICONS[index % FALLBACK_ICONS.length]),
        // A custom media-library image overrides the preset icon on render.
        iconImage: link.iconImage ? safePublicUrl(link.iconImage, '') : '',
      }
    })
  return mapped.length ? mapped : defaultNav
})

const logoUrl = computed(() => {
  const base = typeof window !== 'undefined'
    ? (window.dsfEditorData?.pluginUrl || window.dsfFrontendData?.pluginUrl || '')
    : ''
  const fallback = `${base}assets/images/dsflow-logo.png`
  return props.settings.logoImage ? safePublicUrl(props.settings.logoImage, fallback) : fallback
})

const root = ref(null)
const dockBody = ref(null)
const mobileCurrentLink = ref(null)
const mobileMenuButton = ref(null)
const activeSection = ref('')
const collapsed = ref(false)
const mobileMenuOpen = ref(false)
const mobileMenuId = `dsf-dockhdr-menu-${useId().replace(/[^A-Za-z0-9_-]/g, '')}`
// Reading progress (0–1). In the editor there's no page scroll, so show a static
// partial fill so the bar is visible in the block preview.
const progress = ref(props.isEditor ? 0.4 : 0)
const currentNavItem = computed(() => (
  navItems.value.find((item) => item.href === activeSection.value) || navItems.value[0] || null
))
const markLabel = computed(() => (
  collapsed.value && currentNavItem.value
    ? `Current section: ${currentNavItem.value.label} — Go to Top`
    : 'DesignStudio Flow — Go to Top'
))
const mobileMenuItems = computed(() => (
  navItems.value.filter((item) => item.href !== currentNavItem.value?.href)
))

function onNav(event, href) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if (href && href.startsWith('#')) activeSection.value = href
}

function toggleMobileMenu() {
  if (!mobileMenuItems.value.length) return
  mobileMenuOpen.value = !mobileMenuOpen.value
}

function closeMobileMenu(returnFocus = false) {
  if (!mobileMenuOpen.value) return
  mobileMenuOpen.value = false
  if (returnFocus) nextTick(() => mobileMenuButton.value?.focus())
}

function onMobileNav(event, href) {
  closeMobileMenu()
  onNav(event, href)
  if (!props.isEditor) {
    nextTick(() => mobileCurrentLink.value?.focus({ preventScroll: true }))
  }
}

function onDocumentPointerDown(event) {
  if (!mobileMenuOpen.value || root.value?.contains(event.target)) return
  closeMobileMenu()
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape' || !mobileMenuOpen.value) return
  event.preventDefault()
  closeMobileMenu(true)
}

// The mark always returns to the very top of the page.
function goToTop(event) {
  event.preventDefault()
  closeMobileMenu()
  if (props.isEditor) return
  activeSection.value = ''
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  window.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' })
}

// ---- Collapse-on-scroll (mirrors the editor dock) --------------------------
const reducedMotion = typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
const IDLE_MS = 280

let spies = []
let spyTimer = null
let onScroll = null
let onResize = null
let idleTimer = null
let naturalWidth = 0

function isCompactViewport() {
  return typeof window !== 'undefined' && window.innerWidth <= 860
}

function updateProgress() {
  const scrollable = document.documentElement.scrollHeight - window.innerHeight
  progress.value = scrollable > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollable)) : 0
}

function collapseDock() {
  if (collapsed.value || reducedMotion || isCompactViewport()) return
  const body = dockBody.value
  if (!body) return
  collapsed.value = true
  naturalWidth = body.getBoundingClientRect().width
  body.style.overflow = 'hidden'
  gsap.killTweensOf(body)
  gsap.to(body, { width: 0, opacity: 0, marginLeft: 0, duration: 0.42, ease: 'power3.inOut' })
}

function expandDock() {
  if (isCompactViewport()) {
    resetDockBody()
    return
  }
  if (!collapsed.value) return
  const body = dockBody.value
  if (!body) return
  collapsed.value = false
  gsap.killTweensOf(body)
  gsap.to(body, {
    width: naturalWidth || 'auto',
    opacity: 1,
    marginLeft: 8,
    duration: 0.5,
    ease: 'power3.out',
    onComplete: () => {
      body.style.width = ''
      body.style.marginLeft = ''
      body.style.overflow = ''
    },
  })
}

function resetDockBody() {
  const body = dockBody.value
  collapsed.value = false
  if (!body) return
  gsap.killTweensOf(body)
  body.style.width = ''
  body.style.opacity = ''
  body.style.marginLeft = ''
  body.style.overflow = ''
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocumentPointerDown, true)
  document.addEventListener('keydown', onDocumentKeydown)
  if (props.isEditor) return

  ensureGsap()

  onScroll = () => {
    updateProgress()
    closeMobileMenu()
    if (isCompactViewport()) {
      resetDockBody()
      return
    }
    collapseDock()
    if (idleTimer) clearTimeout(idleTimer)
    idleTimer = setTimeout(expandDock, IDLE_MS)
  }
  onResize = () => {
    updateProgress()
    closeMobileMenu()
    if (isCompactViewport()) resetDockBody()
  }
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onResize)
  updateProgress()

  if (!reducedMotion) {
    gsap.from(root.value?.querySelector('.dsf-dockhdr__bar'), {
      autoAlpha: 0, y: 26, duration: 0.6, ease: 'power3.out',
    })
  }

  // Section spies live in sibling blocks, so wait a tick for the full page DOM.
  spyTimer = window.setTimeout(() => {
    spies = navItems.value
      .map((item) => {
        const target = item.href.startsWith('#') ? document.querySelector(item.href) : null
        if (!target) return null
        return ScrollTrigger.create({
          trigger: target,
          start: 'top 55%',
          end: 'bottom 55%',
          onToggle: (self) => {
            if (!self.isActive) return
            activeSection.value = item.href
            closeMobileMenu()
          },
        })
      })
      .filter(Boolean)
    ScrollTrigger.refresh()
  }, 320)
})

onUnmounted(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown, true)
  document.removeEventListener('keydown', onDocumentKeydown)
  if (onScroll) window.removeEventListener('scroll', onScroll)
  if (onResize) window.removeEventListener('resize', onResize)
  if (spyTimer) window.clearTimeout(spyTimer)
  if (idleTimer) clearTimeout(idleTimer)
  spies.forEach((spy) => spy.kill())
  spies = []
  if (dockBody.value) gsap.killTweensOf(dockBody.value)
})
</script>

<style scoped>
.dsf-dockhdr {
  --dockhdr-accent: var(--dsf-theme-primary, #4aa3ff);
  color: #fff;
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}

/* Frontend: a floating dock pinned to the bottom-centre of the viewport. */
.dsf-dockhdr:not(.is-editor) {
  position: fixed;
  bottom: 22px;
  left: 50%;
  z-index: 999;
  transform: translateX(-50%);
}

/* Editor: render inline so the block is visible in the canvas without covering
   the editor's own dock. */
.dsf-dockhdr.is-editor {
  display: flex;
  justify-content: center;
  padding: 30px 16px;
}

.dsf-dockhdr__bar {
  position: relative;
  display: inline-flex;
  align-items: center;
  /* No flex gap: the body's own margin-left handles expanded spacing and
     animates to 0 on collapse, so the mark stays perfectly centred when the row
     retracts (a gap here would leave dead space on the mark's right). */
  max-width: calc(100vw - 24px);
  padding: 7px;
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 999px;
  background: linear-gradient(180deg, rgba(38, 43, 51, 0.97), rgba(20, 23, 28, 0.98));
  box-shadow:
    0 18px 50px rgba(0, 0, 0, 0.42),
    0 2px 6px rgba(0, 0, 0, 0.32),
    inset 0 1px 0 rgba(255, 255, 255, 0.07);
  backdrop-filter: blur(16px);
}

/* Reading-progress bar hugging the top edge of the dock pill — sits up in the
   top padding, clear of the icon row, and inset a little so it isn't full-width. */
.dsf-dockhdr__progress {
  position: absolute;
  top: 3px;
  right: 26px;
  left: 26px;
  height: 2.5px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  overflow: hidden;
  pointer-events: none;
}

.dsf-dockhdr__progress-fill {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: var(--dockhdr-accent);
  transform: scaleX(0);
  transform-origin: left center;
  transition: transform 140ms ease-out;
  will-change: transform;
}

.dsf-dockhdr__mark {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 48px;
  height: 48px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.07);
  color: var(--dockhdr-accent);
  text-decoration: none;
  transition: background 180ms ease;
}

.dsf-dockhdr__mark:hover { background: rgba(255, 255, 255, 0.12); }

/* Tooltip above the brand mark. */
.dsf-dockhdr__tip {
  position: absolute;
  bottom: calc(100% + 12px);
  left: 50%;
  z-index: 3;
  padding: 5px 10px;
  border-radius: 8px;
  background: rgba(16, 19, 23, 0.98);
  color: #fff;
  font-size: 12px;
  font-weight: 650;
  line-height: 1;
  white-space: nowrap;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.34);
  opacity: 0;
  pointer-events: none;
  transform: translateX(-50%) translateY(5px);
  transition: opacity 150ms ease, transform 150ms ease;
}

.dsf-dockhdr__tip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  width: 7px;
  height: 7px;
  margin-top: -4px;
  margin-left: -3.5px;
  background: rgba(16, 19, 23, 0.98);
  transform: rotate(45deg);
}

.dsf-dockhdr__mark:hover .dsf-dockhdr__tip,
.dsf-dockhdr__mark:focus-visible .dsf-dockhdr__tip {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.dsf-dockhdr__mark-media {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  will-change: transform, opacity;
}

.dsf-dockhdr__mark img {
  display: block;
  width: 28px;
  height: 28px;
  object-fit: contain;
}

/* The retractable icon row. overflow toggles to hidden only during the collapse
   animation (set inline by the component). */
.dsf-dockhdr__body {
  display: inline-flex;
  align-items: center;
  margin-left: 8px;
  box-sizing: border-box;
  white-space: nowrap;
}

.dsf-dockhdr__divider {
  flex: 0 0 auto;
  width: 1px;
  height: 26px;
  margin: 0 6px 0 2px;
  background: rgba(255, 255, 255, 0.12);
}

.dsf-dockhdr__nav {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Icon-only by default; the label expands in on hover. */
.dsf-dockhdr__link {
  position: relative;
  display: inline-flex;
  align-items: center;
  height: 42px;
  padding: 0 11px;
  border-radius: 999px;
  color: rgba(255, 255, 255, 0.84);
  font-size: 14px;
  font-weight: 650;
  white-space: nowrap;
  text-decoration: none;
  transition: color 180ms ease, background 180ms ease;
}

.dsf-dockhdr__link svg { flex: 0 0 auto; }

.dsf-dockhdr__link-img {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  object-fit: contain;
  border-radius: 4px;
}

.dsf-dockhdr__label {
  display: inline-block;
  max-width: 0;
  overflow: hidden;
  opacity: 0;
  line-height: 1;
  transition: max-width 280ms cubic-bezier(0.4, 0, 0.2, 1), opacity 200ms ease, margin-left 280ms cubic-bezier(0.4, 0, 0.2, 1);
}

.dsf-dockhdr__link:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

.dsf-dockhdr__link:hover .dsf-dockhdr__label,
.dsf-dockhdr__link:focus-visible .dsf-dockhdr__label {
  max-width: 160px;
  margin-left: 8px;
  opacity: 1;
}

.dsf-dockhdr__link.is-current {
  color: #fff;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
}

.dsf-dockhdr__link.is-current svg { color: var(--dockhdr-accent); }

/* Compact controls and their disclosure are present in the shared render tree
   but only become visible at the responsive breakpoint below. */
.dsf-dockhdr__mobile,
.dsf-dockhdr__mobile-menu {
  display: none;
}

.dsf-dockhdr__mobile {
  position: relative;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
}

.dsf-dockhdr__mobile-divider {
  flex: 0 0 auto;
  width: 1px;
  height: 26px;
  margin-right: 4px;
  background: rgba(255, 255, 255, 0.12);
}

.dsf-dockhdr__mobile-current,
.dsf-dockhdr__mobile-more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
  text-decoration: none;
  cursor: pointer;
  transition: color 180ms ease, background 180ms ease, border-color 180ms ease;
}

.dsf-dockhdr__mobile-current {
  color: var(--dockhdr-accent);
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.16);
}

.dsf-dockhdr__mobile-current:hover,
.dsf-dockhdr__mobile-more:hover {
  background: rgba(255, 255, 255, 0.16);
}

.dsf-dockhdr__mobile-more:disabled {
  opacity: 0.4;
  cursor: default;
}

.dsf-dockhdr__mobile-dots {
  display: block;
  margin-top: -5px;
  font-size: 19px;
  font-weight: 800;
  letter-spacing: 1.5px;
  line-height: 1;
}

.dsf-dockhdr__mobile-img {
  display: block;
  width: 20px;
  height: 20px;
  border-radius: 4px;
  object-fit: contain;
}

.dsf-dockhdr__mobile-menu {
  position: absolute;
  bottom: calc(100% + 12px);
  left: 50%;
  z-index: 5;
  width: min(342px, calc(100vw - 24px));
  max-height: min(56vh, 420px);
  padding: 10px;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 18px;
  background: linear-gradient(180deg, rgba(38, 43, 51, 0.99), rgba(20, 23, 28, 0.99));
  box-shadow: 0 22px 55px rgba(0, 0, 0, 0.48), inset 0 1px 0 rgba(255, 255, 255, 0.07);
  transform: translateX(-50%);
  -webkit-overflow-scrolling: touch;
}

.dsf-dockhdr__mobile-menu-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-dockhdr__mobile-menu-link {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 6px;
  min-height: 68px;
  padding: 7px 4px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.055);
  color: rgba(255, 255, 255, 0.92);
  font-size: 11px;
  font-weight: 650;
  line-height: 1.15;
  text-align: center;
  text-decoration: none;
}

.dsf-dockhdr__mobile-menu-link:hover {
  border-color: rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.dsf-dockhdr__mark:focus-visible,
.dsf-dockhdr__mobile-current:focus-visible,
.dsf-dockhdr__mobile-more:focus-visible,
.dsf-dockhdr__mobile-menu-link:focus-visible {
  outline: 2px solid var(--dockhdr-accent);
  outline-offset: 2px;
}

@media (max-width: 860px) {
  .dsf-dockhdr:not(.is-editor) {
    bottom: calc(12px + env(safe-area-inset-bottom));
  }

  .dsf-dockhdr__bar {
    overflow: visible;
  }

  .dsf-dockhdr__progress {
    right: 18px;
    left: 18px;
  }

  .dsf-dockhdr__body {
    display: none;
  }

  .dsf-dockhdr__mobile {
    display: inline-flex;
  }

  .dsf-dockhdr__mobile-menu {
    display: block;
  }

  .dsf-dockhdr__tip {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dsf-dockhdr__mark,
  .dsf-dockhdr__link,
  .dsf-dockhdr__mobile-current,
  .dsf-dockhdr__mobile-more,
  .dsf-dockhdr__label,
  .dsf-dockhdr__tip,
  .dsf-dockhdr__progress-fill { transition: none; }
}
</style>

<style>
/* Smooth anchor scrolling when the dock header owns the page. */
html:has(.dsf-dockhdr:not(.is-editor)) { scroll-behavior: smooth; }

@media (prefers-reduced-motion: reduce) {
  html:has(.dsf-dockhdr:not(.is-editor)) { scroll-behavior: auto; }
}
</style>
