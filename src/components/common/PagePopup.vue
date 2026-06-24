<template>
  <Teleport to="body">
    <Transition name="dsf-page-popup">
      <div
        v-if="isOpen"
        class="dsf-page-popup"
        :class="[
          `dsf-page-popup--${resolved.position}`,
          { 'dsf-page-popup--no-overlay': !resolved.showOverlay },
        ]"
        role="presentation"
        @mousedown.self="handleOverlayClick"
      >
        <section
          ref="dialog"
          class="dsf-page-popup__dialog"
          :class="[
            `dsf-page-popup__dialog--${resolved.width}`,
            `dsf-page-popup__dialog--${resolved.type}`,
            `dsf-page-popup__dialog--image-${resolved.imagePosition}`,
          ]"
          :style="dialogStyle"
          role="dialog"
          aria-modal="true"
          :aria-label="resolved.headline || 'Page announcement'"
          tabindex="-1"
        >
          <button
            v-if="resolved.showClose"
            type="button"
            class="dsf-page-popup__close"
            :style="{ color: resolved.textColor }"
            aria-label="Close popup"
            @click="close"
          >
            <X :size="22" />
          </button>

          <template v-if="resolved.type === 'image'">
            <a
              v-if="hasLink"
              class="dsf-page-popup__image-link"
              :href="resolved.buttonUrl"
              :target="resolved.openNewTab ? '_blank' : null"
              :rel="resolved.openNewTab ? 'noopener noreferrer' : null"
              :aria-label="resolved.buttonText || resolved.imageAlt || 'View offer'"
            >
              <img class="dsf-page-popup__full-image" :src="resolved.image" :alt="resolved.imageAlt" />
            </a>
            <img v-else class="dsf-page-popup__full-image" :src="resolved.image" :alt="resolved.imageAlt" />
          </template>

          <template v-else>
            <div v-if="resolved.image" class="dsf-page-popup__media">
              <img :src="resolved.image" :alt="resolved.imageAlt" />
            </div>
            <div class="dsf-page-popup__content">
              <h2 v-if="resolved.headline" class="dsf-page-popup__headline">{{ resolved.headline }}</h2>
              <div v-if="resolved.body" class="dsf-page-popup__body" v-html="resolved.body"></div>
              <a
                v-if="resolved.buttonText && hasLink"
                class="dsf-page-popup__button"
                :style="{ backgroundColor: resolved.accentColor }"
                :href="resolved.buttonUrl"
                :target="resolved.openNewTab ? '_blank' : null"
                :rel="resolved.openNewTab ? 'noopener noreferrer' : null"
              >
                {{ resolved.buttonText }}
              </a>
            </div>
          </template>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { X } from 'lucide-vue-next'

const DEFAULTS = {
  enabled: false,
  type: 'content',
  headline: 'Limited time offer',
  body: '<p>Add your popup message here.</p>',
  image: '',
  imageAlt: '',
  imagePosition: 'top',
  buttonText: 'Learn more',
  buttonUrl: '#',
  openNewTab: false,
  width: 'medium',
  position: 'center',
  delaySeconds: 3,
  startDate: '',
  endDate: '',
  cookieDuration: 24,
  cookieUnit: 'hours',
  showOverlay: true,
  closeOnOverlay: true,
  showClose: true,
  backgroundColor: '#FFFFFF',
  textColor: '#1F2937',
  accentColor: '#2C5F5D',
}

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  postId: { type: [Number, String], default: 0 },
})

const resolved = computed(() => ({ ...DEFAULTS, ...(props.settings || {}) }))
const isOpen = ref(false)
const dialog = ref(null)
let openTimer = null
let previousBodyOverflow = ''

const cookieName = computed(() => `dsf_popup_dismissed_${String(props.postId || 'page').replace(/[^a-zA-Z0-9_-]/g, '')}`)
const hasLink = computed(() => Boolean(resolved.value.buttonUrl && resolved.value.buttonUrl !== '#'))
const dialogStyle = computed(() => ({
  backgroundColor: resolved.value.backgroundColor,
  color: resolved.value.textColor,
}))

onMounted(() => {
  if (!isEligible() || hasDismissalCookie()) return
  const delay = Math.min(3600, Math.max(0, Number(resolved.value.delaySeconds) || 0)) * 1000
  openTimer = window.setTimeout(open, delay)
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  if (openTimer) window.clearTimeout(openTimer)
  window.removeEventListener('keydown', handleKeydown)
  isOpen.value = false
  restoreBodyScroll()
})

function isEligible() {
  if (!resolved.value.enabled) return false
  if (resolved.value.type === 'image' && !resolved.value.image) return false

  const now = Date.now()
  const starts = parseDate(resolved.value.startDate)
  const ends = parseDate(resolved.value.endDate)
  if (starts !== null && now < starts) return false
  if (ends !== null && now > ends) return false
  return true
}

function parseDate(value) {
  if (!value) return null
  const parsed = Date.parse(value)
  return Number.isFinite(parsed) ? parsed : null
}

function hasDismissalCookie() {
  const prefix = `${encodeURIComponent(cookieName.value)}=`
  return document.cookie.split(';').some((part) => part.trim().startsWith(prefix))
}

function open() {
  if (!isEligible() || hasDismissalCookie()) return
  isOpen.value = true
  if (resolved.value.showOverlay) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  }
  nextTick(() => dialog.value?.focus())
}

function close() {
  setDismissalCookie()
  isOpen.value = false
  restoreBodyScroll()
}

function setDismissalCookie() {
  const duration = Math.max(0, Number(resolved.value.cookieDuration) || 0)
  const unitSeconds = resolved.value.cookieUnit === 'days' ? 86400 : 3600
  const maxAge = duration > 0 ? `; Max-Age=${Math.round(duration * unitSeconds)}` : ''
  const secure = window.location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${encodeURIComponent(cookieName.value)}=1${maxAge}; Path=/; SameSite=Lax${secure}`
}

function restoreBodyScroll() {
  if (isOpen.value) return
  document.body.style.overflow = previousBodyOverflow
}

function handleOverlayClick() {
  if (resolved.value.showOverlay && resolved.value.closeOnOverlay) close()
}

function handleKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) close()
}
</script>

<style scoped>
.dsf-page-popup {
  position: fixed;
  inset: 0;
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  box-sizing: border-box;
  background: rgba(15, 23, 42, 0.68);
  backdrop-filter: blur(4px);
}

.dsf-page-popup--no-overlay { pointer-events: none; background: transparent; backdrop-filter: none; }
.dsf-page-popup--bottom-right { align-items: flex-end; justify-content: flex-end; }
.dsf-page-popup--bottom-left { align-items: flex-end; justify-content: flex-start; }

.dsf-page-popup__dialog {
  position: relative;
  display: grid;
  width: 100%;
  max-height: calc(100vh - 48px);
  overflow: auto;
  border-radius: 20px;
  box-shadow: 0 30px 90px rgba(15, 23, 42, 0.3);
  pointer-events: auto;
  box-sizing: border-box;
}

.dsf-page-popup__dialog--small { max-width: 420px; }
.dsf-page-popup__dialog--medium { max-width: 620px; }
.dsf-page-popup__dialog--large { max-width: 860px; }
.dsf-page-popup__dialog--wide { max-width: 1100px; }
.dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-left,
.dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-right { grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); }
.dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-right .dsf-page-popup__media { order: 2; }
.dsf-page-popup__dialog--image { overflow: hidden; }

.dsf-page-popup__close {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.88);
  box-shadow: 0 4px 18px rgba(15, 23, 42, 0.16);
  cursor: pointer;
}

.dsf-page-popup__media { min-height: 220px; overflow: hidden; }
.dsf-page-popup__media img { display: block; width: 100%; height: 100%; min-height: 220px; max-height: 380px; object-fit: cover; }
.dsf-page-popup__image-link { display: block; line-height: 0; }
.dsf-page-popup__full-image { display: block; width: 100%; height: auto; max-height: calc(100vh - 48px); object-fit: contain; }

.dsf-page-popup__content { padding: clamp(30px, 5vw, 56px); }
.dsf-page-popup__headline { margin: 0 0 16px; font-family: var(--dsf-theme-heading-font, inherit); font-size: var(--dsf-theme-h2-size, clamp(2rem, 4vw, 3.25rem)); line-height: 1.08; letter-spacing: -0.03em; }
.dsf-page-popup__body { font-family: var(--dsf-theme-body-font, inherit); font-size: var(--dsf-theme-p-size, 16px); line-height: 1.65; }
.dsf-page-popup__body :deep(:first-child) { margin-top: 0; }
.dsf-page-popup__body :deep(:last-child) { margin-bottom: 0; }
.dsf-page-popup__button { display: inline-flex; align-items: center; justify-content: center; margin-top: 26px; padding: 13px 22px; border-radius: 6px; color: #fff; font-family: var(--dsf-theme-body-font, inherit); font-size: var(--dsf-theme-p-size, 16px); font-weight: 700; line-height: 1.2; text-decoration: none; }

.dsf-page-popup-enter-active,
.dsf-page-popup-leave-active { transition: opacity 0.24s ease; }
.dsf-page-popup-enter-active .dsf-page-popup__dialog,
.dsf-page-popup-leave-active .dsf-page-popup__dialog { transition: transform 0.3s ease, opacity 0.3s ease; }
.dsf-page-popup-enter-from,
.dsf-page-popup-leave-to { opacity: 0; }
.dsf-page-popup-enter-from .dsf-page-popup__dialog,
.dsf-page-popup-leave-to .dsf-page-popup__dialog { opacity: 0; transform: translateY(18px) scale(0.97); }

@media (max-width: 700px) {
  .dsf-page-popup { padding: 14px; align-items: flex-end; }
  .dsf-page-popup__dialog { max-height: calc(100vh - 28px); border-radius: 16px; }
  .dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-left,
  .dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-right { grid-template-columns: 1fr; }
  .dsf-page-popup__dialog--content.dsf-page-popup__dialog--image-right .dsf-page-popup__media { order: 0; }
  .dsf-page-popup__media,
  .dsf-page-popup__media img { min-height: 170px; max-height: 240px; }
  .dsf-page-popup__content { padding: 30px 24px; }
}
</style>
