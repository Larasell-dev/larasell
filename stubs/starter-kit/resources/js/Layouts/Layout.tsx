import type { ReactNode } from 'react'
import { usePage } from '@inertiajs/react'
import Footer from '../Components/Footer'
import Header from '../Components/Header'

export default function Layout({ children }: { children: ReactNode }) {
  const { flash } = usePage().props

  return (
    <>
      <Header />
      {flash.message && <p role="alert">{flash.message}</p>}
      {children}
      <Footer />
    </>
  )
}
