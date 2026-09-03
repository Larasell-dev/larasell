import { Link, usePage } from '@inertiajs/react'
import { useTranslation } from '@larasell-dev/inertia-i18n/react'

type NavigationItem = {
  children: NavigationItem[]
  name: string
  url: string
}

type SharedProps = {
  navigation: NavigationItem[]
}

export default function Header() {
  const { navigation } = usePage<SharedProps>().props
  const { t } = useTranslation()

  return (
    <header>
      <Link href="/">Store</Link>

      {navigation.length > 0 && (
        <nav aria-label={t('common.categories')}>
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
