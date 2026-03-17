import { createApp } from 'vue'
import FrontendApp from './FrontendApp.vue'
import '../styles/main.css'

const data = window.dsfFrontendData || {}

function mountBlocksApp(rootId, blocks) {
  const root = document.getElementById(rootId)
  if (!root) return
  if (!Array.isArray(blocks) || !blocks.length) return

  createApp(FrontendApp, {
    blocks,
  }).mount(root)
}

mountBlocksApp('dsf-frontend-app', data.blocks || [])

const headerTemplateBlocks = data?.layoutTemplates?.header?.blocks || []
const footerTemplateBlocks = data?.layoutTemplates?.footer?.blocks || []

mountBlocksApp('dsf-layout-header-app', headerTemplateBlocks)
mountBlocksApp('dsf-layout-footer-app', footerTemplateBlocks)
