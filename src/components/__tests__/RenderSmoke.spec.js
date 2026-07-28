import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BlockWrapper from '../BlockWrapper.vue'
import FrontendApp from '../../frontend/FrontendApp.vue'
import { preloadPreviewComponents } from '../../frontend/lazyBlockRegistry.js'

const block = {
  id: 'release-smoke',
  type: 'landing-block-ready',
  settings: {},
}

describe('release render smoke test', () => {
  it('renders a lazy-loaded block on the frontend and in the builder', async () => {
    vi.stubGlobal('matchMedia', vi.fn(() => ({
      matches: false,
      addEventListener() {},
      removeEventListener() {},
    })))

    await preloadPreviewComponents([block])

    const frontend = mount(FrontendApp, {
      props: { blocks: [block], popupSettings: {}, postId: 0 },
    })
    await nextTick()
    await flushPromises()

    expect(frontend.find('.dsf-frontend-blocks').exists()).toBe(true)
    expect(frontend.find('.dsf-block .dsf-ready').exists()).toBe(true)

    const builder = mount(BlockWrapper, {
      props: { block, index: 0, previewMode: 'desktop' },
    })

    expect(builder.find('.dsf-block-toolbar').exists()).toBe(true)
    expect(builder.find('.dsf-block .dsf-ready').exists()).toBe(true)

    frontend.unmount()
    builder.unmount()
  })

  it('marks heroes and header chrome so the shared H2 scale can exclude them', async () => {
    const hero = { id: 'hero-scale', type: 'hero', settings: {} }
    const header = { id: 'header-scale', type: 'header-mega-menu', settings: {} }
    await preloadPreviewComponents([hero, header])

    const frontend = mount(FrontendApp, { props: { blocks: [hero, header], popupSettings: {}, postId: 0 } })
    const builder = mount(BlockWrapper, { props: { block: hero, index: 0, previewMode: 'desktop' } })
    await flushPromises()

    expect(frontend.findAll('.dsf-block')[0].classes()).toContain('dsf-block--hero')
    expect(frontend.findAll('.dsf-block')[1].classes()).toContain('dsf-block--chrome')
    expect(builder.classes()).toContain('dsf-block--hero')
  })
})
