#!/usr/bin/env node

import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const asset = (path) => resolve(root, 'assets', path)
const fail = (message) => {
  console.error(`\n❌ Release asset verification failed: ${message}\n`)
  process.exit(1)
}

const manifestPath = asset('.vite/manifest.json')
if (!existsSync(manifestPath)) fail('Vite manifest is missing.')

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
for (const entry of ['src/main.js', 'src/frontend/main.js']) {
  const output = manifest[entry]?.file
  if (!output || !existsSync(asset(output))) fail(`Missing built entry for ${entry}.`)
}

const runtime = Object.values(manifest)
  .map((entry) => entry.file)
  .find((file) => /^js\/main-.*\.js$/.test(file))
if (!runtime || !existsSync(asset(runtime))) fail('The lazy block runtime chunk is missing.')

const runtimeSource = readFileSync(asset(runtime), 'utf8')
if (!runtimeSource.includes('new URL(e,t).href')) {
  fail('Lazy frontend assets are not resolved relative to their runtime module.')
}

const representativeCss = Object.values(manifest)
  .flatMap((entry) => entry.css || [])
  .find((file) => /^css\/LandingBlockReadyPreview-[A-Za-z0-9_-]+\.css$/.test(file))
if (!representativeCss || !existsSync(asset(representativeCss)) || !readFileSync(asset(representativeCss), 'utf8').includes('.dsf-ready')) {
  fail('A representative lazy block stylesheet is missing or invalid.')
}

const explorerEntry = Object.entries(manifest)
  .find(([key]) => key.includes('LandingBlockExplorerPreview'))?.[1]
const explorerCss = explorerEntry?.css?.[0]
if (!explorerEntry?.file || !explorerCss || !/^css\/LandingBlockExplorerPreview-[A-Za-z0-9_-]+\.css$/.test(explorerCss)) {
  fail('The Content Carousel JavaScript and hashed stylesheet are not paired in the manifest.')
}

const explorerJsSource = readFileSync(asset(explorerEntry.file), 'utf8')
const explorerCssSource = readFileSync(asset(explorerCss), 'utf8')
const jsScopes = new Set(explorerJsSource.match(/data-v-[a-f0-9]+/g) || [])
const cssScopes = new Set(explorerCssSource.match(/data-v-[a-f0-9]+/g) || [])
if (![...jsScopes].some((scope) => cssScopes.has(scope))) {
  fail('The Content Carousel stylesheet scope does not match its JavaScript component.')
}

const editorCss = asset('css/editor.css')
if (!existsSync(editorCss) || !readFileSync(editorCss, 'utf8').includes('.dsf-ready')) {
  fail('The editor bundle is missing scoped block styles.')
}

console.log('✓ Release asset verification passed.')
