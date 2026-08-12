import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import EditorDock from '../EditorDock.vue'
import ThemePanel from '../ThemePanel.vue'

function mountDock(props = {}) {
  return mount(EditorDock, {
    props: { postType: 'page', ...props },
    attachTo: document.body,
  })
}

describe('EditorDock — combined settings and save', () => {
  beforeEach(() => {
    window.dsfEditorData = { pluginUrl: '/plugin/', adminUrl: '/wp-admin/' }
  })

  afterEach(() => {
    delete window.dsfEditorData
    document.body.innerHTML = ''
  })

  it('offers one Settings button instead of separate page and theme buttons', () => {
    const wrapper = mountDock()
    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label'))

    expect(labels).toContain('Settings')
    expect(labels).not.toContain('Theme')
    expect(labels).not.toContain('Page settings')

    wrapper.unmount()
  })

  it('opens settings through a single event', async () => {
    const wrapper = mountDock()

    await wrapper.find('button[aria-label="Settings"]').trigger('click')

    expect(wrapper.emitted('open-settings')).toHaveLength(1)
    wrapper.unmount()
  })

  it('opens a save window the way the preview picker does', async () => {
    const wrapper = mountDock()
    const save = wrapper.find('button[aria-label="Save options"]')

    expect(save.exists()).toBe(true)
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(false)

    await save.trigger('click')
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(true)
    expect(save.attributes('aria-expanded')).toBe('true')

    const options = wrapper.findAll('.dsf-dock__save-menu button')
    expect(options.map((o) => o.text())).toEqual([
      expect.stringContaining('Save page'),
      expect.stringContaining('Save as template'),
    ])

    await options[0].trigger('click')
    expect(wrapper.emitted('save')).toHaveLength(1)
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(false)

    await save.trigger('click')
    await wrapper.findAll('.dsf-dock__save-menu button')[1].trigger('click')
    expect(wrapper.emitted('save-as-template')).toHaveLength(1)

    wrapper.unmount()
  })

  it('closes the save window on Escape and on an outside pointer', async () => {
    const wrapper = mountDock()

    await wrapper.find('button[aria-label="Save options"]').trigger('click')
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(true)

    document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(false)

    await wrapper.find('button[aria-label="Save options"]').trigger('click')
    document.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }))
    await nextTick()
    expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(false)

    wrapper.unmount()
  })

  it('saves in one click where there is nothing else to save', async () => {
    for (const props of [{ postType: 'dsf_layout' }, { libraryMode: true }]) {
      const wrapper = mountDock(props)
      const save = wrapper.find('.dsf-dock__save-picker button')

      expect(save.attributes('aria-haspopup')).toBeUndefined()

      await save.trigger('click')
      expect(wrapper.emitted('save')).toHaveLength(1)
      expect(wrapper.find('.dsf-dock__save-menu').exists()).toBe(false)

      wrapper.unmount()
    }
  })

  it('orders the bar with Add block centred and View page last', () => {
    const wrapper = mountDock()
    const order = wrapper
      .findAll('.dsf-dock__body button[aria-label]')
      .map((b) => b.attributes('aria-label'))
      .filter((label) => label !== 'Editor actions')

    // Four controls each side of the centred Add block.
    expect(order).toEqual([
      'Settings',
      'Structure',
      'Save options',
      'Choose preview size',
      'Add block',
      'Help',
      'History',
      'View page',
    ])
    expect(order.indexOf('Add block')).toBe(4)
    expect(order[order.length - 1]).toBe('View page')
    wrapper.unmount()
  })

  it('places the language control between Help and History', () => {
    window.dsfEditorData.translation = {
      active: true,
      current: 'en-US',
      isMain: true,
      canClone: true,
      nonce: 'n',
      languages: [
        { code: 'en-US', label: 'English', isMain: true, isCurrent: true, postId: 1, state: 'published', editUrl: '' },
        { code: 'es-MX', label: 'Español', isMain: false, isCurrent: false, postId: 0, state: 'missing', editUrl: '' },
      ],
    }

    const wrapper = mountDock()
    const help = wrapper.find('button[aria-label="Help"]').element
    const language = wrapper.find('.dsf-lang-menu').element

    expect(language).toBeTruthy()
    // Help, then language, then History.
    expect(help.nextElementSibling).toBe(language)
    expect(language.nextElementSibling.getAttribute('aria-label')).toBe('History')

    wrapper.unmount()
  })
})

/**
 * Which panel bodies v-show has hidden, in document order: [page, theme].
 */
function hidden(wrapper) {
  return wrapper.findAll('.dsf-panel__body').map((body) => (body.attributes('style') || '').includes('display: none'))
}

describe('ThemePanel — settings tabs', () => {
  it('shows only theme controls when there is no page to configure', () => {
    const wrapper = mount(ThemePanel, { props: { settings: {} } })

    expect(wrapper.find('.dsf-settings-panel__tabs').exists()).toBe(true)
    expect(wrapper.findAll('.dsf-settings-panel__tabs button')).toHaveLength(1)
    expect(wrapper.find('.dsf-panel__title').text()).toBe('Settings')
  })

  it('opens on the page tab and renders the page slot there', async () => {
    const wrapper = mount(ThemePanel, {
      props: { settings: {}, showPageTab: true, initialTab: 'page' },
      slots: { page: '<p class="page-slot">Page settings</p>' },
    })

    const tabs = wrapper.findAll('.dsf-settings-panel__tabs button')
    expect(tabs.map((t) => t.text())).toEqual(['Page', 'Theme'])
    expect(tabs[0].attributes('aria-selected')).toBe('true')
    // v-show toggles inline display, which is what the panel relies on.
    expect(hidden(wrapper)).toEqual([false, true])
    expect(wrapper.find('.page-slot').exists()).toBe(true)
    // The theme undo action belongs to the theme tab only.
    expect(wrapper.find('.dsf-theme-panel__undo').exists()).toBe(false)

    await tabs[1].trigger('click')
    expect(hidden(wrapper)).toEqual([true, false])
    expect(wrapper.find('.dsf-theme-panel__undo').exists()).toBe(true)
  })

  it('reports a tab change so the onboarding tour can follow it', async () => {
    const wrapper = mount(ThemePanel, {
      props: { settings: {}, showPageTab: true, initialTab: 'page' },
      slots: { page: '<p class="page-slot">Page settings</p>' },
    })

    await wrapper.findAll('.dsf-settings-panel__tabs button')[1].trigger('click')

    expect(wrapper.emitted('tab-change')).toEqual([['theme']])
  })

  it('opens straight on theme when asked', () => {
    const wrapper = mount(ThemePanel, {
      props: { settings: {}, showPageTab: true, initialTab: 'theme' },
      slots: { page: '<p class="page-slot">Page settings</p>' },
    })

    expect(wrapper.findAll('.dsf-settings-panel__tabs button')[1].attributes('aria-selected')).toBe('true')
    expect(hidden(wrapper)).toEqual([true, false])
  })
})
