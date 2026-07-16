import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import HistoryPanel from '../HistoryPanel.vue'

function mountPanel(props = {}) {
  return mount(HistoryPanel, {
    props: { visible: true, records: [], ...props },
    global: { stubs: { teleport: true } },
  })
}

describe('HistoryPanel', () => {
  it('shows an empty state when no previous versions exist', () => {
    const wrapper = mountPanel()
    expect(wrapper.text()).toContain('No previous versions yet.')
  })

  it('renders bounded metadata and emits the selected restore record', async () => {
    const wrapper = mountPanel({
      records: [{ id: 12, created_at_gmt: '2026-07-14 12:00:00', reason: 'editor_save', summary: 'Changed: blocks', created_by: 4 }],
      editors: { 4: 'Alex' },
    })
    expect(wrapper.text()).toContain('Changed: blocks')
    expect(wrapper.text()).toContain('Alex')
    await wrapper.find('.dsf-history__restore').trigger('click')
    expect(wrapper.emitted('restore')[0][0].id).toBe(12)
  })

  it('emits close on Escape and does not render raw payload fields', async () => {
    const wrapper = mountPanel({ records: [{ id: 1, summary: '<script>alert(1)</script>' }] })
    expect(wrapper.find('script').exists()).toBe(false)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
