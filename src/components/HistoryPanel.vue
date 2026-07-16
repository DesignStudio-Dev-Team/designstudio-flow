<template>
  <Teleport to="body">
    <div v-if="visible" class="dsf-history" role="dialog" aria-modal="true" aria-labelledby="dsf-history-title">
      <div class="dsf-history__backdrop" @click="emit('close')"></div>
      <section ref="panel" class="dsf-history__panel">
        <header class="dsf-history__header">
          <div>
            <p class="dsf-history__eyebrow">Quick Restore</p>
            <h2 id="dsf-history-title">Recent versions</h2>
          </div>
          <button type="button" class="dsf-history__close" aria-label="Close history" @click="emit('close')">×</button>
        </header>
        <p class="dsf-history__description">The current version is kept automatically when you restore an older version.</p>
        <div v-if="loading" class="dsf-history__state" role="status">Loading history…</div>
        <div v-else-if="error" class="dsf-history__state dsf-history__state--error" role="alert">{{ error }}</div>
        <div v-else-if="!records.length" class="dsf-history__state">No previous versions yet.</div>
        <ol v-else class="dsf-history__list">
          <li v-for="record in records" :key="record.id" class="dsf-history__item">
            <div class="dsf-history__item-main">
              <time :datetime="record.created_at_gmt">{{ formatDate(record.created_at_gmt) }}</time>
              <span v-if="record.reason" class="dsf-history__reason">{{ formatReason(record.reason) }}</span>
            </div>
            <p class="dsf-history__summary">{{ record.summary || 'Updated Flow content' }}</p>
            <p class="dsf-history__editor">{{ editorLabel(record.created_by) }}</p>
            <button type="button" class="dsf-history__restore" :disabled="restoring" @click="emit('restore', record)">
              {{ restoring ? 'Restoring…' : 'Restore this version' }}
            </button>
          </li>
        </ol>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  visible: Boolean,
  records: { type: Array, default: () => [] },
  loading: Boolean,
  restoring: Boolean,
  error: { type: String, default: '' },
  editors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close', 'restore'])
const panel = ref(null)
let previousFocus = null

function formatDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T') + 'Z')
  return Number.isNaN(date.getTime()) ? 'Unknown time' : date.toLocaleString()
}

function formatReason(value) {
  return String(value || '').replace(/_/g, ' ')
}

function editorLabel(id) {
  return props.editors[String(id)] || (Number(id) ? `Editor #${id}` : 'DesignStudio Flow')
}

function onKeydown(event) {
  if (!props.visible) return
  if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
.dsf-history { position: fixed; inset: 0; z-index: 100000; display: grid; place-items: center; padding: 20px; }
.dsf-history__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .48); }
.dsf-history__panel { position: relative; width: min(560px, 100%); max-height: min(720px, 90vh); overflow: auto; background: #fff; color: #17202a; border-radius: 16px; box-shadow: 0 24px 80px rgba(15, 23, 42, .25); padding: 24px; }
.dsf-history__header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.dsf-history__eyebrow { margin: 0 0 4px; color: #2c5f5d; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.dsf-history h2 { margin: 0; font-size: 24px; }
.dsf-history__close { border: 0; background: transparent; font-size: 28px; line-height: 1; cursor: pointer; }
.dsf-history__description { color: #5b6573; margin: 14px 0 20px; }
.dsf-history__state { padding: 28px 8px; color: #5b6573; text-align: center; }
.dsf-history__state--error { color: #b42318; }
.dsf-history__list { display: grid; gap: 12px; padding: 0; margin: 0; list-style: none; }
.dsf-history__item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; }
.dsf-history__item-main { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; font-weight: 700; }
.dsf-history__reason { color: #64748b; font-weight: 500; text-transform: capitalize; }
.dsf-history__summary { margin: 9px 0 5px; }
.dsf-history__editor { margin: 0 0 12px; color: #64748b; font-size: 12px; }
.dsf-history__restore { border: 1px solid #2c5f5d; border-radius: 7px; background: #fff; color: #2c5f5d; padding: 7px 10px; cursor: pointer; font-weight: 600; }
.dsf-history__restore:disabled { opacity: .55; cursor: wait; }
</style>
