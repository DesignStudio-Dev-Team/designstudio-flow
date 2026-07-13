import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PageSettingsModal from '../PageSettingsModal.vue'

function mountModal(props = {}) {
  return mount(PageSettingsModal, {
    props: {
      visible: true,
      title: 'Alpine Boots',
      slug: 'alpine-boots',
      status: 'publish',
      parentId: 0,
      parentPages: [],
      popup: {},
      popupId: 0,
      popups: [],
      supportsSeo: true,
      seo: { title: '', description: '', socialImage: '', canonical: '', noindex: false },
      siteName: 'Trail Co',
      pageUrl: 'https://trail.co/alpine-boots/',
      ...props,
    },
    global: { stubs: { Teleport: true, Transition: false } },
  })
}

async function openSeoTab(w) {
  const tab = w.findAll('[role="tab"]').find((b) => b.text() === 'SEO')
  await tab.trigger('click')
}

describe('PageSettingsModal SEO tab', () => {
  it('shows the SEO tab only when supported', () => {
    const on = mountModal()
    expect(on.findAll('[role="tab"]').some((b) => b.text() === 'SEO')).toBe(true)

    const off = mountModal({ supportsSeo: false })
    expect(off.findAll('[role="tab"]').some((b) => b.text() === 'SEO')).toBe(false)
  })

  it('renders the snippet preview with resolved variables', async () => {
    const w = mountModal({ seo: { title: '{title} {sep} {site_name}', description: 'Buy {title} today.' } })
    await openSeoTab(w)
    expect(w.find('.dsf-seo-snippet__title').text()).toBe('Alpine Boots – Trail Co')
    expect(w.find('.dsf-seo-snippet__desc').text()).toBe('Buy Alpine Boots today.')
    expect(w.find('.dsf-seo-snippet__url').text()).toBe('trail.co/alpine-boots')
  })

  it('falls back to the page title and a hint when fields are empty', async () => {
    const w = mountModal()
    await openSeoTab(w)
    expect(w.find('.dsf-seo-snippet__title').text()).toBe('Alpine Boots')
    expect(w.find('.dsf-seo-snippet__desc').text()).toContain('Add a meta description')
  })

  it('shows the pixel-width meter and flags an over-long description', async () => {
    const w = mountModal()
    await openSeoTab(w)
    // jsdom canvas has no real text metrics, so both meters read 0px and stay
    // in-range; assert the meter renders with the px unit.
    expect(w.findAll('.dsf-seo-meter').length).toBe(2)
    expect(w.find('.dsf-seo-meter__label').text()).toContain('px')
    expect(w.find('.dsf-seo-count--over').exists()).toBe(false)
  })

  it('emits the seo payload on save', async () => {
    const w = mountModal()
    await openSeoTab(w)
    await w.find('#dsf-seo-title').setValue('{title} {sep} {site_name}')
    await w.find('#dsf-seo-description').setValue('Great boots.')
    await w.find('#dsf-seo-canonical').setValue('https://trail.co/boots/')
    await w.find('.dsf-pt-toggle input[type="checkbox"]').setValue(true)
    await w.find('form').trigger('submit')

    const payload = w.emitted('save')[0][0]
    expect(payload.seo).toEqual({
      title: '{title} {sep} {site_name}',
      description: 'Great boots.',
      socialImage: '',
      canonical: 'https://trail.co/boots/',
      noindex: true,
      nofollow: false,
    })
  })

  it('hydrates existing seo settings when opened', async () => {
    const w = mountModal({
      seo: { title: 'Saved title', description: 'Saved desc', socialImage: 'https://x/img.jpg', canonical: '', noindex: true },
    })
    await openSeoTab(w)
    expect(w.find('#dsf-seo-title').element.value).toBe('Saved title')
    expect(w.find('#dsf-seo-image').element.value).toBe('https://x/img.jpg')
    expect(w.find('.dsf-pt-toggle input[type="checkbox"]').element.checked).toBe(true)
  })
})
