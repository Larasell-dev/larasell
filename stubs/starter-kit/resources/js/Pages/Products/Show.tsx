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
  compareAt: string | null
  minQuantity: number
  maxQuantity: number | null
}

type ProductImage = {
  alt: string | null
  id: number | string
  url: string
}

type Props = {
  product: {
    images: ProductImage[]
    name: string
    description: string | null
    variants: Variant[]
  }
}

const fieldClass = 'appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'

function galleryRows(images: ProductImage[]) {
  const rows: { featured: ProductImage; gallery: ProductImage[] }[] = []

  for (let index = 0; index < images.length; index += 4) {
    const [featured, ...gallery] = images.slice(index, index + 4)

    if (featured) {
      rows.push({ featured, gallery })
    }
  }

  return rows
}

export default function ProductShow({ product }: Props) {
  const { cart } = usePage<SharedProps>().props
  const [variantId, setVariantId] = useState(product.variants[0]?.id)
  const variant = product.variants.find((candidate) => String(candidate.id) === String(variantId))
  const rows = galleryRows(product.images)

  return (
    <main className="container mx-auto px-4 py-8">
      <Head title={product.name} />

      <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
        <div className="flex min-w-0 flex-col gap-4">
          {rows.length === 0 ? (
            <div className="aspect-square w-full" />
          ) : (
            rows.map(({ featured, gallery }) => (
              <div
                className={gallery.length > 0 ? 'grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,19fr)_minmax(0,6fr)]' : undefined}
                key={featured.id}
              >
                <div className="aspect-square min-w-0 w-full">
                  <img
                    alt={featured.alt ?? product.name}
                    className="size-full object-cover"
                    src={featured.url}
                  />
                </div>

                {gallery.length > 0 && (
                  <div className="grid min-w-0 grid-cols-1 gap-4 lg:h-0 lg:min-h-full lg:grid-rows-3">
                    {gallery.map((image) => (
                      <div className="aspect-square min-h-0 w-full overflow-hidden lg:aspect-auto" key={image.id}>
                        <img
                          alt={image.alt ?? product.name}
                          className="size-full object-cover"
                          src={image.url}
                        />
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))
          )}
        </div>

        <div className="lg:sticky lg:top-8">
          <h1 className="text-3xl font-semibold">{product.name}</h1>

          {variant && (
            <p className="mt-4">
              {variant.compareAt && <><s>{variant.compareAt}</s>{' '}</>}
              {variant.price}
            </p>
          )}

          {product.description && <p className="mt-4">{product.description}</p>}

          {variant === undefined ? (
            <p className="mt-8">This product is currently unavailable.</p>
          ) : (
            <Form
              action="/cart"
              className="mt-8 flex flex-col gap-4"
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
                    <div className="flex flex-col gap-2">
                      <label htmlFor="variant_id">Variant</label>
                      <select
                        className={fieldClass}
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
                    </div>
                  )}

                  <div className="flex w-full items-end gap-4">
                    <div className="flex w-20 shrink-0 flex-col gap-2">
                      <label htmlFor="quantity">Quantity</label>
                      <input
                        className={`${fieldClass} h-10 w-full min-w-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none`}
                        id="quantity"
                        key={variant.id}
                        name="quantity"
                        type="number"
                        min={variant.minQuantity}
                        max={variant.maxQuantity ?? undefined}
                        defaultValue={variant.minQuantity}
                        required
                      />
                    </div>

                    <button
                      className="h-10 min-w-0 flex-1 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring"
                      type="submit"
                      disabled={processing}
                    >
                      Add to cart
                    </button>
                  </div>

                  {errors.quantity && <p>{errors.quantity}</p>}
                  {errors.variant_id && <p>{errors.variant_id}</p>}
                </>
              )}
            </Form>
          )}
        </div>
      </div>
    </main>
  )
}
