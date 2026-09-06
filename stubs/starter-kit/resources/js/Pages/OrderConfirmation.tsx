import { Head } from '@inertiajs/react'

type Order = {
  customerEmail: string
  customerName: string
  discounts: Array<{
    code: string | null
    identifier: string
    name: string
    total: string
  }>
  items: Array<{
    id: number | string
    name: string
    quantity: number
    total: string
    unitPrice: string
  }>
  number: string
  status: string
  subtotal: string
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

      <h2>Items</h2>
      <ul>
        {order.items.map((item) => (
          <li key={item.id}>
            <span>{item.name}</span>{' '}
            <span>{item.quantity} x {item.unitPrice}</span>{' '}
            <span>{item.total}</span>
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
        <div>
          <dt>Total</dt>
          <dd>{order.total}</dd>
        </div>
      </dl>
    </main>
  )
}

export default OrderConfirmation

function formatStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
