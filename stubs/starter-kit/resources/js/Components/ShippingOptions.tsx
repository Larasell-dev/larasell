import { Form } from '@inertiajs/react'

export type CartShippingOption = {
  handle: string
  name: string
  price: string
}

export default function ShippingOptions({
  options,
  selected,
}: {
  options: CartShippingOption[]
  selected: string | null
}) {
  if (options.length === 0) {
    return null
  }

  return (
    <Form
      action="/cart/shipping-option"
      method="patch"
      options={{ preserveScroll: true }}
    >
      {({ errors, processing }) => (
        <fieldset key={selected ?? 'none'}>
          <legend>Delivery</legend>
          {options.map((option) => (
            <p key={option.handle}>
              <label htmlFor={`shipping-option-${option.handle}`}>
                <input
                  id={`shipping-option-${option.handle}`}
                  type="radio"
                  name="shipping_option"
                  value={option.handle}
                  defaultChecked={option.handle === selected}
                  disabled={processing}
                  onChange={(event) => event.currentTarget.form?.requestSubmit()}
                />
                {' '}{option.name} — {option.price}
              </label>
            </p>
          ))}
          {errors.shipping_option && <p>{errors.shipping_option}</p>}
        </fieldset>
      )}
    </Form>
  )
}
