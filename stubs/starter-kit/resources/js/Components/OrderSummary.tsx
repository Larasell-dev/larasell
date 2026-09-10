import LinePrice, { type PricedLine } from './LinePrice'

export type OrderSummaryOrder = {
  billingAddress: string[] | null
  customerEmail: string
  customerName: string
  discounts: Array<{
    code: string | null
    identifier: string
    name: string
    total: string
  }>
  items: Array<PricedLine & {
    id: number | string
    name: string
    quantity: number
    unitPrice: string
  }>
  number: string
  placedAt: string
  publicId: string
  shipping: {
    name: string | null
    price: string
  } | null
  shippingAddress: string[] | null
  status: string
  subtotal: string
  tax: string | null
  total: string
}

export function formatOrderStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

export default function OrderSummary({ order }: { order: OrderSummaryOrder }) {
  return (
    <div className="flex flex-col gap-10">
      <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <dt>Order number</dt>
          <dd>{order.number}</dd>
        </div>
        <div>
          <dt>Placed</dt>
          <dd>{order.placedAt}</dd>
        </div>
        <div>
          <dt>Email</dt>
          <dd>{order.customerEmail}</dd>
        </div>
        <div>
          <dt>Status</dt>
          <dd>{formatOrderStatus(order.status)}</dd>
        </div>
      </dl>

      {(order.shippingAddress || order.billingAddress) && (
        <div className="grid gap-8 sm:grid-cols-2">
          {order.shippingAddress && (
            <div>
              <h2 className="font-semibold">{order.billingAddress ? 'Shipping address' : 'Address'}</h2>
              <AddressLines lines={order.shippingAddress} />
            </div>
          )}

          {order.billingAddress && (
            <div>
              <h2 className="font-semibold">Billing address</h2>
              <AddressLines lines={order.billingAddress} />
            </div>
          )}
        </div>
      )}

      <div>
        <h2 className="font-semibold">Items</h2>
        <ul className="mt-4 divide-y border-y">
          {order.items.map((item) => (
            <li className="flex flex-col gap-1 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-8" key={item.id}>
              <div>
                <p>{item.name}</p>
                <p>{item.quantity} × {item.unitPrice}</p>
              </div>
              <LinePrice
                discountTotal={item.discountTotal}
                total={item.total}
                totalAfterDiscount={item.totalAfterDiscount}
              />
            </li>
          ))}
        </ul>
      </div>

      <dl className="ml-auto flex w-full max-w-sm flex-col gap-2">
        <div className="flex justify-between gap-8">
          <dt>Subtotal</dt>
          <dd>{order.subtotal}</dd>
        </div>
        {order.discounts.map((discount) => (
          <div className="flex justify-between gap-8" key={discount.identifier}>
            <dt>
              {discount.name}
              {discount.code ? ` (${discount.code})` : ''}
            </dt>
            <dd>−{discount.total}</dd>
          </div>
        ))}
        {order.shipping && (
          <div className="flex justify-between gap-8">
            <dt>{order.shipping.name ?? 'Shipping'}</dt>
            <dd>{order.shipping.price}</dd>
          </div>
        )}
        {order.tax !== null && (
          <div className="flex justify-between gap-8">
            <dt>Tax</dt>
            <dd>{order.tax}</dd>
          </div>
        )}
        <div className="flex justify-between gap-8 border-t pt-2 font-semibold">
          <dt>Total</dt>
          <dd>{order.total}</dd>
        </div>
      </dl>
    </div>
  )
}

function AddressLines({ lines }: { lines: string[] }) {
  return (
    <address className="mt-2 not-italic">
      {lines.map((line) => (
        <div key={line}>{line}</div>
      ))}
    </address>
  )
}
