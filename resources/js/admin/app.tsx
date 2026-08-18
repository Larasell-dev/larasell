import '../../css/admin.css'

import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import type { ComponentType } from 'react'
import AppToastProvider from './Components/AppToastProvider'

createInertiaApp({
  title: (title) => title ? `${title} - Larasell Admin` : 'Larasell Admin',
  resolve: (name) => {
    const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true })
    const page = pages[`./Pages/${name}.tsx`]

    if (!page) {
      throw new Error(`Page not found: ${name}`)
    }

    return page
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <AppToastProvider>
        <App {...props} />
      </AppToastProvider>,
    )
  },
})
