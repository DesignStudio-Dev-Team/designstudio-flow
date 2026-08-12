import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import EditorLanguageMenu from '../EditorLanguageMenu.vue'

const basePayload = {
  active: true,
  ready: true,
  current: 'en-US',
  isMain: true,
  canClone: true,
  nonce: 'test-nonce',
  languages: [
    { code: 'en-US', label: 'English (United States)', isMain: true, isCurrent: true, postId: 10, state: 'published', editUrl: '/edit/10' },
    { code: 'es-MX', label: 'Español (México)', isMain: false, isCurrent: false, postId: 11, state: 'draft', editUrl: '/edit/11' },
    { code: 'fr', label: 'Français', isMain: false, isCurrent: false, postId: 0, state: 'missing', editUrl: '' },
  ],
}

function setEditorData(translation = basePayload, extra = {}) {
  window.dsfEditorData = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    postId: 10,
    translation,
    ...extra,
  }
}

async function openMenu(wrapper) {
  await wrapper.find('button.dsf-dock__btn').trigger('click')
  await nextTick()
}

describe('EditorLanguageMenu', () => {
  beforeEach(() => {
    setEditorData()
  })

  afterEach(() => {
    delete window.dsfEditorData
    vi.restoreAllMocks()
  })

  it('renders nothing when multilingual mode is off', () => {
    setEditorData({ active: false, languages: [] })
    expect(mount(EditorLanguageMenu).find('button').exists()).toBe(false)
  })

  it('shows every language with its state', async () => {
    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)

    const rows = wrapper.findAll('.dsf-lang-menu__row')
    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('Editing')
    expect(rows[0].text()).toContain('main')
    expect(rows[1].text()).toContain('Draft')
    expect(rows[2].text()).toContain('Missing')
  })

  it('links to an existing translation and offers a draft for a missing one', async () => {
    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)

    expect(wrapper.find('a.dsf-lang-menu__action').attributes('href')).toBe('/edit/11')
    expect(wrapper.findAll('button.dsf-lang-menu__action')).toHaveLength(1)
  })

  it('never offers to create a draft from a translation', async () => {
    setEditorData({
      ...basePayload,
      isMain: false,
      canClone: false,
      current: 'es-MX',
      notice: 'Translations are created from the main language.',
    })
    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)

    expect(wrapper.findAll('button.dsf-lang-menu__action')).toHaveLength(0)
    expect(wrapper.text()).toContain('Translations are created from the main language.')
  })

  it('still lists the configured languages before the page has been saved', async () => {
    setEditorData({
      active: true,
      current: 'en-US',
      isMain: true,
      canClone: false,
      notice: 'Save this page once to start translating it.',
      nonce: 'n',
      languages: [
        { code: 'en-US', label: 'English (United States)', isMain: true, isCurrent: true, postId: 0, state: 'draft', editUrl: '' },
        { code: 'es-MX', label: 'Español (México)', isMain: false, isCurrent: false, postId: 0, state: 'missing', editUrl: '' },
      ],
    })

    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)

    expect(wrapper.findAll('.dsf-lang-menu__row')).toHaveLength(2)
    expect(wrapper.text()).toContain('Save this page once to start translating it.')
    // Nothing to clone from yet, so no action is offered.
    expect(wrapper.findAll('button.dsf-lang-menu__action')).toHaveLength(0)
  })

  it('stays visible and explains itself while setup is paused', async () => {
    setEditorData({
      ...basePayload,
      ready: false,
      canClone: false,
      notice: 'Language setup stopped before it finished. Open Settings → Languages and save to resume it.',
    })

    const wrapper = mount(EditorLanguageMenu)
    expect(wrapper.find('button.dsf-dock__btn').exists()).toBe(true)

    await openMenu(wrapper)
    expect(wrapper.find('.dsf-lang-menu__paused').exists()).toBe(true)
    expect(wrapper.text()).toContain('Open Settings')
    expect(wrapper.findAll('button.dsf-lang-menu__action')).toHaveLength(0)
  })

  it('asks the server to create the draft and follows it', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      json: async () => ({ success: true, data: { edit_link: '/edit/12' } }),
    })
    vi.stubGlobal('fetch', fetchMock)
    delete window.location
    window.location = { href: '' }

    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)
    await wrapper.find('button.dsf-lang-menu__action').trigger('click')
    await nextTick()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const body = fetchMock.mock.calls[0][1].body
    expect(body.get('action')).toBe('dsf_clone_translation')
    expect(body.get('nonce')).toBe('test-nonce')
    expect(body.get('source_id')).toBe('10')
    expect(body.get('language')).toBe('fr')
    expect(window.location.href).toBe('/edit/12')
  })

  it('shows the server refusal instead of guessing', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        json: async () => ({ success: false, data: { message: 'Translations can only be created from the main language.' } }),
      }),
    )

    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)
    await wrapper.find('button.dsf-lang-menu__action').trigger('click')
    await nextTick()
    await nextTick()

    expect(wrapper.find('.dsf-lang-menu__error').text()).toBe(
      'Translations can only be created from the main language.',
    )
  })

  it('reports a transport failure without leaving the button stuck', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')))

    const wrapper = mount(EditorLanguageMenu)
    await openMenu(wrapper)
    await wrapper.find('button.dsf-lang-menu__action').trigger('click')
    await nextTick()
    await nextTick()

    expect(wrapper.find('.dsf-lang-menu__error').exists()).toBe(true)
    expect(wrapper.find('button.dsf-lang-menu__action').attributes('disabled')).toBeUndefined()
  })

  it('closes on Escape', async () => {
    const wrapper = mount(EditorLanguageMenu, { attachTo: document.body })
    await openMenu(wrapper)
    expect(wrapper.find('.dsf-lang-menu__panel').exists()).toBe(true)

    await wrapper.find('button.dsf-dock__btn').trigger('keydown', { key: 'Escape' })
    await nextTick()

    expect(wrapper.find('.dsf-lang-menu__panel').exists()).toBe(false)
    wrapper.unmount()
  })
})
