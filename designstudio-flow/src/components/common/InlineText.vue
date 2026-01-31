<template>
  <component
    :is="tagName"
    ref="element"
    class="dsf-inline-text"
    :class="{ 
      'dsf-inline-text--editable': isEditor, 
      'dsf-inline-text--empty': isEditor && !modelValue && !isFocused 
    }"
    :contenteditable="isEditor"
    @focus="onFocus"
    @blur="onBlur"
    @keydown.enter="onEnter"
    @paste="onPaste"
    :placeholder="placeholder"
  >{{ modelValue }}</component>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  tagName: {
    type: String,
    default: 'div'
  },
  modelValue: {
    type: String,
    default: ''
  },
  isEditor: {
    type: Boolean,
    default: false
  },
  multiline: {
    type: Boolean,
    default: false
  },
  placeholder: {
    type: String,
    default: 'Enter text...'
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const element = ref(null)
const isFocused = ref(false)

function onFocus() {
  isFocused.value = true
}

function onBlur(e) {
  isFocused.value = false
  const text = e.target.innerText
  // Update parent only on blur to avoid caret jumping
  if (text !== props.modelValue) {
    emit('update:modelValue', text)
    emit('change', text)
  }
}

function onEnter(e) {
  if (!props.isEditor) return
  
  if (!props.multiline) {
    e.preventDefault()
    e.target.blur()
  }
}

function onPaste(e) {
  if (!props.isEditor) return
  
  e.preventDefault()
  // Strip formatting, paste as plain text
  const text = (e.originalEvent || e).clipboardData.getData('text/plain')
  
  const selection = window.getSelection()
  if (!selection.rangeCount) return
  
  selection.deleteFromDocument()
  selection.getRangeAt(0).insertNode(document.createTextNode(text))
  selection.collapseToEnd()
}
</script>

<style scoped>
.dsf-inline-text {
  transition: all 0.2s;
  min-width: 10px; /* Ensure clickability if empty */
}

.dsf-inline-text--editable:hover {
  outline: 1px dashed var(--dsf-primary-300);
  cursor: text;
}

.dsf-inline-text--editable:focus {
  outline: 2px solid var(--dsf-primary-500);
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 2px;
}

.dsf-inline-text--empty:empty::before {
  content: attr(placeholder);
  color: #9CA3AF;
  font-style: italic;
}
</style>
