import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AnchorGalleryPreview from '../blocks/AnchorGalleryPreview.vue'

describe('AnchorGalleryPreview', () => {
  const items = [
    { title: 'Featured', image: 'https://cdn.example.test/featured.jpg', url: '/featured' },
    { title: 'Second', image: 'https://cdn.example.test/second.jpg', url: '/second' },
    { title: 'Third', image: 'https://cdn.example.test/third.jpg', url: '/third' },
    { title: 'Fourth', image: 'https://cdn.example.test/fourth.jpg', url: '/fourth' },
    { title: 'Fifth', image: 'https://cdn.example.test/fifth.jpg', url: '/fifth' },
    { title: 'Sixth', image: 'https://cdn.example.test/sixth.jpg', url: '/sixth' },
    { title: 'Seventh', image: 'https://cdn.example.test/seventh.jpg', url: '/seventh' },
    { title: 'Eighth', image: 'https://cdn.example.test/eighth.jpg', url: '/eighth' },
  ]

  it('renders the anchor layout with one feature tile and four supporting tiles', () => {
    const wrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'anchor', items } } })
    expect(wrapper.classes()).toContain('dsf-anchor-gallery--anchor')
    expect(wrapper.findAll('.dsf-anchor-gallery__tile')).toHaveLength(5)
    expect(wrapper.find('.dsf-anchor-gallery__tile').classes()).not.toContain('dsf-anchor-gallery__tile--overlay')
    expect(wrapper.find('.dsf-anchor-gallery__tile-title').exists()).toBe(true)
    expect(wrapper.find('.dsf-anchor-gallery__tile-title').text()).toBe('Featured')
  })

  it('supports grid layout and overlay titles', () => {
    const wrapper = mount(AnchorGalleryPreview, {
      props: { settings: { layout: 'grid', titlePosition: 'overlay', items } },
    })
    expect(wrapper.classes()).toContain('dsf-anchor-gallery--grid')
    expect(wrapper.findAll('.dsf-anchor-gallery__tile--overlay')).toHaveLength(8)
  })

  it('uses the shared split heading layout for the title and description', () => {
    const wrapper = mount(AnchorGalleryPreview, { props: { settings: { title: 'Collections', description: 'Browse our favorites.', items } } })
    expect(wrapper.find('.dsf-anchor-gallery__header-copy .dsf-anchor-gallery__title').exists()).toBe(true)
    expect(wrapper.find('.dsf-anchor-gallery__header > .dsf-anchor-gallery__description').exists()).toBe(true)
  })

  it('uses responsive grid patterns with balanced final rows', () => {
    for (const [count, columns] of [[1, 1], [2, 2], [3, 3], [4, 4], [5, 4], [6, 4], [8, 4]]) {
      const wrapper = mount(AnchorGalleryPreview, {
        props: { settings: { layout: 'grid', items: items.slice(0, count) } },
      })

      expect(wrapper.attributes('style')).toContain(`--dsf-anchor-gallery-columns: ${columns}`)
    }

    const sixTileWrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'grid', items: items.slice(0, 6) } } })
    expect(sixTileWrapper.classes()).toContain('dsf-anchor-gallery--count-6')
    const sevenTileWrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'grid', items: items.slice(0, 7) } } })
    expect(sevenTileWrapper.classes()).toContain('dsf-anchor-gallery--count-7')
  })

  it('limits anchor layout to five tiles while allowing eight in grid layout', () => {
    const anchorWrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'anchor', items } } })
    const gridWrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'grid', items } } })

    expect(anchorWrapper.findAll('.dsf-anchor-gallery__tile')).toHaveLength(5)
    expect(gridWrapper.findAll('.dsf-anchor-gallery__tile')).toHaveLength(8)
  })

  it('supports left, center, and right tile title alignment', () => {
    for (const alignment of ['left', 'center', 'right']) {
      const wrapper = mount(AnchorGalleryPreview, { props: { settings: { items, textAlign: alignment } } })
      expect(wrapper.classes()).toContain(`dsf-anchor-gallery--align-${alignment}`)
      expect(wrapper.find('.dsf-anchor-gallery__tile-title').attributes('style')).toContain(`text-align: ${alignment}`)
    }
  })

  it('keeps titles visible in anchor layout when positioned below the image', () => {
    const wrapper = mount(AnchorGalleryPreview, { props: { settings: { layout: 'anchor', titlePosition: 'below', items } } })
    expect(wrapper.classes()).toContain('dsf-anchor-gallery--titles-below')
    expect(wrapper.find('.dsf-anchor-gallery__tile-title').text()).toBe('Featured')
  })

  it('can hide the eyebrow while keeping it enabled by default', () => {
    const defaultWrapper = mount(AnchorGalleryPreview, { props: { settings: { eyebrow: 'Eyebrow', items } } })
    const hiddenWrapper = mount(AnchorGalleryPreview, { props: { settings: { showEyebrow: false, eyebrow: 'Eyebrow', items } } })

    expect(defaultWrapper.find('.dsf-anchor-gallery__eyebrow').exists()).toBe(true)
    expect(hiddenWrapper.find('.dsf-anchor-gallery__eyebrow').exists()).toBe(false)
  })

  it('makes every editable text field inline-editable in builder mode', () => {
    const wrapper = mount(AnchorGalleryPreview, {
      props: {
        isEditor: true,
        settings: {
          eyebrow: 'Eyebrow',
          title: 'Title',
          description: 'Description',
          items: [{ title: 'Tile title', image: 'https://cdn.example.test/image.jpg' }],
        },
      },
    })

    expect(wrapper.find('.dsf-anchor-gallery__eyebrow').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-anchor-gallery__title').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-anchor-gallery__description').attributes('contenteditable')).toBe('true')
    expect(wrapper.find('.dsf-anchor-gallery__tile-title').attributes('contenteditable')).toBe('true')
  })

  it('keeps the text presentation read-only outside the builder', () => {
    const wrapper = mount(AnchorGalleryPreview, {
      props: {
        settings: {
          eyebrow: 'Eyebrow',
          title: 'Title',
          description: 'Description',
          items: [{ title: 'Tile title', image: 'https://cdn.example.test/image.jpg' }],
        },
      },
    })

    expect(wrapper.find('[contenteditable="true"]').exists()).toBe(false)
  })

  it('does not navigate tile links in the editor', async () => {
    const wrapper = mount(AnchorGalleryPreview, { props: { isEditor: true, settings: { items } } })
    await wrapper.find('.dsf-anchor-gallery__tile').trigger('click')
    expect(wrapper.find('.dsf-anchor-gallery__tile').attributes('href')).toBe('/featured')
  })

  it('rejects dangerous tile URLs', () => {
    const wrapper = mount(AnchorGalleryPreview, {
      props: { settings: { items: [{ title: 'Unsafe', image: 'https://cdn.example.test/x.jpg', url: 'javascript:alert(1)' }] } },
    })
    expect(wrapper.find('.dsf-anchor-gallery__tile').attributes('href')).toBe('#')
  })
})
