
import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  base: './',

  server: {
    host: '127.0.0.1',
    port: 5173,

    proxy: {
      '/carease': {
        target: 'http://127.0.0.1:3000',
        changeOrigin: true,
        rewrite: (path) => '/index.php' + path,
      },
    },
  },

  plugins: [
    vue(),
    vueDevTools(),
  ],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
