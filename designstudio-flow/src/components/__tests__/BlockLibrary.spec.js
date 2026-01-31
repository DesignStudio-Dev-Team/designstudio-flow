import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BlockLibrary from '../BlockLibrary.vue'

describe('BlockLibrary', () => {
  const mockCategories = {
    heroes: {
      label: 'Heroes',
      blocks: [
        { id: 'hero-1', name: 'Centered Hero', description: 'A hero block', category: 'heroes' }
      ]
    },
    content: {
      label: 'Content',
      blocks: []
    }
  }

  it('renders correctly', () => {
    const wrapper = mount(BlockLibrary, {
      props: {
        categories: mockCategories
      }
    })
    expect(wrapper.find('.dsf-library-header h2').text()).toBe('Block Library')
  })

  it('displays categories', () => {
    const wrapper = mount(BlockLibrary, {
      props: {
        categories: mockCategories
      }
    })
    expect(wrapper.text()).toContain('Heroes')
    expect(wrapper.text()).toContain('Content')
  })

  it('filters blocks when searching', async () => {
    const wrapper = mount(BlockLibrary, {
      props: {
        categories: mockCategories
      }
    })
    
    const input = wrapper.find('input[type="text"]')
    await input.setValue('Centered')
    
    expect(wrapper.text()).toContain('Centered Hero')
    
    await input.setValue('Nonexistent')
    expect(wrapper.text()).toContain('No blocks found')
  })
})
