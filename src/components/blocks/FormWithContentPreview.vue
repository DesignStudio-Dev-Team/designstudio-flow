<template>
  <div class="dsf-block-preview dsf-form-with-content" :style="blockStyle">

    <!-- Section header: title + optional divider -->
    <div v-if="settings.sectionTitle || settings.showDivider" class="dsf-form-with-content__header">
      <h2
        v-if="settings.sectionTitle"
        class="dsf-form-with-content__section-title"
        :style="{ color: settings.titleColor || '#1F2937' }"
      >
        {{ settings.sectionTitle }}
      </h2>
      <hr
        v-if="settings.showDivider"
        class="dsf-form-with-content__divider"
        :style="{ borderColor: settings.dividerColor || '#E5E7EB' }"
      />
    </div>

    <!-- Two-column grid -->
    <div
      class="dsf-form-with-content__grid"
      :class="formSide === 'left' ? 'dsf-form-with-content__grid--form-left' : 'dsf-form-with-content__grid--form-right'"
    >
      <!-- Content column -->
      <div class="dsf-form-with-content__col dsf-form-with-content__col--content" :style="contentColStyle">
        <!-- Rich text -->
        <div
          class="dsf-form-with-content__content"
          :style="{ color: settings.textColor || '#1F2937' }"
          v-html="settings.content || defaultContent"
        />


     

        <!-- Media wrapper: logo + image or video -->
        <div v-if="showImage || showVideoFile || showVideoEmbed" class="dsf-form-with-content__media-wrap">
          <img
            v-if="settings.logo"
            :src="settings.logo"
            class="dsf-form-with-content__logo"
            alt="Logo"
          />

          <!-- Image -->
          <img
            v-if="showImage"
            :src="settings.image"
            class="dsf-form-with-content__image"
            alt=""
          />

          <!-- Hosted video file (mp4 / webm) -->
          <div v-else-if="showVideoFile" class="dsf-form-with-content__video-wrap">
            <video
              class="dsf-form-with-content__video dsf-form-with-content__video--file"
              autoplay
              muted
              loop
            >
              <source :src="settings.videoFile" :type="videoFileType" />
            </video>
          </div>

          <!-- Embed iframe (YouTube / Vimeo) -->
          <div v-else-if="showVideoEmbed" class="dsf-form-with-content__video-wrap">
            <iframe
              :src="videoEmbedUrl"
              class="dsf-form-with-content__video"
              frameborder="0"
              allow="autoplay; fullscreen; picture-in-picture"
              allowfullscreen
            />
          </div>
        </div>

        <!-- Editor placeholder when embed URL isn't recognised -->
        <div v-else-if="settings.video && isEditor && !isImageMode.value" class="dsf-form-with-content__video-placeholder">
          <span>Video: {{ settings.video }}</span>
        </div>
      </div>

      <!-- Form column -->
      <div class="dsf-form-with-content__col dsf-form-with-content__col--form" :style="formColStyle">
        <template v-if="isDsfFormSource">
          <!-- Editor: show badge placeholder -->
          <div v-if="isEditor" class="dsf-form-with-content__form-placeholder">
            <div class="dsf-form-with-content__badge">DesignStudio Flow Form</div>
            <div class="dsf-form-with-content__form-name">{{ selectedFormTitle }}</div>
            <p class="dsf-form-with-content__hint">
              The live form will render here on the frontend.
            </p>
            <code class="dsf-form-with-content__code">{{ shortcodeLabel }}</code>
          </div>

          <!-- Frontend: render the form HTML -->
          <div v-else ref="frontendRoot" class="dsf-form-with-content__form-frontend">
            <div v-if="renderedHtml" v-html="renderedHtml" />
            <div v-else class="dsf-form-with-content__empty">
              {{ normalizedFormId ? 'Form preview is loading.' : 'Select a form in the block settings.' }}
            </div>
          </div>
        </template>

        <div v-else ref="frontendRoot" class="dsf-form-with-content__form-frontend">
          <div v-if="customFormHtml" v-html="customFormHtml" />
          <div v-else class="dsf-form-with-content__empty">
            Add a shortcode or embed code in the block settings.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUpdated, nextTick } from 'vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  previewMode: { type: String, default: 'desktop' },
})

const frontendRoot = ref(null)

const defaultContent = "<p><b>Your dream backyard starts here!</b></p><p>Fill out the form and we'll be in touch as soon as possible.</p>"

const formSide = computed(() => props.settings?.formSide || 'right')

const formSource = computed(() => props.settings?.formSource || 'dsf')

const isDsfFormSource = computed(() => formSource.value !== 'embed')

const blockStyle = computed(() => ({
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  padding: `${props.settings?.padding ?? 60}px ${props.settings?.paddingX ?? 24}px`,
}))

const contentColStyle = computed(() => {
  const bg = props.settings?.contentBg
  return bg ? { backgroundColor: bg } : {}
})

const formColStyle = computed(() => {
  const bg = props.settings?.formBg
  return bg ? { backgroundColor: bg } : {}
})

// Whether the block is in image mode (default 'video' for backwards compat)
const isImageMode = computed(() => props.settings?.mediaType === 'image')

const showImage = computed(() => isImageMode.value && !!props.settings?.image)

const showVideoFile = computed(() => !isImageMode.value && !!props.settings?.videoFile)

const showVideoEmbed = computed(() => !isImageMode.value && !!videoEmbedUrl.value)

// Determine MIME type from videoFile extension
const videoFileType = computed(() => {
  const url = (props.settings?.videoFile || '').toLowerCase()
  if (url.endsWith('.webm')) return 'video/webm'
  if (url.endsWith('.ogg') || url.endsWith('.ogv')) return 'video/ogg'
  return 'video/mp4'
})

// Convert a user-supplied YouTube or Vimeo URL into an embed URL
const videoEmbedUrl = computed(() => {
  const url = (props.settings?.video || '').trim()
  if (!url) return ''

  // Already an embed URL
  if (url.includes('/embed/') || url.includes('player.vimeo.com')) return url

  // YouTube: youtu.be/ID or youtube.com/watch?v=ID or youtube.com/shorts/ID
  const ytShort = url.match(/youtu\.be\/([^?&]+)/)
  if (ytShort) return `https://www.youtube.com/embed/${ytShort[1]}`
  const ytWatch = url.match(/[?&]v=([^&]+)/)
  if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[1]}`
  const ytShorts = url.match(/shorts\/([^?&]+)/)
  if (ytShorts) return `https://www.youtube.com/embed/${ytShorts[1]}`

  // Vimeo: vimeo.com/ID
  const vimeo = url.match(/vimeo\.com\/(\d+)/)
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`

  return ''
})

const editorForms = typeof window !== 'undefined' ? (window.dsfEditorData?.forms || []) : []

const normalizedFormId = computed(() => {
  const raw = props.settings?.formId
  const parsed = Number.parseInt(raw, 10)
  return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : ''
})

const selectedFormTitle = computed(() => {
  const explicit = (props.settings?.formTitle || '').trim()
  if (explicit) return explicit
  if (!normalizedFormId.value) return 'No form selected'
  const match = editorForms.find(f => String(f?.id || '') === normalizedFormId.value)
  return match?.title || `Form #${normalizedFormId.value}`
})

const shortcodeLabel = computed(() =>
  normalizedFormId.value ? `[dsform id='${normalizedFormId.value}']` : "[dsform id='']"
)

const renderedHtml = computed(() => props.settings?.renderedFormHtml || '')

const customFormHtml = computed(() => {
  if (isDsfFormSource.value) return ''
  return props.settings?.renderedEmbedHtml || props.settings?.embedCode || ''
})

function mountEmbeddedForms() {
  if (props.isEditor || (!renderedHtml.value && !customFormHtml.value) || !frontendRoot.value) return
  if (typeof window?.dsfInitForms === 'function') {
    window.dsfInitForms(frontendRoot.value)
  }
}

onMounted(() => nextTick(mountEmbeddedForms))
onUpdated(() => nextTick(mountEmbeddedForms))
</script>

<style scoped>
.dsf-form-with-content {
  container-type: inline-size;
}

/* ── Section header ─────────────────────────────────── */
.dsf-form-with-content__header {
  text-align: center;
  margin-bottom: 2rem;
}

.dsf-form-with-content__section-title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 1rem;
}

.dsf-form-with-content__divider {
  border: none;
  border-top: 2px solid #E5E7EB;
  margin: 0;
  width: 100%;
}

/* ── Two-column grid ────────────────────────────────── */
.dsf-form-with-content__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  max-width: 1200px;
  margin: 0 auto;
  align-items: start;
}

/* Form right (default): content | form */
.dsf-form-with-content__grid--form-right .dsf-form-with-content__col--content { order: 1; }
.dsf-form-with-content__grid--form-right .dsf-form-with-content__col--form    { order: 2; }

/* Form left: form | content */
.dsf-form-with-content__grid--form-left .dsf-form-with-content__col--form    { order: 1; }
.dsf-form-with-content__grid--form-left .dsf-form-with-content__col--content { order: 2; }

.dsf-form-with-content__col {
  border-radius: var(--dsf-radius-lg);
  padding: 1rem;
}

/* ── Rich text ──────────────────────────────────────── */
.dsf-form-with-content__content :deep(h1),
.dsf-form-with-content__content :deep(h2),
.dsf-form-with-content__content :deep(h3),
.dsf-form-with-content__content :deep(h4) {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 0.75rem;
}

.dsf-form-with-content__content :deep(h2) { font-size: 2rem; }
.dsf-form-with-content__content :deep(h3) { font-size: 1.5rem; }

.dsf-form-with-content__content :deep(p) {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: 1rem;
  line-height: 1.65;
  margin-bottom: 1rem;
}

.dsf-form-with-content__content :deep(ul),
.dsf-form-with-content__content :deep(ol) {
  padding-left: 1.5rem;
  margin-bottom: 1rem;
  line-height: 1.65;
}

.dsf-form-with-content__content :deep(a) {
  color: var(--dsf-primary, #2c5f5d);
  text-decoration: underline;
}

/* ── Shared media wrapper (image or video) ──────────── */
.dsf-form-with-content__media-wrap {
  position: relative;
  isolation: isolate;
  width: 100%;
  margin-top: 4rem;
}

/* ── Logo (absolute, centred above media) ───────────── */
.dsf-form-with-content__logo {
  position: absolute;
  z-index: 3;
  left: 50%;
  transform: translateX(-50%);
  top: -35px;
  width: 50%;
  height: 120px;
  padding: 0.75rem 1rem;
  background: #fff;
  border-radius: var(--dsf-radius-md);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  box-sizing: border-box;
  object-fit: contain;
  pointer-events: none;
  transition: opacity 1s ease-out;
}

/* ── Image ──────────────────────────────────────────── */
.dsf-form-with-content__image {
  position: relative;
  z-index: 1;
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
  border-radius: var(--dsf-radius-lg);
}

/* ── Video inner wrap ───────────────────────────────── */
.dsf-form-with-content__video-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
}

/* iframe embeds need the 16:9 padding trick */
.dsf-form-with-content__video-wrap:has(iframe) {
  padding-top: 56.25%;
}

.dsf-form-with-content__video-wrap:has(iframe) iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

/* Native video: just full width, natural aspect ratio */
.dsf-form-with-content__video--file {
  width: 100%;
  height: auto;
  display: block;
  border-radius: var(--dsf-radius-lg);
}

.dsf-form-with-content__video-placeholder {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: var(--dsf-gray-50);
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  font-size: 0.8125rem;
  color: var(--dsf-gray-500);
}

/* ── Form placeholder (editor) ──────────────────────── */
.dsf-form-with-content__form-placeholder {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-lg);
  padding: 1.5rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  height: 100%;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-form-with-content__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--dsf-primary-700, #1a3f3d);
  background: #e0ebff;
  width: fit-content;
}

.dsf-form-with-content__form-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--dsf-gray-900);
}

.dsf-form-with-content__hint {
  color: var(--dsf-gray-500);
  font-size: 0.8125rem;
  line-height: 1.45;
  flex: 1;
}

.dsf-form-with-content__code {
  display: inline-flex;
  border-radius: var(--dsf-radius-md);
  border: 1px solid var(--dsf-gray-300);
  padding: 0.3rem 0.5rem;
  color: var(--dsf-gray-700);
  background: #fff;
  font-size: 0.75rem;
}

.dsf-form-with-content__empty {
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-lg);
  color: var(--dsf-gray-500);
  font-size: 0.875rem;
  padding: 1.5rem;
  text-align: center;
}

.dsf-form-with-content__form-frontend :deep(iframe) {
  max-width: 100%;
}

/* ── Responsive: stack below 680px ─────────────────── */
@container (max-width: 680px) {
  .dsf-form-with-content__grid {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .dsf-form-with-content__grid--form-left .dsf-form-with-content__col--form,
  .dsf-form-with-content__grid--form-right .dsf-form-with-content__col--content {
    order: 1;
  }

  .dsf-form-with-content__grid--form-left .dsf-form-with-content__col--content,
  .dsf-form-with-content__grid--form-right .dsf-form-with-content__col--form {
    order: 2;
  }
}
</style>
