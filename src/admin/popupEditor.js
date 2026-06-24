import { createApp } from 'vue'
import PopupEditorApp from './PopupEditorApp.vue'

const mount = document.getElementById('dsf-popup-editor-app')
const input = document.getElementById('dsf-popup-settings-input')

if (mount) {
  const data = window.dsfPopupEditorData || {}
  createApp(PopupEditorApp, {
    initialSettings: data.settings || {},
    onChange: (json) => {
      if (input) input.value = json
    },
  }).mount(mount)
}
