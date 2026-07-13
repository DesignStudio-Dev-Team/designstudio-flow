<template>
  <div class="dsf-structure" role="dialog" aria-label="Block structure">
    <header class="dsf-structure__header">
      <div class="dsf-structure__heading">
        <ListTree :size="18" />
        <div>
          <h2 class="dsf-structure__title">Structure</h2>
          <p class="dsf-structure__subtitle">{{ blocks.length }} block{{ blocks.length === 1 ? '' : 's' }}</p>
        </div>
      </div>
      <button
        type="button"
        class="dsf-structure__close"
        aria-label="Close structure"
        @click="emit('close')"
      >
        <X :size="18" />
      </button>
    </header>

    <div class="dsf-structure__body">
      <p v-if="!blocks.length" class="dsf-structure__empty">
        No blocks yet. Add one to see it here.
      </p>

      <draggable
        v-else
        :model-value="blocks"
        item-key="id"
        class="dsf-structure__list"
        handle=".dsf-nav-row__handle"
        ghost-class="dsf-nav-row--ghost"
        @update:model-value="(value) => emit('reorder', value)"
      >
        <template #item="{ element, index }">
          <div
            class="dsf-nav-row"
            :class="{ 'is-selected': element.id === selectedId, 'is-editing': editingId === element.id }"
            @click="emit('select', element)"
          >
            <button
              type="button"
              class="dsf-nav-row__handle"
              aria-label="Drag to reorder"
              title="Drag to reorder"
              @click.stop
            >
              <GripVertical :size="15" />
            </button>
            <span class="dsf-nav-row__num">{{ index + 1 }}</span>

            <input
              v-if="editingId === element.id"
              v-model="editValue"
              type="text"
              maxlength="80"
              class="dsf-nav-row__input"
              :placeholder="titleFor(element.type)"
              @click.stop
              @keydown.enter.prevent="commitEdit(element)"
              @keydown.esc.prevent="cancelEdit"
              @blur="commitEdit(element)"
            />
            <span
              v-else
              class="dsf-nav-row__title"
              :title="element.label ? `${element.label} — ${titleFor(element.type)}` : titleFor(element.type)"
              @dblclick.stop="startEdit(element)"
            >
              <span class="dsf-nav-row__name">{{ element.label || titleFor(element.type) }}</span>
              <span v-if="element.label" class="dsf-nav-row__type">{{ titleFor(element.type) }}</span>
            </span>

            <span class="dsf-nav-row__actions">
              <button
                type="button"
                class="dsf-nav-row__btn"
                aria-label="Rename"
                title="Rename"
                @click.stop="startEdit(element)"
              >
                <Pencil :size="14" />
              </button>
              <button
                type="button"
                class="dsf-nav-row__btn"
                aria-label="Move up"
                title="Move up"
                :disabled="index === 0"
                @click.stop="emit('move-up', index)"
              >
                <ChevronUp :size="15" />
              </button>
              <button
                type="button"
                class="dsf-nav-row__btn"
                aria-label="Move down"
                title="Move down"
                :disabled="index === blocks.length - 1"
                @click.stop="emit('move-down', index)"
              >
                <ChevronDown :size="15" />
              </button>
            </span>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, ChevronUp, GripVertical, ListTree, Pencil, X } from 'lucide-vue-next'

const props = defineProps({
  blocks: { type: Array, default: () => [] },
  selectedId: { type: [String, Number], default: null },
  // (type) => human-readable block name
  titleFor: { type: Function, required: true },
})

const emit = defineEmits(['close', 'select', 'move-up', 'move-down', 'reorder', 'rename'])

// Inline rename: a custom, editor-only label so a page full of "Content" blocks
// stays legible. Empty clears the label (falls back to the block's default name).
const editingId = ref(null)
const editValue = ref('')

function startEdit(block) {
  editingId.value = block.id
  editValue.value = block.label || ''
  // Focus + select once, after the input renders — not via a ref callback that
  // would re-fire on every keystroke and re-select the text (the one-letter bug).
  nextTick(() => {
    const input = document.querySelector('.dsf-structure .dsf-nav-row__input')
    if (input) {
      input.focus()
      input.select()
    }
  })
}

function commitEdit(block) {
  if (editingId.value !== block.id) return
  emit('rename', { id: block.id, label: editValue.value.trim() })
  editingId.value = null
}

function cancelEdit() {
  editingId.value = null
}
</script>
