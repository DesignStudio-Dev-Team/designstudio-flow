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
const publicAssetBase = '/wp-content/plugins/designstudio-flow/assets/'
if (!runtimeSource.includes(publicAssetBase)) {
  fail(`Lazy block assets are not scoped to ${publicAssetBase}`)
}

const representativeCss = asset('css/LandingBlockReadyPreview.css')
if (!existsSync(representativeCss) || !readFileSync(representativeCss, 'utf8').includes('.dsf-ready')) {
  fail('A representative lazy block stylesheet is missing or invalid.')
}

console.log('✓ Release asset verification passed.')
