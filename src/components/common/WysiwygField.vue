<template>
  <div class="dsf-wysiwyg">
    <div class="dsf-wysiwyg__toolbar">
      <button type="button" class="dsf-wysiwyg__btn" @click="exec('bold')"><b>B</b></button>
      <button type="button" class="dsf-wysiwyg__btn" @click="exec('italic')"><i>I</i></button>
      <button type="button" class="dsf-wysiwyg__btn" @click="addLink">Link</button>
      <button type="button" class="dsf-wysiwyg__btn" @click="exec('unlink')">Unlink</button>
      <button
        v-if="allowRawHtml"
        type="button"
        class="dsf-wysiwyg__btn"
        :class="{ 'dsf-wysiwyg__btn--active': sourceMode }"
        @click="toggleSourceMode"
      >
        HTML
      </button>
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
import { nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  allowRawHtml: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])
const editor = ref(null)
const sourceMode = ref(false)

function exec(command) {
  document.execCommand(command, false, null)
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
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  overflow: hidden;
  background: #fff;
}

.dsf-wysiwyg__toolbar {
  display: flex;
  gap: 0.5rem;
  padding: 0.5rem;
  border-bottom: 1px solid var(--dsf-gray-200);
  background: var(--dsf-gray-50);
}

.dsf-wysiwyg__btn {
  border: 1px solid var(--dsf-gray-200);
  background: #fff;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.dsf-wysiwyg__btn--active {
  color: #fff;
  background: var(--dsf-primary-600, #2563eb);
  border-color: var(--dsf-primary-600, #2563eb);
}

.dsf-wysiwyg__editor,
.dsf-wysiwyg__source {
  min-height: 120px;
  padding: 0.75rem;
  outline: none;
  font-size: 0.95rem;
  line-height: 1.5;
}

.dsf-wysiwyg__source {
  display: block;
  width: 100%;
  border: 0;
  resize: vertical;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  color: var(--dsf-gray-800);
}

.dsf-wysiwyg__editor a {
  color: #2563eb;
  text-decoration: underline;
}

.dsf-wysiwyg__editor a:hover {
  color: #1d4ed8;
}
</style>
