import { Head } from '@inertiajs/react'

type Order = {
  customerEmail: string
  customerName: string
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

export default function OrderConfirmation({ order }: { order: Order }) {
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
        <dt>Subtotal</dt>
        <dd>{order.subtotal}</dd>
        <dt>Total</dt>
        <dd>{order.total}</dd>
      </dl>
    </main>
  )
}

function formatStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
