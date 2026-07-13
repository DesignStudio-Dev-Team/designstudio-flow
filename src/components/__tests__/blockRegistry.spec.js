import { describe, it, expect, beforeEach } from 'vitest'
import { h, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import {
  registerBlock,
  getCustomBlock,
  getRegisteredBlockTypes,
} from '../../blockRegistry.js'

describe('blockRegistry', () => {
  it('registers and resolves a custom block component', () => {
    const comp = { render: () => h('div', 'acme') }
    expect(registerBlock('acme-quote', comp)).toBe(true)
    expect(getCustomBlock('acme-quote')).toBe(comp)
    expect(getRegisteredBlockTypes()).toContain('acme-quote')
  })

  it('rejects invalid registrations', () => {
    expect(registerBlock('', { render() {} })).toBe(false)
    expect(registerBlock('x', null)).toBe(false)
    expect(getCustomBlock('does-not-exist')).toBe(null)
  })

  it('exposes the shared API and Vue helpers on window.dsfFlow', () => {
    expect(typeof window.dsfFlow.registerBlock).toBe('function')
    expect(typeof window.dsfFlow.vue.h).toBe('function')
    expect(window.dsfFlow.version).toBe(1)
  })

  it('re-renders a consuming component when a block is registered after mount', async () => {
    // A component that resolves its child through the registry, mirroring how
    // getPreviewComponent() reads it inside the editor/frontend render.
    const Host = {
      props: ['type'],
      setup(props) {
        return () => {
          const custom = getCustomBlock(props.type)
          return custom ? h(custom) : h('span', 'placeholder')
        }
      },
    }

    const wrapper = mount(Host, { props: { type: 'late-block' } })
    expect(wrapper.text()).toBe('placeholder')

    // Registration arriving after mount must trigger a re-render (reactive map).
    registerBlock('late-block', { render: () => h('span', 'registered') })
    await nextTick()
    expect(wrapper.text()).toBe('registered')
  })
})
