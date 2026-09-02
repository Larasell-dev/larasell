import { Head, Link, router } from '@inertiajs/react'

type Price = {
  amount: string
}

type Product = {
  id: number | string
  image: {
    alt: string | null
    url: string
  } | null
  name: string
  price: Price
  slug: string
}

type Props = {
  category: {
    name: string
  }
  currency: string
  products: Product[]
  sort: string
}

export default function ProductIndex({ category, currency, products, sort }: Props) {
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
                <p>{formatPrice(product.price, currency)}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}

function formatPrice(price: Price, currency: string) {
  const formatter = new Intl.NumberFormat(undefined, { currency, style: 'currency' })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits ?? 2

  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}
