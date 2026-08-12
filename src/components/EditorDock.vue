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

    <button
      v-if="mobileDock"
      type="button"
      class="dsf-dock__btn dsf-dock__more"
      aria-label="Open editor actions"
      :aria-expanded="mobileMenuOpen"
      aria-haspopup="menu"
      @click="toggleMobileMenu()"
    >
      <MoreHorizontal :size="21" />
      <span class="dsf-dock__tip">Editor actions</span>
    </button>

    <!-- Everything that retracts into the brand mark on scroll. -->
    <Transition name="dsf-mobile-actions">
      <div
        v-show="!mobileDock || mobileMenuOpen"
        ref="dockBody"
        class="dsf-dock__body"
        :class="{ 'dsf-dock__body--mobile-menu': mobileDock }"
        role="menu"
        aria-label="Editor actions"
      >
      <span class="dsf-dock__divider" aria-hidden="true"></span>

      <div class="dsf-dock__group">
        <button
          v-if="!libraryMode"
          type="button"
          class="dsf-dock__btn"
          aria-label="Settings"
          data-dsf-help="dock-settings"
          @click="emit('open-settings')"
        >
          <Settings :size="19" />
          <span class="dsf-dock__tip">Settings</span>
        </button>

        <button
          v-if="!libraryMode"
          type="button"
          class="dsf-dock__btn"
          aria-label="Structure"
          data-dsf-help="dock-structure"
          @click="emit('open-structure')"
        >
          <ListTree :size="19" />
          <span class="dsf-dock__tip">Structure</span>
        </button>

        <!-- Save opens its options the same way the preview picker does. With
             nothing else to offer it stays a plain one-click button. -->
        <div ref="savePicker" class="dsf-dock__save-picker">
          <button
            type="button"
            class="dsf-dock__btn dsf-dock__btn--primary"
            :class="{ 'is-busy': isSaving, 'dsf-dock__btn--active': saveMenuOpen }"
            :disabled="isSaving"
            data-dsf-help="dock-save"
            :aria-label="hasSaveOptions ? 'Save options' : saveLabel"
            :aria-haspopup="hasSaveOptions ? 'menu' : undefined"
            :aria-expanded="hasSaveOptions ? saveMenuOpen : undefined"
            @click="onSaveClick"
          >
            <Save :size="19" />
            <span class="dsf-dock__tip">{{ isSaving ? 'Saving…' : saveLabel }}</span>
          </button>

          <Transition name="dsf-preview-menu">
            <div v-if="saveMenuOpen && hasSaveOptions" class="dsf-dock__save-menu" role="menu" aria-label="Save options">
              <button
                type="button"
                class="dsf-dock__preview-option"
                role="menuitem"
                @click="selectSaveOption('save')"
              >
                <Save :size="17" />
                <span>{{ saveLabel }}</span>
                <small>Publish changes</small>
              </button>
              <button
                type="button"
                class="dsf-dock__preview-option"
                role="menuitem"
                @click="selectSaveOption('save-as-template')"
              >
                <LayoutTemplate :size="17" />
                <span>Save as template</span>
                <small>Reuse this design</small>
              </button>
            </div>
          </Transition>
        </div>

      </div>

      <span class="dsf-dock__divider" aria-hidden="true"></span>

      <div ref="previewPicker" class="dsf-dock__group dsf-dock__preview-picker">
        <button
          type="button"
          class="dsf-dock__btn"
          :class="{ 'dsf-dock__btn--active': previewMenuOpen }"
          data-dsf-help="dock-preview"
          aria-label="Choose preview size"
          aria-haspopup="menu"
          :aria-expanded="previewMenuOpen"
          @click="previewMenuOpen = !previewMenuOpen"
        >
          <component :is="previewIcon" :size="19" />
          <span class="dsf-dock__tip">Preview: {{ previewLabel }}</span>
        </button>

        <Transition name="dsf-preview-menu">
          <div v-if="previewMenuOpen" class="dsf-dock__preview-menu" role="menu" aria-label="Preview size">
            <button
              v-for="option in previewOptions"
              :key="option.value"
              type="button"
              class="dsf-dock__preview-option"
              :class="{ 'is-active': previewMode === option.value }"
              role="menuitemradio"
              :aria-checked="previewMode === option.value"
              @click="selectPreviewMode(option.value)"
            >
              <component :is="option.icon" :size="17" />
              <span>{{ option.label }}</span>
              <small>{{ option.width }}</small>
            </button>
          </div>
        </Transition>
      </div>

      <span class="dsf-dock__divider" aria-hidden="true"></span>

      <!-- Add block sits at the centre of the dock: the action taken most often
           while building, and the easiest target to reach from either side.
           Four controls and two dividers sit on each side of it. -->
      <div class="dsf-dock__group dsf-dock__group--centre">
        <button
          type="button"
          class="dsf-dock__btn dsf-dock__btn--accent"
          :disabled="!canAddBlock"
          aria-label="Add block"
          data-dsf-help="dock-add-block"
          @click="emit('add-block')"
        >
          <Plus :size="20" />
          <span class="dsf-dock__tip">Add block</span>
        </button>
      </div>

      <template v-if="!libraryMode">
        <span class="dsf-dock__divider" aria-hidden="true"></span>

        <div class="dsf-dock__group">
          <button
            type="button"
            class="dsf-dock__btn"
            aria-label="Help"
            data-dsf-help="dock-help"
            @click="emit('open-help')"
          >
            <HelpCircle :size="19" />
            <span class="dsf-dock__tip">Help</span>
          </button>

          <EditorLanguageMenu />

          <button
            type="button"
            class="dsf-dock__btn"
            aria-label="History"
            data-dsf-help="dock-history"
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
            :disabled="isLayout"
            :aria-label="isLayout ? 'View unavailable' : 'View page'"
            @click="emit('view')"
          >
            <ExternalLink :size="19" />
            <span class="dsf-dock__tip">{{ isLayout ? 'No view' : 'View page' }}</span>
          </button>
        </div>
      </template>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { gsap } from 'gsap'
import {
  Monitor, Tablet, Smartphone, Settings,
  ExternalLink, Save, LayoutTemplate, Plus, ListTree, History, HelpCircle, MoreHorizontal,
} from 'lucide-vue-next'
import EditorLanguageMenu from './EditorLanguageMenu.vue'

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
  'view', 'save', 'set-preview-mode',
  'open-settings', 'save-as-template', 'add-block', 'open-structure', 'open-history', 'open-help', 'mobile-actions-open',
])

const logoUrl = computed(() => `${window.dsfEditorData?.pluginUrl || ''}assets/images/dsflow-logo.png`)
const adminUrl = computed(() => window.dsfEditorData?.adminUrl || '')
const isLayout = computed(() => props.postType === 'dsf_layout')
const previewMenuOpen = ref(false)
const previewPicker = ref(null)
const savePicker = ref(null)
const saveMenuOpen = ref(false)
// Layouts and the saved-block editor have nothing to save as a template, so the
// caret disappears and Save stays a plain button.
const hasSaveOptions = computed(() => !props.libraryMode && props.postType !== 'dsf_layout')
const previewOptions = [
  { value: 'desktop', label: 'Desktop', width: '1800 px', icon: Monitor },
  { value: 'tablet', label: 'Tablet', width: '768 px', icon: Tablet },
  { value: 'mobile', label: 'Mobile', width: '375 px', icon: Smartphone },
]
const selectedPreview = computed(() => previewOptions.find((option) => option.value === props.previewMode) || previewOptions[0])
const previewIcon = computed(() => selectedPreview.value.icon)
const previewLabel = computed(() => selectedPreview.value.label)

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
const mobileDock = ref(false)
const mobileMenuOpen = ref(false)

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
const IDLE_MS = 280

let scrollEl = null
let idleTimer = null
let naturalWidth = 0
let mobileMediaQuery = null

function syncMobileDock(event) {
  mobileDock.value = Boolean(event?.matches ?? mobileMediaQuery?.matches)
  if (!mobileDock.value) setMobileMenu(false)
}

function setMobileMenu(open) {
  mobileMenuOpen.value = open
  emit('mobile-actions-open', open)
}

function toggleMobileMenu() {
  setMobileMenu(!mobileMenuOpen.value)
}

function collapseDock() {
  if (mobileDock.value || collapsed.value || reducedMotion) return
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
  if (mobileDock.value) return
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
  if (mobileDock.value) return
  collapseDock()
  if (idleTimer) clearTimeout(idleTimer)
  idleTimer = setTimeout(expandDock, IDLE_MS)
}

function selectPreviewMode(mode) {
  emit('set-preview-mode', mode)
  previewMenuOpen.value = false
}

function onSaveClick() {
  if (!hasSaveOptions.value) {
    emit('save')
    return
  }
  saveMenuOpen.value = !saveMenuOpen.value
}

function selectSaveOption(action) {
  emit(action)
  saveMenuOpen.value = false
}

function onDocumentPointerDown(event) {
  if (previewPicker.value && !previewPicker.value.contains(event.target)) {
    previewMenuOpen.value = false
  }
  if (savePicker.value && !savePicker.value.contains(event.target)) {
    saveMenuOpen.value = false
  }
  if (dockRoot.value && !dockRoot.value.contains(event.target)) {
    setMobileMenu(false)
  }
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape') {
    previewMenuOpen.value = false
    saveMenuOpen.value = false
    setMobileMenu(false)
  }
}

onMounted(() => {
  scrollEl = document.querySelector('.dsf-canvas')
  if (scrollEl) scrollEl.addEventListener('scroll', onScroll, { passive: true })
  document.addEventListener('pointerdown', onDocumentPointerDown)
  document.addEventListener('keydown', onDocumentKeydown)
  mobileMediaQuery = window.matchMedia?.('(max-width: 760px)')
  syncMobileDock(mobileMediaQuery)
  mobileMediaQuery?.addEventListener?.('change', syncMobileDock)
})

onBeforeUnmount(() => {
  if (scrollEl) scrollEl.removeEventListener('scroll', onScroll)
  if (idleTimer) clearTimeout(idleTimer)
  if (dockBody.value) gsap.killTweensOf(dockBody.value)
  document.removeEventListener('pointerdown', onDocumentPointerDown)
  document.removeEventListener('keydown', onDocumentKeydown)
  mobileMediaQuery?.removeEventListener?.('change', syncMobileDock)
})
</script>
