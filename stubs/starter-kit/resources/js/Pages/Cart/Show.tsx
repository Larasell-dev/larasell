import { Form, Head, Link } from '@inertiajs/react'
import CartTotals, { type CartDiscount } from '../../Components/CartTotals'
import LinePrice, { type PricedLine } from '../../Components/LinePrice'
import PromotionCodeForm, { type CartPromotionCode } from '../../Components/PromotionCodeForm'

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
                {item.options.length > 0 && (
                  <ul>
                    {item.options.map((option) => (
                      <li key={option.name}>{option.name}: {option.value}</li>
                    ))}
                  </ul>
                )}
                <p>{item.unitPrice} each</p>
                <p>
                  <LinePrice
                    discountTotal={item.discountTotal}
                    total={item.total}
                    totalAfterDiscount={item.totalAfterDiscount}
                  />
                </p>

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

          <PromotionCodeForm promotionCodes={cart.promotionCodes} />

          <CartTotals
            discounts={cart.discounts}
            quantity={cart.quantity}
            subtotal={cart.subtotal}
            total={cart.total}
          />

          <p>
            <Link href="/checkout">Checkout</Link>
          </p>
        </>
      )}
    </main>
  )
}
