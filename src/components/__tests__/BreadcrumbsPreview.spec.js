import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BreadcrumbsPreview from '../blocks/BreadcrumbsPreview.vue'

const trail = [
  { name: 'Home', url: 'https://x.test/' },
  { name: 'Shop', url: 'https://x.test/shop/' },
  { name: 'Widget', url: 'https://x.test/product/widget/' },
]

function mountWith(settings = {}, provideTrail = trail, isEditor = false) {
  return mount(BreadcrumbsPreview, {
    props: { settings, isEditor },
    global: { provide: { dsfBreadcrumbs: provideTrail } },
  })
}

describe('BreadcrumbsPreview', () => {
  it('renders the injected trail: links for ancestors, current as text', () => {
    const w = mountWith()
    expect(w.findAll('.dsf-breadcrumbs__link').map((l) => l.text())).toEqual(['Home', 'Shop'])
    const current = w.find('.dsf-breadcrumbs__current')
    expect(current.text()).toBe('Widget')
    expect(current.attributes('aria-current')).toBe('page')
    expect(w.findAll('.dsf-breadcrumbs__sep').length).toBe(2)
  })

  it('separator setting maps to the right glyph', () => {
    expect(mountWith({ separator: 'slash' }).find('.dsf-breadcrumbs__sep').text()).toBe('/')
    expect(mountWith({ separator: 'arrow' }).find('.dsf-breadcrumbs__sep').text()).toBe('→')
    expect(mountWith({}).find('.dsf-breadcrumbs__sep').text()).toBe('›')
  })

  it('hides the current page when showCurrent is false', () => {
    const w = mountWith({ showCurrent: false })
    expect(w.find('.dsf-breadcrumbs__current').exists()).toBe(false)
    expect(w.findAll('.dsf-breadcrumbs__link').map((l) => l.text())).toEqual(['Home', 'Shop'])
  })

  it('renders nothing on the frontend when no trail is available', () => {
    const w = mountWith({}, [], false)
    expect(w.find('nav').exists()).toBe(false)
  })

  it('shows a sample trail in the editor so the design is visible', () => {
    const w = mountWith({}, [], true)
    expect(w.findAll('.dsf-breadcrumbs__item').length).toBe(3)
    expect(w.find('.dsf-breadcrumbs__current').text()).toBe('Current Page')
  })
})
