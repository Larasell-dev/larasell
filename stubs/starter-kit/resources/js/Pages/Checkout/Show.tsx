import { Form, Head, Link, usePage } from '@inertiajs/react'
import { useState } from 'react'
import CartTotals, { type CartDiscount } from '../../Components/CartTotals'
import LinePrice, { type PricedLine } from '../../Components/LinePrice'
import PromotionCodeForm, { type CartPromotionCode } from '../../Components/PromotionCodeForm'

const BILLING_FIELDS = [
  'billing_first_name',
  'billing_last_name',
  'billing_street',
  'billing_city',
  'billing_postcode',
  'billing_country',
] as const

type AddressPrefix = 'billing' | 'shipping'

type Props = {
  cart: {
    discounts: CartDiscount[]
    items: Array<PricedLine & {
      id: number | string
      name: string
      quantity: number
      unitPrice: string
    }>
    promotionCodes: CartPromotionCode[]
    quantity: number
    subtotal: string | null
    total: string | null
  }
  idempotencyKey: string
}

export default function CheckoutShow({ cart, idempotencyKey }: Props) {
  const pageErrors = usePage().props.errors
  const [billingSameAsShipping, setBillingSameAsShipping] = useState(
    () => !BILLING_FIELDS.some((field) => pageErrors[field]),
  )

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
            <p>
              <LinePrice
                discountTotal={item.discountTotal}
                total={item.total}
                totalAfterDiscount={item.totalAfterDiscount}
              />
            </p>
          </li>
        ))}
      </ul>

      <PromotionCodeForm promotionCodes={cart.promotionCodes} />

      <CartTotals
        discounts={cart.discounts}
        quantity={cart.quantity}
        subtotal={cart.subtotal}
        total={cart.total}
      />

      <Form action="/checkout" method="post">
        {({ errors, processing }) => (
          <>
            <input type="hidden" name="idempotency_key" value={idempotencyKey} />
            <input type="hidden" name="billing_same_as_shipping" value={billingSameAsShipping ? '1' : '0'} />

            <p>
              <label htmlFor="email">Email</label>{' '}
              <input id="email" name="email" type="email" required />
              {errors.email && <span> {errors.email}</span>}
            </p>

            <fieldset>
              <legend>Shipping address</legend>
              <AddressFields errors={errors} prefix="shipping" />
            </fieldset>

            <p>
              <label htmlFor="billing_same_as_shipping">
                <input
                  id="billing_same_as_shipping"
                  type="checkbox"
                  checked={billingSameAsShipping}
                  onChange={(event) => setBillingSameAsShipping(event.target.checked)}
                />
                {' '}Billing address is the same as shipping address
              </label>
            </p>

            {!billingSameAsShipping && (
              <fieldset>
                <legend>Billing address</legend>
                <AddressFields errors={errors} prefix="billing" />
              </fieldset>
            )}

            {errors.checkout && <p>{errors.checkout}</p>}

            <button type="submit" disabled={processing}>Place order</button>
          </>
        )}
      </Form>
    </main>
  )
}

const ADDRESS_FIELDS = [
  { name: 'first_name', label: 'First name' },
  { name: 'last_name', label: 'Last name' },
  { name: 'street', label: 'Street' },
  { name: 'city', label: 'City' },
  { name: 'postcode', label: 'Postcode' },
  { name: 'country', label: 'Country' },
] as const

function AddressFields({
  errors,
  prefix,
}: {
  errors: Record<string, string>
  prefix: AddressPrefix
}) {
  return ADDRESS_FIELDS.map((field) => {
    const name = `${prefix}_${field.name}`

    return (
      <p key={name}>
        <label htmlFor={name}>{field.label}</label>{' '}
        <input id={name} name={name} type="text" required />
        {errors[name] && <span> {errors[name]}</span>}
      </p>
    )
  })
}
