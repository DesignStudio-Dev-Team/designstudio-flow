<template>
  <header class="dsf-showcase-header" :class="previewClass" :style="cssVars" @mouseleave="closeDesktop">
    <div class="dsf-showcase-header__utility">
      <div class="dsf-showcase-header__container dsf-showcase-header__utility-inner">
        <a class="dsf-showcase-header__promo" :href="url(settings.promoUrl)" @click="guardLink">{{ settings.promoText || 'Seasonal Event' }}</a>
        <nav aria-label="Utility navigation">
          <div v-for="(item, index) in navigation.utility" :key="`utility-${index}`" class="dsf-showcase-header__utility-item">
            <a :href="url(item.url)" :aria-expanded="isDesktopOpen('utility', index)" @mouseenter="openDesktop('utility', index)" @focus="openDesktop('utility', index)" @click="activateUtility($event, item, index)">
              <component :is="iconFor(item.icon)" :size="16" /><span>{{ item.label }}</span><ChevronDown v-if="item.kind !== 'link'" :size="14" />
            </a>
            <div v-if="item.kind === 'dropdown' && isDesktopOpen('utility', index)" class="dsf-showcase-header__dropdown">
              <a v-for="(link, linkIndex) in item.links" :key="linkIndex" :href="url(link.url)" @click="guardLink">{{ link.label }}</a>
            </div>
          </div>
        </nav>
      </div>
    </div>

    <div class="dsf-showcase-header__main">
      <div class="dsf-showcase-header__container dsf-showcase-header__main-inner">
        <a class="dsf-showcase-header__brand" :href="url(settings.homeUrl, '/')" @click="guardLink">
          <img v-if="settings.logoImage" :src="settings.logoImage" :alt="settings.logoAlt || 'Site logo'" />
          <span v-else>{{ settings.logoText || 'YOUR BRAND' }}</span>
        </a>
        <nav class="dsf-showcase-header__desktop-nav" aria-label="Primary navigation">
          <a v-for="(item, index) in navigation.menu" :key="`menu-${index}`" :href="url(item.url)" :aria-expanded="item.hasMega ? isDesktopOpen('menu', index) : undefined" @mouseenter="openDesktop('menu', index)" @focus="openDesktop('menu', index)" @click="activateMenu($event, item, index)">{{ item.label }}<ChevronDown v-if="item.hasMega" :size="14" /></a>
          <a v-if="settings.specialButtonText" class="dsf-showcase-header__special" :href="url(settings.specialButtonUrl)" @click="guardLink">{{ settings.specialButtonText }}</a>
        </nav>
        <div class="dsf-showcase-header__mobile-actions">
          <a v-if="settings.showMobileSearch !== false" :href="url(settings.searchUrl, '/?s=')" aria-label="Search" @click="guardLink"><Search :size="24" /></a>
          <button type="button" aria-label="Open menu" :aria-expanded="mobileOpen" @click="openMobile"><Menu :size="26" /></button>
        </div>
      </div>
    </div>

    <transition name="dsf-showcase-panel">
      <ShowcaseMegaPanel v-if="activePanel" :panel="activePanel" :is-editor="isEditor" @navigate="guardLink" />
    </transition>

    <div class="dsf-showcase-header__overlay" :class="{ 'is-open': mobileOpen }" @click="closeMobile"></div>
    <aside class="dsf-showcase-header__drawer" :class="{ 'is-open': mobileOpen }" :aria-hidden="!mobileOpen" aria-label="Mobile navigation">
      <div class="dsf-showcase-header__drawer-top">
        <button type="button" @click="showMobileView('locations')"><MapPin :size="16" />{{ settings.mobileLocationsLabel || 'Locations' }}</button>
        <button type="button" @click="showMobileView('calls')"><Phone :size="16" />{{ settings.mobileCallLabel || 'Call Us' }}</button>
        <button type="button" aria-label="Close menu" @click="closeMobile"><X :size="20" /></button>
      </div>
      <div v-if="mobileView !== 'root'" class="dsf-showcase-header__drawer-back"><button type="button" @click="mobileView = 'root'; mobileItem = null"><ChevronLeft :size="16" /> Main Menu</button></div>
      <nav v-if="mobileView === 'root'" class="dsf-showcase-header__drawer-list" aria-label="Mobile primary navigation">
        <button v-for="(item, index) in navigation.menu" :key="`mobile-menu-${index}`" type="button" @click="openMobileItem(item)"><span>{{ item.label }}</span><ChevronRight v-if="item.hasMega" :size="18" /></button>
        <button v-for="(item, index) in mobileUtilityItems" :key="`mobile-utility-${index}`" type="button" @click="openMobileUtility(item)"><span>{{ item.label }}</span><ChevronRight :size="18" /></button>
        <a v-if="settings.specialButtonText" :href="url(settings.specialButtonUrl)" @click="guardLink">{{ settings.specialButtonText }}</a>
      </nav>
      <ShowcaseMegaPanel v-else-if="mobileView === 'panel'" :panel="mobileItem.panel" :is-editor="isEditor" mobile @navigate="guardLink" />
      <div v-else-if="mobileView === 'dropdown'" class="dsf-showcase-header__drawer-list"><a v-for="(link, index) in mobileItem.links" :key="index" :href="url(link.url)" @click="guardLink">{{ link.label }}</a></div>
      <div v-else-if="mobileView === 'locations'" class="dsf-showcase-header__locations">
        <article v-for="(location, index) in navigation.locations" :key="index"><img v-if="location.image" :src="location.image" :alt="location.name || 'Location'" /><h3>{{ location.name }}</h3><p>{{ location.address }}</p><p>{{ location.hours }}</p><a v-if="location.phone" :href="url(location.phoneUrl)">{{ location.phone }}</a><a v-if="location.directionsUrl" :href="url(location.directionsUrl)">Get Directions</a></article>
      </div>
      <div v-else-if="mobileView === 'calls'" class="dsf-showcase-header__drawer-list"><a v-for="(call, index) in navigation.calls" :key="index" :href="url(call.url)"><Phone :size="16" />{{ call.label }}</a></div>
    </aside>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { BookOpen, ChevronDown, ChevronLeft, ChevronRight, MapPin, Menu, Phone, Search, Settings, X } from 'lucide-vue-next'
import { safePublicUrl } from '../../utils/safeUrl'
import ShowcaseMegaPanel from './ShowcaseMegaPanel.vue'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: Boolean, previewMode: { type: String, default: 'desktop' } })
const active = ref(null), mobileOpen = ref(false), mobileView = ref('root'), mobileItem = ref(null)
const navigation = computed(() => ({ utility: Array.isArray(props.settings.navigation?.utility) ? props.settings.navigation.utility.slice(0, 4) : [], menu: Array.isArray(props.settings.navigation?.menu) ? props.settings.navigation.menu.slice(0, 8) : [], locations: Array.isArray(props.settings.navigation?.locations) ? props.settings.navigation.locations.slice(0, 6) : [], calls: Array.isArray(props.settings.navigation?.calls) ? props.settings.navigation.calls.slice(0, 8) : [] }))
const previewClass = computed(() => ({ 'is-tablet-preview': props.previewMode === 'tablet', 'is-mobile-preview': props.previewMode === 'mobile' }))
const mobileUtilityItems = computed(() => navigation.value.utility.filter((item) => ['mega', 'dropdown'].includes(item.kind)))
const activePanel = computed(() => { if (!active.value) return null; const items = active.value.type === 'menu' ? navigation.value.menu : navigation.value.utility; const item = items[active.value.index]; return item?.panel && (item.hasMega || item.kind === 'mega') ? item.panel : null })
const cssVars = computed(() => ({ '--utility-bg': props.settings.utilityBackground || '#2f73b6', '--utility-text': props.settings.utilityTextColor || '#fff', '--nav-bg': props.settings.navBackground || '#0d0d0d', '--nav-text': props.settings.navTextColor || '#fff', '--accent': props.settings.accentColor || '#2f73b6', '--panel-bg': props.settings.panelBackground || '#fff', '--panel-text': props.settings.panelTextColor || '#171717', '--drawer-bg': props.settings.mobileBackground || '#2f73b6', '--drawer-text': props.settings.mobileTextColor || '#fff', '--logo-width': `${Number(props.settings.logoWidth) || 250}px` }))
function url(value, fallback = '#') { return safePublicUrl(value, fallback) }
function iconFor(icon) { return { book: BookOpen, 'map-pin': MapPin, phone: Phone, settings: Settings }[icon] || Settings }
function isDesktopOpen(type, index) { return active.value?.type === type && active.value?.index === index }
function openDesktop(type, index) { active.value = { type, index } }
function closeDesktop() { active.value = null }
function guardLink(event) { if (props.isEditor) event.preventDefault() }
function activateMenu(event, item, index) { if (item.hasMega) { event.preventDefault(); openDesktop('menu', index) } else guardLink(event) }
function activateUtility(event, item, index) { if (item.kind !== 'link') { event.preventDefault(); if (item.kind === 'locations' || item.kind === 'calls') { openMobile(); showMobileView(item.kind) } else openDesktop('utility', index) } else guardLink(event) }
function openMobile() { mobileOpen.value = true; mobileView.value = 'root'; if (typeof document !== 'undefined') document.body.style.overflow = 'hidden' }
function closeMobile() { mobileOpen.value = false; mobileView.value = 'root'; mobileItem.value = null; if (typeof document !== 'undefined') document.body.style.overflow = '' }
function showMobileView(view) { mobileView.value = view; mobileItem.value = null }
function openMobileItem(item) { if (item.hasMega) { mobileItem.value = item; mobileView.value = 'panel' } else if (!props.isEditor && typeof window !== 'undefined') window.location.assign(url(item.url)) }
function openMobileUtility(item) { mobileItem.value = item; mobileView.value = item.kind === 'mega' ? 'panel' : 'dropdown' }
function onKeydown(event) { if (event.key === 'Escape') closeMobile() }
onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => { document.removeEventListener('keydown', onKeydown); closeMobile() })
</script>

<style>
.dsf-showcase-header{position:relative;z-index:30;font-family:inherit}.dsf-showcase-header a{text-decoration:none;color:inherit}.dsf-showcase-header__container{width:min(1536px,100%);margin:auto;padding:0 4.5vw}.dsf-showcase-header__utility{background:var(--utility-bg);color:var(--utility-text)}.dsf-showcase-header__utility-inner{height:46px;display:flex;align-items:center;justify-content:space-between}.dsf-showcase-header__promo{font-weight:700}.dsf-showcase-header__utility nav,.dsf-showcase-header__utility-item>a,.dsf-showcase-header__desktop-nav,.dsf-showcase-header__desktop-nav>a{display:flex;align-items:center}.dsf-showcase-header__utility nav{gap:28px}.dsf-showcase-header__utility-item{position:relative}.dsf-showcase-header__utility-item>a{gap:8px;font-weight:700;text-transform:uppercase}.dsf-showcase-header__dropdown{position:absolute;right:0;top:35px;min-width:250px;padding:14px 0;background:#fff;color:var(--accent);border-radius:0 0 18px 18px;box-shadow:0 20px 40px #0002}.dsf-showcase-header__dropdown a{display:block;padding:12px 28px;font-weight:700}.dsf-showcase-header__main{background:var(--nav-bg);color:var(--nav-text)}.dsf-showcase-header__main-inner{height:98px;display:flex;align-items:center;justify-content:space-between;gap:30px}.dsf-showcase-header__brand{display:flex;align-items:center;font-size:25px;font-weight:800;letter-spacing:.08em}.dsf-showcase-header__brand img{display:block;width:min(var(--logo-width),28vw);max-height:76px;object-fit:contain}.dsf-showcase-header__desktop-nav{justify-content:flex-end;gap:28px}.dsf-showcase-header__desktop-nav>a{gap:6px;font-weight:800;text-transform:uppercase;white-space:nowrap}.dsf-showcase-header__special{padding:14px 31px;border-radius:999px;background:var(--accent);box-shadow:0 9px 20px color-mix(in srgb,var(--accent),transparent 55%)}.dsf-showcase-header__panel-shell{position:absolute;right:3vw;top:calc(100% - 18px);width:min(1050px,92vw);padding:30px;background:var(--panel-bg);color:var(--panel-text);border-radius:20px;box-shadow:0 28px 60px #0002}.dsf-showcase-header__panel{display:grid;grid-template-columns:280px 1fr 240px;gap:30px}.dsf-showcase-header__intro{padding-right:28px;border-right:1px solid #e5e7eb}.dsf-showcase-header__intro h2,.dsf-showcase-header__mobile-intro h2{font-size:24px;margin:8px 0 16px}.dsf-showcase-header__intro p,.dsf-showcase-header__mobile-intro p{line-height:1.55}.dsf-showcase-header__intro>a,.dsf-showcase-header__mobile-intro>a{display:block;margin-top:24px;padding:14px;background:var(--accent);color:#fff;text-align:center;border-radius:999px;font-weight:800}.dsf-showcase-header__cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.dsf-showcase-header__cards>a{min-height:82px;border:1px solid #e1e4e8;border-radius:9px;padding:12px;display:flex;align-items:center;gap:12px}.dsf-showcase-header__cards img{width:54px;height:54px;object-fit:contain}.dsf-showcase-header__cards span{display:grid}.dsf-showcase-header__cards small{text-transform:uppercase;color:#777;font-size:10px;font-weight:700}.dsf-showcase-header__accent-link{display:block;margin-top:14px;padding:16px;background:var(--accent);color:#fff;text-align:center;font-weight:800;border-radius:7px}.dsf-showcase-header__panel-promo{position:relative;min-height:250px;border-radius:13px;overflow:hidden;background:#0d2b57;display:flex;align-items:flex-end}.dsf-showcase-header__panel-promo img{position:absolute;width:100%;height:100%;object-fit:cover}.dsf-showcase-header__panel-promo:after{content:"";position:absolute;inset:45% 0 0;background:linear-gradient(transparent,#0d2b57)}.dsf-showcase-header__panel-promo span{position:relative;z-index:1;width:100%;padding:24px;text-align:center;color:#fff;display:grid;text-transform:uppercase}.dsf-showcase-header__panel-promo small{margin-top:8px}.dsf-showcase-header__mobile-actions{display:none;align-items:center;gap:18px}.dsf-showcase-header__mobile-actions button{border:0;background:none;color:inherit}.dsf-showcase-header__overlay{position:fixed;inset:0;background:#0008;opacity:0;visibility:hidden;transition:.25s}.dsf-showcase-header__overlay.is-open{opacity:1;visibility:visible}.dsf-showcase-header__drawer{position:fixed;z-index:3;right:0;top:0;bottom:0;width:min(420px,100vw);overflow:auto;background:var(--drawer-bg);color:var(--drawer-text);transform:translateX(100%);transition:transform .3s}.dsf-showcase-header__drawer.is-open{transform:none}.dsf-showcase-header__drawer button{font:inherit}.dsf-showcase-header__drawer-top{display:grid;grid-template-columns:1fr 1fr 60px;background:#fff;color:#0d4f87}.dsf-showcase-header__drawer-top button,.dsf-showcase-header__drawer-back button{min-height:60px;border:0;border-right:1px solid #d9e0e7;background:transparent;color:inherit;display:flex;align-items:center;justify-content:center;gap:8px;font-weight:800;text-transform:uppercase}.dsf-showcase-header__drawer-back{background:#255f96}.dsf-showcase-header__drawer-back button{justify-content:flex-start;padding:0 20px}.dsf-showcase-header__drawer-list{display:grid}.dsf-showcase-header__drawer-list>a,.dsf-showcase-header__drawer-list>button{min-height:59px;padding:0 20px;border:0;border-bottom:1px solid #ffffff30;background:transparent;color:inherit;display:flex;align-items:center;justify-content:space-between;font-weight:800;text-transform:uppercase;text-align:left}.dsf-showcase-header__locations article{padding:20px;border-bottom:1px solid #ffffff30}.dsf-showcase-header__locations img{width:100%;height:130px;object-fit:cover;border-radius:50px 10px}.dsf-showcase-header__locations p{white-space:pre-line}.dsf-showcase-header__locations a{display:inline-block;margin:5px 15px 0 0}.dsf-showcase-header__mobile-panel{background:#fff;color:#171717}.dsf-showcase-header__mobile-intro{padding:20px}.dsf-showcase-header__mobile-panel .dsf-showcase-header__cards{display:grid;grid-template-columns:1fr}.dsf-showcase-header__mobile-panel .dsf-showcase-header__cards>a{border-width:1px 0 0;border-radius:0;padding:15px 20px}.dsf-showcase-header__mobile-panel .dsf-showcase-header__accent-link{border-radius:0;margin:0}.dsf-showcase-header__mobile-promo{display:block;padding:18px}.dsf-showcase-header__mobile-promo img{width:100%;border-radius:14px}.dsf-showcase-panel-enter-active,.dsf-showcase-panel-leave-active{transition:.18s}.dsf-showcase-panel-enter-from,.dsf-showcase-panel-leave-to{opacity:0;transform:translateY(-8px)}
@media(max-width:1100px){.dsf-showcase-header__utility,.dsf-showcase-header__desktop-nav{display:none}.dsf-showcase-header__main-inner{height:112px}.dsf-showcase-header__mobile-actions{display:flex}.dsf-showcase-header__brand img{width:min(var(--logo-width),55vw)}.dsf-showcase-header__panel-shell{display:none}}
.is-tablet-preview .dsf-showcase-header__utility,.is-tablet-preview .dsf-showcase-header__desktop-nav,.is-mobile-preview .dsf-showcase-header__utility,.is-mobile-preview .dsf-showcase-header__desktop-nav{display:none}.is-tablet-preview .dsf-showcase-header__mobile-actions,.is-mobile-preview .dsf-showcase-header__mobile-actions{display:flex}.is-mobile-preview .dsf-showcase-header__main-inner{height:90px}.is-mobile-preview .dsf-showcase-header__brand img{width:min(var(--logo-width),55vw)}
</style>
