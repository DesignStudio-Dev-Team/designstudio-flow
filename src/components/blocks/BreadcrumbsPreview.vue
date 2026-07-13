<template>
  <nav v-if="trail.length" class="dsf-breadcrumbs" :style="wrapperStyle" aria-label="Breadcrumb">
    <ol class="dsf-breadcrumbs__list" :style="listStyle">
      <li
        v-for="(crumb, index) in trail"
        :key="index"
        class="dsf-breadcrumbs__item"
      >
        <a
          v-if="isLink(crumb, index)"
          class="dsf-breadcrumbs__link"
          :href="crumb.url"
          :style="{ color: linkColor }"
        >{{ crumb.name }}</a>
        <span
          v-else
          class="dsf-breadcrumbs__current"
          :aria-current="showsCurrent && index === trail.length - 1 ? 'page' : undefined"
        >{{ crumb.name }}</span>
        <span
          v-if="index < trail.length - 1"
          class="dsf-breadcrumbs__sep"
          aria-hidden="true"
        >{{ separatorChar }}</span>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed, inject } from 'vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
})

// The real trail is provided by the frontend app from server data. In the editor
// canvas there is no page context, so a representative sample is shown so the
// author can style the block; nothing renders pre-hydration on the live page.
const injectedTrail = inject('dsfBreadcrumbs', null)

const SAMPLE = [
  { name: 'Home', url: '#' },
  { name: 'Section', url: '#' },
  { name: 'Current Page', url: '' },
]

const trail = computed(() => {
  const source = Array.isArray(injectedTrail?.value)
    ? injectedTrail.value
    : (Array.isArray(injectedTrail) ? injectedTrail : null)
  let items = source && source.length ? source : (props.isEditor ? SAMPLE : [])
  items = items
    .filter((c) => c && typeof c.name === 'string' && c.name !== '')
    .map((c) => ({ name: c.name, url: typeof c.url === 'string' ? c.url : '' }))
  if (props.settings.showCurrent === false && items.length > 1) {
    items = items.slice(0, -1)
  }
  return items
})

// The current page (last item) is shown as plain text; everything else that has
// a URL is a link. With "show current" off, the last remaining item is an
// ancestor and stays a link.
const showsCurrent = computed(() => props.settings.showCurrent !== false)
function isLink(crumb, index) {
  const isCurrent = showsCurrent.value && index === trail.value.length - 1
  return !!crumb.url && !isCurrent
}

const separatorChar = computed(() => {
  const map = { chevron: '›', slash: '/', dot: '·', arrow: '→' }
  return map[props.settings.separator] || '›'
})

const linkColor = computed(() => props.settings.linkColor || '#111827')

const wrapperStyle = computed(() => ({
  paddingTop: `${props.settings.paddingY ?? 16}px`,
  paddingBottom: `${props.settings.paddingY ?? 16}px`,
  paddingLeft: `${props.settings.paddingX ?? 24}px`,
  paddingRight: `${props.settings.paddingX ?? 24}px`,
  color: props.settings.textColor || '#6B7280',
  fontSize: `${props.settings.fontSize ?? 14}px`,
}))

const listStyle = computed(() => {
  const align = props.settings.align || 'left'
  return {
    maxWidth: `${props.settings.maxWidth ?? 1100}px`,
    justifyContent: align === 'center' ? 'center' : (align === 'right' ? 'flex-end' : 'flex-start'),
  }
})
</script>

<style scoped>
.dsf-breadcrumbs {
  width: 100%;
}

.dsf-breadcrumbs__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0 auto;
  padding: 0;
  list-style: none;
  line-height: 1.4;
}

.dsf-breadcrumbs__item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.dsf-breadcrumbs__link {
  color: inherit;
  text-decoration: none;
  font-weight: 500;
}

.dsf-breadcrumbs__link:hover {
  text-decoration: underline;
}

.dsf-breadcrumbs__current {
  opacity: 0.85;
}

.dsf-breadcrumbs__sep {
  opacity: 0.6;
}
</style>
