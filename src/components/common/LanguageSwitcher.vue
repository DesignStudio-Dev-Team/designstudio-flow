<template>
  <nav
    v-if="items.length > 1"
    class="dsf-language-switcher"
    :class="[`dsf-language-switcher--${resolvedStyle}`, { 'is-open': open }]"
    :aria-label="ariaLabel"
  >
    <!-- Dropdown and compact share one disclosure button; the list style is
         always visible so it needs no button at all. -->
    <button
      v-if="resolvedStyle !== 'list'"
      ref="toggle"
      type="button"
      class="dsf-language-switcher__toggle"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="true"
      @click.prevent.stop="toggleOpen"
      @keydown.down.prevent="openAndFocusFirst"
      @keydown.esc.prevent="close"
    >
      <Globe :size="16" aria-hidden="true" />
      <span class="dsf-language-switcher__toggle-text">{{ currentText }}</span>
      <ChevronDown v-if="resolvedStyle === 'dropdown'" :size="14" aria-hidden="true" />
    </button>

    <ul
      v-show="resolvedStyle === 'list' || open"
      ref="list"
      class="dsf-language-switcher__list"
    >
      <li
        v-for="item in items"
        :key="item.code"
        class="dsf-language-switcher__item"
        :class="{ 'is-current': item.current }"
      >
        <span
          v-if="item.current"
          class="dsf-language-switcher__current"
          aria-current="true"
          :lang="item.html_lang"
        >{{ labelFor(item) }}</span>
        <a
          v-else
          class="dsf-language-switcher__link"
          :href="item.url"
          :lang="item.html_lang"
          :hreflang="item.html_lang"
          :dir="item.direction === 'rtl' ? 'rtl' : 'ltr'"
          @click="onNavigate"
          @keydown.esc.prevent="close"
        >{{ labelFor(item) }}</a>
      </li>
    </ul>
  </nav>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ChevronDown, Globe } from 'lucide-vue-next'

const props = defineProps({
  // 'dropdown' | 'compact' | 'list'
  variant: { type: String, default: 'dropdown' },
  // 'native' | 'code' | 'both'
  labels: { type: String, default: 'native' },
  isEditor: { type: Boolean, default: false },
})

const open = ref(false)
const toggle = ref(null)
const list = ref(null)

const wpData = () =>
  (typeof window !== 'undefined' && (window.dsfFrontendData || window.dsfEditorData)) || {}

const ariaLabel = 'Language'

const allowedStyles = ['dropdown', 'compact', 'list']
const resolvedStyle = computed(() =>
  allowedStyles.includes(props.variant) ? props.variant : 'dropdown',
)

/**
 * Items always come from the server, which resolves reviewed, published
 * siblings only. In the editor there is no request context, so a small preview
 * set from the configured languages keeps the control visible while designing.
 */
const items = computed(() => {
  const data = wpData()
  const resolved = Array.isArray(data.languageSwitcher) ? data.languageSwitcher : []
  if (resolved.length) {
    return resolved
  }
  if (!props.isEditor) {
    return []
  }

  const configured = (data.language && Array.isArray(data.language.list) && data.language.list) || []
  return configured.map((language) => ({
    code: language.code,
    label: language.label,
    html_lang: language.code,
    direction: language.dir || 'ltr',
    short: String(language.code || '').split('-')[0].toUpperCase(),
    url: '#',
    current: language.code === (data.language && data.language.current),
  }))
})

const current = computed(() => items.value.find((item) => item.current) || items.value[0] || null)

const labelFor = (item) => {
  const short = item.short || String(item.code || '').split('-')[0].toUpperCase()
  if (resolvedStyle.value === 'compact' || props.labels === 'code') {
    return short
  }
  if (props.labels === 'both') {
    return `${item.label} (${short})`
  }
  return item.label
}

const currentText = computed(() => (current.value ? labelFor(current.value) : ''))

const toggleOpen = () => {
  open.value = !open.value
}

const close = () => {
  open.value = false
  if (toggle.value) {
    toggle.value.focus()
  }
}

const openAndFocusFirst = () => {
  open.value = true
  requestAnimationFrame(() => {
    const first = list.value && list.value.querySelector('a')
    if (first) {
      first.focus()
    }
  })
}

const onNavigate = (event) => {
  // In the editor the preview targets are placeholders, so navigation is inert.
  if (props.isEditor) {
    event.preventDefault()
  }
}

const onDocumentPointer = (event) => {
  if (!open.value) {
    return
  }
  const root = toggle.value && toggle.value.closest('.dsf-language-switcher')
  if (root && !root.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentPointer)
  document.addEventListener('touchstart', onDocumentPointer, { passive: true })
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentPointer)
  document.removeEventListener('touchstart', onDocumentPointer)
})
</script>

<style scoped>
.dsf-language-switcher {
  position: relative;
  display: inline-block;
}

.dsf-language-switcher__toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid var(--dsf-switcher-border, rgba(0, 0, 0, 0.15));
  border-radius: 999px;
  background: var(--dsf-switcher-bg, transparent);
  color: inherit;
  font: inherit;
  line-height: 1.2;
  cursor: pointer;
  /* Long native names must not stretch a header row. */
  max-width: 14rem;
}

.dsf-language-switcher__toggle-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-language-switcher__toggle:focus-visible,
.dsf-language-switcher__link:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

.dsf-language-switcher__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-language-switcher--dropdown .dsf-language-switcher__list,
.dsf-language-switcher--compact .dsf-language-switcher__list {
  position: absolute;
  z-index: 60;
  top: calc(100% + 6px);
  inset-inline-start: 0;
  min-width: 100%;
  max-height: 60vh;
  overflow-y: auto;
  padding: 4px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.14);
}

.dsf-language-switcher--list .dsf-language-switcher__list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.dsf-language-switcher__link,
.dsf-language-switcher__current {
  display: block;
  padding: 6px 10px;
  border-radius: 6px;
  color: inherit;
  text-decoration: none;
  white-space: nowrap;
}

.dsf-language-switcher--dropdown .dsf-language-switcher__link,
.dsf-language-switcher--compact .dsf-language-switcher__link {
  color: #111827;
}

.dsf-language-switcher__link:hover {
  background: rgba(0, 0, 0, 0.06);
}

.dsf-language-switcher__current {
  font-weight: 600;
  opacity: 0.75;
}

@media (max-width: 782px) {
  .dsf-language-switcher__toggle {
    /* Comfortable touch target on small screens. */
    min-height: 44px;
  }
}
</style>
