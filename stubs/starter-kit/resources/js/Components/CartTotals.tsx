export type CartDiscount = {
  code: string | null
  identifier: string
  name: string
  total: string
}

export default function CartTotals({
  discounts,
  quantity,
  subtotal,
  total,
}: {
  discounts: CartDiscount[]
  quantity: number
  subtotal: string | null
  total: string | null
}) {
  return (
    <dl>
      <div>
        <dt>Items</dt>
        <dd>{quantity}</dd>
      </div>
      <div>
        <dt>Subtotal</dt>
        <dd>{subtotal}</dd>
      </div>
      {discounts.map((discount) => (
        <div key={discount.identifier}>
          <dt>
            {discount.name}
            {discount.code ? ` (${discount.code})` : ''}
          </dt>
          <dd>−{discount.total}</dd>
        </div>
      ))}
      <div>
        <dt>Total</dt>
        <dd>{total}</dd>
      </div>
    </dl>
  )
}
