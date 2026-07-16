<template>
  <header
    ref="root"
    class="dsf-mmega"
    :class="rootClass"
    :style="cssVars"
    @mouseleave="onHeaderLeave"
  >
    <div class="dsf-mmega__bar">
      <!-- Brand -->
      <a class="dsf-mmega__brand" :href="url(settings.homeUrl || '/')" @click="guard">
        <img
          v-if="settings.logoImage"
          :src="settings.logoImage"
          :alt="settings.logoAlt || 'Site logo'"
          class="dsf-mmega__brand-image"
          :style="{ width: `${logoImageSizePercent}%` }"
        />
        <InlineText
          v-else
          tagName="span"
          class="dsf-mmega__brand-text"
          v-model="settings.logoText"
          :is-editor="isEditor"
          placeholder="Brand name"
          @click.stop
        />
      </a>

      <!-- Centered primary navigation -->
      <nav class="dsf-mmega__nav" aria-label="Primary">
        <div
          v-for="(item, index) in menuItems"
          :key="`nav-${index}`"
          class="dsf-mmega__nav-cell"
        >
          <a
            :href="url(item.url || '#')"
            class="dsf-mmega__nav-item"
            :class="{ 'is-active': activeIndex === index }"
            :aria-haspopup="item.hasMega ? 'true' : null"
            :aria-expanded="item.hasMega ? (activeIndex === index ? 'true' : 'false') : null"
            @mouseenter="setActive(index)"
            @focus="setActive(index)"
            @click="onItemClick($event, item, index)"
          >
            <InlineText
              tagName="span"
              v-model="item.label"
              :is-editor="isEditor"
              placeholder="Menu Item"
              @click.stop
            />
            <ChevronDown v-if="item.hasMega" :size="14" class="dsf-mmega__chevron" />
          </a>
        </div>
      </nav>

      <!-- Right-side actions -->
      <div class="dsf-mmega__actions">
        <form
          v-if="settings.showSearch !== false"
          class="dsf-mmega__search"
          :class="{ 'is-open': searchOpen }"
          role="search"
          @submit.prevent="submitSearch"
        >
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            class="dsf-mmega__search-input"
            placeholder="Search…"
            aria-label="Search"
            :tabindex="searchOpen ? 0 : -1"
          />
          <button
            type="button"
            class="dsf-mmega__icon-btn"
            :aria-label="searchOpen ? 'Submit search' : 'Open search'"
            :aria-expanded="searchOpen"
            @click="onSearchButton"
          >
            <Search :size="19" />
          </button>
        </form>

        <a
          v-if="settings.showAccount !== false"
          class="dsf-mmega__icon-btn"
          :href="url(settings.accountUrl || '/my-account/')"
          aria-label="Account"
          @click="guard"
        >
          <User :size="19" />
        </a>

        <a
          v-if="settings.showCart !== false"
          class="dsf-mmega__icon-btn dsf-mmega__cart"
          :href="url(settings.cartUrl || '/cart/')"
          aria-label="Cart"
          @click="guard"
        >
          <ShoppingCart :size="19" />
          <span v-if="numericCartCount > 0" class="dsf-mmega__cart-count">{{ numericCartCount }}</span>
        </a>

        <button
          type="button"
          class="dsf-mmega__mobile-toggle"
          aria-label="Open menu"
          :aria-expanded="mobileOpen"
          @click="openMobile"
        >
          <Menu :size="22" />
        </button>
      </div>
    </div>

    <!-- Desktop mega panel -->
    <transition name="dsf-mmega-fade">
      <div
        v-if="activeItem && activeItem.hasMega"
        class="dsf-mmega__panel-wrap"
        @mouseenter="panelHover = true"
        @mouseleave="panelHover = false"
      >
        <div class="dsf-mmega__panel">
          <div class="dsf-mmega__panel-grid">
            <div
              v-for="(column, columnIndex) in activeItem.columns"
              :key="`col-${columnIndex}`"
              class="dsf-mmega__col"
            >
              <h4 v-if="column.heading || isEditor" class="dsf-mmega__col-heading">
                <InlineText
                  tagName="span"
                  v-model="column.heading"
                  :is-editor="isEditor"
                  placeholder="Heading"
                  @click.stop
                />
              </h4>

              <div v-if="columnLayout(column) === 'cards'" class="dsf-mmega__cards" :style="{ '--cols': imageColumns(column) }">
                <a
                  v-for="(link, linkIndex) in column.links"
                  :key="`card-${linkIndex}`"
                  class="dsf-mmega__card"
                  :href="url(link.url || '#')"
                  @click="guard"
                >
                  <img v-if="link.image" :src="link.image" :alt="link.label || ''" />
                  <span v-else>{{ link.label }}</span>
                </a>
              </div>

              <div v-else-if="columnLayout(column) === 'icons'" class="dsf-mmega__icons">
                <a
                  v-for="(link, linkIndex) in column.links"
                  :key="`icon-${linkIndex}`"
                  class="dsf-mmega__icon-link"
                  :href="url(link.url || '#')"
                  @click="guard"
                >
                  <span class="dsf-mmega__icon"><component :is="iconFor(link.icon)" :size="18" /></span>
                  <span class="dsf-mmega__icon-label">{{ link.label }}</span>
                </a>
              </div>

              <div v-else class="dsf-mmega__links">
                <a
                  v-for="(link, linkIndex) in column.links"
                  :key="`link-${linkIndex}`"
                  class="dsf-mmega__link"
                  :href="url(link.url || '#')"
                  @click="guard"
                >{{ link.label }}</a>
              </div>
            </div>

            <a
              v-if="hasFeatured(activeItem.banner)"
              class="dsf-mmega__featured"
              :href="url(activeItem.banner.url || '#')"
              @click="guard"
            >
              <img v-if="activeItem.banner.image" :src="activeItem.banner.image" :alt="activeItem.banner.title || ''" />
              <span class="dsf-mmega__featured-body">
                <strong v-if="activeItem.banner.title">{{ activeItem.banner.title }}</strong>
                <small v-if="activeItem.banner.text">{{ activeItem.banner.text }}</small>
                <span v-if="activeItem.banner.buttonLabel" class="dsf-mmega__featured-btn">{{ activeItem.banner.buttonLabel }}</span>
              </span>
            </a>
          </div>
        </div>
      </div>
    </transition>

    <!-- Mobile drawer -->
    <div class="dsf-mmega__overlay" :class="{ 'is-open': mobileOpen }" @click="closeMobile"></div>
    <aside
      class="dsf-mmega__drawer"
      :class="{ 'is-open': mobileOpen }"
      :aria-hidden="!mobileOpen"
      aria-label="Mobile navigation"
    >
      <div class="dsf-mmega__drawer-top">
        <span class="dsf-mmega__drawer-title">Menu</span>
        <button type="button" class="dsf-mmega__icon-btn" aria-label="Close menu" @click="closeMobile">
          <X :size="20" />
        </button>
      </div>

      <nav class="dsf-mmega__drawer-nav" aria-label="Mobile menu">
        <div v-for="(item, index) in menuItems" :key="`m-${index}`" class="dsf-mmega__drawer-item">
          <div class="dsf-mmega__drawer-row">
            <a class="dsf-mmega__drawer-link" :href="url(item.url || '#')" @click="guard">{{ item.label }}</a>
            <button
              v-if="hasChildren(item)"
              type="button"
              class="dsf-mmega__drawer-expand"
              :aria-expanded="isExpanded(index)"
              :aria-label="isExpanded(index) ? 'Collapse' : 'Expand'"
              @click="toggleMobileItem(index)"
            >
              <ChevronDown v-if="isExpanded(index)" :size="18" />
              <ChevronRight v-else :size="18" />
            </button>
          </div>

          <div v-if="hasChildren(item) && isExpanded(index)" class="dsf-mmega__drawer-sub">
            <div v-for="(column, columnIndex) in item.columns" :key="`ms-${index}-${columnIndex}`" class="dsf-mmega__drawer-group">
              <div v-if="column.heading" class="dsf-mmega__drawer-group-title">{{ column.heading }}</div>
              <a
                v-for="(link, linkIndex) in column.links"
                :key="`ml-${index}-${columnIndex}-${linkIndex}`"
                class="dsf-mmega__drawer-sublink"
                :href="url(link.url || '#')"
                @click="guard"
              >
                <component v-if="columnLayout(column) === 'icons'" :is="iconFor(link.icon)" :size="16" />
                <span>{{ link.label }}</span>
              </a>
            </div>
          </div>
        </div>
      </nav>
    </aside>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { ChevronDown, ChevronRight, Menu, Search, ShoppingCart, User, X } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { iconFor } from '../../utils/landingIcons'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const COLUMN_LAYOUTS = ['links', 'cards', 'icons']

const defaultMenuItems = [
  { label: 'Products', url: '#', hasMega: true, columns: [
    { heading: 'Categories', layout: 'links', links: [{ label: 'New Arrivals', url: '#' }, { label: 'Best Sellers', url: '#' }] },
  ], banner: { title: 'New Season', text: 'Discover the latest collection.', buttonLabel: 'Shop now', url: '#' } },
  { label: 'Solutions', url: '#', hasMega: false, columns: [], banner: {} },
  { label: 'Contact', url: '#', hasMega: false, columns: [], banner: {} },
]

const root = ref(null)
const searchInput = ref(null)
const activeIndex = ref(null)
const panelHover = ref(false)
const searchOpen = ref(false)
const searchQuery = ref('')
const mobileOpen = ref(false)
const condensed = ref(false)
const mobileExpanded = ref(new Set())
let previousBodyOverflow = ''

const menuItems = computed(() => {
  const list = Array.isArray(props.settings?.menuItems) ? props.settings.menuItems : []
  return list.length ? list : defaultMenuItems
})

const activeItem = computed(() => (activeIndex.value === null ? null : menuItems.value[activeIndex.value] || null))

const numericCartCount = computed(() => {
  const value = parseInt(props.settings?.cartCount ?? 0, 10)
  return Number.isNaN(value) ? 0 : value
})

const logoImageSizePercent = computed(() => {
  const parsed = parseInt(props.settings?.logoImageSize ?? 100, 10)
  if (Number.isNaN(parsed)) return 100
  return Math.min(100, Math.max(30, parsed))
})

const isSticky = computed(() => !props.isEditor && props.settings?.sticky !== false)

const rootClass = computed(() => ({
  'is-editor': props.isEditor,
  'is-sticky': isSticky.value,
  'is-condensed': condensed.value,
  'preview-tablet': props.isEditor && props.previewMode === 'tablet',
  'preview-mobile': props.isEditor && props.previewMode === 'mobile',
}))

const cssVars = computed(() => ({
  '--mmega-bg': props.settings?.navBackground || '#ffffff',
  '--mmega-text': props.settings?.navTextColor || '#111827',
  '--mmega-accent': props.settings?.accentColor || '#2563eb',
  '--mmega-panel-bg': props.settings?.panelBackground || '#ffffff',
  '--mmega-panel-heading': props.settings?.panelHeadingColor || '#111827',
  '--mmega-panel-link': props.settings?.panelLinkColor || '#4b5563',
  '--mmega-mobile-bg': props.settings?.mobileBackground || '#0f172a',
  '--mmega-mobile-text': props.settings?.mobileTextColor || '#ffffff',
}))

function url(value) {
  return safePublicUrl(value || '#')
}

function guard(event) {
  if (props.isEditor) event.preventDefault()
}

function columnLayout(column) {
  if (COLUMN_LAYOUTS.includes(column?.layout)) return column.layout
  return column?.imageLinks ? 'cards' : 'links'
}

function imageColumns(column) {
  const parsed = parseInt(column?.imageColumns ?? 2, 10)
  if (Number.isNaN(parsed)) return 2
  return Math.min(4, Math.max(1, parsed))
}

function hasFeatured(banner) {
  if (!banner || typeof banner !== 'object') return false
  return !!(banner.image || String(banner.title || '').trim() || String(banner.text || '').trim() || String(banner.buttonLabel || '').trim())
}

// ---- Desktop mega panel ----------------------------------------------------
function setActive(index) {
  const item = menuItems.value[index]
  if (!item) return
  if (item.hasMega) {
    activeIndex.value = index
  } else if (!props.isEditor) {
    activeIndex.value = null
  }
}

function onItemClick(event, item, index) {
  if (!item) return
  if (item.hasMega) {
    event.preventDefault()
    activeIndex.value = activeIndex.value === index ? null : index
    return
  }
  guard(event)
}

function onHeaderLeave() {
  if (props.isEditor || panelHover.value) return
  activeIndex.value = null
}

// ---- Search ----------------------------------------------------------------
function onSearchButton() {
  if (!searchOpen.value) {
    searchOpen.value = true
    if (!props.isEditor && typeof window !== 'undefined') {
      window.requestAnimationFrame(() => searchInput.value?.focus())
    }
    return
  }
  submitSearch()
}

function submitSearch() {
  if (props.isEditor) return
  const query = String(searchQuery.value || '').trim()
  if (!query) {
    searchInput.value?.focus()
    return
  }
  const base = props.settings?.searchUrl || '/?s='
  const separator = base.includes('?') ? '' : '?s='
  const target = base.includes('?') || base.includes('=') ? `${base}${encodeURIComponent(query)}` : `${base}${separator}${encodeURIComponent(query)}`
  if (typeof window !== 'undefined') window.location.href = target
}

// ---- Mobile drawer ---------------------------------------------------------
function openMobile() {
  mobileOpen.value = true
}

function closeMobile() {
  mobileOpen.value = false
  mobileExpanded.value = new Set()
}

function hasChildren(item) {
  return !!(item && item.hasMega && Array.isArray(item.columns) && item.columns.length)
}

function toggleMobileItem(index) {
  const next = new Set(mobileExpanded.value)
  if (next.has(index)) {
    next.delete(index)
  } else {
    next.add(index)
  }
  mobileExpanded.value = next
}

function isExpanded(index) {
  return mobileExpanded.value.has(index)
}

// ---- Sticky shrink on scroll ----------------------------------------------
let onScroll = null

function updateCondensed() {
  condensed.value = typeof window !== 'undefined' && window.scrollY > 8
}

// ---- Lifecycle -------------------------------------------------------------
watch(
  () => menuItems.value,
  (items) => {
    if (activeIndex.value !== null && !items[activeIndex.value]?.hasMega) {
      activeIndex.value = null
    }
  }
)

watch(
  () => props.previewMode,
  (mode) => {
    if (mode !== 'desktop') mobileOpen.value = false
  }
)

watch(
  () => mobileOpen.value,
  (open) => {
    if (typeof document === 'undefined') return
    if (open) {
      previousBodyOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = previousBodyOverflow
    }
  }
)

function handleEscape(event) {
  if (event.key !== 'Escape') return
  if (mobileOpen.value) return closeMobile()
  if (searchOpen.value) {
    searchOpen.value = false
    return
  }
  activeIndex.value = null
}

onMounted(() => {
  if (typeof window === 'undefined') return
  window.addEventListener('keydown', handleEscape)
  if (!props.isEditor && props.settings?.sticky !== false && props.settings?.shrinkOnScroll !== false) {
    onScroll = () => updateCondensed()
    window.addEventListener('scroll', onScroll, { passive: true })
    updateCondensed()
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleEscape)
    if (onScroll) window.removeEventListener('scroll', onScroll)
  }
  if (typeof document !== 'undefined') document.body.style.overflow = previousBodyOverflow
})
</script>

<style scoped>
.dsf-mmega {
  --mmega-bg: #ffffff;
  --mmega-text: #111827;
  --mmega-accent: #2563eb;
  --mmega-panel-bg: #ffffff;
  --mmega-panel-heading: #111827;
  --mmega-panel-link: #4b5563;
  --mmega-mobile-bg: #0f172a;
  --mmega-mobile-text: #ffffff;
  position: relative;
  width: 100%;
  z-index: 30;
  background: var(--mmega-bg);
  color: var(--mmega-text);
  font-family: var(--dsf-theme-body-font, 'Inter', sans-serif);
  border-bottom: 1px solid rgba(17, 24, 39, 0.08);
}

.dsf-mmega.is-sticky {
  position: sticky;
  top: 0;
}

.dsf-mmega__bar {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 1.5rem;
  width: min(var(--dsf-theme-container-width, 1280px), 100%);
  margin: 0 auto;
  padding: 1rem 1.25rem;
  transition: padding 0.2s ease;
}

.dsf-mmega.is-condensed .dsf-mmega__bar {
  padding: 0.5rem 1.25rem;
}

/* Brand */
.dsf-mmega__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
}

.dsf-mmega__brand-image {
  display: block;
  height: auto;
  max-height: 48px;
  object-fit: contain;
  transition: max-height 0.2s ease;
}

.dsf-mmega.is-condensed .dsf-mmega__brand-image {
  max-height: 38px;
}

.dsf-mmega__brand-text {
  font-family: var(--dsf-theme-heading-font, 'Inter', sans-serif);
  font-size: clamp(1.1rem, 1.4vw, 1.5rem);
  font-weight: 800;
  letter-spacing: 0.02em;
}

/* Nav */
.dsf-mmega__nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
}

.dsf-mmega__nav-item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.5rem 0.85rem;
  border-radius: 999px;
  color: inherit;
  font-size: 0.98rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s ease, color 0.15s ease;
}

.dsf-mmega__nav-item:hover,
.dsf-mmega__nav-item.is-active {
  background: rgba(17, 24, 39, 0.06);
  color: var(--mmega-accent);
}

.dsf-mmega__chevron {
  transition: transform 0.15s ease;
}

.dsf-mmega__nav-item.is-active .dsf-mmega__chevron {
  transform: rotate(180deg);
}

/* Actions */
.dsf-mmega__actions {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  justify-self: end;
}

.dsf-mmega__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s ease;
}

.dsf-mmega__icon-btn:hover {
  background: rgba(17, 24, 39, 0.06);
}

.dsf-mmega__cart {
  position: relative;
}

.dsf-mmega__cart-count {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--mmega-accent);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 16px;
  text-align: center;
}

/* Expandable search */
.dsf-mmega__search {
  display: inline-flex;
  align-items: center;
}

.dsf-mmega__search-input {
  width: 0;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 999px;
  background: rgba(17, 24, 39, 0.05);
  color: inherit;
  font-size: 0.9rem;
  opacity: 0;
  transition: width 0.2s ease, opacity 0.2s ease, padding 0.2s ease;
}

.dsf-mmega__search.is-open .dsf-mmega__search-input {
  width: clamp(140px, 18vw, 220px);
  padding: 0.5rem 0.9rem;
  opacity: 1;
  border-color: rgba(17, 24, 39, 0.12);
}

.dsf-mmega__mobile-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

/* Mega panel */
.dsf-mmega__panel-wrap {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  z-index: 40;
}

.dsf-mmega__panel {
  width: min(var(--dsf-theme-container-width, 1280px), 100%);
  margin: 0 auto;
  background: var(--mmega-panel-bg);
  border: 1px solid rgba(17, 24, 39, 0.1);
  border-radius: 0 0 16px 16px;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.14);
  overflow: hidden;
}

.dsf-mmega__panel-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem 1.75rem;
}

.dsf-mmega__col-heading {
  margin: 0 0 0.75rem;
  color: var(--mmega-panel-heading);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.dsf-mmega__links {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.dsf-mmega__link {
  color: var(--mmega-panel-link);
  font-size: 0.95rem;
  text-decoration: none;
  transition: color 0.15s ease;
}

.dsf-mmega__link:hover {
  color: var(--mmega-accent);
}

.dsf-mmega__cards {
  display: grid;
  grid-template-columns: repeat(var(--cols, 2), minmax(0, 1fr));
  gap: 0.6rem;
}

.dsf-mmega__card {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 64px;
  padding: 0.5rem;
  border: 1px solid rgba(17, 24, 39, 0.08);
  border-radius: 10px;
  background: rgba(17, 24, 39, 0.02);
  color: var(--mmega-panel-link);
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
  text-decoration: none;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.dsf-mmega__card:hover {
  border-color: var(--mmega-accent);
  background: rgba(37, 99, 235, 0.04);
}

.dsf-mmega__card img {
  max-width: 100%;
  max-height: 44px;
  object-fit: contain;
}

.dsf-mmega__icons {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.4rem;
}

.dsf-mmega__icon-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.45rem 0.5rem;
  border-radius: 10px;
  color: var(--mmega-panel-link);
  font-size: 0.9rem;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s ease, color 0.15s ease;
}

.dsf-mmega__icon-link:hover {
  background: rgba(17, 24, 39, 0.04);
  color: var(--mmega-accent);
}

.dsf-mmega__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  border-radius: 9px;
  background: rgba(37, 99, 235, 0.1);
  color: var(--mmega-accent);
}

/* Featured card */
.dsf-mmega__featured {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 14px;
  background: linear-gradient(160deg, rgba(37, 99, 235, 0.12), rgba(37, 99, 235, 0.04));
  border: 1px solid rgba(37, 99, 235, 0.16);
  text-decoration: none;
  color: var(--mmega-panel-heading);
}

.dsf-mmega__featured img {
  width: 100%;
  height: 120px;
  object-fit: cover;
}

.dsf-mmega__featured-body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem;
}

.dsf-mmega__featured-body strong {
  font-size: 1.05rem;
  font-weight: 700;
}

.dsf-mmega__featured-body small {
  color: var(--mmega-panel-link);
  font-size: 0.88rem;
}

.dsf-mmega__featured-btn {
  margin-top: 0.35rem;
  align-self: flex-start;
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  background: var(--mmega-accent);
  color: #fff;
  font-size: 0.85rem;
  font-weight: 600;
}

/* Mobile drawer */
.dsf-mmega__overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s ease;
  z-index: 60;
}

.dsf-mmega__overlay.is-open {
  opacity: 1;
  pointer-events: auto;
}

.dsf-mmega__drawer {
  position: fixed;
  top: 0;
  right: 0;
  width: min(360px, 86vw);
  height: 100%;
  background: var(--mmega-mobile-bg);
  color: var(--mmega-mobile-text);
  transform: translateX(100%);
  transition: transform 0.25s ease;
  z-index: 70;
  display: flex;
  flex-direction: column;
  box-shadow: -12px 0 30px rgba(15, 23, 42, 0.3);
}

.dsf-mmega__drawer.is-open {
  transform: translateX(0);
}

.dsf-mmega__drawer-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.dsf-mmega__drawer-title {
  font-weight: 700;
}

.dsf-mmega__drawer .dsf-mmega__icon-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.dsf-mmega__drawer-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem 0;
}

.dsf-mmega__drawer-item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.dsf-mmega__drawer-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.4rem 1.1rem;
}

.dsf-mmega__drawer-link {
  flex: 1;
  padding: 0.5rem 0;
  color: inherit;
  font-weight: 600;
  text-decoration: none;
}

.dsf-mmega__drawer-expand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.dsf-mmega__drawer-sub {
  padding: 0 1.1rem 0.75rem;
}

.dsf-mmega__drawer-group {
  padding: 0.4rem 0;
}

.dsf-mmega__drawer-group-title {
  margin-bottom: 0.25rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.7;
}

.dsf-mmega__drawer-sublink {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0;
  color: inherit;
  font-size: 0.92rem;
  text-decoration: none;
  opacity: 0.9;
}

.dsf-mmega-fade-enter-active,
.dsf-mmega-fade-leave-active {
  transition: opacity 0.18s ease;
}

.dsf-mmega-fade-enter-from,
.dsf-mmega-fade-leave-to {
  opacity: 0;
}

/* Responsive + editor preview: collapse to the mobile toggle */
@media (max-width: 980px) {
  .dsf-mmega__nav,
  .dsf-mmega__search,
  .dsf-mmega__cart,
  .dsf-mmega__actions .dsf-mmega__icon-btn:not(.dsf-mmega__mobile-toggle) {
    display: none;
  }

  .dsf-mmega__mobile-toggle {
    display: inline-flex;
  }
}

.dsf-mmega.preview-tablet .dsf-mmega__nav,
.dsf-mmega.preview-mobile .dsf-mmega__nav,
.dsf-mmega.preview-tablet .dsf-mmega__search,
.dsf-mmega.preview-mobile .dsf-mmega__search,
.dsf-mmega.preview-tablet .dsf-mmega__cart,
.dsf-mmega.preview-mobile .dsf-mmega__cart,
.dsf-mmega.preview-tablet .dsf-mmega__actions .dsf-mmega__icon-btn:not(.dsf-mmega__mobile-toggle),
.dsf-mmega.preview-mobile .dsf-mmega__actions .dsf-mmega__icon-btn:not(.dsf-mmega__mobile-toggle) {
  display: none;
}

.dsf-mmega.preview-tablet .dsf-mmega__mobile-toggle,
.dsf-mmega.preview-mobile .dsf-mmega__mobile-toggle {
  display: inline-flex;
}
</style>
