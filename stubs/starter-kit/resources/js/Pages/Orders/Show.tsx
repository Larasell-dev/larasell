import { Head, Link } from '@inertiajs/react'
import OrderSummary, { type OrderSummaryOrder } from '../../Components/OrderSummary'

export default function OrderShow({ order }: { order: OrderSummaryOrder }) {
  return (
    <main>
      <Head title={`Order ${order.number}`} />
      <p>
        <Link href="/orders">All orders</Link>
      </p>
      <h1>Order {order.number}</h1>
      <OrderSummary order={order} />
    </main>
  )
}
