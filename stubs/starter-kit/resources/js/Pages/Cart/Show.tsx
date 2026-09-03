import { Form, Head, router } from '@inertiajs/react'

type CartItem = {
  id: number | string
  maxQuantity: number | null
  minQuantity: number | null
  productName: string
  quantity: number
  sku: string | null
  total: string
  unitPrice: string
  updateUrl: string
  removeUrl: string
}

type Discount = {
  code: string | null
  identifier: string
  name: string
  total: string
}

type Cart = {
  currency: string
  discountTotal: string
  discounts: Discount[]
  id: number | string
  items: CartItem[]
  promotionCodes: string[]
  quantity: number
  shipping: {
    name: string
    price: string
  } | null
  subtotal: string | null
  total: string | null
}

type Props = {
  cart: Cart | null
}

export default function CartShow({ cart }: Props) {
  return (
    <main>
      <Head title="Cart" />
      <h1>Cart</h1>

      {cart === null || cart.items.length === 0 ? (
        <p>Your cart is empty.</p>
      ) : (
        <>
          <dl>
            <div>
              <dt>Items</dt>
              <dd>{cart.quantity}</dd>
            </div>
            <div>
              <dt>Currency</dt>
              <dd>{cart.currency}</dd>
            </div>
          </dl>

          <table>
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">SKU</th>
                <th scope="col">Unit price</th>
                <th scope="col">Quantity</th>
                <th scope="col">Total</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              {cart.items.map((item) => (
                <tr key={item.id}>
                  <th scope="row">{item.productName}</th>
                  <td>{item.sku ?? ''}</td>
                  <td>{item.unitPrice}</td>
                  <td>
                    <Form
                      action={item.updateUrl}
                      method="patch"
                      onSuccess={() => router.reload({ only: ['cart'] })}
                      optimistic={(props, formData) => {
                        const quantity = Number(formData.quantity)

                        if (!Number.isFinite(quantity)) {
                          return props
                        }

                        return {
                          cart: {
                            ...props.cart,
                            quantity: Math.max(0, props.cart.quantity + quantity - item.quantity),
                          },
                        }
                      }}
                    >
                      <label htmlFor={`quantity-${item.id}`}>Quantity</label>{' '}
                      <input
                        id={`quantity-${item.id}`}
                        name="quantity"
                        type="number"
                        min={item.minQuantity ?? 1}
                        max={item.maxQuantity ?? undefined}
                        defaultValue={item.quantity}
                      />
                      <button type="submit">Update</button>
                    </Form>
                  </td>
                  <td>{item.total}</td>
                  <td>
                    <Form
                      action={item.removeUrl}
                      method="delete"
                      onSuccess={() => router.reload({ only: ['cart'] })}
                      optimistic={(props) => ({
                        cart: {
                          ...props.cart,
                          quantity: Math.max(0, props.cart.quantity - item.quantity),
                        },
                      })}
                    >
                      <button type="submit">Remove</button>
                    </Form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {cart.promotionCodes.length > 0 && (
            <section>
              <h2>Promotion codes</h2>
              <ul>
                {cart.promotionCodes.map((code) => (
                  <li key={code}>{code}</li>
                ))}
              </ul>
            </section>
          )}

          {cart.discounts.length > 0 && (
            <section>
              <h2>Discounts</h2>
              <ul>
                {cart.discounts.map((discount) => (
                  <li key={discount.identifier}>
                    {discount.name}: {discount.total}
                    {discount.code && <> ({discount.code})</>}
                  </li>
                ))}
              </ul>
            </section>
          )}

          <dl>
            <div>
              <dt>Subtotal</dt>
              <dd>{cart.subtotal}</dd>
            </div>
            {cart.shipping && (
              <div>
                <dt>Shipping</dt>
                <dd>
                  {cart.shipping.name}: {cart.shipping.price}
                </dd>
              </div>
            )}
            <div>
              <dt>Discounts</dt>
              <dd>{cart.discountTotal}</dd>
            </div>
            <div>
              <dt>Total</dt>
              <dd>{cart.total}</dd>
            </div>
          </dl>
        </>
      )}
    </main>
  )
}
