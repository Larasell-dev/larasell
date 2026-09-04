import { Form, Head, usePage } from '@inertiajs/react'

type SharedProps = {
  cart: {
    quantity: number
  }
}

type Props = {
  product: {
    id: number | string
    image: {
      alt: string | null
      url: string
    } | null
    name: string
    description: string | null
    price: string
    minQuantity: number
    maxQuantity: number | null
  }
}

export default function ProductShow({ product }: Props) {
  const { cart } = usePage<SharedProps>().props

  return (
    <main>
      <Head title={product.name} />

      {product.image && (
        <img src={product.image.url} alt={product.image.alt ?? product.name} />
      )}

      <h1>{product.name}</h1>
      <p>{product.price}</p>
      {product.description && <p>{product.description}</p>}

      <Form
        action="/cart"
        method="post"
        optimistic={(_, formData) => ({
          cart: {
            quantity: cart.quantity + Number(formData.quantity),
          },
        })}
        options={{ only: ['cart'] }}
        resetOnSuccess={['quantity']}
      >
        {({ errors, processing }) => (
          <>
            <input type="hidden" name="product_id" value={product.id} />

            <label htmlFor="quantity">Quantity</label>{' '}
            <input
              id="quantity"
              name="quantity"
              type="number"
              min={product.minQuantity}
              max={product.maxQuantity ?? undefined}
              defaultValue={product.minQuantity}
              required
            />

            {errors.quantity && <p>{errors.quantity}</p>}
            {errors.product_id && <p>{errors.product_id}</p>}

            <button type="submit" disabled={processing}>Add to cart</button>
          </>
        )}
      </Form>
    </main>
  )
}
