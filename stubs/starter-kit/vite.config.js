import inertia from '@inertiajs/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { laravelI18n } from './i18n/vite'

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
