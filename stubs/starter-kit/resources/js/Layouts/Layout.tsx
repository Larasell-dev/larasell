import type { ReactNode } from 'react'
import Footer from '../Components/Footer'
import Header from '../Components/Header'

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <>
      <Header />
      {children}
      <Footer />
    </>
  )
}
