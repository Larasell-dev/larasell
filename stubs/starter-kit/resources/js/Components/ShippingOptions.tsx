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
        <fieldset className="flex flex-col gap-3" key={selected ?? 'none'}>
          <legend className="mb-3 font-semibold">Delivery</legend>
          {options.map((option) => (
            <label className="flex cursor-pointer items-center gap-2" htmlFor={`shipping-option-${option.handle}`} key={option.handle}>
              <span className="relative inline-grid size-5 place-items-center">
                <input
                  className="peer col-start-1 row-start-1 size-5 appearance-none rounded-full border bg-transparent checked:border-black focus-visible:focus-ring"
                  id={`shipping-option-${option.handle}`}
                  type="radio"
                  name="shipping_option"
                  value={option.handle}
                  defaultChecked={option.handle === selected}
                  disabled={processing}
                  onChange={(event) => event.currentTarget.form?.requestSubmit()}
                />
                <span className="pointer-events-none col-start-1 row-start-1 size-2.5 rounded-full bg-black opacity-0 peer-checked:opacity-100" />
              </span>
              {option.name} — {option.price}
            </label>
          ))}
          {errors.shipping_option && <p>{errors.shipping_option}</p>}
        </fieldset>
      )}
    </Form>
  )
}
