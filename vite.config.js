import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  // The plugin is installed below a WordPress site root. Relative URLs ensure
  // Vite's lazy-loaded CSS and chunks resolve from assets/js rather than /.
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
            return 'css/[name][extname]'
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
