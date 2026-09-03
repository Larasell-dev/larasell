import inertia from '@inertiajs/vite'
import { laravelI18n } from '@larasell/inertia-i18n/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx'],
      refresh: true,
    }),
    inertia(),
    laravelI18n(),
    react(),
  ],
})
