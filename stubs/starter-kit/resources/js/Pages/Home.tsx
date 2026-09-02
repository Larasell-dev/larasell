import { Head } from '@inertiajs/react'
import Header from '../Components/Header'

export default function Home() {
  return (
    <>
      <Head title="Home" />
      <Header />

      <main>
        <h1>Hello store!</h1>
      </main>
    </>
  )
}
