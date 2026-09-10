import '../../css/admin.css'

import { createInertiaApp } from '@inertiajs/react'
import type { ComponentType } from 'react'
import AppToastProvider from './Components/AppToastProvider'

void createInertiaApp({
  title: (title) => title ? `${title} - Larasell Admin` : 'Larasell Admin',
  resolve: (name) => {
    const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true })
    const page = pages[`./Pages/${name}.tsx`]

    if (!page) {
      throw new Error(`Page not found: ${name}`)
    }

    return page
  },
  withApp(app) {
    return <AppToastProvider>{app}</AppToastProvider>
  },
})
