import { createInertiaApp } from '@inertiajs/react'
import { inertiaI18n } from '@larasell/inertia-i18n/react'
import Layout from './Layouts/Layout'

const i18n = inertiaI18n({
  global: ['common'],
})

createInertiaApp({
  layout: () => Layout,
  resolve: i18n.resolve(import.meta.glob('./Pages/**/*.tsx')),
  withApp: i18n.withApp,
})
