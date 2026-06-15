<template>
  <Teleport to="body">
    <Transition name="dsf-page-settings">
      <div v-if="visible" class="dsf-page-settings-overlay" @click.self="close">
        <form class="dsf-page-settings-modal" @submit.prevent="save">
          <div class="dsf-page-settings-modal__header">
            <div>
              <h2 class="dsf-page-settings-modal__title">Page Settings</h2>
              <p class="dsf-page-settings-modal__subtitle">Manage WordPress page details before saving.</p>
            </div>
            <button type="button" class="dsf-page-settings-modal__close" @click="close">
              <X :size="20" />
            </button>
          </div>

          <div class="dsf-page-settings-modal__body">
            <div class="dsf-form-group">
              <label class="dsf-label" for="dsf-page-title">Page Title</label>
              <input
                id="dsf-page-title"
                v-model="localTitle"
                class="dsf-input"
                type="text"
                placeholder="Untitled Page"
              />
            </div>

            <div class="dsf-form-group">
              <label class="dsf-label" for="dsf-page-slug">Slug</label>
              <div class="dsf-page-settings-modal__slug-row">
                <input
                  id="dsf-page-slug"
                  v-model="localSlug"
                  class="dsf-input"
                  type="text"
                  placeholder="page-slug"
                />
                <button type="button" class="dsf-btn dsf-btn--secondary" @click="generateSlug">
                  Use Title
                </button>
              </div>
              <p class="dsf-page-settings-modal__hint">Leave blank to let WordPress generate it from the title.</p>
            </div>

            <div class="dsf-page-settings-modal__grid">
              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-page-status">Status</label>
                <select id="dsf-page-status" v-model="localStatus" class="dsf-input">
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                </select>
              </div>

              <div class="dsf-form-group">
                <label class="dsf-label" for="dsf-page-parent">Parent Page</label>
                <select id="dsf-page-parent" v-model.number="localParentId" class="dsf-input">
                  <option :value="0">No parent</option>
                  <option
                    v-for="page in parentPages"
                    :key="page.id"
                    :value="page.id"
                  >
                    {{ page.depthLabel }}{{ page.title }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="dsf-page-settings-modal__footer">
            <button type="button" class="dsf-btn dsf-btn--secondary" @click="close">Cancel</button>
            <button type="submit" class="dsf-btn dsf-btn--primary">Apply Settings</button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  slug: {
    type: String,
    default: '',
  },
  status: {
    type: String,
    default: 'draft',
  },
  parentId: {
    type: Number,
    default: 0,
  },
  parentPages: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['close', 'save'])

const localTitle = ref('')
const localSlug = ref('')
const localStatus = ref('draft')
const localParentId = ref(0)

watch(
  () => props.visible,
  (isVisible) => {
    if (!isVisible) return
    localTitle.value = props.title || ''
    localSlug.value = props.slug || ''
    localStatus.value = props.status === 'publish' ? 'publish' : 'draft'
    localParentId.value = Number.parseInt(props.parentId, 10) || 0
  },
  { immediate: true }
)

function slugify(value) {
  return String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function generateSlug() {
  localSlug.value = slugify(localTitle.value)
}

function save() {
  emit('save', {
    title: localTitle.value.trim(),
    slug: slugify(localSlug.value),
    status: localStatus.value === 'publish' ? 'publish' : 'draft',
    parentId: Number.parseInt(localParentId.value, 10) || 0,
  })
}

function close() {
  emit('close')
}
</script>

<style scoped>
.dsf-page-settings-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.58);
  backdrop-filter: blur(4px);
}

.dsf-page-settings-modal {
  width: min(560px, 100%);
  overflow: hidden;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 26px 60px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.dsf-page-settings-modal__header,
.dsf-page-settings-modal__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 20px 22px;
}

.dsf-page-settings-modal__header {
  border-bottom: 1px solid var(--dsf-gray-200);
}

.dsf-page-settings-modal__footer {
  border-top: 1px solid var(--dsf-gray-200);
  background: var(--dsf-gray-50);
}

.dsf-page-settings-modal__title,
.dsf-page-settings-modal__subtitle {
  margin: 0;
}

.dsf-page-settings-modal__title {
  color: var(--dsf-gray-900);
  font-size: 1.125rem;
  font-weight: 700;
}

.dsf-page-settings-modal__subtitle,
.dsf-page-settings-modal__hint {
  color: var(--dsf-gray-500);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.dsf-page-settings-modal__hint {
  margin: 0.375rem 0 0;
}

.dsf-page-settings-modal__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-gray-100);
  color: var(--dsf-gray-500);
  cursor: pointer;
}

.dsf-page-settings-modal__close:hover {
  background: var(--dsf-gray-200);
  color: var(--dsf-gray-800);
}

.dsf-page-settings-modal__body {
  display: grid;
  gap: 1rem;
  padding: 22px;
}

.dsf-page-settings-modal__slug-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.625rem;
}

.dsf-page-settings-modal__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.dsf-page-settings-enter-active,
.dsf-page-settings-leave-active {
  transition: opacity 0.16s ease;
}

.dsf-page-settings-enter-active .dsf-page-settings-modal,
.dsf-page-settings-leave-active .dsf-page-settings-modal {
  transition: transform 0.16s ease;
}

.dsf-page-settings-enter-from,
.dsf-page-settings-leave-to {
  opacity: 0;
}

.dsf-page-settings-enter-from .dsf-page-settings-modal,
.dsf-page-settings-leave-to .dsf-page-settings-modal {
  transform: translateY(10px) scale(0.98);
}

@media (max-width: 640px) {
  .dsf-page-settings-modal__slug-row,
  .dsf-page-settings-modal__grid {
    grid-template-columns: 1fr;
  }

  .dsf-page-settings-modal__footer {
    flex-direction: column-reverse;
  }

  .dsf-page-settings-modal__footer .dsf-btn {
    width: 100%;
  }
}
</style>
