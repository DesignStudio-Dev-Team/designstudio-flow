import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

function bundleEditorBlockStyles() {
  return {
    name: 'bundle-editor-block-styles',
    enforce: 'post',
    generateBundle(_options, bundle) {
      const cssAssets = Object.values(bundle).filter(
        (item) => item.type === 'asset' && item.fileName.endsWith('.css'),
      )
      const editorCss = cssAssets.find((item) => item.fileName === 'css/editor.css')
      if (!editorCss) {
        this.error('Unable to find css/editor.css while bundling editor block styles.')
      }

      // Vite extracts the Vue components shared by the frontend and editor into
      // separate CSS chunks. The frontend loads those chunks lazily, but the
      // WordPress editor only enqueues its two stable entry stylesheets. Copy
      // the component CSS into editor.css so the builder never renders blocks
      // before (or without) their scoped styles.
      const componentCss = cssAssets
        .filter((item) => ![
          'css/editor.css',
          'css/main.css',
          'css/notification-bar.css',
          'css/popup-editor.css',
        ].includes(item.fileName))
        .sort((a, b) => a.fileName.localeCompare(b.fileName))
        .map((item) => String(item.source))
        .join('\n')

      editorCss.source = `${componentCss}\n${String(editorCss.source)}`
    },
  }
}

export default defineConfig({
  plugins: [vue(), bundleEditorBlockStyles()],
  // Resolve lazy frontend chunks relative to their JS module so this continues
  // to work on subdirectory installs and custom WordPress content URLs.
  base: './',

  root: './',
  
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    manifest: true,
    rollupOptions: {
      input: {
        editor: resolve(__dirname, 'src/main.js'),
        frontend: resolve(__dirname, 'src/frontend/main.js'),
        'notification-bar': resolve(__dirname, 'src/frontend/notificationBar.js'),
        'popup-editor': resolve(__dirname, 'src/admin/popupEditor.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            // The entry stylesheets are enqueued by WordPress with a file
            // version, so keep their stable URLs. Lazy Vue component styles
            // are injected by the browser directly, however; a stable URL
            // lets an old scoped stylesheet survive a plugin update and no
            // longer match the new component's data-v scope. Hash those files
            // so the JS chunk and its stylesheet are always from one build.
            const stableEntryStyles = new Set([
              'editor',
              'main',
              'frontend',
              'notification-bar',
              'popup-editor',
              'editor.css',
              'main.css',
              'frontend.css',
              'notification-bar.css',
              'popup-editor.css',
            ])
            return stableEntryStyles.has(assetInfo.name)
              ? 'css/[name][extname]'
              : 'css/[name]-[hash][extname]'
          }
          return 'assets/[name][extname]'
        },
      },
    },
  },
  
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
  
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
})
