<template>
  <div v-if="translation.active" class="dsf-dock__group dsf-lang-menu">
    <button
      ref="toggle"
      type="button"
      class="dsf-dock__btn"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="true"
      :aria-label="`Language: ${currentLabel}`"
      data-dsf-help="dock-language"
      @click.stop="open = !open"
      @keydown.esc.prevent="close"
    >
      <Languages :size="19" />
      <span class="dsf-lang-menu__code">{{ currentCode }}</span>
      <span class="dsf-dock__tip">Language — {{ currentLabel }}</span>
    </button>

    <div v-if="open" class="dsf-lang-menu__panel" role="menu" @keydown.esc.prevent="close">
      <p class="dsf-lang-menu__heading">Languages</p>
      <p v-if="!translation.ready" class="dsf-lang-menu__paused" role="status">Translating is paused</p>

      <div
        v-for="language in translation.languages"
        :key="language.code"
        class="dsf-lang-menu__row"
        :class="{ 'is-current': language.isCurrent }"
      >
        <span class="dsf-lang-menu__label">
          {{ language.label }}
          <span v-if="language.isMain" class="dsf-lang-menu__badge">main</span>
        </span>

        <span class="dsf-lang-menu__state" :class="`is-${language.state}`">{{ stateLabel(language) }}</span>

        <a
          v-if="language.postId && !language.isCurrent"
          class="dsf-lang-menu__action"
          :href="language.editUrl"
          role="menuitem"
        >Edit</a>
        <button
          v-else-if="!language.postId && canClone"
          type="button"
          class="dsf-lang-menu__action"
          role="menuitem"
          :disabled="busy === language.code"
          @click="createDraft(language)"
        >{{ busy === language.code ? 'Creating…' : 'Create draft' }}</button>
        <span v-else class="dsf-lang-menu__action is-inert" aria-hidden="true">—</span>
      </div>

      <p v-if="translation.notice" class="dsf-lang-menu__note">{{ translation.notice }}</p>
      <p v-if="error" class="dsf-lang-menu__error" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Languages } from 'lucide-vue-next'

const open = ref(false)
const busy = ref('')
const error = ref('')
const toggle = ref(null)

const translation = computed(() => {
  const payload = (typeof window !== 'undefined' && window.dsfEditorData?.translation) || {}
  return {
    active: !!payload.active,
    current: payload.current || '',
    isMain: !!payload.isMain,
    canClone: !!payload.canClone,
    ready: !!payload.ready,
    notice: payload.notice || '',
    nonce: payload.nonce || '',
    languages: Array.isArray(payload.languages) ? payload.languages : [],
  }
})

const canClone = computed(() => translation.value.canClone)

const currentEntry = computed(
  () => translation.value.languages.find((language) => language.isCurrent) || null,
)

const currentLabel = computed(() => currentEntry.value?.label || translation.value.current || '')

const currentCode = computed(() =>
  String(currentEntry.value?.code || translation.value.current || '').split('-')[0].toUpperCase(),
)

const stateLabel = (language) => {
  if (language.isCurrent) return 'Editing'
  if (language.state === 'published') return 'Published'
  if (language.state === 'draft') return 'Draft'
  return 'Missing'
}

const close = () => {
  open.value = false
  if (toggle.value) {
    toggle.value.focus()
  }
}

/**
 * Creating a draft is a server decision: this only asks. Every rule — main
 * language only, no duplicate, permissions — is enforced there, and a refusal
 * is shown as-is rather than second-guessed here.
 */
const createDraft = async (language) => {
  const data = window.dsfEditorData || {}
  if (!data.ajaxUrl || !translation.value.nonce) {
    error.value = 'Editor is not configured for translation actions.'
    return
  }

  busy.value = language.code
  error.value = ''

  try {
    const body = new FormData()
    body.append('action', 'dsf_clone_translation')
    body.append('nonce', translation.value.nonce)
    body.append('source_id', String(data.postId || 0))
    body.append('language', language.code)

    const response = await fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
    const payload = await response.json()

    if (payload && payload.success && payload.data && payload.data.edit_link) {
      window.location.href = payload.data.edit_link
      return
    }
    error.value = (payload && payload.data && payload.data.message) || 'That draft could not be created.'
  } catch (e) {
    error.value = 'That draft could not be created.'
  } finally {
    busy.value = ''
  }
}

const onDocumentPointer = (event) => {
  if (!open.value || !toggle.value) {
    return
  }
  const root = toggle.value.closest('.dsf-lang-menu')
  if (root && !root.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentPointer)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentPointer)
})
</script>

<style scoped>
.dsf-lang-menu {
  position: relative;
}

.dsf-lang-menu__code {
  position: absolute;
  right: 4px;
  bottom: 3px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.02em;
  opacity: 0.75;
}

.dsf-lang-menu__panel {
  position: absolute;
  z-index: 90;
  bottom: calc(100% + 10px);
  inset-inline-start: 0;
  width: 320px;
  max-height: 60vh;
  overflow-y: auto;
  padding: 12px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
  color: #0f172a;
  text-align: start;
}

.dsf-lang-menu__heading {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.6;
}

.dsf-lang-menu__row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 8px;
  padding: 7px 0;
  border-top: 1px solid rgba(15, 23, 42, 0.07);
  font-size: 13px;
}

.dsf-lang-menu__row:first-of-type {
  border-top: 0;
}

.dsf-lang-menu__row.is-current {
  font-weight: 600;
}

.dsf-lang-menu__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-lang-menu__badge {
  margin-inline-start: 6px;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(15, 23, 42, 0.08);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.dsf-lang-menu__state {
  font-size: 11px;
  opacity: 0.7;
  white-space: nowrap;
}

.dsf-lang-menu__state.is-published {
  color: #15803d;
  opacity: 1;
}

.dsf-lang-menu__state.is-missing {
  color: #b45309;
  opacity: 1;
}

.dsf-lang-menu__action {
  padding: 4px 9px;
  border: 1px solid rgba(15, 23, 42, 0.18);
  border-radius: 6px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 12px;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
}

.dsf-lang-menu__action:disabled,
.dsf-lang-menu__action.is-inert {
  border-color: transparent;
  opacity: 0.5;
  cursor: default;
}

.dsf-lang-menu__paused {
  margin: 0 0 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: rgba(180, 83, 9, 0.1);
  color: #b45309;
  font-size: 12px;
  font-weight: 600;
}

.dsf-lang-menu__note,
.dsf-lang-menu__error {
  margin: 10px 0 0;
  font-size: 12px;
}

.dsf-lang-menu__error {
  color: #b91c1c;
}
</style>
