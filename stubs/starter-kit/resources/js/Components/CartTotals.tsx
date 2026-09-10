export type CartDiscount = {
  code: string | null
  identifier: string
  name: string
  total: string
}

export type CartShipping = {
  name: string | null
  price: string | null
}

export type CartTax = {
  amount: string | null
  priceMode: 'inclusive' | 'exclusive'
  reason: string | null
  status: 'calculated' | 'provisional' | 'unavailable'
}

export default function CartTotals({
  discounts,
  quantity,
  shipping,
  subtotal,
  tax,
  total,
}: {
  discounts: CartDiscount[]
  quantity: number
  shipping: CartShipping
  subtotal: string | null
  tax: CartTax
  total: string | null
}) {
  return (
    <dl className="flex flex-col gap-2">
      <div className="flex justify-between gap-8">
        <dt>Items</dt>
        <dd>{quantity}</dd>
      </div>
      <div className="flex justify-between gap-8">
        <dt>Subtotal</dt>
        <dd>{subtotal}</dd>
      </div>
      {discounts.map((discount) => (
        <div className="flex justify-between gap-8" key={discount.identifier}>
          <dt>
            {discount.name}
            {discount.code ? ` (${discount.code})` : ''}
          </dt>
          <dd>−{discount.total}</dd>
        </div>
      ))}
      {shipping.price !== null && (
        <div className="flex justify-between gap-8">
          <dt>{shipping.name ?? 'Shipping'}</dt>
          <dd>{shipping.price}</dd>
        </div>
      )}
      <div className="flex justify-between gap-8">
        <dt>{tax.priceMode === 'inclusive' ? 'Included tax' : 'Tax'}</dt>
        <dd>
          {tax.status === 'unavailable' || tax.amount === null
            ? 'Calculated at checkout'
            : tax.status === 'provisional'
              ? `${tax.amount} (estimated)`
              : tax.amount}
        </dd>
      </div>
      <div className="flex justify-between gap-8 border-t pt-2 font-semibold">
        <dt>Total</dt>
        <dd>{total ?? 'Calculated at checkout'}</dd>
      </div>
    </dl>
  )
}
