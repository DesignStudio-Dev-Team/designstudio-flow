import { createApp } from 'vue'
import FrontendApp from './FrontendApp.vue'

const root = document.getElementById('dsf-frontend-app')
if (root) {
  const data = window.dsfFrontendData || {}
  createApp(FrontendApp, {
    blocks: data.blocks || [],
  }).mount(root)
}
