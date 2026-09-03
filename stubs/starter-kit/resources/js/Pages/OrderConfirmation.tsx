import { Head } from '@inertiajs/react'
import { useI18n } from '../i18n'

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

function OrderConfirmation({ order }: { order: Order }) {
  const { t } = useI18n()

  return (
    <main>
      <Head title={t('orders.confirmation.title', { number: order.number })} />
      <h1>{t('orders.confirmation.heading')}</h1>
      <p>{t('orders.confirmation.message', { name: order.customerName })}</p>

      <dl>
        <dt>{t('orders.order_number')}</dt>
        <dd>{order.number}</dd>
        <dt>{t('orders.email')}</dt>
        <dd>{order.customerEmail}</dd>
        <dt>{t('orders.status')}</dt>
        <dd>{formatStatus(order.status)}</dd>
      </dl>

      <h2>{t('orders.items')}</h2>
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
        <dt>{t('orders.subtotal')}</dt>
        <dd>{order.subtotal}</dd>
        <dt>{t('orders.total')}</dt>
        <dd>{order.total}</dd>
      </dl>
    </main>
  )
}

OrderConfirmation.translation = ['orders']

export default OrderConfirmation

function formatStatus(status: string) {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
