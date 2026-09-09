import { Head } from '@inertiajs/react'
import OrderSummary, { type OrderSummaryOrder } from '../Components/OrderSummary'

function OrderConfirmation({ order }: { order: OrderSummaryOrder }) {
  return (
    <main>
      <Head title={`Order ${order.number}`} />
      <h1>Order confirmed</h1>
      <p>Thank you, {order.customerName}. Your order has been received.</p>
      <OrderSummary order={order} />
    </main>
  )
}

export default OrderConfirmation
