<template>
  <div
    ref="dockRoot"
    class="dsf-dock"
    :class="{ 'dsf-dock--collapsed': collapsed }"
  >
    <!-- Brand mark doubles as Back-to-WordPress. It stays pinned to the left at
         rest; while the canvas scrolls the action row retracts to nothing and the
         centre-anchored dock re-centres the mark to the middle, then slides it
         back left when scrolling stops. -->
    <component
      :is="adminUrl ? 'a' : 'button'"
      :href="adminUrl || undefined"
      :type="adminUrl ? undefined : 'button'"
      class="dsf-dock__logo"
      aria-label="DesignStudio Flow — Back to WordPress admin"
    >
      <img :src="logoUrl" alt="" />
      <span class="dsf-dock__tip">DesignStudio Flow — Back to WP Admin</span>
    </component>

    <!-- Everything that retracts into the brand mark on scroll. -->
    <div ref="dockBody" class="dsf-dock__body">
      <span class="dsf-dock__divider" aria-hidden="true"></span>

      <div class="dsf-dock__group">
        <button
          v-if="!libraryMode"
          type="button"
          class="dsf-dock__btn"
          :disabled="isLayout"
          :aria-label="isLayout ? 'Settings unavailable' : 'Page settings'"
          @click="emit('open-settings')"
        >
          <Settings :size="19" />
          <span class="dsf-dock__tip">{{ isLayout ? 'No settings' : 'Page settings' }}</span>
        </button>

        <button
          v-if="!libraryMode"
          type="button"
          class="dsf-dock__btn"
          aria-label="Theme"
          @click="emit('open-theme')"
        >
          <Palette :size="19" />
          <span class="dsf-dock__tip">Theme</span>
        </button>

        <button
          v-if="!isLayout && !libraryMode"
          type="button"
          class="dsf-dock__btn"
          aria-label="Save as template"
          @click="emit('save-as-template')"
        >
          <LayoutTemplate :size="19" />
          <span class="dsf-dock__tip">Save as template</span>
        </button>

        <button
          v-if="!libraryMode"
          type="button"
          class="dsf-dock__btn"
          :disabled="isLayout"
          :aria-label="isLayout ? 'View unavailable' : 'View page'"
          @click="emit('view')"
        >
          <ExternalLink :size="19" />
          <span class="dsf-dock__tip">{{ isLayout ? 'No view' : 'View page' }}</span>
        </button>

        <button
          type="button"
          class="dsf-dock__btn dsf-dock__btn--primary"
          :class="{ 'is-busy': isSaving }"
          :disabled="isSaving"
          :aria-label="saveLabel"
          @click="emit('save')"
        >
          <Save :size="19" />
          <span class="dsf-dock__tip">{{ isSaving ? 'Saving…' : saveLabel }}</span>
        </button>

        <button
          type="button"
          class="dsf-dock__btn"
          aria-label="History"
          @click="emit('open-history')"
        >
          <History :size="19" />
          <span class="dsf-dock__tip">History</span>
        </button>
      </div>

      <span class="dsf-dock__divider" aria-hidden="true"></span>

      <div class="dsf-dock__group">
        <button
          type="button"
          class="dsf-dock__btn"
          :class="{ 'dsf-dock__btn--active': previewMode === 'desktop' }"
          aria-label="Desktop"
          @click="emit('set-preview-mode', 'desktop')"
        >
          <Monitor :size="19" />
          <span class="dsf-dock__tip">Desktop</span>
        </button>
        <button
          type="button"
          class="dsf-dock__btn"
          :class="{ 'dsf-dock__btn--active': previewMode === 'tablet' }"
          aria-label="Tablet"
          @click="emit('set-preview-mode', 'tablet')"
        >
          <Tablet :size="19" />
          <span class="dsf-dock__tip">Tablet</span>
        </button>
        <button
          type="button"
          class="dsf-dock__btn"
          :class="{ 'dsf-dock__btn--active': previewMode === 'mobile' }"
          aria-label="Mobile"
          @click="emit('set-preview-mode', 'mobile')"
        >
          <Smartphone :size="19" />
          <span class="dsf-dock__tip">Mobile</span>
        </button>
      </div>

      <template v-if="!libraryMode">
        <span class="dsf-dock__divider" aria-hidden="true"></span>

        <div class="dsf-dock__group">
          <button
            type="button"
            class="dsf-dock__btn"
            aria-label="Structure"
            @click="emit('open-structure')"
          >
            <ListTree :size="19" />
            <span class="dsf-dock__tip">Structure</span>
          </button>

          <button
            type="button"
            class="dsf-dock__btn dsf-dock__btn--accent"
            :disabled="!canAddBlock"
            aria-label="Add block"
            @click="emit('add-block')"
          >
            <Plus :size="20" />
            <span class="dsf-dock__tip">Add block</span>
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { gsap } from 'gsap'
import {
  Monitor, Tablet, Smartphone, Palette, Settings,
  ExternalLink, Save, LayoutTemplate, Plus, ListTree, History,
} from 'lucide-vue-next'

const props = defineProps({
  isSaving: Boolean,
  previewMode: { type: String, default: 'desktop' },
  postType: { type: String, default: 'page' },
  layoutType: { type: String, default: 'header' },
  canAddBlock: { type: Boolean, default: true },
  // Saved-block editor: hide page-only controls, keep just save + responsive.
  libraryMode: { type: Boolean, default: false },
})

const emit = defineEmits([
  'view', 'save', 'set-preview-mode', 'open-theme',
  'open-settings', 'save-as-template', 'add-block', 'open-structure', 'open-history',
])

const logoUrl = computed(() => `${window.dsfEditorData?.pluginUrl || ''}assets/images/dsflow-logo.png`)
const adminUrl = computed(() => window.dsfEditorData?.adminUrl || '')
const isLayout = computed(() => props.postType === 'dsf_layout')

const saveLabel = computed(() => {
  if (props.libraryMode) return 'Save block'
  if (props.postType === 'dsf_layout') {
    return props.layoutType === 'footer' ? 'Save footer template' : 'Save header template'
  }
  return 'Save page'
})

// ---- Collapse-on-scroll ----------------------------------------------------
// While the canvas scrolls, the action row retracts into the brand mark; once
// scrolling stops the row springs back out. Because the dock is centre-anchored
// (translateX(-50%)) the shrinking width re-centres the left-pinned mark to the
// middle as it collapses, and slides it back left as it expands — the mnrk.test
// motion.
const dockRoot = ref(null)
const dockBody = ref(null)
const collapsed = ref(false)

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
const IDLE_MS = 280

let scrollEl = null
let idleTimer = null
let naturalWidth = 0

function collapseDock() {
  if (collapsed.value || reducedMotion) return
  const body = dockBody.value
  if (!body) return
  collapsed.value = true
  // Capture the resting width so we can spring back to exactly the same size.
  // Measure before clipping — overflow is visible at rest so tooltips can escape.
  naturalWidth = body.getBoundingClientRect().width
  body.style.overflow = 'hidden'
  gsap.killTweensOf(body)
  gsap.to(body, {
    width: 0,
    opacity: 0,
    marginLeft: 0,
    duration: 0.42,
    ease: 'power3.inOut',
  })
}

function expandDock() {
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
      // Hand sizing back to the layout so the dock stays correct on resize,
      // and restore overflow so hover tooltips can escape the bar again.
      body.style.width = ''
      body.style.marginLeft = ''
      body.style.overflow = ''
    },
  })
}

function onScroll() {
  collapseDock()
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = setTimeout(expandDock, IDLE_MS)
}

onMounted(() => {
  scrollEl = document.querySelector('.dsf-canvas')
  if (scrollEl) scrollEl.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  if (scrollEl) scrollEl.removeEventListener('scroll', onScroll)
  if (idleTimer) clearTimeout(idleTimer)
  if (dockBody.value) gsap.killTweensOf(dockBody.value)
})
</script>
