import { Head } from '@inertiajs/react'
import OrderSummary, { type OrderSummaryOrder } from '../Components/OrderSummary'

function OrderConfirmation({ order }: { order: OrderSummaryOrder }) {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title={`Order ${order.number}`} />
      <h1 className="text-3xl font-semibold">Order confirmed</h1>
      <p className="mt-4">Thank you, {order.customerName}. Your order has been received.</p>
      <div className="mt-8">
        <OrderSummary order={order} />
      </div>
    </main>
  )
}

export default OrderConfirmation
