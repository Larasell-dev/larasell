import { Form, Head, Link } from '@inertiajs/react'
import CartTotals, { type CartDiscount, type CartShipping, type CartTax } from '../../Components/CartTotals'
import Image, { type ImagePlaceholder } from '../../Components/Image'
import LinePrice, { type PricedLine } from '../../Components/LinePrice'
import PromotionCodeForm, { type CartPromotionCode } from '../../Components/PromotionCodeForm'
import ShippingOptions, { type CartShippingOption } from '../../Components/ShippingOptions'

const fieldClass = 'h-10 appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'
const primaryButtonClass = 'h-10 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring'

type Props = {
  cart: {
    discounts: CartDiscount[]
    items: Array<PricedLine & {
      id: number | string
      image: {
        alt: string | null
        placeholder: ImagePlaceholder | null
        url: string
      } | null
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
  } | null
}

export default function CartShow({ cart }: Props) {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title="Cart" />
      <h1 className="text-3xl font-semibold">Cart</h1>

      {cart === null || cart.items.length === 0 ? (
        <div className="mt-8">
          <p>Your cart is empty.</p>
          <p className="mt-4">
            <Link className="underline hover:no-underline focus:no-underline focus-visible:focus-ring" href="/">Continue shopping</Link>
          </p>
        </div>
      ) : (
        <div className="mt-8 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
          <ul className="divide-y">
            {cart.items.map((item) => (
              <li className="flex flex-col gap-4 py-6 first:pt-0" key={item.id}>
                <div className="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-x-3">
                  <div className="relative row-span-2 aspect-square h-full w-auto overflow-hidden">
                    {item.image && (
                      <Image
                        alt={item.image.alt ?? item.name}
                        className="absolute inset-0 size-full object-cover"
                        placeholder={item.image.placeholder}
                        src={item.image.url}
                      />
                    )}
                  </div>
                  <h2 className="font-semibold">{item.name}</h2>
                  <LinePrice
                    discountTotal={item.discountTotal}
                    total={item.total}
                    totalAfterDiscount={item.totalAfterDiscount}
                  />
                  <p className="col-start-2">{item.unitPrice} each</p>
                </div>

                {item.options.length > 0 && (
                  <ul>
                    {item.options.map((option) => (
                      <li key={option.name}>{option.name}: {option.value}</li>
                    ))}
                  </ul>
                )}

                <div className="flex flex-wrap items-end gap-4">
                  <Form
                    action={`/cart/items/${item.id}`}
                    className="flex flex-wrap items-end gap-4"
                    errorBag={`updateCartItem${item.id}`}
                    method="patch"
                    options={{ preserveScroll: true }}
                  >
                    {({ errors, processing }) => (
                      <>
                        <div className="flex flex-col gap-2">
                          <label htmlFor={`quantity-${item.id}`}>Quantity</label>
                          <input
                            className={`${fieldClass} w-20 min-w-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none`}
                            key={item.quantity}
                            id={`quantity-${item.id}`}
                            name="quantity"
                            type="number"
                            min={1}
                            defaultValue={item.quantity}
                            required
                          />
                        </div>
                        <button className={primaryButtonClass} type="submit" disabled={processing}>
                          Update
                        </button>
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
                      <button
                        className="h-10 cursor-pointer underline hover:no-underline focus:no-underline focus-visible:focus-ring"
                        type="submit"
                        disabled={processing}
                      >
                        Remove
                      </button>
                    )}
                  </Form>
                </div>
              </li>
            ))}
          </ul>

          <div className="flex flex-col gap-8">
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

            <Link className={`flex items-center justify-center ${primaryButtonClass}`} href="/checkout">
              Checkout
            </Link>
          </div>
        </div>
      )}
    </main>
  )
}
