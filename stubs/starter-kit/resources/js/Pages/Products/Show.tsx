import { Form, Head, usePage } from '@inertiajs/react'
import { useState } from 'react'

type SharedProps = {
  cart: {
    quantity: number
  }
}

type Variant = {
  id: number | string
  name: string
  price: string
  minQuantity: number
  maxQuantity: number | null
}

type Props = {
  product: {
    image: {
      alt: string | null
      url: string
    } | null
    name: string
    description: string | null
    variants: Variant[]
  }
}

export default function ProductShow({ product }: Props) {
  const { cart } = usePage<SharedProps>().props
  const [variantId, setVariantId] = useState(product.variants[0]?.id)
  const variant = product.variants.find((candidate) => String(candidate.id) === String(variantId))

  return (
    <main>
      <Head title={product.name} />

      {product.image && (
        <img src={product.image.url} alt={product.image.alt ?? product.name} />
      )}

      <h1>{product.name}</h1>
      {variant && <p>{variant.price}</p>}
      {product.description && <p>{product.description}</p>}

      {variant === undefined ? (
        <p>This product is currently unavailable.</p>
      ) : (
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
              {product.variants.length === 1 ? (
                <input type="hidden" name="variant_id" value={variant.id} />
              ) : (
                <p>
                  <label htmlFor="variant_id">Variant</label>{' '}
                  <select
                    id="variant_id"
                    name="variant_id"
                    value={String(variant.id)}
                    onChange={(event) => setVariantId(event.target.value)}
                    required
                  >
                    {product.variants.map((candidate) => (
                      <option key={candidate.id} value={candidate.id}>
                        {candidate.name}
                      </option>
                    ))}
                  </select>
                </p>
              )}

              <label htmlFor="quantity">Quantity</label>{' '}
              <input
                id="quantity"
                key={variant.id}
                name="quantity"
                type="number"
                min={variant.minQuantity}
                max={variant.maxQuantity ?? undefined}
                defaultValue={variant.minQuantity}
                required
              />

              {errors.quantity && <p>{errors.quantity}</p>}
              {errors.variant_id && <p>{errors.variant_id}</p>}

              <button type="submit" disabled={processing}>Add to cart</button>
            </>
          )}
        </Form>
      )}
    </main>
  )
}
