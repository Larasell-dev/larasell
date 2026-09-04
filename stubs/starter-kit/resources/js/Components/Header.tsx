import { Link, usePage } from '@inertiajs/react'

type NavigationItem = {
  children: NavigationItem[]
  name: string
  url: string
}

type SharedProps = {
  cart: {
    quantity: number
  }
  navigation: NavigationItem[]
}

export default function Header() {
  const { cart, navigation } = usePage<SharedProps>().props

  return (
    <header>
      <Link href="/">Store</Link>
      <Link href="/cart">Cart ({cart.quantity})</Link>

      {navigation.length > 0 && (
        <nav aria-label="Categories">
          <NavigationItems items={navigation} />
        </nav>
      )}
    </header>
  )
}

function NavigationItems({ items }: { items: NavigationItem[] }) {
  return (
    <ul>
      {items.map((item) => (
        <li key={item.url}>
          <Link href={item.url}>{item.name}</Link>

          {item.children.length > 0 && <NavigationItems items={item.children} />}
        </li>
      ))}
    </ul>
  )
}
