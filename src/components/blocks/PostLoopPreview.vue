<template>
  <section
    class="dsf-post-loop"
    :class="`dsf-post-loop--${layout}`"
    :style="blockStyle"
  >
    <div class="dsf-post-loop__inner" :style="innerStyle">
      <template v-if="cards.length">
        <!-- Featured hero card (first post) -->
        <article v-if="featured" class="dsf-post-loop__hero" :style="cardBg">
          <a
            :href="featured.url || '#'"
            class="dsf-post-loop__hero-media"
            tabindex="-1"
            aria-hidden="true"
            @click="isEditor && $event.preventDefault()"
          >
            <img v-if="settings.showImage !== false && featured.image" :src="featured.image" :alt="''" fetchpriority="high" decoding="async" />
            <span v-else class="dsf-post-loop__media-empty"></span>
          </a>
          <div class="dsf-post-loop__hero-body">
            <span v-if="settings.showCategories !== false && featured.categories?.length" class="dsf-post-loop__chips">
              <a
                v-for="(cat, i) in featured.categories"
                :key="i"
                :href="cat.url || '#'"
                class="dsf-post-loop__chip"
                @click="isEditor && $event.preventDefault()"
              >{{ cat.name }}</a>
            </span>
            <h2 class="dsf-post-loop__hero-title">
              <a :href="featured.url || '#'" @click="isEditor && $event.preventDefault()">{{ featured.title }}</a>
            </h2>
            <p v-if="settings.showExcerpt !== false && featured.excerpt" class="dsf-post-loop__excerpt">{{ featured.excerpt }}</p>
            <div class="dsf-post-loop__meta">
              <template v-if="settings.showAuthor !== false && featured.author?.name">
                <img v-if="featured.author.avatarUrl" class="dsf-post-loop__avatar" :src="featured.author.avatarUrl" alt="" />
                <a
                  class="dsf-post-loop__author"
                  :href="featured.author.url || '#'"
                  @click="isEditor && $event.preventDefault()"
                >{{ featured.author.name }}</a>
              </template>
              <time v-if="settings.showDate !== false && featured.date" :datetime="featured.dateIso || undefined">{{ featured.date }}</time>
              <span v-if="settings.showReadingTime !== false && featured.readingTime">{{ featured.readingTime }} min read</span>
            </div>
            <a
              class="dsf-post-loop__more"
              :href="featured.url || '#'"
              @click="isEditor && $event.preventDefault()"
            >{{ settings.readMoreText || 'Read article' }} →</a>
          </div>
        </article>

        <!-- Remaining posts: grid or editorial list -->
        <div class="dsf-post-loop__items" :style="layout === 'grid' ? gridStyle : undefined">
          <article v-for="card in rest" :key="card.id" class="dsf-post-loop__card" :style="cardBg">
            <a
              :href="card.url || '#'"
              class="dsf-post-loop__media"
              tabindex="-1"
              aria-hidden="true"
              @click="isEditor && $event.preventDefault()"
            >
              <img v-if="settings.showImage !== false && card.image" :src="card.image" :alt="''" loading="lazy" decoding="async" />
              <span v-else class="dsf-post-loop__media-empty"></span>
            </a>
            <div class="dsf-post-loop__body">
              <span v-if="settings.showCategories !== false && card.categories?.length" class="dsf-post-loop__chips">
                <a
                  v-for="(cat, i) in card.categories.slice(0, 2)"
                  :key="i"
                  :href="cat.url || '#'"
                  class="dsf-post-loop__chip"
                  @click="isEditor && $event.preventDefault()"
                >{{ cat.name }}</a>
              </span>
              <h3 class="dsf-post-loop__title">
                <a :href="card.url || '#'" @click="isEditor && $event.preventDefault()">{{ card.title }}</a>
              </h3>
              <p v-if="settings.showExcerpt !== false && card.excerpt" class="dsf-post-loop__excerpt">{{ card.excerpt }}</p>
              <div class="dsf-post-loop__meta">
                <template v-if="settings.showAuthor !== false && card.author?.name">
                  <img v-if="card.author.avatarUrl" class="dsf-post-loop__avatar" :src="card.author.avatarUrl" alt="" />
                  <a
                    class="dsf-post-loop__author"
                    :href="card.author.url || '#'"
                    @click="isEditor && $event.preventDefault()"
                  >{{ card.author.name }}</a>
                </template>
                <time v-if="settings.showDate !== false && card.date" :datetime="card.dateIso || undefined">{{ card.date }}</time>
                <span v-if="settings.showReadingTime !== false && card.readingTime">{{ card.readingTime }} min</span>
              </div>
            </div>
          </article>
        </div>
      </template>

      <div v-else class="dsf-post-loop__empty">
        <template v-if="isEditor">
          <div class="dsf-post-loop__ghosts" :style="gridStyle" aria-hidden="true">
            <span v-for="i in 3" :key="i" class="dsf-post-loop__ghost"></span>
          </div>
          <p class="dsf-post-loop__note">
            The archive's posts render here. Pick a preview category in Page Settings → Blog.
          </p>
        </template>
        <p v-else class="dsf-post-loop__note">No posts found.</p>
      </div>

      <nav
        v-if="settings.showPagination !== false && pagination.length"
        class="dsf-post-loop__pagination"
        aria-label="Posts pagination"
      >
        <template v-for="(link, i) in pagination" :key="i">
          <span v-if="link.current" class="dsf-post-loop__page is-current" aria-current="page">{{ link.label }}</span>
          <a v-else :href="link.url || '#'" class="dsf-post-loop__page" @click="isEditor && $event.preventDefault()">{{ link.label }}</a>
        </template>
      </nav>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useBlogContext } from '../../utils/useBlogContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { archive } = useBlogContext()

const layout = computed(() => (props.settings?.layout === 'list' ? 'list' : 'grid'))

const cards = computed(() => {
  const raw = Array.isArray(archive.value?.posts) ? archive.value.posts : []
  return raw.filter((c) => c && typeof c === 'object' && typeof c.title === 'string')
})

const featured = computed(() => (props.settings?.featuredFirst !== false && cards.value.length ? cards.value[0] : null))
const rest = computed(() => (featured.value ? cards.value.slice(1) : cards.value))

const pagination = computed(() => {
  const raw = Array.isArray(archive.value?.pagination) ? archive.value.pagination : []
  return raw.filter((l) => l && typeof l === 'object')
})

const gridStyle = computed(() => {
  const desktopCols = Math.max(1, Math.min(4, Number(props.settings?.columns) || 3))
  const cols = props.previewMode === 'mobile' ? 1 : props.previewMode === 'tablet' ? Math.min(2, desktopCols) : desktopCols
  return { gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 24
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-loop-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1200}px` }))

const cardBg = computed(() =>
  props.settings?.cardBackground ? { backgroundColor: props.settings.cardBackground } : undefined
)
</script>

<style scoped>
.dsf-post-loop {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-post-loop__inner {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  margin: 0 auto;
}

/* ---- Featured hero card ---- */
.dsf-post-loop__hero {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: clamp(1.25rem, 3vw, 2.5rem);
  align-items: center;
  border-radius: 24px;
  overflow: hidden;
}

.dsf-post-loop__hero-media {
  display: block;
  aspect-ratio: 16 / 10;
  border-radius: 20px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-post-loop__hero-media img,
.dsf-post-loop__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s ease;
}

.dsf-post-loop__hero:hover .dsf-post-loop__hero-media img,
.dsf-post-loop__card:hover .dsf-post-loop__media img {
  transform: scale(1.035);
}

.dsf-post-loop__media-empty {
  display: block;
  width: 100%;
  height: 100%;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-post-loop__hero-body {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  min-width: 0;
}

.dsf-post-loop__hero-title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.5rem, 3vw, 2.2rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.02em;
}

.dsf-post-loop__hero-title a,
.dsf-post-loop__title a {
  color: inherit;
  text-decoration: none;
}

.dsf-post-loop__hero-title a:hover,
.dsf-post-loop__title a:hover {
  color: var(--dsf-loop-accent);
}

/* ---- Cards ---- */
.dsf-post-loop--grid .dsf-post-loop__items {
  display: grid;
  gap: 26px 20px;
}

.dsf-post-loop--list .dsf-post-loop__items {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.dsf-post-loop__card {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  border-radius: 18px;
  min-width: 0;
}

.dsf-post-loop--list .dsf-post-loop__card {
  flex-direction: row;
  gap: 1.25rem;
  align-items: center;
}

.dsf-post-loop__media {
  display: block;
  aspect-ratio: 16 / 10;
  border-radius: 16px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-post-loop--list .dsf-post-loop__media {
  flex: 0 0 220px;
  aspect-ratio: 4 / 3;
}

.dsf-post-loop__body {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  min-width: 0;
}

.dsf-post-loop__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

.dsf-post-loop__excerpt {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  line-height: 1.6;
  opacity: 0.75;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ---- Chips + meta ---- */
.dsf-post-loop__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.dsf-post-loop__chip {
  padding: 0.18rem 0.65rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--dsf-loop-accent) 10%, transparent);
  color: var(--dsf-loop-accent);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  text-decoration: none;
}

.dsf-post-loop__chip:hover {
  background: color-mix(in srgb, var(--dsf-loop-accent) 18%, transparent);
}

.dsf-post-loop__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  font-size: 0.8rem;
  opacity: 0.75;
}

.dsf-post-loop__meta time::before,
.dsf-post-loop__meta > span::before {
  content: '·';
  margin-right: 0.75rem;
  opacity: 0.6;
}

.dsf-post-loop__meta > :first-child::before,
.dsf-post-loop__meta > img + a::before {
  content: none;
}

.dsf-post-loop__avatar {
  width: 22px;
  height: 22px;
  border-radius: 999px;
}

.dsf-post-loop__author {
  color: inherit;
  font-weight: 600;
  text-decoration: none;
}

.dsf-post-loop__author:hover {
  color: var(--dsf-loop-accent);
}

.dsf-post-loop__more {
  align-self: flex-start;
  margin-top: 0.2rem;
  color: var(--dsf-loop-accent);
  font-weight: 700;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  text-decoration: none;
}

.dsf-post-loop__more:hover {
  text-decoration: underline;
}

/* ---- Empty / ghosts ---- */
.dsf-post-loop__ghosts {
  display: grid;
  gap: 20px;
}

.dsf-post-loop__ghost {
  aspect-ratio: 16 / 10;
  border-radius: 16px;
  background: var(--dsf-gray-100, #f3f4f6);
}

.dsf-post-loop__note {
  margin: 0.5rem 0 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

/* ---- Pagination ---- */
.dsf-post-loop__pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
}

.dsf-post-loop__page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 38px;
  height: 38px;
  padding: 0 0.6rem;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 999px;
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.dsf-post-loop__page:hover {
  border-color: var(--dsf-loop-accent);
  color: var(--dsf-loop-accent);
}

.dsf-post-loop__page.is-current {
  background: var(--dsf-loop-accent);
  border-color: var(--dsf-loop-accent);
  color: #fff;
}

@media (max-width: 860px) {
  .dsf-post-loop__hero {
    grid-template-columns: 1fr;
  }

  .dsf-post-loop--list .dsf-post-loop__card {
    flex-direction: column;
    align-items: stretch;
  }

  .dsf-post-loop--list .dsf-post-loop__media {
    flex: none;
  }
}
</style>
