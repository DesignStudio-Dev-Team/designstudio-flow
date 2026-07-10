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

  it('starts with every category collapsed', () => {
    const wrapper = mount(BlockLibrary, {
      props: {
        categories: mockCategories
      }
    })

    expect(wrapper.findAll('.dsf-library-category__chevron--open')).toHaveLength(0)
    expect(wrapper.find('.dsf-library-block').isVisible()).toBe(false)
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

  it('renders an export link for saved blocks that provide an export URL', () => {
    const wrapper = mount(BlockLibrary, {
      props: {
        categories: mockCategories,
        savedBlocks: [
          { id: 7, name: 'My Hero', type: 'hero-1', settings: {}, tags: [], exportUrl: 'https://example.com/wp-admin/admin-post.php?action=dsf_export_item&post_id=7&_wpnonce=abc' },
          { id: 8, name: 'Legacy', type: 'hero-1', settings: {}, tags: [] },
        ],
      },
    })

    const links = wrapper.findAll('.dsf-library-block__export')
    expect(links).toHaveLength(1)
    expect(links[0].attributes('href')).toContain('action=dsf_export_item')
    expect(links[0].attributes('href')).toContain('post_id=7')
  })

  it('emits import-saved with the chosen JSON file and resets the input', async () => {
    const wrapper = mount(BlockLibrary, {
      props: { categories: mockCategories, savedBlocks: [] },
    })

    const input = wrapper.find('.dsf-library-import__input')
    expect(input.exists()).toBe(true)

    const file = new File(['{"_dsf_export":true,"items":[]}'], 'block.json', { type: 'application/json' })
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')

    expect(wrapper.emitted('import-saved')).toHaveLength(1)
    expect(wrapper.emitted('import-saved')[0][0]).toBe(file)
  })

  it('does not emit import-saved when no file is chosen', async () => {
    const wrapper = mount(BlockLibrary, {
      props: { categories: mockCategories, savedBlocks: [] },
    })

    const input = wrapper.find('.dsf-library-import__input')
    Object.defineProperty(input.element, 'files', { value: [], configurable: true })
    await input.trigger('change')

    expect(wrapper.emitted('import-saved')).toBeUndefined()
  })
})
