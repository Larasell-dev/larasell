import { Head, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import EmptyState from '../../Components/EmptyState'
import Icon from '../../Components/Icon'
import Table, { type PaginationData } from '../../Components/Table'

type Order = {
  createdAt: string
  currency: string
  customerEmail: string
  id: number | string
  number: string
  status: 'pending_payment' | 'paid' | 'payment_failed' | 'fulfilled' | 'cancelled'
  total: { amount: string }
  url: string
}

type Props = AdminLayoutProps & {
  orders: Order[]
  pagination: PaginationData
}

export default function OrderIndex({ orders, pagination, ...layoutProps }: Props) {
  return (
    <AdminLayout active="orders" {...layoutProps}>
      <Head title="Orders" />
      {orders.length === 0 ? (
        <div {...stylex.props(styles.emptyStateWrapper)}>
          <EmptyState
            description="Orders will appear here after customers complete checkout."
            renderIcon={() => <Icon name="orders" height={24} width={24} />}
            title="No orders yet"
          />
        </div>
      ) : (
        <Table.Frame>
          <Table.Scroll>
            <Table.Root>
              <Table.Header>
                <tr>
                  <Table.Heading>Order</Table.Heading>
                  <Table.Heading>Customer</Table.Heading>
                  <Table.Heading>Status</Table.Heading>
                  <Table.Heading>Date</Table.Heading>
                  <Table.Heading numeric>Total</Table.Heading>
                </tr>
              </Table.Header>
              <Table.Body>
                {orders.map((order, index) => (
                  <Table.Row first={index === 0} interactive key={order.id} onClick={() => router.visit(order.url)}>
                    <Table.Cell selectable>
                      <Link href={order.url} onClick={(event) => event.stopPropagation()} {...stylex.props(styles.orderNumber)}>
                        {order.number}
                      </Link>
                    </Table.Cell>
                    <Table.Cell selectable>
                      <span {...stylex.props(styles.customerEmail)}>{order.customerEmail}</span>
                    </Table.Cell>
                    <Table.Cell>
                      <span {...stylex.props(styles.status, statusStyle(order.status))}>{formatStatus(order.status)}</span>
                    </Table.Cell>
                    <Table.Cell selectable>{formatDate(order.createdAt)}</Table.Cell>
                    <Table.Cell numeric selectable>{formatPrice(order.total, order.currency)}</Table.Cell>
                  </Table.Row>
                ))}
              </Table.Body>
            </Table.Root>
          </Table.Scroll>
          <Table.Pagination data={pagination} itemLabel="orders" label="Order pagination" />
        </Table.Frame>
      )}
    </AdminLayout>
  )
}

function formatPrice(price: Order['total'], currency: string) {
  const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits
  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function formatStatus(status: Order['status']) {
  return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function statusStyle(status: Order['status']) {
  if (status === 'paid' || status === 'fulfilled') return styles.statusSuccess
  if (status === 'payment_failed' || status === 'cancelled') return styles.statusDanger
  return styles.statusPending
}

const styles = stylex.create({
  emptyStateWrapper: { height: '100%', marginInline: 'auto', maxWidth: 360, width: '100%' },
  customerEmail: { color: 'var(--color-neutral-950)', fontSize: 14 },
  orderNumber: { borderRadius: 4, color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3 },
  status: { borderRadius: 4, display: 'inline-block', fontSize: 12, fontWeight: 600, paddingBlock: 3, paddingInline: 7 },
  statusSuccess: { backgroundColor: '#dcfce7', color: '#166534' },
  statusDanger: { backgroundColor: '#fee2e2', color: '#991b1b' },
  statusPending: { backgroundColor: '#fef3c7', color: '#92400e' },
})
