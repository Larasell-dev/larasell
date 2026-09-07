import { Head } from '@inertiajs/react'
import LinePrice, { type PricedLine } from '../Components/LinePrice'

type Order = {
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

function OrderConfirmation({ order }: { order: Order }) {
  return (
    <main>
      <Head title={`Order ${order.number}`} />
      <h1>Order confirmed</h1>
      <p>Thank you, {order.customerName}. Your order has been received.</p>

      <dl>
        <dt>Order number</dt>
        <dd>{order.number}</dd>
        <dt>Email</dt>
        <dd>{order.customerEmail}</dd>
        <dt>Status</dt>
        <dd>{formatStatus(order.status)}</dd>
      </dl>

      {order.shippingAddress && (
        <>
          <h2>{order.billingAddress ? 'Shipping address' : 'Address'}</h2>
          <AddressLines lines={order.shippingAddress} />
        </>
      )}

      {order.billingAddress && (
        <>
          <h2>Billing address</h2>
          <AddressLines lines={order.billingAddress} />
        </>
      )}

      <h2>Items</h2>
      <ul>
        {order.items.map((item) => (
          <li key={item.id}>
            <span>{item.name}</span>{' '}
            <span>{item.quantity} x {item.unitPrice}</span>{' '}
            <LinePrice
              discountTotal={item.discountTotal}
              total={item.total}
              totalAfterDiscount={item.totalAfterDiscount}
            />
          </li>
        ))}
      </ul>

      <dl>
        <div>
          <dt>Subtotal</dt>
          <dd>{order.subtotal}</dd>
        </div>
        {order.discounts.map((discount) => (
          <div key={discount.identifier}>
            <dt>
              {discount.name}
              {discount.code ? ` (${discount.code})` : ''}
            </dt>
            <dd>−{discount.total}</dd>
          </div>
        ))}
        {order.shipping && (
          <div>
            <dt>{order.shipping.name ?? 'Shipping'}</dt>
            <dd>{order.shipping.price}</dd>
          </div>
        )}
        {order.tax !== null && (
          <div>
            <dt>Tax</dt>
            <dd>{order.tax}</dd>
          </div>
        )}
        <div>
          <dt>Total</dt>
          <dd>{order.total}</dd>
        </div>
      </dl>
    </main>
  )
}

export default OrderConfirmation

function AddressLines({ lines }: { lines: string[] }) {
  return (
    <address>
      {lines.map((line) => (
        <div key={line}>{line}</div>
      ))}
    </address>
  )
}

function formatStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
