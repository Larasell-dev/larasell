import { Form, Head, Link, usePage } from '@inertiajs/react'
import { useState } from 'react'
import CartTotals, { type CartDiscount, type CartShipping, type CartTax } from '../../Components/CartTotals'
import LinePrice, { type PricedLine } from '../../Components/LinePrice'
import PromotionCodeForm, { type CartPromotionCode } from '../../Components/PromotionCodeForm'
import ShippingOptions, { type CartShippingOption } from '../../Components/ShippingOptions'

const fieldClass = 'h-10 appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'
const primaryButtonClass = 'h-10 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring'

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
      options: Array<{
        name: string
        value: string
      }>
      quantity: number
      unitPrice: string
    }>
    promotionCodes: CartPromotionCode[]
    quantity: number
    shipping: CartShipping & {
      options: CartShippingOption[]
      selected: string | null
    }
    subtotal: string | null
    tax: CartTax
    total: string | null
  }
  customer: {
    email: string
    firstName: string
    lastName: string
  } | null
  idempotencyKey: string
}

export default function CheckoutShow({ cart, customer, idempotencyKey }: Props) {
  const pageErrors = usePage().props.errors
  const [billingSameAsShipping, setBillingSameAsShipping] = useState(
    () => !BILLING_FIELDS.some((field) => pageErrors[field]),
  )

  return (
    <main className="container mx-auto px-4 py-8">
      <Head title="Checkout" />
      <p>
        <Link className="underline hover:no-underline focus:no-underline focus-visible:focus-ring" href="/cart" prefetch cacheFor="0s">Back to cart</Link>
      </p>
      <h1 className="mt-4 text-3xl font-semibold">Checkout</h1>

      <div className="mt-8 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
        <Form action="/checkout" className="flex w-full max-w-xl flex-col gap-8" method="post">
          {({ errors, processing }) => (
            <>
              <input type="hidden" name="idempotency_key" value={idempotencyKey} />
              <input type="hidden" name="billing_same_as_shipping" value={billingSameAsShipping ? '1' : '0'} />

              <div className="flex flex-col gap-2">
                <label htmlFor="email">Email</label>
                <input
                  autoComplete="email"
                  className={fieldClass}
                  defaultValue={customer?.email ?? ''}
                  id="email"
                  name="email"
                  required
                  type="email"
                />
                {errors.email && <p>{errors.email}</p>}
              </div>

              <fieldset className="flex flex-col gap-4">
                <legend className="mb-4 font-semibold">Shipping address</legend>
                <AddressFields
                  defaults={{
                    first_name: customer?.firstName ?? '',
                    last_name: customer?.lastName ?? '',
                  }}
                  errors={errors}
                  prefix="shipping"
                />
              </fieldset>

              <label className="flex cursor-pointer items-center gap-2">
                <span className="relative inline-grid size-5 place-items-center">
                  <input
                    checked={billingSameAsShipping}
                    className="peer col-start-1 row-start-1 size-5 appearance-none border bg-transparent checked:border-black checked:bg-black focus-visible:focus-ring"
                    id="billing_same_as_shipping"
                    onChange={(event) => setBillingSameAsShipping(event.target.checked)}
                    type="checkbox"
                  />
                  <svg
                    aria-hidden="true"
                    className="pointer-events-none col-start-1 row-start-1 size-3.5 text-white opacity-0 peer-checked:opacity-100"
                    fill="none"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M3.5 8.5 6.5 11.5 12.5 4.5"
                      stroke="currentColor"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                    />
                  </svg>
                </span>
                Billing address is the same as shipping address
              </label>

              {!billingSameAsShipping && (
                <fieldset className="flex flex-col gap-4">
                  <legend className="mb-4 font-semibold">Billing address</legend>
                  <AddressFields
                    defaults={{
                      first_name: customer?.firstName ?? '',
                      last_name: customer?.lastName ?? '',
                    }}
                    errors={errors}
                    prefix="billing"
                  />
                </fieldset>
              )}

              {errors.checkout && <p>{errors.checkout}</p>}

              <button className={`${primaryButtonClass} self-start`} disabled={processing} type="submit">
                Place order
              </button>
            </>
          )}
        </Form>

        <div className="flex flex-col gap-8">
          <ul className="divide-y">
            {cart.items.map((item) => (
              <li className="flex flex-col gap-1 py-6 first:pt-0 last:pb-0" key={item.id}>
                <div className="flex items-baseline justify-between gap-8">
                  <h2 className="font-semibold">{item.name}</h2>
                  <LinePrice
                    discountTotal={item.discountTotal}
                    total={item.total}
                    totalAfterDiscount={item.totalAfterDiscount}
                  />
                </div>
                <p>{item.quantity} x {item.unitPrice}</p>
                {item.options.length > 0 && (
                  <ul>
                    {item.options.map((option) => (
                      <li key={option.name}>{option.name}: {option.value}</li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>

          <PromotionCodeForm promotionCodes={cart.promotionCodes} />

          <ShippingOptions options={cart.shipping.options} selected={cart.shipping.selected} />

          <CartTotals
            discounts={cart.discounts}
            quantity={cart.quantity}
            shipping={cart.shipping}
            subtotal={cart.subtotal}
            tax={cart.tax}
            total={cart.total}
          />
        </div>
      </div>
    </main>
  )
}

const ADDRESS_FIELDS = [
  { autoComplete: 'given-name', name: 'first_name', label: 'First name', wide: false },
  { autoComplete: 'family-name', name: 'last_name', label: 'Last name', wide: false },
  { autoComplete: 'street-address', name: 'street', label: 'Street', wide: true },
  { autoComplete: 'address-level2', name: 'city', label: 'City', wide: false },
  { autoComplete: 'postal-code', name: 'postcode', label: 'Postcode', wide: false },
  { autoComplete: 'country-name', name: 'country', label: 'Country', wide: true },
] as const

function AddressFields({
  defaults = {},
  errors,
  prefix,
}: {
  defaults?: Partial<Record<(typeof ADDRESS_FIELDS)[number]['name'], string>>
  errors: Record<string, string>
  prefix: AddressPrefix
}) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {ADDRESS_FIELDS.map((field) => {
        const name = `${prefix}_${field.name}`

        return (
          <div className={`flex flex-col gap-2 ${field.wide ? 'sm:col-span-2' : ''}`} key={name}>
            <label htmlFor={name}>{field.label}</label>
            <input
              autoComplete={field.autoComplete}
              className={fieldClass}
              defaultValue={defaults[field.name] ?? ''}
              id={name}
              name={name}
              required
              type="text"
            />
            {errors[name] && <p>{errors[name]}</p>}
          </div>
        )
      })}
    </div>
  )
}
