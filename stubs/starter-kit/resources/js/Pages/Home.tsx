import { Head, Link, usePage } from '@inertiajs/react'

type NavigationItem = {
  name: string
  url: string
}

type SharedProps = {
  navigation: NavigationItem[]
}

const linkClass = 'flex min-h-48 items-center justify-center px-4 py-8 text-center hover:bg-black hover:text-white hover:underline focus-visible:focus-ring lg:min-h-0'

function Home() {
  const categories = usePage<SharedProps>().props.navigation.slice(0, 3)

  return (
    <main className="grid flex-1 lg:grid-cols-2 lg:grid-rows-2">
      <Head title="Home" />
      {categories[0] && (
        <Link className={`${linkClass} max-lg:border-b lg:row-span-2 lg:border-r`} href={categories[0].url}>
          {categories[0].name}
        </Link>
      )}
      {categories[1] && (
        <Link className={`${linkClass} border-b`} href={categories[1].url}>
          {categories[1].name}
        </Link>
      )}
      {categories[2] && (
        <Link className={linkClass} href={categories[2].url}>
          {categories[2].name}
        </Link>
      )}
    </main>
  )
}

export default Home
