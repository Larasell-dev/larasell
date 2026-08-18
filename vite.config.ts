import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import stylex from '@stylexjs/unplugin'
import Icons from 'unplugin-icons/vite'

export default defineConfig({
  base: '/vendor/larasell/admin/',
  plugins: [
    stylex.vite({
      useCSSLayers: true,
    }),
    Icons({ compiler: 'jsx', jsx: 'react' }),
    laravel({
      input: [
        'resources/css/admin.css',
        'resources/images/admin/favicon.svg',
        'resources/js/admin/app.tsx',
      ],
      refresh: true,
    }),
    react(),
  ],
})
