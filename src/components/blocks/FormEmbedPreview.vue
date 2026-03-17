<template>
  <div class="dsf-block-preview dsf-form-embed-preview">
    <h3 v-if="blockTitle" class="dsf-form-embed-preview__title">{{ blockTitle }}</h3>

    <div v-if="isEditor" class="dsf-form-embed-preview__editor">
      <div class="dsf-form-embed-preview__badge">DesignStudio Flow Form</div>
      <div class="dsf-form-embed-preview__name">{{ selectedFormTitle }}</div>
      <p class="dsf-form-embed-preview__hint">
        Double-check your form fields in the Forms builder. This block renders the live form on the frontend.
      </p>
      <code class="dsf-form-embed-preview__code">{{ shortcodeLabel }}</code>
    </div>

    <div v-else ref="frontendRoot" class="dsf-form-embed-preview__frontend">
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
import { computed, nextTick, onMounted, onUpdated, ref } from 'vue'

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

function mountEmbeddedForms() {
  if (props.isEditor || !renderedHtml.value || !frontendRoot.value) return
  if (typeof window === 'undefined') return
  if (typeof window.dsfInitForms !== 'function') return

  window.dsfInitForms(frontendRoot.value)
}

onMounted(() => {
  nextTick(mountEmbeddedForms)
})

onUpdated(() => {
  nextTick(mountEmbeddedForms)
})
</script>

<style scoped>
.dsf-form-embed-preview {
  padding: 16px;
}

.dsf-form-embed-preview__title {
  margin: 0 0 0.875rem 0;
  font-size: 1.125rem;
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

.dsf-form-embed-preview__empty {
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-lg);
  color: var(--dsf-gray-600);
  font-size: 0.9rem;
  padding: 1rem;
  text-align: center;
}
</style>
