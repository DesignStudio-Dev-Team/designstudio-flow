<template>
  <div class="dsf-wysiwyg">
    <div class="dsf-wysiwyg__toolbar" aria-label="Rich text editor tools">
      <button
        type="button"
        class="dsf-wysiwyg__btn dsf-wysiwyg__btn--heading"
        title="Heading 1"
        @click="formatBlock('H1')"
      >
        H1
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn dsf-wysiwyg__btn--heading"
        title="Heading 2"
        @click="formatBlock('H2')"
      >
        H2
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn dsf-wysiwyg__btn--heading"
        title="Heading 3"
        @click="formatBlock('H3')"
      >
        H3
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn dsf-wysiwyg__btn--heading"
        title="Heading 4"
        @click="formatBlock('H4')"
      >
        H4
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn dsf-wysiwyg__btn--paragraph"
        title="Paragraph"
        @click="formatBlock('P')"
      >
        P
      </button>
      <span class="dsf-wysiwyg__sep" aria-hidden="true"></span>
      <button type="button" class="dsf-wysiwyg__btn" title="Bold" @click="exec('bold')">
        <Bold :size="16" />
      </button>
      <button type="button" class="dsf-wysiwyg__btn" title="Italic" @click="exec('italic')">
        <Italic :size="16" />
      </button>
      <button type="button" class="dsf-wysiwyg__btn" title="Link" @click="addLink">
        <Link :size="16" />
      </button>
      <button type="button" class="dsf-wysiwyg__btn" title="Unlink" @click="exec('unlink')">
        <Unlink :size="16" />
      </button>
      <span class="dsf-wysiwyg__sep" aria-hidden="true"></span>
      <button
        type="button"
        class="dsf-wysiwyg__btn"
        title="Bullet List"
        @click="exec('insertUnorderedList')"
      >
        <List :size="16" />
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn"
        title="Numbered List"
        @click="exec('insertOrderedList')"
      >
        <ListOrdered :size="16" />
      </button>
      <button
        type="button"
        class="dsf-wysiwyg__btn"
        title="Horizontal Rule"
        @click="exec('insertHorizontalRule')"
      >
        <Minus :size="16" />
      </button>
      <span class="dsf-wysiwyg__sep" aria-hidden="true"></span>
      <button
        type="button"
        class="dsf-wysiwyg__btn"
        :class="{ 'dsf-wysiwyg__btn--active': sourceMode }"
        title="Edit HTML"
        @click="toggleSourceMode"
      >
        <Code2 :size="16" />
      </button>

      <div v-if="gravityForms.length" class="dsf-wysiwyg__gf">
        <select
          v-model="selectedGravityFormId"
          class="dsf-wysiwyg__gf-select"
          aria-label="Gravity Form to insert"
        >
          <option v-for="form in gravityForms" :key="form.id" :value="form.id">
            {{ form.title }} (ID {{ form.id }})
          </option>
        </select>
        <button
          type="button"
          class="dsf-wysiwyg__btn dsf-wysiwyg__gf-insert"
          title="Insert the selected Gravity Form shortcode"
          @click="insertGravityForm"
        >
          <Plus :size="12" />
          <span>Form</span>
        </button>
      </div>
    </div>
    <textarea
      v-if="sourceMode"
      class="dsf-wysiwyg__source"
      :value="modelValue"
      @input="emitSourceUpdate"
    ></textarea>
    <div
      v-else
      ref="editor"
      class="dsf-wysiwyg__editor"
      contenteditable="true"
      @input="emitUpdate"
    ></div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import {
  Bold,
  Code2,
  Italic,
  Link,
  List,
  ListOrdered,
  Minus,
  Plus,
  Unlink,
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  allowRawHtml: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const editor = ref(null)
const sourceMode = ref(false)

const gravityForms = computed(() => {
  const forms = typeof window !== 'undefined' ? window.dsfEditorData?.gravityForms : []
  if (!Array.isArray(forms)) return []
  return forms
    .map((form) => ({
      id: String(form?.id || '').trim(),
      title: String(form?.title || '').trim(),
      shortcode: String(form?.shortcode || '').trim(),
    }))
    .filter((form) => form.id && form.shortcode)
})

const selectedGravityFormId = ref('')

watch(
  gravityForms,
  (forms) => {
    if (!forms.length) {
      selectedGravityFormId.value = ''
      return
    }
    if (!forms.some((form) => form.id === selectedGravityFormId.value)) {
      selectedGravityFormId.value = forms[0].id
    }
  },
  { immediate: true }
)

function insertGravityForm() {
  const form = gravityForms.value.find((item) => item.id === selectedGravityFormId.value)
  if (!form) return

  if (sourceMode.value) {
    const current = props.modelValue || ''
    emit('update:modelValue', `${current}${current.trim() ? '\n' : ''}${form.shortcode}`)
    return
  }

  if (!editor.value) return
  editor.value.focus()

  let inserted = false
  try {
    inserted = document.execCommand('insertText', false, form.shortcode)
  } catch (error) {
    inserted = false
  }
  if (!inserted) {
    editor.value.innerHTML = `${editor.value.innerHTML || ''}<p>${form.shortcode}</p>`
  }
  emitUpdate()
}

function exec(command) {
  if (sourceMode.value) return
  if (editor.value && document.activeElement !== editor.value) {
    editor.value.focus()
  }
  document.execCommand(command, false, null)
  emitUpdate()
}

function formatBlock(tag) {
  if (sourceMode.value) return
  if (editor.value && document.activeElement !== editor.value) {
    editor.value.focus()
  }
  // Some browsers expect the tag wrapped in angle brackets for formatBlock.
  document.execCommand('formatBlock', false, `<${tag.toLowerCase()}>`)
  emitUpdate()
}

function addLink() {
  const url = window.prompt('Enter link URL')
  if (url) {
    document.execCommand('createLink', false, url)
    emitUpdate()
  }
}

function emitUpdate() {
  emit('update:modelValue', editor.value?.innerHTML || '')
}

function emitSourceUpdate(event) {
  emit('update:modelValue', event.target.value)
}

function toggleSourceMode() {
  if (sourceMode.value) {
    sourceMode.value = false
    nextTick(() => {
      if (editor.value) {
        editor.value.innerHTML = props.modelValue || ''
      }
    })
    return
  }

  emitUpdate()
  sourceMode.value = true
}

onMounted(() => {
  if (editor.value) {
    editor.value.innerHTML = props.modelValue || ''
  }
})

watch(
  () => props.modelValue,
  (value) => {
    if (!editor.value || sourceMode.value) return
    if (editor.value.innerHTML !== value) {
      editor.value.innerHTML = value || ''
    }
  }
)
</script>

<style scoped>
.dsf-wysiwyg {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  overflow: visible;
  border: 1px solid #e6e8ec;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 4px 12px rgba(16, 24, 40, 0.03);
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.dsf-wysiwyg:focus-within {
  border-color: var(--dsf-primary-400, #7aa7e9);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}

.dsf-wysiwyg__toolbar {
  box-sizing: border-box;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 8px;
  border: 1px solid #eef0f3;
  border-radius: 12px;
  background: #f8f9fb;
}

.dsf-wysiwyg__btn {
  box-sizing: border-box;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  min-width: 36px;
  height: 36px;
  padding: 0 9px;
  border: 1px solid transparent;
  border-radius: 9px;
  background: transparent;
  color: #475467;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1;
  box-shadow: none;
  transition: transform 0.12s ease, color 0.15s ease, border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.dsf-wysiwyg__btn--heading {
  min-width: 40px;
  font-size: 0.9rem;
  font-weight: 800;
  letter-spacing: 0;
}

.dsf-wysiwyg__btn--paragraph {
  font-family: Georgia, "Times New Roman", serif;
  font-size: 1.05rem;
}

.dsf-wysiwyg__btn:hover {
  border-color: transparent;
  color: var(--dsf-primary-700, #1d4ed8);
  background: rgba(37, 99, 235, 0.1);
  box-shadow: none;
}

.dsf-wysiwyg__btn:active {
  background: rgba(37, 99, 235, 0.16);
}

.dsf-wysiwyg__btn--active,
.dsf-wysiwyg__btn--active:hover {
  color: #fff;
  background: var(--dsf-primary-600, #2563eb);
  border-color: var(--dsf-primary-600, #2563eb);
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.28);
}

.dsf-wysiwyg__gf {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
}

.dsf-wysiwyg__gf-select {
  box-sizing: border-box;
  height: 36px;
  max-width: 190px;
  padding: 0 10px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #fff;
  color: #344054;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.dsf-wysiwyg__gf-select:focus {
  outline: none;
  border-color: var(--dsf-primary-400, #7aa7e9);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
}

.dsf-wysiwyg__gf-insert {
  gap: 4px;
  padding: 0 12px;
  color: #fff;
  background: var(--dsf-primary-600, #2563eb);
  border-color: var(--dsf-primary-600, #2563eb);
}

.dsf-wysiwyg__gf-insert svg {
  display: block;
  flex-shrink: 0;
}

.dsf-wysiwyg__gf-insert span {
  font-size: 0.8rem;
  line-height: 1;
}

.dsf-wysiwyg__gf-insert:hover {
  color: #fff;
  background: var(--dsf-primary-700, #1d4ed8);
  border-color: var(--dsf-primary-700, #1d4ed8);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.dsf-wysiwyg__sep {
  width: 1px;
  align-self: stretch;
  min-height: 20px;
  margin: 0 5px;
  background: #e2e8f0;
}

.dsf-wysiwyg__editor,
.dsf-wysiwyg__source {
  box-sizing: border-box;
  width: 100%;
  min-height: 280px;
  padding: 5px;
  margin: 0;
  border: 1px solid #e6e8ec;
  border-radius: 12px;
  outline: none;
  font-size: 1rem;
  line-height: 1.7;
  background: #fff;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.dsf-wysiwyg__editor {
  color: var(--dsf-theme-text, #1f2937);
  font-family: var(--dsf-theme-body-font, inherit);
  background: #fff;
  cursor: text;
}

.dsf-wysiwyg__editor:focus,
.dsf-wysiwyg__source:focus {
  border-color: var(--dsf-primary-400, #7aa7e9);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.dsf-wysiwyg__editor > :first-child {
  margin-top: 0;
}

.dsf-wysiwyg__editor > :last-child {
  margin-bottom: 0;
}

.dsf-wysiwyg__editor :deep(h1),
.dsf-wysiwyg__editor :deep(h2),
.dsf-wysiwyg__editor :deep(h3),
.dsf-wysiwyg__editor :deep(h4) {
  margin: 0 0 0.6em;
  font-family: var(--dsf-theme-heading-font, inherit);
  line-height: 1.2;
  color: var(--dsf-theme-text, var(--dsf-gray-900));
}

.dsf-wysiwyg__editor :deep(h1) {
  font-size: var(--dsf-theme-h1, 42px);
}

.dsf-wysiwyg__editor :deep(h2) {
  font-size: var(--dsf-theme-h2, 37px);
}

.dsf-wysiwyg__editor :deep(h3) {
  font-size: var(--dsf-theme-h3, 28px);
}

.dsf-wysiwyg__editor :deep(h4) {
  font-size: calc(var(--dsf-theme-p-size, 20px) * 0.9);
  font-weight: 700;
}

.dsf-wysiwyg__editor :deep(p) {
  margin: 0 0 1.1em;
  font-size: var(--dsf-theme-p-size, 20px);
  line-height: 1.6;
}

.dsf-wysiwyg__editor :deep(ul) {
  margin: 0 0 1.1em;
  padding-left: 1.6em;
  list-style: disc outside;
}

.dsf-wysiwyg__editor :deep(ol) {
  margin: 0 0 1.1em;
  padding-left: 1.6em;
  list-style: decimal outside;
}

.dsf-wysiwyg__editor :deep(li) {
  display: list-item;
  margin: 0 0 0.4em;
}

.dsf-wysiwyg__editor :deep(hr) {
  border: 0;
  border-top: 1px solid #e6e8ec;
  margin: 1.5rem 0;
}

.dsf-wysiwyg__source {
  display: block;
  resize: vertical;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: 0.9rem;
  color: #1f2937;
  background: #fff;
  tab-size: 2;
}

.dsf-wysiwyg__source::selection {
  background: rgba(37, 99, 235, 0.18);
}

.dsf-wysiwyg__editor :deep(a) {
  color: var(--dsf-brand-blue, rgb(12, 95, 168));
  text-decoration: underline;
}

.dsf-wysiwyg__editor :deep(a:hover) {
  color: var(--dsf-brand-blue-dark, rgb(8, 73, 132));
}
</style>
