import { Form, Link, usePage } from '@inertiajs/react'

type NavigationItem = {
  children: NavigationItem[]
  name: string
  url: string
}

type SharedProps = {
  auth: {
    user: {
      email: string
      id: number
      name: string
    } | null
  }
  cart: {
    quantity: number
  } | null
  navigation: NavigationItem[]
}

export default function Header() {
  const { auth, cart, navigation } = usePage<SharedProps>().props

  return (
    <header>
      <Link href="/">Store</Link>
      <Link href="/cart">Cart ({cart?.quantity ?? 0})</Link>
      {auth.user ? (
        <>
          <Link href="/orders">Orders</Link>
          <span>{auth.user.name}</span>
          <Form action="/logout" method="post">
            {({ processing }) => (
              <button disabled={processing} type="submit">Log out</button>
            )}
          </Form>
        </>
      ) : (
        <>
          <Link href="/login">Log in</Link>
          <Link href="/register">Create account</Link>
        </>
      )}

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
