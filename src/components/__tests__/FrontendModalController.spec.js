import { describe, it, expect, vi } from 'vitest'
import { nextTick } from 'vue'
import { createModalController } from '../../frontend/modalController.js'

describe('Frontend modal controller', () => {
  it('loads shortcode content via AJAX and updates modal state', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      json: () =>
        Promise.resolve({
          success: true,
          data: { html: '<p>Shortcode</p>' },
        }),
    })
    global.fetch = fetchMock

    window.dsfFrontendData = {
      ajaxUrl: '/ajax',
      nonce: 'nonce',
    }

    const { modalState, openModalAction } = createModalController()

    await openModalAction({
      layout: 'center',
      contentType: 'shortcode',
      content: '[shortcode]',
    })

    await nextTick()

    expect(fetchMock).toHaveBeenCalled()
    expect(modalState.loading).toBe(false)
    expect(modalState.content).toBe('<p>Shortcode</p>')
    expect(modalState.open).toBe(true)
  })
})
