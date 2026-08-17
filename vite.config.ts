import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import stylex from '@stylexjs/unplugin'

export default defineConfig({
  plugins: [
    stylex.vite({
      useCSSLayers: true,
    }),
    laravel({
      input: [
        'resources/css/admin.css',
        'resources/js/admin/app.tsx',
      ],
      refresh: true,
    }),
    react(),
  ],
})
