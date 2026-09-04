import { Form, Head, Link } from '@inertiajs/react'

type Props = {
  cart: {
    items: Array<{
      id: number | string
      name: string
      quantity: number
      unitPrice: string
      total: string
    }>
    quantity: number
    subtotal: string | null
    total: string | null
  } | null
}

export default function CartShow({ cart }: Props) {
  return (
    <main>
      <Head title="Cart" />
      <h1>Cart</h1>

      {cart === null || cart.items.length === 0 ? (
        <>
          <p>Your cart is empty.</p>
          <Link href="/">Continue shopping</Link>
        </>
      ) : (
        <>
          <ul>
            {cart.items.map((item) => (
              <li key={item.id}>
                <h2>{item.name}</h2>
                <p>{item.unitPrice} each</p>
                <p>{item.total}</p>

                <Form
                  action={`/cart/items/${item.id}`}
                  errorBag={`updateCartItem${item.id}`}
                  method="patch"
                  options={{ preserveScroll: true }}
                >
                  {({ errors, processing }) => (
                    <>
                      <label htmlFor={`quantity-${item.id}`}>Quantity</label>{' '}
                      <input
                        key={item.quantity}
                        id={`quantity-${item.id}`}
                        name="quantity"
                        type="number"
                        min={1}
                        defaultValue={item.quantity}
                        required
                      />{' '}
                      <button type="submit" disabled={processing}>Update</button>
                      {errors.quantity && <p>{errors.quantity}</p>}
                    </>
                  )}
                </Form>

                <Form
                  action={`/cart/items/${item.id}`}
                  method="delete"
                  options={{ preserveScroll: true }}
                >
                  {({ processing }) => (
                    <button type="submit" disabled={processing}>Remove</button>
                  )}
                </Form>
              </li>
            ))}
          </ul>

          <dl>
            <div>
              <dt>Items</dt>
              <dd>{cart.quantity}</dd>
            </div>
            <div>
              <dt>Subtotal</dt>
              <dd>{cart.subtotal}</dd>
            </div>
            <div>
              <dt>Total</dt>
              <dd>{cart.total}</dd>
            </div>
          </dl>

          <p>
            <Link href="/checkout">Checkout</Link>
          </p>
        </>
      )}
    </main>
  )
}
