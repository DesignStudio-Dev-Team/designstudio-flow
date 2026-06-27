<template>
  <div class="dsf-shortcode-embed">
    <div class="dsf-shortcode-embed__header">
      <div>
        <div class="dsf-shortcode-embed__eyebrow">Embed</div>
        <div class="dsf-shortcode-embed__title">Shortcode or HTML embed</div>
      </div>
      <button
        v-if="gravityForms.length"
        type="button"
        class="dsf-shortcode-embed__quick-btn"
        @click="insertSelectedGravityForm"
      >
        <Plus :size="15" />
        <span>Gravity Form</span>
      </button>
    </div>

    <div v-if="gravityForms.length" class="dsf-shortcode-embed__gravity">
      <label class="dsf-shortcode-embed__label" for="dsf-shortcode-embed-gravity">
        Insert Gravity Form
      </label>
      <div class="dsf-shortcode-embed__gravity-row">
        <select
          id="dsf-shortcode-embed-gravity"
          v-model="selectedGravityFormId"
          class="dsf-shortcode-embed__select"
        >
          <option
            v-for="form in gravityForms"
            :key="form.id"
            :value="String(form.id)"
          >
            {{ form.title }} (ID: {{ form.id }})
          </option>
        </select>
        <button
          type="button"
          class="dsf-shortcode-embed__insert"
          @click="insertSelectedGravityForm"
        >
          <Plus :size="15" />
          <span>Insert</span>
        </button>
      </div>
    </div>

    <div class="dsf-shortcode-embed__toolbar" aria-label="Embed editor tools">
      <button type="button" class="is-active">
        <Code2 :size="15" />
        <span>Code</span>
      </button>
    </div>

    <textarea
      class="dsf-shortcode-embed__source"
      :value="modelValue"
      spellcheck="false"
      placeholder="[gravityform id=&quot;1&quot; title=&quot;false&quot; description=&quot;false&quot; ajax=&quot;true&quot;]"
      @input="$emit('update:modelValue', $event.target.value)"
    ></textarea>

    <div class="dsf-shortcode-embed__footer">
      <span>{{ snippetKind }}</span>
      <span>{{ characterCount }} chars</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Code2, Plus } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

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
  { immediate: true },
)

const characterCount = computed(() => (props.modelValue || '').length)
const snippetKind = computed(() => {
  const value = (props.modelValue || '').trim()
  if (!value) return 'Empty'
  if (/^\[[\s\S]+\]$/.test(value)) return 'Shortcode'
  if (/<[a-z][\s\S]*>/i.test(value)) return 'HTML embed'
  return 'Text snippet'
})

function insertSelectedGravityForm() {
  const selected = gravityForms.value.find((form) => form.id === selectedGravityFormId.value)
  if (!selected) return

  const current = props.modelValue || ''
  const separator = current.trim() ? '\n' : ''
  emit('update:modelValue', `${current}${separator}${selected.shortcode}`)
}
</script>

<style scoped>
.dsf-shortcode-embed {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  border: 1px solid #8c8f94;
  border-radius: 4px;
  padding: 12px;
  background: #f6f7f7;
  overflow: visible;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.dsf-shortcode-embed__header {
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.875rem;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  background: #fff;
}

.dsf-shortcode-embed__eyebrow {
  color: var(--dsf-gray-500);
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1;
  text-transform: uppercase;
}

.dsf-shortcode-embed__title {
  margin-top: 0.25rem;
  color: var(--dsf-gray-900);
  font-size: 0.9rem;
  font-weight: 700;
  line-height: 1.2;
}

.dsf-shortcode-embed__quick-btn,
.dsf-shortcode-embed__insert,
.dsf-shortcode-embed__toolbar button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: 3px;
  background: #f6f7f7;
  color: var(--dsf-gray-700);
  cursor: pointer;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1;
  min-height: 32px;
  padding: 0 0.625rem;
}

.dsf-shortcode-embed__quick-btn:hover,
.dsf-shortcode-embed__insert:hover {
  border-color: var(--dsf-primary-300);
  color: var(--dsf-primary-700);
}

.dsf-shortcode-embed__gravity {
  box-sizing: border-box;
  padding: 0.875rem;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  background: #fff;
}

.dsf-shortcode-embed__label {
  display: block;
  margin-bottom: 0.4rem;
  color: var(--dsf-gray-600);
  font-size: 0.75rem;
  font-weight: 700;
}

.dsf-shortcode-embed__gravity-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.5rem;
}

.dsf-shortcode-embed__select {
  width: 100%;
  min-width: 0;
  min-height: 34px;
  border: 1px solid #8c8f94;
  border-radius: 4px;
  background: #fff;
  color: var(--dsf-gray-800);
  font-size: 0.82rem;
  padding: 0 0.5rem;
}

.dsf-shortcode-embed__toolbar {
  box-sizing: border-box;
  display: flex;
  gap: 0.375rem;
  padding: 0.625rem;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  background: #fff;
}

.dsf-shortcode-embed__toolbar button.is-active {
  border-color: var(--dsf-primary-500);
  background: var(--dsf-primary-50, #eef2ff);
  color: var(--dsf-primary-700);
}

.dsf-shortcode-embed__source {
  box-sizing: border-box;
  display: block;
  width: 100%;
  min-height: 190px;
  border: 1px solid #8c8f94;
  border-radius: 4px;
  outline: 0;
  resize: vertical;
  color: var(--dsf-gray-900);
  background: #0f172a;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: 0.82rem;
  line-height: 1.55;
  padding: 0.875rem;
  color: #e5edf7;
}

.dsf-shortcode-embed__source::placeholder {
  color: #94a3b8;
}

.dsf-shortcode-embed__footer {
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.5rem 0.875rem;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  background: #fff;
  color: var(--dsf-gray-500);
  font-size: 0.72rem;
  font-weight: 700;
}
</style>
