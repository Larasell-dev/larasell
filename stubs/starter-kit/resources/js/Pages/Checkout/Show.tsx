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
  }
  idempotencyKey: string
}

export default function CheckoutShow({ cart, idempotencyKey }: Props) {
  return (
    <main>
      <Head title="Checkout" />
      <h1>Checkout</h1>

      <p>
        <Link href="/cart">Back to cart</Link>
      </p>

      <ul>
        {cart.items.map((item) => (
          <li key={item.id}>
            <h2>{item.name}</h2>
            <p>{item.quantity} x {item.unitPrice}</p>
            <p>{item.total}</p>
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

      <Form action="/checkout" method="post">
        {({ errors, processing }) => (
          <>
            <input type="hidden" name="idempotency_key" value={idempotencyKey} />

            <p>
              <label htmlFor="email">Email</label>{' '}
              <input id="email" name="email" type="email" required />
              {errors.email && <span> {errors.email}</span>}
            </p>

            <p>
              <label htmlFor="first_name">First name</label>{' '}
              <input id="first_name" name="first_name" type="text" required />
              {errors.first_name && <span> {errors.first_name}</span>}
            </p>

            <p>
              <label htmlFor="last_name">Last name</label>{' '}
              <input id="last_name" name="last_name" type="text" required />
              {errors.last_name && <span> {errors.last_name}</span>}
            </p>

            <p>
              <label htmlFor="street">Street</label>{' '}
              <input id="street" name="street" type="text" required />
              {errors.street && <span> {errors.street}</span>}
            </p>

            <p>
              <label htmlFor="city">City</label>{' '}
              <input id="city" name="city" type="text" required />
              {errors.city && <span> {errors.city}</span>}
            </p>

            <p>
              <label htmlFor="postcode">Postcode</label>{' '}
              <input id="postcode" name="postcode" type="text" required />
              {errors.postcode && <span> {errors.postcode}</span>}
            </p>

            <p>
              <label htmlFor="country">Country</label>{' '}
              <input id="country" name="country" type="text" required />
              {errors.country && <span> {errors.country}</span>}
            </p>

            {errors.checkout && <p>{errors.checkout}</p>}

            <button type="submit" disabled={processing}>Place order</button>
          </>
        )}
      </Form>
    </main>
  )
}
