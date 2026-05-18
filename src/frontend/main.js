import { createApp } from 'vue'
import FrontendApp from './FrontendApp.vue'
import '../styles/main.css'

const data = window.dsfFrontendData || {}

function mountBlocksApp(rootId, blocks) {
  const root = document.getElementById(rootId)
  if (!root) return

  // Always clear the server-rendered snapshot HTML before deciding what to do,
  // so a stale snapshot can never remain visible when the saved block list is
  // empty (or partially empty) — that would otherwise look like an unstyled
  // duplicate block at the bottom of the page.
  const safeBlocks = Array.isArray(blocks) ? blocks : []
  if (!safeBlocks.length) {
    root.textContent = ''
    return
  }

  createApp(FrontendApp, {
    blocks: safeBlocks,
  }).mount(root)
}

mountBlocksApp('dsf-frontend-app', data.blocks || [])

const headerTemplateBlocks = data?.layoutTemplates?.header?.blocks || []
const footerTemplateBlocks = data?.layoutTemplates?.footer?.blocks || []

mountBlocksApp('dsf-layout-header-app', headerTemplateBlocks)
mountBlocksApp('dsf-layout-footer-app', footerTemplateBlocks)
