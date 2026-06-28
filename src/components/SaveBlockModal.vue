<template>
  <Teleport to="body">
    <Transition name="dsf-dialog">
      <div v-if="visible" class="dsf-savemodal-overlay" @click.self="$emit('cancel')">
        <div class="dsf-savemodal" role="dialog" aria-modal="true" aria-labelledby="dsf-savemodal-title">
          <div class="dsf-savemodal__head">
            <Bookmark :size="18" />
            <h3 id="dsf-savemodal-title">Save block to library</h3>
          </div>

          <label class="dsf-savemodal__label" for="dsf-savemodal-name">Name</label>
          <input
            id="dsf-savemodal-name"
            ref="nameInput"
            v-model="name"
            type="text"
            class="dsf-savemodal__input"
            placeholder="e.g. Marketing hero — dark"
            @keyup.enter="onSave"
          />

          <fieldset v-if="existing.length" class="dsf-savemodal__modes">
            <label class="dsf-savemodal__radio">
              <input type="radio" value="new" v-model="mode" />
              <span>Create new saved block</span>
            </label>
            <label class="dsf-savemodal__radio">
              <input type="radio" value="update" v-model="mode" />
              <span>Update an existing one</span>
            </label>

            <select v-if="mode === 'update'" v-model="updateId" class="dsf-savemodal__select">
              <option v-for="item in existing" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </fieldset>

          <div class="dsf-savemodal__actions">
            <button type="button" class="dsf-savemodal__btn dsf-savemodal__btn--cancel" @click="$emit('cancel')">Cancel</button>
            <button type="button" class="dsf-savemodal__btn dsf-savemodal__btn--save" @click="onSave">
              {{ mode === 'update' ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { Bookmark } from 'lucide-vue-next'

const props = defineProps({
  visible: { type: Boolean, default: false },
  suggestedName: { type: String, default: '' },
  // Existing saved blocks of the same type (offered for "update existing").
  existing: { type: Array, default: () => [] },
})

const emit = defineEmits(['save', 'cancel'])

const name = ref('')
const mode = ref('new')
const updateId = ref(null)
const nameInput = ref(null)

watch(
  () => props.visible,
  (open) => {
    if (open) {
      name.value = props.suggestedName
      mode.value = 'new'
      updateId.value = props.existing.length ? props.existing[0].id : null
      nextTick(() => nameInput.value?.focus())
    }
  }
)

// Picking a block to update pulls its name into the field for an easy rename.
watch(updateId, (id) => {
  if (mode.value !== 'update') return
  const match = props.existing.find((item) => item.id === id)
  if (match) name.value = match.name
})

function onSave() {
  const finalName = name.value.trim() || props.suggestedName
  emit('save', {
    name: finalName,
    id: mode.value === 'update' ? updateId.value : null,
  })
}
</script>

<style scoped>
.dsf-savemodal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 1rem;
}

.dsf-savemodal {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
  padding: 1.5rem;
}

.dsf-savemodal__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--dsf-gray-900, #0f172a);
  margin-bottom: 1rem;
}

.dsf-savemodal__head h3 {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
}

.dsf-savemodal__label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--dsf-gray-600, #4b5563);
  margin-bottom: 0.375rem;
}

.dsf-savemodal__input,
.dsf-savemodal__select {
  width: 100%;
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--dsf-gray-200, #e5e7eb);
  border-radius: 8px;
  font-size: 0.875rem;
  background: var(--dsf-gray-50, #f9fafb);
}

.dsf-savemodal__input:focus,
.dsf-savemodal__select:focus {
  outline: none;
  border-color: var(--dsf-primary-500, #3b82f6);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.dsf-savemodal__modes {
  border: none;
  margin: 1rem 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-savemodal__radio {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: var(--dsf-gray-800, #1f2937);
  cursor: pointer;
}

.dsf-savemodal__select {
  margin-top: 0.25rem;
}

.dsf-savemodal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.5rem;
}

.dsf-savemodal__btn {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
}

.dsf-savemodal__btn--cancel {
  background: #fff;
  border-color: var(--dsf-gray-200, #e5e7eb);
  color: var(--dsf-gray-700, #374151);
}

.dsf-savemodal__btn--cancel:hover {
  background: var(--dsf-gray-50, #f9fafb);
}

.dsf-savemodal__btn--save {
  background: var(--dsf-primary-500, #3b82f6);
  color: #fff;
}

.dsf-savemodal__btn--save:hover {
  background: var(--dsf-primary-600, #2563eb);
}

.dsf-dialog-enter-active,
.dsf-dialog-leave-active {
  transition: opacity 0.18s ease;
}

.dsf-dialog-enter-from,
.dsf-dialog-leave-to {
  opacity: 0;
}
</style>
