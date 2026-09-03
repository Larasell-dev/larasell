import { createInertiaApp } from '@inertiajs/react'
import { createI18n } from '@larasell-dev/inertia-i18n/react'
import Layout from './Layouts/Layout'
import './types'

const i18n = createI18n({
  global: ['common'],
})

createInertiaApp({
  layout: () => Layout,
  resolve: i18n.resolve(import.meta.glob('./Pages/**/*.tsx')),
  withApp: i18n.withApp,
})
