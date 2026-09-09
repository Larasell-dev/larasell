import { Head, Link } from '@inertiajs/react'
import OrderSummary, { type OrderSummaryOrder } from '../../Components/OrderSummary'

export default function OrderShow({ order }: { order: OrderSummaryOrder }) {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title={`Order ${order.number}`} />
      <p>
        <Link className="underline hover:no-underline focus:no-underline focus-visible:focus-ring" href="/orders">All orders</Link>
      </p>
      <h1 className="mt-4 text-3xl font-semibold">Order {order.number}</h1>
      <div className="mt-8">
        <OrderSummary order={order} />
      </div>
    </main>
  )
}
