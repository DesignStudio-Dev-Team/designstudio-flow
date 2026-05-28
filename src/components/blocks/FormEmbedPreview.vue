<template>
  <div class="dsf-block-preview dsf-form-embed-preview" :style="blockStyle">
    <h3 v-if="blockTitle" class="dsf-form-embed-preview__title">{{ blockTitle }}</h3>

    <div v-if="isEditor" class="dsf-form-embed-preview__editor">
      <div class="dsf-form-embed-preview__badge">DesignStudio Flow Form</div>
      <div class="dsf-form-embed-preview__name">{{ selectedFormTitle }}</div>
      <p class="dsf-form-embed-preview__hint">
        Double-check your form fields in the Forms builder. This block renders the live form on the frontend.
      </p>
      <code class="dsf-form-embed-preview__code">{{ shortcodeLabel }}</code>
    </div>

    <div
      v-else
      ref="frontendRoot"
      class="dsf-form-embed-preview__frontend"
      data-dsf-form-embed-form
    >
      <div
        v-if="renderedHtml"
        class="dsf-form-embed-preview__rendered"
        v-html="renderedHtml"
      ></div>
      <div v-else class="dsf-form-embed-preview__empty">
        {{ emptyStateMessage }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUpdated, onBeforeUnmount, ref } from 'vue'

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

const editorData = typeof window !== 'undefined' ? window.dsfEditorData : null
const editorForms = Array.isArray(editorData?.forms) ? editorData.forms : []
const frontendRoot = ref(null)
let pendingScriptTimeoutId = null
let isUnmounted = false
let gravityPageLoadedHandler = null
let gravityNativePageChangeHandler = null

const blockStyle = computed(() => {
  const maxWidth = props.settings?.formMaxWidth ?? 600
  const alignment = props.settings?.formAlignment ?? 'center'

  let margin = '0 auto'
  if (alignment === 'left') {
    margin = '0 auto 0 0'
  } else if (alignment === 'right') {
    margin = '0 0 0 auto'
  }

  return {
    maxWidth: `${maxWidth}px`,
    width: '100%',
    margin: margin,
  }
})

const normalizedFormId = computed(() => {
  const raw = props.settings?.formId
  const parsed = Number.parseInt(raw, 10)
  if (!Number.isFinite(parsed) || parsed <= 0) return ''
  return String(parsed)
})

const selectedFormTitle = computed(() => {
  const explicitTitle = (props.settings?.formTitle || '').trim()
  if (explicitTitle) return explicitTitle

  const formId = normalizedFormId.value
  if (!formId) return 'No form selected'

  const matched = editorForms.find((form) => String(form?.id || '') === formId)
  if (matched?.title) return matched.title

  return `Form #${formId}`
})

const blockTitle = computed(() => {
  if (!props.settings?.showTitle) return ''

  const customTitle = (props.settings?.title || '').trim()
  if (customTitle) return customTitle

  return selectedFormTitle.value
})

const shortcodeLabel = computed(() => {
  if (!normalizedFormId.value) return '[dsform id=\'\']'
  return `[dsform id='${normalizedFormId.value}']`
})

const renderedHtml = computed(() => props.settings?.renderedFormHtml || '')
const emptyStateMessage = computed(() => (
  normalizedFormId.value
    ? 'Form preview is loading.'
    : 'Select a form in the block settings.'
))

function executeEmbeddedScripts(htmlString, attempt = 0) {
  if (typeof document === 'undefined' || !htmlString || isUnmounted) return

  const parser = new DOMParser()
  const doc = parser.parseFromString(htmlString, 'text/html')
  const scripts = Array.from(doc.querySelectorAll('script'))

  if (!scripts.length) return

  const needsGravityForms = scripts.some((script) =>
    /\bgform\b|gravity_form|gform_wrapper/.test(script.textContent || '')
  )

  if (
    needsGravityForms &&
    typeof window !== 'undefined' &&
    !window.gform &&
    attempt < 80
  ) {
    pendingScriptTimeoutId = window.setTimeout(() => {
      pendingScriptTimeoutId = null
      executeEmbeddedScripts(htmlString, attempt + 1)
    }, 50)
    return
  }

  scripts.forEach((scriptEl) => {
    const code = (scriptEl.textContent || '').trim()
    if (!code) return

    const script = document.createElement('script')
    script.type = 'text/javascript'
    script.text = code
    document.body.appendChild(script)
    script.remove()
  })

  triggerGravityPostRender()
}

function triggerGravityPostRender() {
  const root = frontendRoot.value
  if (!root || typeof window === 'undefined') return

  const wrappers = root.querySelectorAll('.gform_wrapper')
  if (!wrappers.length) return

  wrappers.forEach((wrapper) => {
    const match = (wrapper.id || '').match(/gform_wrapper_(\d+)/)
    const formId = match ? Number.parseInt(match[1], 10) : 0
    if (!formId) return

    const currentPage =
      Number.parseInt(
        wrapper.querySelector("input[name^='gform_source_page_number_']")?.value,
        10,
      ) || 1

    if (window.jQuery) {
      try {
        window.jQuery(document).trigger('gform_post_render', [formId, currentPage])
      } catch (e) {
        /* noop */
      }
    }

    if (window.gform && typeof window.gform.doAction === 'function') {
      try {
        window.gform.doAction('gform_post_render', formId, currentPage)
      } catch (e) {
        /* noop */
      }
    }
  })

  normalizeEmbeddedFormChrome()
}

function normalizeEmbeddedFormChrome() {
  const root = frontendRoot.value
  if (!root) return

  root.querySelectorAll('.akismet-fields-container').forEach((element) => {
    element.setAttribute('hidden', '')
    element.setAttribute('aria-hidden', 'true')
  })

  root.querySelectorAll('.gform_wrapper').forEach((wrapper) => {
    const requiredLegend = wrapper.querySelector('.gform_heading .gform_required_legend')
    const progressTitle = wrapper.querySelector('.gf_progressbar_title')
    if (!requiredLegend || !progressTitle || progressTitle.contains(requiredLegend)) return

    requiredLegend.classList.add('dsf-gform-required-legend--inline')
    progressTitle.appendChild(requiredLegend)
  })
}

function mountEmbeddedForms() {
  if (props.isEditor || !renderedHtml.value || !frontendRoot.value) return
  if (typeof window === 'undefined') return

  if (typeof window.dsfInitForms === 'function') {
    window.dsfInitForms(frontendRoot.value)
  }

  normalizeEmbeddedFormChrome()
  executeEmbeddedScripts(renderedHtml.value)
  normalizeEmbeddedFormChrome()
  bindGravityFormEvents()
}

function bindGravityFormEvents() {
  if (props.isEditor || gravityPageLoadedHandler || typeof window === 'undefined') return

  gravityPageLoadedHandler = () => {
    nextTick(normalizeEmbeddedFormChrome)
  }

  gravityNativePageChangeHandler = () => {
    nextTick(normalizeEmbeddedFormChrome)
  }

  if (window.jQuery) {
    window.jQuery(document).on('gform_page_loaded', gravityPageLoadedHandler)
  }

  document.addEventListener('gform/ajax/post_page_change', gravityNativePageChangeHandler)
}

onMounted(() => {
  nextTick(mountEmbeddedForms)
})

onUpdated(() => {
  nextTick(mountEmbeddedForms)
})

onBeforeUnmount(() => {
  isUnmounted = true
  if (pendingScriptTimeoutId !== null) {
    clearTimeout(pendingScriptTimeoutId)
    pendingScriptTimeoutId = null
  }
  if (typeof window !== 'undefined' && window.jQuery && gravityPageLoadedHandler) {
    window.jQuery(document).off('gform_page_loaded', gravityPageLoadedHandler)
  }
  if (typeof document !== 'undefined' && gravityNativePageChangeHandler) {
    document.removeEventListener('gform/ajax/post_page_change', gravityNativePageChangeHandler)
  }
})
</script>

<style scoped>
.dsf-form-embed-preview {
  padding: 16px;
  container-type: inline-size;
}

.dsf-form-embed-preview__title {
  margin: 0 0 0.875rem 0;
  font-size: var(--dsf-theme-h5, 1.125rem);
  font-weight: 700;
  color: var(--dsf-gray-900);
}

.dsf-form-embed-preview__editor {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-lg);
  padding: 1rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.dsf-form-embed-preview__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--dsf-primary-700);
  background: #e0ebff;
}

.dsf-form-embed-preview__name {
  margin-top: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  color: var(--dsf-gray-900);
}

.dsf-form-embed-preview__hint {
  margin: 0.5rem 0 0.75rem;
  color: var(--dsf-gray-600);
  font-size: 0.85rem;
  line-height: 1.45;
}

.dsf-form-embed-preview__code {
  display: inline-flex;
  border-radius: var(--dsf-radius-md);
  border: 1px solid var(--dsf-gray-300);
  padding: 0.35rem 0.5rem;
  color: var(--dsf-gray-700);
  background: #fff;
  font-size: 0.8rem;
}

.dsf-form-embed-preview__rendered {
  width: 100%;
}

/* ── Gravity Forms inheritance: use the site's body font, not GF's defaults. */
.dsf-form-embed-preview__frontend :deep(.gform_wrapper),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper *),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper.gravity-theme),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper.gravity-theme *) {
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-form-embed-preview__frontend :deep(.gform_wrapper),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper p),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper legend),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gform-field-label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield_label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield_description),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gchoice),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gchoice label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield_checkbox label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield_radio label),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .ginput_container input),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .ginput_container textarea),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .ginput_container select),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gform_button),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gform_next_button),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gform_previous_button),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gf_progressbar_title) {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  font-size: var(--dsf-theme-text-base, 16px) !important;
  line-height: 1.65 !important;
}

.dsf-form-embed-preview__frontend :deep(.akismet-fields-container) {
  display: none !important;
  visibility: hidden !important;
  height: 0 !important;
  overflow: hidden !important;
}

/* Tuck "* indicates required fields" to the right of the step indicator row
   so it doesn't claim its own line. */
.dsf-form-embed-preview__frontend :deep(.gform_heading) {
  position: relative;
}

.dsf-form-embed-preview__frontend :deep(.gf_progressbar_title) {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
}

.dsf-form-embed-preview__frontend :deep(.gform_heading .gform_required_legend),
.dsf-form-embed-preview__frontend :deep(.dsf-gform-required-legend--inline) {
  position: absolute;
  top: 0;
  right: 0;
  margin: 0;
  padding: 0;
  font-size: 0.6875rem;
  line-height: 1.4;
  color: var(--dsf-gray-600, #4B5563);
  text-align: right;
  max-width: 50%;
}

.dsf-form-embed-preview__frontend :deep(.dsf-gform-required-legend--inline) {
  position: static;
  margin-left: auto;
  flex: 0 1 auto;
  max-width: 48%;
}

/* Default inputs stretch full-width UNLESS GF set a size class. */
.dsf-form-embed-preview__frontend
  :deep(
    input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not(
        [type="button"]
      ):not([type="image"]):not(.small):not(.medium):not(.large)
  ),
.dsf-form-embed-preview__frontend :deep(select:not(.small):not(.medium):not(.large)),
.dsf-form-embed-preview__frontend :deep(textarea:not(.small):not(.medium):not(.large)) {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

/* Honor Gravity Forms field size classes. */
.dsf-form-embed-preview__frontend :deep(input.small),
.dsf-form-embed-preview__frontend :deep(select.small),
.dsf-form-embed-preview__frontend :deep(textarea.small) {
  width: 25%;
  max-width: 100%;
  box-sizing: border-box;
}

.dsf-form-embed-preview__frontend :deep(input.medium),
.dsf-form-embed-preview__frontend :deep(select.medium),
.dsf-form-embed-preview__frontend :deep(textarea.medium) {
  width: 50%;
  max-width: 100%;
  box-sizing: border-box;
}

.dsf-form-embed-preview__frontend :deep(input.large),
.dsf-form-embed-preview__frontend :deep(select.large),
.dsf-form-embed-preview__frontend :deep(textarea.large) {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

/* Gravity Forms 2.5+ CSS Grid system — render side-by-side columns. */
.dsf-form-embed-preview__frontend :deep(.gform_wrapper.gravity-theme .gform_fields),
.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gform_fields) {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  grid-column-gap: 16px;
  row-gap: 1rem;
}

.dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield) {
  grid-column: span var(--gf-grid-col-span, 12);
  min-width: 0;
}

.dsf-form-embed-preview__frontend :deep(.gfield--width-full) { --gf-grid-col-span: 12; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-eleven-twelfths) { --gf-grid-col-span: 11; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-five-sixths) { --gf-grid-col-span: 10; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-three-quarters) { --gf-grid-col-span: 9; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-two-thirds) { --gf-grid-col-span: 8; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-seven-twelfths) { --gf-grid-col-span: 7; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-half) { --gf-grid-col-span: 6; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-five-twelfths) { --gf-grid-col-span: 5; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-third) { --gf-grid-col-span: 4; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-quarter) { --gf-grid-col-span: 3; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-sixth) { --gf-grid-col-span: 2; }
.dsf-form-embed-preview__frontend :deep(.gfield--width-twelfth) { --gf-grid-col-span: 1; }

/* Side-by-side name/address sub-fields. */
.dsf-form-embed-preview__frontend :deep(.ginput_complex) {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
  width: 100%;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex > span),
.dsf-form-embed-preview__frontend :deep(.ginput_complex > div:not(.gf_clear)),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .gform-grid-col) {
  display: block !important;
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex .name_first),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_city),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_zip) {
  grid-column: 1 / span 1 !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex .name_last),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_state) {
  grid-column: 2 / span 1 !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex .ginput_full),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_line_1),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_line_2),
.dsf-form-embed-preview__frontend :deep(.ginput_complex .address_country) {
  grid-column: 1 / -1 !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex > span input) {
  width: 100% !important;
  max-width: 100% !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex input),
.dsf-form-embed-preview__frontend :deep(.ginput_complex select),
.dsf-form-embed-preview__frontend :deep(.ginput_complex textarea) {
  width: 100% !important;
  max-width: 100% !important;
}

.dsf-form-embed-preview__frontend :deep(.ginput_complex .gf_clear) {
  display: none !important;
}

/* Inline checkboxes/radios with their labels. */
.dsf-form-embed-preview__frontend :deep(.gchoice) {
  display: grid !important;
  grid-template-columns: 16px minmax(0, 1fr);
  align-items: start;
  column-gap: 0.625rem;
}

.dsf-form-embed-preview__frontend :deep(.gchoice > label) {
  margin: 0 !important;
  display: block;
  grid-column: 2;
  min-width: 0;
}

.dsf-form-embed-preview__frontend :deep(.gchoice > input[type="checkbox"]),
.dsf-form-embed-preview__frontend :deep(.gchoice > input[type="radio"]) {
  grid-column: 1;
  width: 16px !important;
  height: 16px !important;
  margin: 0.25em 0 0 !important;
  flex: 0 0 16px !important;
}

@container (max-width: 600px) {
  .dsf-form-embed-preview__frontend :deep(.gform_wrapper .gfield) {
    grid-column: span 12;
  }

  .dsf-form-embed-preview__frontend :deep(.ginput_complex) {
    grid-template-columns: 1fr;
  }

  .dsf-form-embed-preview__frontend :deep(.ginput_complex > span),
  .dsf-form-embed-preview__frontend :deep(.ginput_complex > div:not(.gf_clear)),
  .dsf-form-embed-preview__frontend :deep(.ginput_complex .gform-grid-col) {
    grid-column: 1 / -1 !important;
  }
}

.dsf-form-embed-preview__empty {
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-lg);
  color: var(--dsf-gray-600);
  font-size: 0.9rem;
  padding: 1rem;
  text-align: center;
}
</style>
