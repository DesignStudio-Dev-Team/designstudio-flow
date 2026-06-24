import { createApp } from 'vue'
import FrontendApp from './FrontendApp.vue'
import '../styles/main.css'

const data = window.dsfFrontendData || {}
const hydrationStartedAt = Date.now()

function revealLandingPage(root) {
  const page = root.closest('.dsf-page-content--loading')
  if (!page) return

  const loader = page.querySelector('.dsf-landing-loader')
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const minimumDuration = reducedMotion ? 120 : 520
  const delay = Math.max(0, minimumDuration - (Date.now() - hydrationStartedAt))

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      window.setTimeout(() => {
        page.classList.remove('dsf-page-content--loading')
        page.classList.add('dsf-page-content--ready')
        loader?.setAttribute('aria-hidden', 'true')
        window.setTimeout(() => loader?.remove(), reducedMotion ? 0 : 280)
      }, delay)
    })
  })
}

function mountBlocksApp(rootId, blocks, options = {}) {
  const root = document.getElementById(rootId)
  if (!root) return

  // Always clear the server-rendered snapshot HTML before deciding what to do,
  // so a stale snapshot can never remain visible when the saved block list is
  // empty (or partially empty) — that would otherwise look like an unstyled
  // duplicate block at the bottom of the page.
  const safeBlocks = Array.isArray(blocks) ? blocks : []
  if (!safeBlocks.length && !options?.popupSettings?.enabled) {
    root.textContent = ''
    return
  }

  createApp(FrontendApp, {
    blocks: safeBlocks,
    ...options,
  }).mount(root)

  revealLandingPage(root)
}

mountBlocksApp('dsf-frontend-app', data.blocks || [], {
  popupSettings: data.popup || {},
  postId: data.postId || 0,
})

const headerTemplateBlocks = data?.layoutTemplates?.header?.blocks || []
const footerTemplateBlocks = data?.layoutTemplates?.footer?.blocks || []

mountBlocksApp('dsf-layout-header-app', headerTemplateBlocks)
mountBlocksApp('dsf-layout-footer-app', footerTemplateBlocks)
