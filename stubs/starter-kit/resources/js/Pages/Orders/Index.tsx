import { Head, Link } from '@inertiajs/react'
import { formatOrderStatus } from '../../Components/OrderSummary'

type Order = {
  number: string
  placedAt: string
  publicId: string
  status: string
  total: string
}

export default function OrdersIndex({ orders }: { orders: Order[] }) {
  return (
    <main>
      <Head title="Orders" />
      <h1>Orders</h1>

      {orders.length === 0 ? (
        <p>You have not placed any orders yet.</p>
      ) : (
        <ul>
          {orders.map((order) => (
            <li key={order.publicId}>
              <Link href={`/orders/${order.publicId}`}>{order.number}</Link>
              {' '}
              <span>{order.placedAt}</span>
              {' '}
              <span>{formatOrderStatus(order.status)}</span>
              {' '}
              <span>{order.total}</span>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
