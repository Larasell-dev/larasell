import { Link } from '@inertiajs/react'
import LocaleSwitch from './LocaleSwitch'

export default function Header() {
  return (
    <header>
      <Link href="/">Larasell</Link>
      <nav aria-label="Storefront">
        <Link href="/">Home</Link>
      </nav>
      <LocaleSwitch />
    </header>
  )
}
