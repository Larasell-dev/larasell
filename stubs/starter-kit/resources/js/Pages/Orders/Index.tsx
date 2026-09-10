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
    <main className="container mx-auto px-4 py-8">
      <Head title="Orders" />
      <h1 className="text-3xl font-semibold">Orders</h1>

      {orders.length === 0 ? (
        <p className="mt-8">You have not placed any orders yet.</p>
      ) : (
        <div className="mt-8">
          <div className="hidden border-b py-2 sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:gap-4">
            <span>Order</span>
            <span>Placed</span>
            <span>Status</span>
            <span className="text-right">Total</span>
          </div>

          <ul className="divide-y border-y sm:border-t-0">
            {orders.map((order) => (
              <li key={order.publicId}>
                <Link
                  className="flex flex-col gap-1 py-4 focus-visible:focus-ring sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-baseline sm:gap-4"
                  href={`/orders/${order.publicId}`}
                >
                  <span className="underline hover:no-underline focus:no-underline">{order.number}</span>
                  <span>{order.placedAt}</span>
                  <span>{formatOrderStatus(order.status)}</span>
                  <span className="sm:text-right">{order.total}</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      )}
    </main>
  )
}
