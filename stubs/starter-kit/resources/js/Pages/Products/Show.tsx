import { Form, Head, router } from '@inertiajs/react'

type ProductImage = {
  alt: string | null
  id: number | string
  url: string
}

type Product = {
  description: string | null
  id: number | string
  images: ProductImage[]
  maxQuantity: number | null
  minQuantity: number | null
  name: string
  price: string
  slug: string
}

type Props = {
  addToCartUrl: string
  product: Product
}

export default function ProductShow({ addToCartUrl, product }: Props) {
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

        <Form
          action={addToCartUrl}
          method="post"
          onSuccess={() => router.reload({ only: ['cart'] })}
          optimistic={(props, formData) => {
            const quantity = Number(formData.quantity ?? 0)

            return {
              cart: {
                quantity: props.cart.quantity + (Number.isFinite(quantity) ? quantity : 0),
              },
            }
          }}
        >
          <input type="hidden" name="product_id" value={product.id} />

          <label htmlFor="quantity">Quantity</label>{' '}
          <input
            id="quantity"
            name="quantity"
            type="number"
            min={product.minQuantity ?? 1}
            max={product.maxQuantity ?? undefined}
            defaultValue={product.minQuantity ?? 1}
          />

          <button type="submit">Add to cart</button>
        </Form>
      </article>
    </main>
  )
}
