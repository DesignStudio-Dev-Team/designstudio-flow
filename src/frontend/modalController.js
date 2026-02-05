import { nextTick, reactive } from 'vue'

export function createModalController() {
  const modalState = reactive({
    open: false,
    layout: 'center',
    content: '',
    loading: false,
  })

  async function openModalAction({ layout, contentType, content }) {
    modalState.open = true
    modalState.layout = layout || 'center'
    modalState.loading = false
    if (contentType === 'shortcode') {
      modalState.loading = true
      const data = window.dsfFrontendData || {}
      const formData = new FormData()
      formData.append('action', 'dsf_render_shortcode')
      formData.append('shortcode', content || '')
      if (data.nonce) {
        formData.append('nonce', data.nonce)
      }
      await fetch(data.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
      })
        .then((res) => res.json())
        .then((res) => {
          modalState.content = res.success ? res.data.html : ''
        })
        .catch(() => {
          modalState.content = ''
        })
        .finally(() => {
          modalState.loading = false
        })
      await nextTick()
    } else {
      modalState.content = content || ''
    }
  }

  function closeModalAction() {
    modalState.open = false
    modalState.content = ''
    modalState.loading = false
  }

  return { modalState, openModalAction, closeModalAction }
}
