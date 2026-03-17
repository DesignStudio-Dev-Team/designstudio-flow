<template>
  <header class="dsf-header-cutout" @mouseleave="onHeaderLeave">
    <div
      class="dsf-header-cutout__top"
      :style="{
        backgroundColor: settings.topStripBackground || '#86bf25',
        color: settings.topStripTextColor || '#111111',
        minHeight: `${settings.topStripHeight || 30}px`,
      }"
    >
      <div class="dsf-header-cutout__top-inner">
        <a
          v-for="(link, index) in utilityLinks"
          :key="`utility-${index}`"
          :href="link.url || '#'"
          class="dsf-header-cutout__top-link"
          @click="preventInEditor"
        >
          <InlineText
            tagName="span"
            v-model="link.label"
            :is-editor="isEditor"
            placeholder="Top link"
            @click.stop
          />
        </a>
        <button
          v-if="settings.showSearch !== false"
          class="dsf-header-cutout__search-btn"
          @click="preventInEditor"
          aria-label="Search"
        >
          <Search :size="16" />
        </button>
      </div>
    </div>

    <div
      class="dsf-header-cutout__nav-wrap"
      :style="{ '--top-strip-height': `${settings.topStripHeight || 30}px` }"
    >
      <div
        class="dsf-header-cutout__container"
        :style="{
          '--logo-width': `${settings.logoWidth || 248}px`,
          '--logo-height': `${settings.logoHeight || 124}px`,
        }"
      >
        <a
          class="dsf-header-cutout__logo"
          :href="settings.homeUrl || '/'"
          @click="preventInEditor"
        >
          <img v-if="settings.logoImage" :src="settings.logoImage" alt="Site logo" />
          <span v-else class="dsf-header-cutout__logo-placeholder">Select Logo Image</span>
        </a>

        <div
          class="dsf-header-cutout__menu-shell"
          :style="{
            '--menu-height': `${resolvedMenuBarHeight}px`,
            '--menu-text': settings.menuTextColor || '#111111',
            '--menu-divider': settings.menuDividerColor || '#d9d9d9',
            '--menu-bg': settings.menuShellBackground || '#ebebeb',
          }"
        >
          <div class="dsf-header-cutout__menu-row">
            <a
              v-for="(item, index) in menuItems"
              :key="`menu-${index}`"
              :href="item.url || '#'"
              class="dsf-header-cutout__menu-item"
              :class="{ 'is-active': activeIndex === index }"
              :style="activeIndex === index ? {
                backgroundColor: settings.activeMenuBackground || '#f5f5f5',
                color: settings.activeMenuTextColor || '#4f8e2f',
              } : {}"
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

          <transition name="dsf-mega-fade">
            <div
              v-if="activeItem && activeItem.hasMega"
              class="dsf-header-cutout__panel-wrap"
              :style="{
                '--panel-bg': settings.megaBackground || '#f0f0f0',
                '--panel-heading': settings.megaHeadingColor || '#111111',
                '--panel-link': settings.megaLinkColor || '#4f8e2f',
                '--panel-border': settings.megaBorderColor || '#d8d8d8',
                '--panel-height': `${settings.megaMinHeight || 180}px`,
              }"
            >
              <div class="dsf-header-cutout__panel" :class="{ 'has-banner': hasBannerContent(activeItem.banner) }" @mouseenter="panelHover = true" @mouseleave="panelHover = false">
                <div class="dsf-header-cutout__panel-columns">
                  <div
                    v-for="(column, columnIndex) in activeItem.columns"
                    :key="`column-${columnIndex}`"
                    class="dsf-header-cutout__column"
                    :class="{ 'dsf-header-cutout__column--brands': isBrandColumn(column, columnIndex) }"
                  >
                    <InlineText
                      tagName="h4"
                      v-model="column.heading"
                      :is-editor="isEditor"
                      :placeholder="`Sub Heading ${columnIndex + 1}`"
                    />
                    <div
                      class="dsf-header-cutout__column-links"
                      :style="isBrandColumn(column, columnIndex) ? { '--image-link-columns': getImageColumnCount(column) } : null"
                    >
                      <a
                        v-for="(link, linkIndex) in column.links"
                        :key="`link-${columnIndex}-${linkIndex}`"
                        :href="link.url || '#'"
                        class="dsf-header-cutout__panel-link"
                        :class="{ 'dsf-header-cutout__panel-link--brand': isBrandColumn(column, columnIndex) }"
                        @click="preventInEditor"
                      >
                        <img
                          v-if="isBrandColumn(column, columnIndex) && link.image"
                          :src="link.image"
                          :alt="link.label || 'Brand'"
                          class="dsf-header-cutout__brand-image"
                        />
                        <InlineText
                          v-if="!(isBrandColumn(column, columnIndex) && link.image)"
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
                  class="dsf-header-cutout__banner"
                  :href="activeItem.banner.url || '#'"
                  @click="preventInEditor"
                >
                  <img v-if="activeItem.banner.image" :src="activeItem.banner.image" alt="Featured" />
                  <InlineText
                    v-else
                    tagName="div"
                    class="dsf-header-cutout__banner-placeholder"
                    v-model="activeItem.banner.title"
                    :is-editor="isEditor"
                    placeholder="Featured"
                    @click.stop
                  />
                </a>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, ref, watch, watchEffect } from 'vue'
import { ChevronDown, Search } from 'lucide-vue-next'
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
})

const activeIndex = ref(null)
const panelHover = ref(false)

const defaultUtilityLinks = [
  { label: 'CONTACT', url: '#' },
  { label: 'TELEFOON 0180 - 421399', url: '#' },
  { label: 'ADRES', url: '#' },
]

const defaultMegaColumns = [
  {
    heading: 'Merken',
    imageLinks: true,
    imageColumns: 2,
    links: [
      { label: 'Bekijk alle producten', url: '#', image: '' },
      { label: 'STIHL', url: '#', image: '' },
      { label: 'Honda', url: '#', image: '' },
      { label: 'Orec', url: '#', image: '' },
      { label: 'Ferrari', url: '#', image: '' },
      { label: 'Stiga', url: '#', image: '' },
    ],
  },
  {
    heading: 'Maaien',
    imageLinks: false,
    imageColumns: 2,
    links: [
      { label: 'Grasmaaiers', url: '#' },
      { label: 'Robotmaaiers', url: '#' },
      { label: 'Trimmers', url: '#' },
      { label: 'Mulchmaaiers', url: '#' },
      { label: 'Zitmaaiers', url: '#' },
    ],
  },
  {
    heading: 'Grond Bewerken',
    imageLinks: false,
    imageColumns: 2,
    links: [
      { label: 'Drukspuiten', url: '#' },
      { label: 'Grondboren', url: '#' },
      { label: 'Tuinfrezen', url: '#' },
      { label: 'Verticuteermachines', url: '#' },
      { label: 'Begraafplaatstechniek', url: '#' },
    ],
  },
]

const defaultMenuItems = [
  {
    label: 'GROND',
    url: '#',
    hasMega: true,
    columns: defaultMegaColumns,
    banner: { title: 'Featured', image: '', url: '#' },
  },
  { label: 'ZAGEN', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'OPRUIMEN', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'DRAGERS', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'STROOM', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'GEREEDSCHAP', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'ACCESSOIRES', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
  { label: 'SERVICE', url: '#', hasMega: false, columns: [], banner: { title: '', image: '', url: '#' } },
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
  return list.length ? list : defaultUtilityLinks
})

const menuItems = computed(() => {
  const list = Array.isArray(props.settings?.menuItems) ? props.settings.menuItems : []
  return list.length ? list : defaultMenuItems
})

watchEffect(() => {
  if (!props.isEditor || !props.settings) return

  if (!Array.isArray(props.settings.utilityLinks) || !props.settings.utilityLinks.length) {
    props.settings.utilityLinks = defaultUtilityLinks.map((link) => ({ ...link }))
  }

  if (!Array.isArray(props.settings.menuItems) || !props.settings.menuItems.length) {
    props.settings.menuItems = cloneMenuItems(defaultMenuItems)
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
})

const activeItem = computed(() => {
  if (activeIndex.value === null) return null
  return menuItems.value[activeIndex.value] || null
})

const resolvedMenuBarHeight = computed(() => {
  const value = parseInt(props.settings?.menuBarHeight ?? 52, 10)
  if (Number.isNaN(value)) return 52
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

function hasBannerContent(banner) {
  if (!banner || typeof banner !== 'object') return false
  if (banner.image) return true
  return !!String(banner.title || '').trim()
}

function isBrandColumn(column, columnIndex) {
  if (column && typeof column.imageLinks === 'boolean') {
    return column.imageLinks
  }
  return columnIndex === 0
}

function clampInRange(rawValue, min, max, fallback) {
  const parsed = parseInt(rawValue ?? fallback, 10)
  if (Number.isNaN(parsed)) return fallback
  return Math.min(max, Math.max(min, parsed))
}

function getImageColumnCount(column) {
  return clampInRange(column?.imageColumns, 1, 4, 2)
}
</script>

<style scoped>
.dsf-header-cutout {
  width: 100%;
  font-family: var(--dsf-theme-body-font, 'Inter', sans-serif);
  position: relative;
  z-index: 30;
  overflow: visible;
}

.dsf-header-cutout__top {
  width: 100%;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  position: relative;
}

.dsf-header-cutout__top::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: clamp(180px, 15vw, 312px);
  background: #ffffff;
  clip-path: polygon(0 0, 100% 0, calc(100% - 26px) 100%, 0 100%);
}

.dsf-header-cutout__top-inner {
  width: min(var(--dsf-theme-container-width, 1500px), 100%);
  margin: 0 auto;
  padding: 0 0.75rem;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.35rem;
  min-height: inherit;
  position: relative;
  z-index: 1;
}

.dsf-header-cutout__top-link {
  text-decoration: none;
  color: inherit;
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1;
  padding: 0 0.5rem;
  border-right: 1px solid rgba(0, 0, 0, 0.25);
  text-transform: uppercase;
  letter-spacing: 0.01em;
}

.dsf-header-cutout__top-link:last-of-type {
  border-right: none;
}

.dsf-header-cutout__search-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: inherit;
}

.dsf-header-cutout__nav-wrap {
  background: #f6f6f6;
}

.dsf-header-cutout__container {
  width: min(var(--dsf-theme-container-width, 1500px), 100%);
  margin: 0 auto;
  padding: calc((var(--logo-height) - var(--top-strip-height)) * 0.9) 0.75rem 0.65rem;
  position: relative;
  --logo-width: 248px;
  --logo-height: 124px;
}

.dsf-header-cutout__logo {
  position: absolute;
  left: 0.75rem;
  top: calc((var(--top-strip-height) * -1) + 2px);
  width: var(--logo-width);
  height: var(--logo-height);
  z-index: 6;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.dsf-header-cutout__logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.dsf-header-cutout__logo-placeholder {
  color: #6b7280;
  font-size: 0.9rem;
  font-weight: 600;
}

.dsf-header-cutout__menu-shell {
  width: 100%;
  background: var(--menu-bg);
  border: 1px solid var(--menu-divider);
  position: relative;
  min-height: var(--menu-height);
}

.dsf-header-cutout__menu-row {
  display: flex;
  align-items: stretch;
  min-height: var(--menu-height);
}

.dsf-header-cutout__menu-item {
  flex: 1;
  min-width: 120px;
  min-height: var(--menu-height);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  text-decoration: none;
  color: var(--menu-text);
  font-size: 1.05rem;
  font-weight: 700;
  text-transform: uppercase;
  border-left: 1px solid var(--menu-divider);
  padding: 0 0.65rem;
}

.dsf-header-cutout__menu-item:first-child {
  border-left: none;
}

.dsf-header-cutout__panel-wrap {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 20;
}

.dsf-header-cutout__panel {
  background: var(--panel-bg);
  border: 1px solid var(--panel-border);
  border-top: none;
  min-height: var(--panel-height);
  display: block;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}

.dsf-header-cutout__panel.has-banner {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(290px, 40%);
}

.dsf-header-cutout__panel-columns {
  padding: 1rem;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.dsf-header-cutout__column :deep(h4) {
  margin: 0 0 0.7rem;
  color: var(--panel-heading);
  font-size: 1.05rem;
  font-weight: 700;
}

.dsf-header-cutout__column-links {
  display: flex;
  flex-direction: column;
  gap: 0.34rem;
}

.dsf-header-cutout__column--brands .dsf-header-cutout__column-links {
  display: grid;
  grid-template-columns: repeat(var(--image-link-columns, 2), minmax(0, 1fr));
  gap: 0.45rem;
}

.dsf-header-cutout__panel-link {
  display: block;
  text-decoration: none;
  color: var(--panel-link);
  font-size: 0.95rem;
  line-height: 1.25;
  padding: 0.14rem 0;
}

.dsf-header-cutout__panel-link--brand {
  min-height: 56px;
  border: 1px solid #dde5d2;
  background: #f1f6e8;
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem;
  text-align: center;
}

.dsf-header-cutout__brand-image {
  max-width: 100%;
  max-height: 44px;
  object-fit: contain;
}

.dsf-header-cutout__banner {
  border-left: 1px solid var(--panel-border);
  display: block;
  text-decoration: none;
  min-height: 100%;
}

.dsf-header-cutout__banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-header-cutout__banner-placeholder {
  min-height: var(--panel-height);
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-weight: 700;
  background: #eef1e7;
}

.dsf-mega-fade-enter-active,
.dsf-mega-fade-leave-active {
  transition: opacity 0.2s ease;
}

.dsf-mega-fade-enter-from,
.dsf-mega-fade-leave-to {
  opacity: 0;
}

@media (max-width: 1100px) {
  .dsf-header-cutout__top::before {
    display: none;
  }

  .dsf-header-cutout__logo {
    position: static;
    width: min(320px, 100%);
    height: 96px;
    margin: 0.5rem auto;
  }

  .dsf-header-cutout__menu-shell {
    width: 100%;
  }

  .dsf-header-cutout__container {
    padding-top: 0;
  }

  .dsf-header-cutout__panel-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dsf-header-cutout__panel.has-banner {
    grid-template-columns: 1fr;
  }

  .dsf-header-cutout__banner {
    border-left: none;
    border-top: 1px solid var(--panel-border);
    min-height: 180px;
  }
}

@media (max-width: 768px) {
  .dsf-header-cutout__top-inner {
    justify-content: center;
    flex-wrap: wrap;
    row-gap: 0.3rem;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
  }

  .dsf-header-cutout__menu-row {
    overflow-x: auto;
  }

  .dsf-header-cutout__menu-item {
    flex: 0 0 auto;
    min-width: 150px;
    font-size: 0.9rem;
  }

  .dsf-header-cutout__panel-columns {
    grid-template-columns: 1fr;
  }

  .dsf-header-cutout__column--brands .dsf-header-cutout__column-links {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
