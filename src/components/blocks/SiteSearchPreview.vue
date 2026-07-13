<template>
  <section class="dsf-site-search" :style="blockStyle">
    <div class="dsf-site-search__inner" :style="innerStyle">
      <h1 v-if="settings.headingText" class="dsf-site-search__heading">{{ settings.headingText }}</h1>

      <form
        class="dsf-site-search__form"
        method="get"
        :action="search.action || undefined"
        role="search"
        @submit="isEditor && $event.preventDefault()"
      >
        <input v-if="pageId" type="hidden" name="page_id" :value="pageId" />
        <svg class="dsf-site-search__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input
          class="dsf-site-search__input"
          type="search"
          name="s"
          :placeholder="settings.placeholder || 'What are you looking for?'"
          :value="search.query"
          aria-label="Search"
          required
        />
        <button type="submit" class="dsf-site-search__submit">Search</button>
      </form>

      <template v-if="results.length">
        <p class="dsf-site-search__count">
          {{ search.total }} {{ search.total === 1 ? 'result' : 'results' }} for “{{ search.query }}”
        </p>
        <ol class="dsf-site-search__results">
          <li v-for="result in results" :key="result.id" class="dsf-site-search__result">
            <a
              :href="result.url || '#'"
              class="dsf-site-search__result-link"
              @click="isEditor && $event.preventDefault()"
            >
              <span v-if="settings.showImages !== false && result.image" class="dsf-site-search__thumb">
                <img :src="result.image" alt="" loading="lazy" decoding="async" />
              </span>
              <span class="dsf-site-search__result-body">
                <span class="dsf-site-search__result-top">
                  <span class="dsf-site-search__result-title">{{ result.title }}</span>
                  <span v-if="settings.showTypeBadges !== false" class="dsf-site-search__badge">{{ result.type }}</span>
                </span>
                <span v-if="result.excerpt" class="dsf-site-search__excerpt">{{ result.excerpt }}</span>
              </span>
            </a>
          </li>
        </ol>

        <nav v-if="pagination.length" class="dsf-site-search__pagination" aria-label="Search results pagination">
          <template v-for="(link, i) in pagination" :key="i">
            <span v-if="link.current" class="dsf-site-search__page is-current" aria-current="page">{{ link.label }}</span>
            <a v-else :href="link.url || '#'" class="dsf-site-search__page" @click="isEditor && $event.preventDefault()">{{ link.label }}</a>
          </template>
        </nav>
      </template>

      <p v-else-if="search.query && !isEditor" class="dsf-site-search__empty">
        Nothing found for “{{ search.query }}”. Try a different search.
      </p>
      <p v-else-if="isEditor" class="dsf-site-search__empty">
        Visitors' search results render here — pages, posts, and products.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useSiteContext } from '../../utils/useSiteContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { site } = useSiteContext()

const EMPTY_SEARCH = { query: '', action: '', results: [], total: 0, totalPages: 0, pagination: [] }

const search = computed(() => {
  const raw = site.value?.search
  return raw && typeof raw === 'object' ? { ...EMPTY_SEARCH, ...raw } : EMPTY_SEARCH
})

const pageId = computed(() => Number(site.value?.pageId) || 0)

const results = computed(() => {
  const raw = Array.isArray(search.value.results) ? search.value.results : []
  return raw.filter((r) => r && typeof r === 'object' && typeof r.title === 'string')
})

const pagination = computed(() => {
  const raw = Array.isArray(search.value.pagination) ? search.value.pagination : []
  return raw.filter((l) => l && typeof l === 'object')
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 32
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-search-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 760}px` }))
</script>

<style scoped>
.dsf-site-search {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-site-search__inner {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  margin: 0 auto;
}

.dsf-site-search__heading {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.7rem, 3.2vw, 2.4rem);
  font-weight: 800;
  letter-spacing: -0.02em;
}

.dsf-site-search__form {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.dsf-site-search__icon {
  position: absolute;
  left: 1rem;
  opacity: 0.45;
  pointer-events: none;
}

.dsf-site-search__input {
  flex: 1;
  min-width: 0;
  padding: 0.85rem 1rem 0.85rem 2.6rem;
  border: 1px solid rgba(0, 0, 0, 0.14);
  border-radius: 999px;
  font: inherit;
  font-size: 1rem;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.dsf-site-search__input:focus {
  outline: none;
  border-color: var(--dsf-search-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dsf-search-accent) 18%, transparent);
}

.dsf-site-search__submit {
  padding: 0.85rem 1.6rem;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-search-accent);
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.dsf-site-search__submit:hover {
  opacity: 0.92;
}

.dsf-site-search__count {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  opacity: 0.65;
}

.dsf-site-search__results {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-site-search__result-link {
  display: flex;
  gap: 0.9rem;
  padding: 0.9rem 1rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.dsf-site-search__result-link:hover {
  border-color: var(--dsf-search-accent);
  box-shadow: 0 8px 24px -18px rgba(0, 0, 0, 0.4);
}

.dsf-site-search__thumb {
  flex-shrink: 0;
  width: 56px;
  height: 56px;
  border-radius: 12px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-site-search__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dsf-site-search__result-body {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.dsf-site-search__result-top {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.dsf-site-search__result-title {
  font-weight: 700;
}

.dsf-site-search__badge {
  padding: 0.12rem 0.55rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--dsf-search-accent) 12%, transparent);
  color: var(--dsf-search-accent);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.dsf-site-search__excerpt {
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  line-height: 1.55;
  opacity: 0.75;
}

.dsf-site-search__pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.dsf-site-search__page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 0.55rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.85rem);
  font-weight: 600;
}

.dsf-site-search__page.is-current {
  background: var(--dsf-search-accent);
  border-color: var(--dsf-search-accent);
  color: #fff;
}

.dsf-site-search__empty {
  margin: 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
}
</style>
