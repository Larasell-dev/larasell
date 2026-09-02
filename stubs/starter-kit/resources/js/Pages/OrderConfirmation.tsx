import { Head } from '@inertiajs/react'

type Price = {
  amount: string
}

type Order = {
  currency: string
  customerEmail: string
  customerName: string
  items: Array<{
    id: number | string
    name: string
    quantity: number
    total: Price
    unitPrice: Price
  }>
  number: string
  status: string
  subtotal: Price
  total: Price
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
            <span>{item.quantity} x {formatPrice(item.unitPrice, order.currency)}</span>{' '}
            <span>{formatPrice(item.total, order.currency)}</span>
          </li>
        ))}
      </ul>

      <dl>
        <dt>Subtotal</dt>
        <dd>{formatPrice(order.subtotal, order.currency)}</dd>
        <dt>Total</dt>
        <dd>{formatPrice(order.total, order.currency)}</dd>
      </dl>
    </main>
  )
}

function formatPrice(price: Price, currency: string) {
  const formatter = new Intl.NumberFormat(undefined, { currency, style: 'currency' })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits ?? 2

  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}

function formatStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
