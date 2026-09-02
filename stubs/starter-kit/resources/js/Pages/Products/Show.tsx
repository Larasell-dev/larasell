import { Head } from '@inertiajs/react'

type ProductImage = {
  alt: string | null
  id: number | string
  url: string
}

type Product = {
  description: string | null
  id: number | string
  images: ProductImage[]
  name: string
  price: string
  slug: string
}

type Props = {
  product: Product
}

export default function ProductShow({ product }: Props) {
  return (
    <main>
      <Head title={product.name} />

      <article>
        <header>
          <h1>{product.name}</h1>
          <p>{product.price}</p>
        </header>

        {product.images.length > 0 && (
          <ul>
            {product.images.map((image) => (
              <li key={image.id}>
                <img src={image.url} alt={image.alt ?? product.name} />
              </li>
            ))}
          </ul>
        )}

        {product.description && <p>{product.description}</p>}

        <form>
          <input type="hidden" name="product_id" value={product.id} />

          <label htmlFor="quantity">Quantity</label>{' '}
          <input id="quantity" name="quantity" type="number" min="1" defaultValue="1" />

          <button type="submit">Add to cart</button>
        </form>
      </article>
    </main>
  )
}
