import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import type { ComponentType } from 'react'
import Layout from './Layouts/Layout'

createInertiaApp({
  layout: () => Layout,
  resolve: (name) => {
    const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true })
    const page = pages[`./Pages/${name}.tsx`]

    if (!page) {
      throw new Error(`Page not found: ${name}`)
    }

    return page
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
