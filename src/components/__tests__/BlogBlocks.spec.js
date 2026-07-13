import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import BlogHeaderPreview from '../blocks/BlogHeaderPreview.vue'
import PostLoopPreview from '../blocks/PostLoopPreview.vue'

const POST = (id, title, extra = {}) => ({
  id,
  title,
  url: `/post/${id}`,
  excerpt: `Excerpt for ${title}.`,
  date: 'July 10, 2026',
  dateIso: '2026-07-10T10:00:00+00:00',
  author: { name: 'Ada Writer', url: '/author/ada', avatarUrl: 'ava.png' },
  categories: [{ name: 'News', url: '/cat/news' }],
  image: `img-${id}.jpg`,
  imageAlt: '',
  readingTime: 4,
  ...extra,
})

const ARCHIVE = {
  title: 'Journal',
  descriptionHtml: '<p>Notes from the studio.</p>',
  total: 14,
  perPage: 9,
  currentPage: 1,
  totalPages: 2,
  posts: [POST(1, 'First Story'), POST(2, 'Second Story'), POST(3, 'Third Story'), 'bad', null],
  pagination: [
    { label: '1', url: '/blog/', current: true },
    { label: '2', url: '/blog/page/2/', current: false },
  ],
}

function mountBlock(Component, settings = {}, archive = ARCHIVE, isEditor = true) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 'b1', isEditor },
    global: { provide: { dsfBlogContext: ref(archive) } },
  })
}

describe('BlogHeaderPreview', () => {
  it('renders archive title, post count, and description', () => {
    const w = mountBlock(BlogHeaderPreview)
    expect(w.find('.dsf-blog-header__title').text()).toBe('Journal')
    expect(w.find('.dsf-blog-header__count').text()).toContain('14 articles')
    expect(w.find('.dsf-blog-header__description').html()).toContain('Notes from the studio.')
  })

  it('hides sections per toggles and falls back to the placeholder', () => {
    const w = mountBlock(BlogHeaderPreview, { showCount: false, showDescription: false }, null)
    expect(w.find('.dsf-blog-header__title').text()).toBe('Blog')
    expect(w.find('.dsf-blog-header__count').exists()).toBe(false)
    expect(w.find('.dsf-blog-header__description').exists()).toBe(false)
  })
})

describe('PostLoopPreview', () => {
  it('features the first post as a hero and grids the rest', () => {
    const w = mountBlock(PostLoopPreview)
    expect(w.find('.dsf-post-loop__hero-title').text()).toBe('First Story')
    const cards = w.findAll('.dsf-post-loop__card')
    expect(cards).toHaveLength(2)
    expect(cards[0].find('.dsf-post-loop__title').text()).toBe('Second Story')
  })

  it('shows all posts as cards when the hero is disabled', () => {
    const w = mountBlock(PostLoopPreview, { featuredFirst: false })
    expect(w.find('.dsf-post-loop__hero').exists()).toBe(false)
    expect(w.findAll('.dsf-post-loop__card')).toHaveLength(3)
  })

  it('renders meta: author, date with datetime, reading time, and category chips', () => {
    const w = mountBlock(PostLoopPreview)
    const hero = w.find('.dsf-post-loop__hero')
    expect(hero.find('.dsf-post-loop__author').text()).toBe('Ada Writer')
    expect(hero.find('time').attributes('datetime')).toBe('2026-07-10T10:00:00+00:00')
    expect(hero.text()).toContain('4 min read')
    expect(hero.find('.dsf-post-loop__chip').text()).toBe('News')
    expect(hero.find('.dsf-post-loop__more').text()).toContain('Read article')
  })

  it('hides meta per toggles', () => {
    const w = mountBlock(PostLoopPreview, {
      showAuthor: false,
      showDate: false,
      showReadingTime: false,
      showCategories: false,
      showExcerpt: false,
    })
    expect(w.find('.dsf-post-loop__author').exists()).toBe(false)
    expect(w.find('time').exists()).toBe(false)
    expect(w.find('.dsf-post-loop__chip').exists()).toBe(false)
    expect(w.find('.dsf-post-loop__excerpt').exists()).toBe(false)
  })

  it('applies the list layout and clamps grid columns on tablet', () => {
    const list = mountBlock(PostLoopPreview, { layout: 'list' })
    expect(list.find('.dsf-post-loop--list').exists()).toBe(true)

    const tablet = mount(PostLoopPreview, {
      props: { settings: { columns: 4 }, previewMode: 'tablet', blockId: 'b1', isEditor: true },
      global: { provide: { dsfBlogContext: ref(ARCHIVE) } },
    })
    expect(tablet.find('.dsf-post-loop__items').attributes('style')).toContain('repeat(2')
  })

  it('renders pagination with the current page marked', () => {
    const w = mountBlock(PostLoopPreview)
    expect(w.find('.dsf-post-loop__page.is-current').text()).toBe('1')
    expect(w.findAll('a.dsf-post-loop__page')[0].attributes('href')).toBe('/blog/page/2/')
  })

  it('shows editor ghosts when empty and a frontend empty message', () => {
    const editor = mountBlock(PostLoopPreview, {}, { ...ARCHIVE, posts: [], pagination: [] })
    expect(editor.findAll('.dsf-post-loop__ghost')).toHaveLength(3)
    expect(editor.find('.dsf-post-loop__note').text()).toContain('preview category')

    const front = mountBlock(PostLoopPreview, {}, { ...ARCHIVE, posts: [] }, false)
    expect(front.find('.dsf-post-loop__note').text()).toContain('No posts found')
  })

  it('survives a missing context via the placeholder', () => {
    const w = mountBlock(PostLoopPreview, {}, null, false)
    expect(w.find('.dsf-post-loop').exists()).toBe(true)
  })
})
