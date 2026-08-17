import { Button as BaseButton } from '@base-ui/react/button'
import { Deferred, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'

type Product = {
  id: number | string
  name: string
  price: { amount: string; currency: string }
  stock: number | null
  status: 'visible' | 'hidden'
  url: string
}

type Pagination = {
  currentPage: number
  from: number | null
  lastPage: number
  nextUrl: string | null
  previousUrl: string | null
  to: number | null
  total: number
}

type ProductImage = { alt: string | null; url: string }

type Props = AdminLayoutProps & {
  pagination: Pagination
  productImages?: Record<string, ProductImage | null>
  products: Product[]
}

export default function ProductIndex({ pagination, productImages, products, ...layoutProps }: Props) {
  const [hoveredProductId, setHoveredProductId] = useState<Product['id'] | null>(null)

  return (
    <AdminLayout active="products" {...layoutProps}>
      <div {...stylex.props(styles.tableFrame)}>
        <div {...stylex.props(styles.tableScroll)}>
          <table {...stylex.props(styles.table)}>
            <thead {...stylex.props(styles.tableHeader)}>
              <tr>
                <th {...stylex.props(styles.headingCell)}>Product</th>
                <th {...stylex.props(styles.headingCell)}>Status</th>
                <th {...stylex.props(styles.headingCell, styles.numeric)}>Price</th>
                <th {...stylex.props(styles.headingCell, styles.numeric)}>Stock</th>
              </tr>
            </thead>
            <tbody>
              {products.map((product, index) => (
                <tr
                  key={product.id}
                  onClick={() => router.visit(product.url)}
                  onMouseEnter={() => setHoveredProductId(product.id)}
                  onMouseLeave={() => setHoveredProductId(null)}
                  {...stylex.props(styles.row, index === 0 && styles.firstRow)}
                >
                  <td {...stylex.props(styles.cell, styles.productName, styles.selectable)}>
                    <div {...stylex.props(styles.productIdentity)}>
                      <Deferred data="productImages" fallback={<ProductImagePlaceholder />}>
                        <ProductThumbnail image={productImages?.[String(product.id)]} />
                      </Deferred>
                      <Link
                        href={product.url}
                        onClick={(event) => event.stopPropagation()}
                        {...stylex.props(styles.productLink, hoveredProductId === product.id && styles.productLinkRowHovered)}
                      >
                        {product.name}
                      </Link>
                    </div>
                  </td>
                  <td {...stylex.props(styles.cell)}>
                    <span {...stylex.props(styles.status, product.status === 'visible' ? styles.visible : styles.hidden)}>
                      {product.status === 'visible' ? 'Visible' : 'Hidden'}
                    </span>
                  </td>
                  <td {...stylex.props(styles.cell, styles.numeric, styles.selectable)}>{formatPrice(product.price)}</td>
                  <td {...stylex.props(styles.cell, styles.numeric, styles.selectable)}>{product.stock ?? 'Unlimited'}</td>
                </tr>
              ))}
              {products.length === 0 && (
                <tr><td colSpan={4} {...stylex.props(styles.empty)}>No products yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>

        <footer {...stylex.props(styles.pagination)}>
          <span {...stylex.props(styles.paginationSummary)}>
            {pagination.total === 0
              ? '0 products'
              : `${pagination.from}-${pagination.to} of ${pagination.total}`}
          </span>
          <span {...stylex.props(styles.pageCount)}>
            Page {pagination.currentPage} of {pagination.lastPage}
          </span>
          <nav aria-label="Product pagination" {...stylex.props(styles.paginationControls)}>
            <PaginationButton direction="left" label="Previous page" url={pagination.previousUrl} />
            <PaginationButton direction="right" label="Next page" separated url={pagination.nextUrl} />
          </nav>
        </footer>
      </div>
    </AdminLayout>
  )
}

function ProductThumbnail({ image }: { image: ProductImage | null | undefined }) {
  if (!image) {
    return <ProductImagePlaceholder />
  }

  return <img alt={image.alt ?? ''} decoding="async" src={image.url} {...stylex.props(styles.productImage)} />
}

function ProductImagePlaceholder() {
  return <span aria-hidden="true" {...stylex.props(styles.productImagePlaceholder)} />
}

function PaginationButton({ direction, label, separated = false, url }: {
  direction: 'left' | 'right'
  label: string
  separated?: boolean
  url: string | null
}) {
  return (
    <BaseButton
      aria-label={label}
      disabled={!url}
      onClick={() => url && router.visit(url, { preserveScroll: true })}
      title={label}
      {...stylex.props(styles.paginationButton, separated && styles.paginationButtonSeparated, !url && styles.paginationButtonDisabled)}
    >
      <ChevronIcon direction={direction} />
    </BaseButton>
  )
}

function ChevronIcon({ direction }: { direction: 'left' | 'right' }) {
  const points = direction === 'left' ? '9 5 5 9 9 13' : '7 5 11 9 7 13'

  return (
    <svg aria-hidden="true" fill="none" height="18" viewBox="0 0 18 18" width="18">
      <polyline points={points} stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" />
    </svg>
  )
}

function formatPrice(price: Product['price']) {
  const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: price.currency })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits
  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}

const styles = stylex.create({
  tableFrame: { backgroundColor: '#fff', display: 'flex', flexDirection: 'column', height: '100vh', overflow: 'hidden', position: 'relative', width: '100%' },
  tableScroll: { flex: 1, minHeight: 0, overflow: 'auto', overscrollBehavior: 'none' },
  table: { borderCollapse: 'collapse', fontSize: 14, minWidth: 620, width: '100%' },
  tableHeader: { backgroundClip: 'padding-box', backgroundColor: '#fff', height: 'var(--admin-header-height)', position: 'sticky', top: 0, zIndex: 3 },
  headingCell: { boxShadow: 'inset 0 -1px 0 rgba(20, 15, 18, 0.14)', color: 'var(--color-neutral-500)', fontSize: 12, fontWeight: 600, height: 'var(--admin-header-height)', paddingInline: 16, textAlign: 'left' },
  row: { backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' }, borderTopColor: 'var(--color-neutral-200)', borderTopStyle: 'solid', borderTopWidth: 1, cursor: 'pointer' },
  firstRow: { borderTopWidth: 0 },
  cell: { paddingBlock: 10, paddingInline: 16 },
  selectable: { userSelect: 'text' },
  productName: { fontWeight: 600 },
  productIdentity: { alignItems: 'center', display: 'flex', gap: 8 },
  productImage: { borderRadius: 3, flexShrink: 0, height: 20, objectFit: 'cover', width: 20 },
  productImagePlaceholder: { backgroundColor: 'var(--color-neutral-100)', borderColor: 'var(--color-neutral-200)', borderRadius: 3, borderStyle: 'solid', borderWidth: 1, display: 'block', flexShrink: 0, height: 20, width: 20 },
  productLink: { borderRadius: 4, color: 'var(--color-neutral-950)', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3, userSelect: 'text' },
  productLinkRowHovered: { color: 'var(--color-brand-700)' },
  numeric: { textAlign: 'right' },
  status: { borderRadius: 4, display: 'inline-block', fontSize: 12, fontWeight: 600, paddingBlock: 3, paddingInline: 7 },
  visible: { backgroundColor: '#dcfce7', color: '#166534' },
  hidden: { backgroundColor: 'var(--color-neutral-100)', color: 'var(--color-neutral-600)' },
  empty: { color: 'var(--color-neutral-500)', padding: 28, textAlign: 'center' },
  pagination: { alignItems: 'center', backgroundClip: 'padding-box', backgroundColor: '#fff', borderTopColor: 'rgba(20, 15, 18, 0.14)', borderTopStyle: 'solid', borderTopWidth: 1, display: 'flex', flexShrink: 0, flexWrap: 'wrap', gap: 16, justifyContent: 'space-between', minHeight: 60, paddingLeft: 16, position: 'relative', zIndex: 1 },
  paginationSummary: { color: 'var(--color-neutral-500)', fontSize: 13 },
  pageCount: { color: 'var(--color-neutral-600)', fontSize: 13, marginLeft: 'auto' },
  paginationControls: { alignSelf: 'stretch', borderLeftColor: 'var(--color-neutral-200)', borderLeftStyle: 'solid', borderLeftWidth: 1, display: 'flex', flexShrink: 0 },
  paginationButton: { alignItems: 'center', backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' }, borderWidth: 0, color: { default: 'var(--color-neutral-700)', ':hover': 'var(--color-neutral-700)' }, cursor: { default: 'pointer', ':disabled': 'default' }, display: 'flex', height: '100%', justifyContent: 'center', minHeight: 59, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: -3, outlineStyle: 'solid', outlineWidth: 2, width: 60 },
  paginationButtonDisabled: { backgroundColor: { default: '#fff', ':hover': '#fff' }, color: { default: 'var(--color-neutral-400)', ':hover': 'var(--color-neutral-400)' } },
  paginationButtonSeparated: { borderLeftColor: 'var(--color-neutral-200)', borderLeftStyle: 'solid', borderLeftWidth: 1 },
})
