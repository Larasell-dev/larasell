import { Form, Link, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import Logo from './Logo'

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
  const url = usePage().url
  const [menuOpen, setMenuOpen] = useState(false)
  const cartQuantity = cart?.quantity ?? 0

  useEffect(() => {
    setMenuOpen(false)
  }, [url])

  return (
    <header className="border-b">
      <div className="container mx-auto grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center px-4 py-4 lg:grid-cols-3">
        <Link className="justify-self-start focus-visible:focus-ring" href="/">
          <Logo className="size-10" />
        </Link>

        <nav aria-label="Categories" className="min-w-0 justify-self-center">
          {navigation.length > 0 && <NavigationItems items={navigation} />}
        </nav>

        <div className="justify-self-end">
          <button
            aria-controls="mobile-menu"
            aria-expanded={menuOpen}
            className="cursor-pointer hover:underline focus-visible:focus-ring lg:hidden"
            onClick={() => setMenuOpen((open) => !open)}
            type="button"
          >
            Menu
          </button>

          <div className="hidden items-center gap-4 lg:flex">
            <StoreLinks auth={auth} cartQuantity={cartQuantity} />
          </div>
        </div>
      </div>

      {menuOpen && (
        <nav
          aria-label="Menu"
          className="container mx-auto flex flex-col items-end gap-4 px-4 py-4 lg:hidden"
          id="mobile-menu"
        >
          <StoreLinks
            auth={auth}
            cartQuantity={cartQuantity}
          />
        </nav>
      )}
    </header>
  )
}

function StoreLinks({
  auth,
  cartQuantity,
}: {
  auth: SharedProps['auth']
  cartQuantity: number
}) {
  return (
    <>
      <Link className="hover:underline focus-visible:focus-ring" href="/cart" prefetch cacheFor="0s">
        Cart ({cartQuantity})
      </Link>

      {auth.user ? (
        <>
          <Link className="hover:underline focus-visible:focus-ring" href="/orders">
            Orders
          </Link>
          <Form action="/logout" method="post">
            {({ processing }) => (
              <button
                className="cursor-pointer hover:underline focus-visible:focus-ring"
                disabled={processing}
                type="submit"
              >
                Log out
              </button>
            )}
          </Form>
        </>
      ) : (
        <>
          <Link className="hover:underline focus-visible:focus-ring" href="/login">
            Log in
          </Link>
          <Link className="hover:underline focus-visible:focus-ring" href="/register">
            Create account
          </Link>
        </>
      )}
    </>
  )
}

function NavigationItems({ items }: { items: NavigationItem[] }) {
  return (
    <ul className="flex flex-nowrap items-center justify-center gap-6 whitespace-nowrap">
      {items.map((item) => (
        <li key={item.url}>
          <Link className="hover:underline focus-visible:focus-ring" href={item.url} prefetch>{item.name}</Link>

          {item.children.length > 0 && <NavigationItems items={item.children} />}
        </li>
      ))}
    </ul>
  )
}
