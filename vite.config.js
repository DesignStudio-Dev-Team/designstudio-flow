import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  // Dynamic block chunks must resolve from the installed plugin, not from the
  // current wp-admin URL. Vite otherwise derives CSS URLs from admin.php and
  // the editor renders every block without its component stylesheet.
  base: '/wp-content/plugins/designstudio-flow/assets/',

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
