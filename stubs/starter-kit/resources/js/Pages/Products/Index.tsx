import { Head, Link, router } from '@inertiajs/react'

type Product = {
  id: number | string
  image: {
    alt: string | null
    url: string
  } | null
  name: string
  price: string
  compareAt: string | null
  slug: string
}

type Props = {
  category: {
    name: string
  }
  products: Product[]
  sort: string
}

function ProductIndex({ category, products, sort }: Props) {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title={category.name} />

      <div className="mb-8 flex items-center justify-between gap-4">
        <h1 className="text-3xl font-semibold">{category.name}</h1>

        <div className="flex items-center gap-2">
          <label htmlFor="sort">Sort by</label>
          <select
            className="appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring"
            id="sort"
            value={sort}
            onChange={(event) => router.reload({ data: { sort: event.target.value } })}
          >
            <option value="name">Name</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
          </select>
        </div>
      </div>

      {products.length === 0 ? (
        <p>No products found.</p>
      ) : (
        <ul className="grid grid-cols-2 gap-6 lg:grid-cols-3 xl:grid-cols-4">
          {products.map((product) => (
            <li key={product.id}>
              <Link className="block focus-visible:focus-ring" href={`/p/${product.slug}`}>
                <div className="mb-3 aspect-square w-full">
                  {product.image && (
                    <img
                      alt={product.image.alt ?? product.name}
                      className="size-full object-cover"
                      src={product.image.url}
                    />
                  )}
                </div>
                <h2>{product.name}</h2>
                <p>
                  {product.compareAt && <><s>{product.compareAt}</s>{' '}</>}
                  {product.price}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}

export default ProductIndex
