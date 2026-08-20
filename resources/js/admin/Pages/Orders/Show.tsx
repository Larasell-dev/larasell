import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import BackLink from '../../Components/BackLink'
import Card from '../../Components/Card'

type Price = { amount: string }

type Address = {
  city: string
  country: string
  email: string | null
  first_name: string
  last_name: string
  phone: string | null
  postcode: string
  state: string | null
  street: string[]
  title: string | null
}

type Order = {
  billingAddress: Address
  createdAt: string
  currency: string
  customerEmail: string
  customerName: string
  id: number | string
  items: Array<{
    id: number | string
    name: string
    quantity: number
    slug: string | null
    total: Price
    unitPrice: Price
  }>
  number: string
  payments: Array<{
    amount: Price
    createdAt: string
    failureMessage: string | null
    id: number | string
    provider: string
    reference: string | null
    status: 'succeeded' | 'failed'
  }>
  shippingAddress: Address
  status: 'pending_payment' | 'paid' | 'payment_failed' | 'fulfilled' | 'cancelled'
  subtotal: Price
  total: Price
}

type Props = AdminLayoutProps & { order: Order }

export default function OrderShow({ order, ...layoutProps }: Props) {
  return (
    <AdminLayout active="orders" {...layoutProps}>
      <Head title={`Order ${order.number}`} />
      <div {...stylex.props(styles.page)}>
        <div {...stylex.props(styles.pageContent)}>
          <header {...stylex.props(styles.pageHeader)}>
            <div>
              <BackLink href={layoutProps.ordersUrl}>Back to orders</BackLink>
              <div {...stylex.props(styles.headingRow)}>
                <h1 {...stylex.props(styles.heading)}>{order.number}</h1>
                <span {...stylex.props(styles.status, orderStatusStyle(order.status))}>{formatStatus(order.status)}</span>
              </div>
              <p {...stylex.props(styles.createdAt)}>{formatDate(order.createdAt)}</p>
            </div>
          </header>

          <div {...stylex.props(styles.content)}>
            <Card>
              <Card.Header><Card.Title>Items</Card.Title></Card.Header>
              <Card.Body>
                <div {...stylex.props(styles.items)}>
                  {order.items.map((item) => (
                    <div key={item.id} {...stylex.props(styles.item)}>
                      <div {...stylex.props(styles.itemDetails)}>
                        <span {...stylex.props(styles.itemName, styles.selectable)}>{item.name}</span>
                        <span {...stylex.props(styles.secondaryText)}>{formatPrice(item.unitPrice, order.currency)} × {item.quantity}</span>
                      </div>
                      <span {...stylex.props(styles.amount)}>{formatPrice(item.total, order.currency)}</span>
                    </div>
                  ))}
                </div>
                <dl {...stylex.props(styles.totals)}>
                  <div {...stylex.props(styles.totalRow)}><dt>Subtotal</dt><dd>{formatPrice(order.subtotal, order.currency)}</dd></div>
                  <div {...stylex.props(styles.grandTotal)}><dt>Total</dt><dd>{formatPrice(order.total, order.currency)}</dd></div>
                </dl>
              </Card.Body>
            </Card>

            <Card>
              <Card.Header><Card.Title>Customer</Card.Title></Card.Header>
              <Card.Body>
                <dl {...stylex.props(styles.customerDetails)}>
                  <Detail label="Name" value={order.customerName} />
                  <Detail label="Email" value={order.customerEmail} />
                </dl>
                <div {...stylex.props(styles.addressGrid)}>
                  <AddressBlock address={order.shippingAddress} title="Shipping address" />
                  <AddressBlock address={order.billingAddress} title="Billing address" />
                </div>
              </Card.Body>
            </Card>

            <Card>
              <Card.Header><Card.Title>Payments</Card.Title></Card.Header>
              <Card.Body>
                {order.payments.length === 0 ? (
                  <p {...stylex.props(styles.secondaryText)}>No payment attempts recorded.</p>
                ) : (
                  <div {...stylex.props(styles.payments)}>
                    {order.payments.map((payment) => (
                      <div key={payment.id} {...stylex.props(styles.payment)}>
                        <div {...stylex.props(styles.paymentHeader)}>
                          <span {...stylex.props(styles.itemName)}>{formatProvider(payment.provider)}</span>
                          <span {...stylex.props(styles.paymentStatus, payment.status === 'succeeded' ? styles.paymentSucceeded : styles.paymentFailed)}>
                            {formatStatus(payment.status)}
                          </span>
                        </div>
                        <span>{formatPrice(payment.amount, order.currency)}</span>
                        {payment.reference && <span {...stylex.props(styles.secondaryText, styles.selectable)}>Reference: {payment.reference}</span>}
                        {payment.failureMessage && <span {...stylex.props(styles.failureMessage)}>{payment.failureMessage}</span>}
                        <span {...stylex.props(styles.secondaryText)}>{formatDate(payment.createdAt)}</span>
                      </div>
                    ))}
                  </div>
                )}
              </Card.Body>
            </Card>
          </div>
        </div>
      </div>
    </AdminLayout>
  )
}

function AddressBlock({ address, title }: { address: Address; title: string }) {
  const fullName = [address.title, address.first_name, address.last_name].filter(Boolean).join(' ')

  return (
    <section {...stylex.props(styles.addressBlock)}>
      <h3 {...stylex.props(styles.addressTitle)}>{title}</h3>
      <address {...stylex.props(styles.address)}>
        <span {...stylex.props(styles.selectable)}>{fullName}</span>
        {address.street.map((line, index) => <span key={index} {...stylex.props(styles.selectable)}>{line}</span>)}
        <span {...stylex.props(styles.selectable)}>{address.postcode} {address.city}</span>
        {address.state && <span {...stylex.props(styles.selectable)}>{address.state}</span>}
        <span {...stylex.props(styles.selectable)}>{address.country}</span>
        {address.email && <span {...stylex.props(styles.selectable)}>{address.email}</span>}
        {address.phone && <span {...stylex.props(styles.selectable)}>{address.phone}</span>}
      </address>
    </section>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return <div {...stylex.props(styles.detail)}><dt {...stylex.props(styles.addressTitle)}>{label}</dt><dd {...stylex.props(styles.selectable)}>{value}</dd></div>
}

function formatPrice(price: Price, currency: string) {
  const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits
  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function formatStatus(status: string) {
  return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function formatProvider(provider: string) {
  return provider.split(/[._-]/).map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function orderStatusStyle(status: Order['status']) {
  if (status === 'paid' || status === 'fulfilled') return styles.statusSuccess
  if (status === 'payment_failed' || status === 'cancelled') return styles.statusDanger
  return styles.statusPending
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { marginInline: 'auto', maxWidth: 1120, paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 }, width: '100%' },
  pageHeader: { marginBottom: 24, minHeight: 48 },
  headingRow: { alignItems: 'center', display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 4 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, userSelect: 'text' },
  createdAt: { color: 'var(--color-neutral-500)', fontSize: 13, marginTop: 3 },
  content: { display: 'grid', gap: 24, minWidth: 0 },
  items: { display: 'grid' },
  item: { alignItems: 'center', borderBottomColor: 'var(--color-neutral-200)', borderBottomStyle: 'solid', borderBottomWidth: 1, display: 'flex', gap: 16, justifyContent: 'space-between', paddingBlock: 12 },
  itemDetails: { display: 'flex', flexDirection: 'column', gap: 3, minWidth: 0 },
  itemName: { color: 'var(--color-neutral-950)', fontSize: 14, fontWeight: 600 },
  amount: { color: 'var(--color-neutral-950)', flexShrink: 0, fontSize: 14 },
  secondaryText: { color: 'var(--color-neutral-500)', fontSize: 13, overflowWrap: 'anywhere' },
  totals: { display: 'grid', gap: 10, marginLeft: 'auto', maxWidth: 300, paddingTop: 4, width: '100%' },
  totalRow: { color: 'var(--color-neutral-600)', display: 'flex', fontSize: 14, justifyContent: 'space-between' },
  grandTotal: { borderTopColor: 'var(--color-neutral-200)', borderTopStyle: 'solid', borderTopWidth: 1, display: 'flex', fontSize: 15, fontWeight: 650, justifyContent: 'space-between', paddingTop: 12 },
  customerDetails: { display: 'grid', gap: 14, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': 'minmax(0, 1fr)' }, userSelect: 'text' },
  detail: { display: 'grid', gap: 3, overflowWrap: 'anywhere' },
  addressGrid: { borderTopColor: 'var(--color-neutral-200)', borderTopStyle: 'solid', borderTopWidth: 1, display: 'grid', gap: 24, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': 'minmax(0, 1fr)' }, paddingTop: 20 },
  addressBlock: { display: 'grid', gap: 8 },
  addressTitle: { color: 'var(--color-neutral-950)', fontSize: 14, fontWeight: 600 },
  address: { color: 'var(--color-neutral-800)', display: 'flex', flexDirection: 'column', fontSize: 14, fontStyle: 'normal', lineHeight: 1.55, overflowWrap: 'anywhere', userSelect: 'text' },
  selectable: { userSelect: 'text' },
  payments: { display: 'grid', gap: 16 },
  payment: { borderBottomColor: 'var(--color-neutral-200)', borderBottomStyle: 'solid', borderBottomWidth: { default: 1, ':last-child': 0 }, display: 'grid', fontSize: 14, gap: 5, paddingBottom: { default: 16, ':last-child': 0 } },
  paymentHeader: { alignItems: 'center', display: 'flex', gap: 8 },
  paymentStatus: { borderRadius: 4, fontSize: 11, fontWeight: 600, paddingBlock: 2, paddingInline: 6 },
  paymentSucceeded: { backgroundColor: '#dcfce7', color: '#166534' },
  paymentFailed: { backgroundColor: '#fee2e2', color: '#991b1b' },
  failureMessage: { color: '#991b1b', fontSize: 13, overflowWrap: 'anywhere' },
  status: { borderRadius: 4, display: 'inline-block', fontSize: 12, fontWeight: 600, paddingBlock: 3, paddingInline: 7 },
  statusSuccess: { backgroundColor: '#dcfce7', color: '#166534' },
  statusDanger: { backgroundColor: '#fee2e2', color: '#991b1b' },
  statusPending: { backgroundColor: '#fef3c7', color: '#92400e' },
})
