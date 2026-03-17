<template>
  <header
    class="dsf-header-mega"
    :class="previewClass"
    @mouseleave="onHeaderLeave"
  >
    <div
      class="dsf-header-mega__top"
      :style="{ backgroundColor: settings.topBarBackground || '#EFEFF1', color: settings.topBarTextColor || '#2C6B34', minHeight: `${settings.topBarHeight || 72}px` }"
    >
      <div class="dsf-header-mega__container dsf-header-mega__top-inner" :style="{ '--top-side-padding': `${topBarSidePadding}px` }">
        <nav class="dsf-header-mega__utility" aria-label="Utility links">
          <a
            v-for="(link, index) in utilityLinks"
            :key="`utility-${index}`"
            :href="link.url || '#'"
            class="dsf-header-mega__utility-link"
            :style="{ color: settings.topBarTextColor || '#2C6B34' }"
            @click="preventInEditor"
          >
            <InlineText
              tagName="span"
              v-model="link.label"
              :is-editor="isEditor"
              placeholder="Utility link"
              @click.stop
            />
          </a>
        </nav>

        <a class="dsf-header-mega__brand" :href="settings.homeUrl || '/'" @click="preventInEditor">
          <img
            v-if="settings.logoImage"
            :src="settings.logoImage"
            alt="Logo"
            class="dsf-header-mega__brand-image"
            :style="{ width: `${logoImageSizePercent}%`, maxHeight: `${logoImageMaxHeight}px` }"
          />
          <InlineText
            v-else
            tagName="span"
            class="dsf-header-mega__brand-text"
            v-model="settings.logoText"
            :is-editor="isEditor"
            placeholder="Brand name"
            :style="{ color: settings.logoColor || '#111827' }"
            @click.stop
          />
        </a>

        <div class="dsf-header-mega__actions" :style="{ '--icon-bg': settings.iconBackground || '#2C6B34', '--icon-color': settings.iconColor || '#fff' }">
          <button v-if="settings.showLanguage" class="dsf-header-mega__icon-btn" @click="preventInEditor">
            <Globe :size="16" />
          </button>
          <button v-if="settings.showSearch !== false" class="dsf-header-mega__icon-btn" @click="preventInEditor">
            <Search :size="16" />
          </button>
          <button v-if="settings.showAccount !== false" class="dsf-header-mega__icon-btn" @click="preventInEditor">
            <User :size="16" />
          </button>
          <button v-if="settings.showCart !== false" class="dsf-header-mega__icon-btn dsf-header-mega__icon-btn--cart" @click="preventInEditor">
            <ShoppingCart :size="16" />
            <span>{{ numericCartCount }}</span>
          </button>
        </div>

        <div class="dsf-header-mega__mobile-actions">
          <button class="dsf-header-mega__mobile-toggle" type="button" @click="openMobileMenu">
            <Menu :size="20" />
          </button>
        </div>
      </div>
    </div>

    <div
      class="dsf-header-mega__menu"
      :style="{
        backgroundColor: settings.mainNavBackground || '#2C6B34',
        minHeight: `${resolvedMenuBarHeight}px`,
        '--menu-height': `${resolvedMenuBarHeight}px`,
        '--menu-text': settings.mainNavTextColor || '#fff',
        '--menu-divider': settings.mainNavBorderColor || '#5E8A62',
      }"
    >
      <div class="dsf-header-mega__container dsf-header-mega__menu-row">
        <a
          v-for="(item, index) in menuItems"
          :key="`menu-${index}`"
          :href="item.url || '#'"
          class="dsf-header-mega__menu-item"
          :class="{ 'is-active': activeIndex === index }"
          :style="activeIndex === index ? { backgroundColor: settings.activeNavBackground || '#fff', color: settings.activeNavTextColor || '#111827' } : {}"
          @mouseenter="setActive(index)"
          @click="onMenuClick($event, index)"
        >
          <InlineText
            tagName="span"
            v-model="item.label"
            :is-editor="isEditor"
            placeholder="Menu Item"
            @click.stop
          />
          <ChevronDown v-if="item.hasMega" :size="14" />
        </a>
      </div>
    </div>

    <transition name="dsf-mega-fade">
      <div
        v-if="activeItem && activeItem.hasMega"
        class="dsf-header-mega__panel-wrap"
        :style="{ '--panel-bg': settings.megaBackground || '#fff', '--panel-heading': settings.megaHeadingColor || '#111827', '--panel-link': settings.megaLinkColor || '#374151', '--panel-border': settings.megaBorderColor || '#E5E7EB', '--panel-height': `${settings.megaMinHeight || 160}px` }"
      >
        <div class="dsf-header-mega__container">
          <div class="dsf-header-mega__panel" @mouseenter="panelHover = true" @mouseleave="panelHover = false">
            <div class="dsf-header-mega__panel-columns">
              <div
                v-for="(column, columnIndex) in activeItem.columns"
                :key="`column-${columnIndex}`"
                class="dsf-header-mega__column"
                :class="{ 'dsf-header-mega__column--cards': isCardColumn(column, columnIndex) }"
              >
                <InlineText
                  tagName="h4"
                  v-model="column.heading"
                  :is-editor="isEditor"
                  :placeholder="`Sub Heading ${columnIndex + 1}`"
                />
                <div
                  class="dsf-header-mega__column-links"
                  :style="isCardColumn(column, columnIndex) ? { '--image-link-columns': getImageColumnCount(column) } : null"
                >
                  <a
                    v-for="(link, linkIndex) in column.links"
                    :key="`link-${columnIndex}-${linkIndex}`"
                    :href="link.url || '#'"
                    class="dsf-header-mega__panel-link"
                    :class="{ 'dsf-header-mega__panel-link--card': isCardColumn(column, columnIndex) }"
                    @click="preventInEditor"
                  >
                    <img
                      v-if="isCardColumn(column, columnIndex) && link.image"
                      :src="link.image"
                      :alt="link.label || 'Brand'"
                      class="dsf-header-mega__panel-link-image"
                      :style="{ width: `${megaBrandImageSizePercent}%` }"
                    />
                    <InlineText
                      v-if="!(isCardColumn(column, columnIndex) && link.image)"
                      tagName="span"
                      v-model="link.label"
                      :is-editor="isEditor"
                      :placeholder="`Link ${linkIndex + 1}`"
                      @click.stop
                    />
                  </a>
                </div>
              </div>
            </div>
            <a
              v-if="hasBannerContent(activeItem.banner)"
              class="dsf-header-mega__banner"
              :href="activeItem.banner.url || '#'"
              @click="preventInEditor"
            >
              <img v-if="activeItem.banner.image" :src="activeItem.banner.image" alt="Menu banner" />
              <InlineText
                v-else
                tagName="div"
                class="dsf-header-mega__banner-placeholder"
                v-model="activeItem.banner.title"
                :is-editor="isEditor"
                placeholder="Menu Banner"
                @click.stop
              />
            </a>
          </div>
        </div>
      </div>
    </transition>

    <div
      class="dsf-header-mega__mobile-overlay"
      :class="{ 'is-open': mobileOpen }"
      @click="closeMobileMenu"
    ></div>

    <aside
      class="dsf-header-mega__mobile-drawer"
      :class="{ 'is-open': mobileOpen }"
      :style="{
        '--mobile-bg': settings.mobileMenuBackground || '#27357a',
        '--mobile-text': settings.mobileMenuTextColor || '#ffffff',
        '--mobile-divider': settings.mobileMenuDividerColor || '#3c4a93',
        '--mobile-top-bg': settings.mobileMenuTopBackground || '#ffffff',
        '--mobile-top-text': settings.mobileMenuTopTextColor || '#1f2a44',
        '--mobile-button-bg': settings.mobileMenuButtonBackground || '#3c6fb2',
        '--mobile-button-text': settings.mobileMenuButtonTextColor || '#ffffff',
      }"
    >
      <div class="dsf-header-mega__mobile-top">
        <div class="dsf-header-mega__mobile-top-actions">
          <button
            v-if="settings.mobileShowFindLocation !== false"
            class="dsf-header-mega__mobile-top-link"
            type="button"
            @click="openFindPopup('location')"
          >
            <MapPin :size="16" />
            <span>{{ settings.mobileFindLabel || 'Find a Store' }}</span>
          </button>
          <a
            v-if="mobilePhonePosition === 'top' && settings.mobilePhoneNumber && mobilePhoneUrl"
            class="dsf-header-mega__mobile-top-link"
            :href="mobilePhoneUrl"
            @click="preventInEditor"
          >
            <Phone :size="16" />
            <span>{{ settings.mobilePhoneNumber }}</span>
          </a>
        </div>
        <button class="dsf-header-mega__mobile-close" type="button" @click="closeMobileMenu">
          <X :size="20" />
        </button>
      </div>

      <nav class="dsf-header-mega__mobile-nav" aria-label="Mobile menu">
        <div
          v-for="(item, index) in mobileMenuItems"
          :key="`mobile-item-${index}`"
          class="dsf-header-mega__mobile-item"
        >
          <div class="dsf-header-mega__mobile-row">
            <a
              class="dsf-header-mega__mobile-link"
              :href="item.url || '#'"
              @click="preventInEditor"
            >
              <InlineText
                tagName="span"
                v-model="item.label"
                :is-editor="isEditor"
                placeholder="Menu Item"
                @click.stop
              />
            </a>
            <button
              v-if="hasMobileChildren(item)"
              class="dsf-header-mega__mobile-expand"
              type="button"
              @click="toggleMobileItem(index)"
            >
              <ChevronDown v-if="isMobileExpanded(index)" :size="18" />
              <ChevronRight v-else :size="18" />
            </button>
          </div>

          <div v-if="hasMobileChildren(item) && isMobileExpanded(index)" class="dsf-header-mega__mobile-submenu">
            <div
              v-for="(column, columnIndex) in item.columns"
              :key="`mobile-column-${index}-${columnIndex}`"
              class="dsf-header-mega__mobile-group"
            >
              <div class="dsf-header-mega__mobile-group-head">
                <InlineText
                  v-if="column.heading || isEditor"
                  tagName="div"
                  class="dsf-header-mega__mobile-group-title"
                  v-model="column.heading"
                  :is-editor="isEditor"
                  placeholder="Sub Heading"
                  @click.stop
                />
                <button
                  v-if="hasMobileColumnLinks(column)"
                  class="dsf-header-mega__mobile-expand"
                  type="button"
                  @click="toggleMobileColumn(index, columnIndex)"
                >
                  <ChevronDown v-if="isMobileColumnExpanded(index, columnIndex)" :size="18" />
                  <ChevronRight v-else :size="18" />
                </button>
              </div>
              <div v-if="hasMobileColumnLinks(column) && isMobileColumnExpanded(index, columnIndex)" class="dsf-header-mega__mobile-group-links">
                <a
                  v-for="(link, linkIndex) in column.links"
                  :key="`mobile-link-${index}-${columnIndex}-${linkIndex}`"
                  class="dsf-header-mega__mobile-sublink"
                  :href="link.url || '#'"
                  @click="preventInEditor"
                >
                  <InlineText
                    tagName="span"
                    v-model="link.label"
                    :is-editor="isEditor"
                    :placeholder="link.image ? 'Image link' : `Link ${linkIndex + 1}`"
                    @click.stop
                  />
                </a>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <a
        v-if="mobilePhonePosition === 'bottom' && settings.mobilePhoneNumber && mobilePhoneUrl"
        class="dsf-header-mega__mobile-phone"
        :href="mobilePhoneUrl"
        @click="preventInEditor"
      >
        <Phone :size="18" />
        <span>{{ settings.mobilePhoneNumber }}</span>
      </a>
    </aside>

    <div v-if="findPopupOpen" class="dsf-header-mega__find-overlay" @click="closeFindPopup">
      <div class="dsf-header-mega__find-modal" @click.stop>
        <div class="dsf-header-mega__find-header">
          <h3>{{ findPopupTitle }}</h3>
          <button class="dsf-header-mega__find-close" type="button" @click="closeFindPopup">
            <X :size="18" />
          </button>
        </div>
        <div class="dsf-header-mega__find-search">
          <input type="text" class="dsf-input" placeholder="Zip code or city" />
          <button class="dsf-header-mega__find-search-btn" type="button">
            Search
          </button>
        </div>
        <div
          class="dsf-header-mega__find-results"
          :style="{
            '--find-modal-bg': settings.mobileFindModalBackground || '#ffffff',
            '--find-modal-text': settings.mobileFindModalTextColor || '#1f2a44',
            '--find-modal-link': settings.mobileFindModalLinkColor || '#2c3d87',
            '--find-modal-maps-link': settings.mobileFindModalMapsLinkColor || '#2c3d87',
            '--find-modal-button-bg': settings.mobileFindModalButtonBackground || '#2c3d87',
            '--find-modal-button-text': settings.mobileFindModalButtonText || '#ffffff',
          }"
        >
          <div
            v-for="(store, storeIndex) in mobileStores"
            :key="`store-${storeIndex}`"
            class="dsf-header-mega__find-card"
          >
            <InlineText
              tagName="h4"
              v-model="store.title"
              :is-editor="isEditor"
              placeholder="Store Name"
              @click.stop
            />
            <InlineText
              tagName="div"
              class="dsf-header-mega__find-address"
              v-model="store.address"
              :is-editor="isEditor"
              placeholder="Store address"
              @click.stop
            />
            <a :href="store.mapsUrl || '#'" class="dsf-header-mega__find-map-link" @click="preventInEditor">
              <InlineText
                tagName="span"
                v-model="store.mapsLabel"
                :is-editor="isEditor"
                placeholder="Open in Google Maps"
                @click.stop
              />
            </a>
            <a
              v-if="store.buttonLabel"
              :href="store.buttonUrl || '#'"
              class="dsf-header-mega__find-button"
              @click="preventInEditor"
            >
              <InlineText
                tagName="span"
                v-model="store.buttonLabel"
                :is-editor="isEditor"
                placeholder="Set as Default"
                @click.stop
              />
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, ref, watch, watchEffect } from 'vue'
import { ChevronDown, ChevronRight, Globe, MapPin, Menu, Phone, Search, ShoppingCart, User, X } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: {
    type: Boolean,
    default: false,
  },
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const activeIndex = ref(null)
const panelHover = ref(false)
const mobileOpen = ref(false)
const mobileExpanded = ref(new Set())
const mobileExpandedColumns = ref(new Set())
const findPopupOpen = ref(false)
const findPopupType = ref('store')

const defaultUtilityLinks = [
  { label: 'Test', url: '#' },
  { label: 'About', url: '#' },
]

const defaultMegaColumns = [
  {
    heading: 'Shop by Brand',
    imageLinks: true,
    imageColumns: 2,
    links: [
      { label: 'Brand 1', url: '#', image: '' },
      { label: 'Brand 2', url: '#', image: '' },
      { label: 'Brand 3', url: '#', image: '' },
      { label: 'Brand 4', url: '#', image: '' },
      { label: 'Brand 5', url: '#', image: '' },
    ],
  },
  {
    heading: 'Shop by Series',
    imageLinks: false,
    imageColumns: 2,
    links: [
      { label: 'Highlife Collection', url: '#' },
      { label: 'Limelight Collection', url: '#' },
      { label: 'Hot Spot Collection', url: '#' },
    ],
  },
  {
    heading: 'Shop by Size',
    imageLinks: false,
    imageColumns: 2,
    links: [
      { label: '1-3 Seats', url: '#' },
      { label: '4-5 Seats', url: '#' },
      { label: '6-8+ Seats', url: '#' },
    ],
  },
  {
    heading: 'Other',
    imageLinks: false,
    imageColumns: 2,
    links: [
      { label: "Buyer's Guides", url: '#' },
      { label: "Owner's Manuals", url: '#' },
      { label: 'Pre-Delivery Instructions', url: '#' },
      { label: 'Warranties', url: '#' },
    ],
  },
]

const defaultMenuItems = [
  { label: 'Product Line 1', url: '#', hasMega: true, columns: defaultMegaColumns, banner: { title: '', image: '', url: '#' } },
  { label: 'Product Line 2', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'Product Line 3', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'Product Line 4', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'Promotions', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'Shop Online', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
]

function cloneMenuItems(items) {
  return items.map((item) => ({
    label: item.label || '',
    url: item.url || '#',
    hasMega: !!item.hasMega,
    columns: Array.isArray(item.columns)
      ? item.columns.map((column, columnIndex) => ({
        heading: column.heading || '',
        imageLinks: typeof column.imageLinks === 'boolean' ? column.imageLinks : columnIndex === 0,
        imageColumns: getImageColumnCount(column),
        links: Array.isArray(column.links)
          ? column.links.map((link) => ({
            label: link.label || '',
            url: link.url || '#',
            image: link.image || '',
          }))
          : [],
      }))
      : [],
    banner: item.banner
      ? { title: item.banner.title || '', image: item.banner.image || '', url: item.banner.url || '#' }
      : { title: '', image: '', url: '#' },
  }))
}

const utilityLinks = computed(() => {
  const list = Array.isArray(props.settings?.utilityLinks) ? props.settings.utilityLinks : []
  if (!list.length) {
    return defaultUtilityLinks
  }
  return list
})

const menuItems = computed(() => {
  const list = Array.isArray(props.settings?.menuItems) ? props.settings.menuItems : []
  if (!list.length) {
    return defaultMenuItems
  }
  return list
})

const previewClass = computed(() => ({
  'preview-tablet': props.isEditor && props.previewMode === 'tablet',
  'preview-mobile': props.isEditor && props.previewMode === 'mobile',
}))

const mobileMenuItems = computed(() => {
  const list = Array.isArray(props.settings?.mobileMenuItems) ? props.settings.mobileMenuItems : []
  if (list.length) {
    return list
  }
  return menuItems.value
})

watchEffect(() => {
  if (!props.isEditor || !props.settings) return

  if (props.settings.logoText === undefined || props.settings.logoText === null) {
    props.settings.logoText = 'DESIGNSTUDIO'
  }

  if (!Array.isArray(props.settings.utilityLinks) || !props.settings.utilityLinks.length) {
    props.settings.utilityLinks = defaultUtilityLinks.map((link) => ({ ...link }))
  }

  if (!Array.isArray(props.settings.menuItems) || !props.settings.menuItems.length) {
    props.settings.menuItems = cloneMenuItems(defaultMenuItems)
  }

  if (!Array.isArray(props.settings.mobileMenuItems) || !props.settings.mobileMenuItems.length) {
    props.settings.mobileMenuItems = cloneMenuItems(props.settings.menuItems.length ? props.settings.menuItems : defaultMenuItems)
  }

  if (!Array.isArray(props.settings.mobileStores) || !props.settings.mobileStores.length) {
    props.settings.mobileStores = defaultMobileStores.map((store) => ({ ...store }))
  }

  props.settings.menuItems.forEach((item) => {
    if (!Array.isArray(item.columns)) {
      item.columns = []
    }
    if (item.hasMega && !item.columns.length) {
      item.columns = defaultMegaColumns.map((column) => ({
        heading: column.heading,
        imageLinks: !!column.imageLinks,
        imageColumns: getImageColumnCount(column),
        links: column.links.map((link) => ({ ...link })),
      }))
    }
    if (!item.banner || typeof item.banner !== 'object') {
      item.banner = { title: '', image: '', url: '#' }
    }
    if (item.banner.title === undefined || item.banner.title === null) {
      item.banner.title = ''
    }
    if (item.banner.image === undefined || item.banner.image === null) {
      item.banner.image = ''
    }
    if (item.banner.url === undefined || item.banner.url === null || item.banner.url === '') {
      item.banner.url = '#'
    }

    item.columns.forEach((column, columnIndex) => {
      if (typeof column.imageLinks !== 'boolean') {
        column.imageLinks = columnIndex === 0
      }
      column.imageColumns = getImageColumnCount(column)
      if (!Array.isArray(column.links)) {
        column.links = []
      }
      column.links.forEach((link) => {
        if (link.image === undefined || link.image === null) {
          link.image = ''
        }
      })
    })
  })

  if (Array.isArray(props.settings.mobileMenuItems)) {
    props.settings.mobileMenuItems.forEach((item) => {
      if (!Array.isArray(item.columns)) {
        item.columns = []
      }
      if (item.hasMega && !item.columns.length) {
        item.columns = defaultMegaColumns.map((column) => ({
          heading: column.heading,
          imageLinks: !!column.imageLinks,
          imageColumns: getImageColumnCount(column),
          links: column.links.map((link) => ({ ...link })),
        }))
      }
      if (!item.banner || typeof item.banner !== 'object') {
        item.banner = { title: '', image: '', url: '#' }
      }
      if (item.banner.title === undefined || item.banner.title === null) {
        item.banner.title = ''
      }
      if (item.banner.image === undefined || item.banner.image === null) {
        item.banner.image = ''
      }
      if (item.banner.url === undefined || item.banner.url === null || item.banner.url === '') {
        item.banner.url = '#'
      }

      item.columns.forEach((column, columnIndex) => {
        if (typeof column.imageLinks !== 'boolean') {
          column.imageLinks = columnIndex === 0
        }
        column.imageColumns = getImageColumnCount(column)
        if (!Array.isArray(column.links)) {
          column.links = []
        }
        column.links.forEach((link) => {
          if (link.image === undefined || link.image === null) {
            link.image = ''
          }
        })
      })
    })
  }
})

watch(
  () => props.previewMode,
  (mode) => {
    if (mode === 'desktop') {
      mobileOpen.value = false
    }
  }
)

const numericCartCount = computed(() => {
  const value = parseInt(props.settings?.cartCount ?? 0, 10)
  return Number.isNaN(value) ? 0 : value
})

function clampInRange(rawValue, min, max, fallback) {
  const parsed = parseInt(rawValue ?? fallback, 10)
  if (Number.isNaN(parsed)) return fallback
  return Math.min(max, Math.max(min, parsed))
}

function clampPercent(rawValue) {
  return clampInRange(rawValue, 30, 100, 100)
}

const logoImageSizePercent = computed(() => clampPercent(props.settings?.logoImageSize))
const megaBrandImageSizePercent = computed(() => clampPercent(props.settings?.megaBrandImageSize))
const topBarSidePadding = computed(() => clampInRange(props.settings?.topBarSidePadding, 15, 60, 15))
const mobilePhonePosition = computed(() => props.settings?.mobilePhonePosition || 'bottom')

const mobilePhoneUrl = computed(() => props.settings?.mobilePhoneUrl || '#')

const logoImageMaxHeight = computed(() => {
  const topBarHeight = parseInt(props.settings?.topBarHeight ?? 72, 10)
  if (Number.isNaN(topBarHeight)) return 64
  return Math.max(28, topBarHeight - 8)
})

const activeItem = computed(() => {
  if (activeIndex.value === null) return null
  return menuItems.value[activeIndex.value] || null
})

const resolvedMenuBarHeight = computed(() => {
  const value = parseInt(props.settings?.menuBarHeight ?? 30, 10)
  if (Number.isNaN(value)) return 30
  // Keep older default values visually compact without overriding custom sizes.
  if (value === 52 || value === 44) return 30
  return value
})

watch(
  menuItems,
  (items) => {
    if (!items.length) {
      activeIndex.value = null
      return
    }

    if (activeIndex.value !== null && !items[activeIndex.value]?.hasMega) {
      activeIndex.value = null
    }
  },
  { immediate: true }
)

function setActive(index) {
  const item = menuItems.value[index]
  if (!item) return
  if (item.hasMega) {
    activeIndex.value = index
    return
  }
  if (!props.isEditor) {
    activeIndex.value = null
  }
}

function onMenuClick(event, index) {
  const item = menuItems.value[index]
  if (!item) return

  if (props.isEditor) {
    event.preventDefault()
    if (item.hasMega) {
      activeIndex.value = activeIndex.value === index ? null : index
    }
    return
  }

  if (item.hasMega) {
    event.preventDefault()
    activeIndex.value = activeIndex.value === index ? null : index
  }
}

function onHeaderLeave() {
  if (props.isEditor || panelHover.value) return
  activeIndex.value = null
}

function preventInEditor(event) {
  if (props.isEditor) {
    event.preventDefault()
  }
}

function isCardColumn(column, columnIndex) {
  if (column && typeof column.imageLinks === 'boolean') {
    return column.imageLinks
  }
  return columnIndex === 0
}

function getImageColumnCount(column) {
  return clampInRange(column?.imageColumns, 1, 4, 2)
}

function hasBannerContent(banner) {
  if (!banner || typeof banner !== 'object') return false
  if (banner.image) return true
  return !!String(banner.title || '').trim()
}

const defaultMobileStores = [
  { title: 'New Hampton', address: '5008 Route 17M\nNew Hampton, New York 10958', mapsLabel: 'Open in Google Maps', mapsUrl: '#', buttonLabel: 'Set as Default', buttonUrl: '#' },
  { title: 'Newburgh', address: '49 Route 17K\nNewburgh, New York 12550', mapsLabel: 'Open in Google Maps', mapsUrl: '#', buttonLabel: 'Set as Default', buttonUrl: '#' },
]

const mobileStores = computed(() => {
  const list = Array.isArray(props.settings?.mobileStores) ? props.settings.mobileStores : []
  if (list.length) {
    return list
  }
  return defaultMobileStores
})

const findPopupTitle = computed(() => {
  return props.settings?.mobileFindPopupTitle || 'Find your closest store'
})

function openMobileMenu() {
  mobileOpen.value = true
}

function closeMobileMenu() {
  mobileOpen.value = false
}

function toggleMobileItem(index) {
  const isOpen = mobileExpanded.value.has(index)
  if (isOpen) {
    mobileExpanded.value = new Set()
    return
  }

  const next = new Set([index])
  const columnNext = new Set()
  mobileExpandedColumns.value.forEach((key) => {
    if (key.startsWith(`${index}-`)) {
      columnNext.add(key)
    }
  })
  mobileExpanded.value = next
  mobileExpandedColumns.value = columnNext
}

function isMobileExpanded(index) {
  return mobileExpanded.value.has(index)
}

function hasMobileChildren(item) {
  return !!(item && item.hasMega && Array.isArray(item.columns) && item.columns.length)
}

function hasMobileColumnLinks(column) {
  return !!(column && Array.isArray(column.links) && column.links.length)
}

function toggleMobileColumn(itemIndex, columnIndex) {
  const key = `${itemIndex}-${columnIndex}`
  const next = new Set(mobileExpandedColumns.value)
  const isOpen = next.has(key)
  next.forEach((entry) => {
    if (entry.startsWith(`${itemIndex}-`)) {
      next.delete(entry)
    }
  })
  if (!isOpen) {
    next.add(key)
  }
  mobileExpandedColumns.value = next
}

function isMobileColumnExpanded(itemIndex, columnIndex) {
  return mobileExpandedColumns.value.has(`${itemIndex}-${columnIndex}`)
}

function openFindPopup(type) {
  findPopupType.value = type
  findPopupOpen.value = true
}

function closeFindPopup() {
  findPopupOpen.value = false
}
</script>

<style scoped>
.dsf-header-mega {
  width: 100%;
  font-family: var(--dsf-theme-body-font, 'Inter', sans-serif);
  border: 1px solid #e5e7eb;
  position: relative;
  z-index: 30;
  overflow: visible;
}

.dsf-header-mega__container {
  width: min(var(--dsf-theme-container-width, 1400px), 100%);
  margin: 0 auto;
  padding: 0 0.5rem;
}

.dsf-header-mega__top {
  display: flex;
  align-items: center;
}

.dsf-header-mega__top .dsf-header-mega__container {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 0.75rem;
}

.dsf-header-mega__top-inner {
  padding: 0 var(--top-side-padding, 15px);
}

.dsf-header-mega__utility {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.dsf-header-mega__mobile-toggle {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 0.35rem;
}

.dsf-header-mega__utility-link {
  text-decoration: none;
  font-size: 1rem;
  font-weight: 500;
  opacity: 0.88;
}

.dsf-header-mega__brand {
  justify-self: center;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: clamp(180px, 20vw, 330px);
}

.dsf-header-mega__brand-image {
  height: auto;
  display: block;
  object-fit: contain;
}

.dsf-header-mega__brand-text {
  font-family: var(--dsf-theme-heading-font, 'Inter', sans-serif);
  letter-spacing: 0.14em;
  font-size: clamp(1.2rem, 1.7vw, 2rem);
  font-weight: 700;
}

.dsf-header-mega__actions {
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.dsf-header-mega__mobile-actions {
  display: none;
  justify-self: end;
}

.dsf-header-mega__icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--icon-bg);
  color: var(--icon-color);
  cursor: pointer;
}

.dsf-header-mega__icon-btn--cart {
  width: auto;
  padding: 0 0.9rem;
  gap: 0.35rem;
  font-weight: 700;
}

.dsf-header-mega__menu {
  border: none;
  min-height: var(--menu-height);
  display: flex;
  align-items: stretch;
}

.dsf-header-mega__menu-row {
  display: flex;
  align-items: stretch;
  width: 100%;
  min-height: var(--menu-height);
}

.dsf-header-mega__menu-item {
  flex: 1;
  min-width: 130px;
  min-height: var(--menu-height);
  display: inline-flex;
  justify-content: center;
  align-items: center;
  gap: 0.4rem;
  text-decoration: none;
  color: var(--menu-text);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.01em;
  border-left: 1px solid var(--menu-divider);
  padding: 0 0.75rem;
}

.dsf-header-mega__menu-item:first-child {
  border-left: none;
}

.dsf-header-mega__panel-wrap {
  background: transparent;
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  z-index: 40;
}

.dsf-header-mega__panel {
  background: var(--panel-bg);
  border: 1px solid var(--panel-border);
  border-top: none;
  display: block;
  min-height: var(--panel-height);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.dsf-header-mega__panel-columns {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.6rem;
  padding: 1rem 1rem 1.25rem;
}

.dsf-header-mega__column :deep(h4) {
  margin: 0 0 0.75rem;
  color: var(--panel-heading);
  font-size: 1.08rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.01em;
}

.dsf-header-mega__column-links {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.dsf-header-mega__column--cards .dsf-header-mega__column-links {
  display: grid;
  grid-template-columns: repeat(var(--image-link-columns, 2), minmax(0, 1fr));
  gap: 0.7rem;
}

.dsf-header-mega__panel-link {
  display: block;
  text-decoration: none;
  color: var(--panel-link);
  font-size: 1.06rem;
  line-height: 1.35;
  padding: 0.05rem 0;
}

.dsf-header-mega__panel-link--card {
  min-height: 88px;
  border-radius: 10px;
  border: 1px solid #edf0f3;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.6rem;
  text-align: center;
}

.dsf-header-mega__panel-link--card :deep(span) {
  font-weight: 600;
}

.dsf-header-mega__panel-link-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  display: block;
}

.dsf-header-mega__banner {
  border-left: 1px solid var(--panel-border);
  display: block;
  text-decoration: none;
  min-height: 100%;
}

.dsf-header-mega__banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-header-mega__banner-placeholder {
  height: 100%;
  min-height: var(--panel-height);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  text-align: center;
  font-weight: 600;
  color: var(--panel-link);
  background: #f9fafb;
}

.dsf-header-mega__mobile-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s ease;
  z-index: 60;
}

.dsf-header-mega__mobile-overlay.is-open {
  opacity: 1;
  pointer-events: auto;
}

.dsf-header-mega__mobile-drawer {
  position: fixed;
  top: 0;
  left: 0;
  width: min(360px, 85vw);
  height: 100%;
  background: var(--mobile-bg);
  color: var(--mobile-text);
  transform: translateX(-100%);
  transition: transform 0.25s ease;
  z-index: 70;
  display: flex;
  flex-direction: column;
  box-shadow: 12px 0 30px rgba(15, 23, 42, 0.25);
}

.dsf-header-mega__mobile-drawer.is-open {
  transform: translateX(0);
}

.dsf-header-mega__mobile-top {
  background: var(--mobile-top-bg);
  color: var(--mobile-top-text);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.dsf-header-mega__mobile-top-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.dsf-header-mega__mobile-top-link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: none;
  background: transparent;
  color: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  text-decoration: none;
}

.dsf-header-mega__mobile-close {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.dsf-header-mega__mobile-nav {
  padding: 0.75rem 0;
  overflow-y: auto;
  flex: 1;
}

.dsf-header-mega__mobile-item {
  border-bottom: 1px solid var(--mobile-divider);
}

.dsf-header-mega__mobile-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1.25rem;
}

.dsf-header-mega__mobile-link {
  color: inherit;
  text-decoration: none;
  font-weight: 700;
  font-size: 1rem;
  flex: 1;
}

.dsf-header-mega__mobile-expand {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.dsf-header-mega__mobile-submenu {
  padding: 0 1.25rem 0.9rem;
}

.dsf-header-mega__mobile-group {
  padding: 0.5rem 0 0.75rem;
}

.dsf-header-mega__mobile-group-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.dsf-header-mega__mobile-group-title {
  font-weight: 700;
  font-size: 0.92rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin-bottom: 0.35rem;
  opacity: 0.85;
}

.dsf-header-mega__mobile-group-links {
  padding-top: 0.35rem;
}

.dsf-header-mega__mobile-sublink {
  display: block;
  color: inherit;
  text-decoration: none;
  font-size: 0.95rem;
  padding: 0.35rem 0;
}

.dsf-header-mega__mobile-phone {
  margin: 1rem;
  background: var(--mobile-button-bg);
  color: var(--mobile-button-text);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 999px;
  font-weight: 700;
  align-self: flex-start;
}

.dsf-header-mega__find-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 90;
}

.dsf-header-mega__find-modal {
  width: min(680px, 92vw);
  background: var(--find-modal-bg, #ffffff);
  color: var(--find-modal-text, #1f2a44);
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
}

.dsf-header-mega__find-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.dsf-header-mega__find-header h3 {
  font-size: 1.35rem;
  margin: 0;
}

.dsf-header-mega__find-close {
  border: none;
  background: transparent;
  cursor: pointer;
}

.dsf-header-mega__find-search {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.dsf-header-mega__find-search-btn {
  border: none;
  background: var(--find-modal-button-bg, #2c3d87);
  color: var(--find-modal-button-text, #ffffff);
  padding: 0 1.25rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

.dsf-header-mega__find-results {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.dsf-header-mega__find-card {
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 8px;
  display: grid;
  gap: 0.5rem;
}

.dsf-header-mega__find-card h4 {
  margin: 0;
  font-size: 1rem;
}

.dsf-header-mega__find-address {
  margin: 0;
  white-space: pre-line;
}

.dsf-header-mega__find-card a {
  color: var(--find-modal-link, #2c3d87);
  text-decoration: none;
  font-weight: 600;
}

.dsf-header-mega__find-map-link {
  color: var(--find-modal-maps-link, #2c3d87);
}

.dsf-header-mega__find-button {
  border: none;
  background: var(--find-modal-button-bg, #2c3d87);
  color: var(--find-modal-button-text, #ffffff);
  padding: 0.5rem 0.75rem;
  border-radius: 999px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  justify-self: start;
}

.dsf-mega-fade-enter-active,
.dsf-mega-fade-leave-active {
  transition: opacity 0.2s ease;
}

.dsf-mega-fade-enter-from,
.dsf-mega-fade-leave-to {
  opacity: 0;
}

@media (max-width: 1024px) {
  .dsf-header-mega__top .dsf-header-mega__container {
    grid-template-columns: 1fr auto;
    gap: 0.75rem;
  }

  .dsf-header-mega__utility {
    display: none;
  }

  .dsf-header-mega__panel {
    display: block;
  }

  .dsf-header-mega__banner {
    border-left: none;
    border-top: 1px solid var(--panel-border);
    min-height: 140px;
  }

  .dsf-header-mega__panel-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dsf-header-mega__column--cards .dsf-header-mega__column-links {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .dsf-header-mega__menu {
    display: none;
  }

  .dsf-header-mega__utility {
    display: none;
  }

  .dsf-header-mega__utility-link {
    display: none;
  }

  .dsf-header-mega__actions {
    display: none;
  }

  .dsf-header-mega__top .dsf-header-mega__container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  .dsf-header-mega__mobile-toggle {
    display: inline-flex;
  }

  .dsf-header-mega__mobile-actions {
    display: inline-flex;
  }

  .dsf-header-mega__brand {
    justify-self: start;
    margin-right: auto;
    width: auto;
  }
}

.dsf-header-mega.preview-mobile .dsf-header-mega__menu,
.dsf-header-mega.preview-tablet .dsf-header-mega__menu {
  display: none;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__utility,
.dsf-header-mega.preview-tablet .dsf-header-mega__utility {
  display: none;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__utility-link,
.dsf-header-mega.preview-tablet .dsf-header-mega__utility-link {
  display: none;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__actions,
.dsf-header-mega.preview-tablet .dsf-header-mega__actions {
  display: none;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__top .dsf-header-mega__container,
.dsf-header-mega.preview-tablet .dsf-header-mega__top .dsf-header-mega__container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__mobile-toggle,
.dsf-header-mega.preview-tablet .dsf-header-mega__mobile-toggle {
  display: inline-flex;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__mobile-actions,
.dsf-header-mega.preview-tablet .dsf-header-mega__mobile-actions {
  display: inline-flex;
}

.dsf-header-mega.preview-mobile .dsf-header-mega__brand,
.dsf-header-mega.preview-tablet .dsf-header-mega__brand {
  justify-self: start;
  margin-right: auto;
  width: auto;
}

@media (max-width: 768px) {
  .dsf-header-mega__menu-row {
    overflow-x: auto;
  }

  .dsf-header-mega__menu-item {
    flex: 0 0 auto;
    min-width: 180px;
  }

  .dsf-header-mega__brand-text {
    font-size: 1.05rem;
  }

  .dsf-header-mega__panel-columns {
    grid-template-columns: 1fr;
  }

  .dsf-header-mega__column--cards .dsf-header-mega__column-links {
    grid-template-columns: 1fr;
  }
}
</style>
