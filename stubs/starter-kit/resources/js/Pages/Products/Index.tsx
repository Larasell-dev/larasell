import { Head, Link, router } from '@inertiajs/react'

type Product = {
  id: number | string
  image: {
    alt: string | null
    url: string
  } | null
  name: string
  price: string
  slug: string
}

type Props = {
  category: {
    name: string
  }
  products: Product[]
  sort: string
}

export default function ProductIndex({ category, products, sort }: Props) {
  return (
    <main>
      <Head title={category.name} />
      <h1>{category.name}</h1>

      <label htmlFor="sort">Sort by</label>{' '}
      <select
        id="sort"
        value={sort}
        onChange={(event) => router.reload({ data: { sort: event.target.value } })}
      >
        <option value="name">Name</option>
        <option value="price_asc">Price: low to high</option>
        <option value="price_desc">Price: high to low</option>
      </select>

      {products.length === 0 ? (
        <p>No products found.</p>
      ) : (
        <ul>
          {products.map((product) => (
            <li key={product.id}>
              <Link href={`/p/${product.slug}`}>
                {product.image && (
                  <img src={product.image.url} alt={product.image.alt ?? product.name} />
                )}
                <h2>{product.name}</h2>
                <p>{product.price}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
