import { Deferred, Head, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Table, { type PaginationData } from '../../Components/Table'

type Product = {
  id: number | string
  name: string
  price: { amount: string; currency: string }
  stock: number | null
  status: 'visible' | 'hidden'
  url: string
}

type ProductImage = { alt: string | null; url: string }

type Props = AdminLayoutProps & {
  pagination: PaginationData
  productImages?: Record<string, ProductImage | null>
  products: Product[]
}

export default function ProductIndex({ pagination, productImages, products, ...layoutProps }: Props) {
  return (
    <AdminLayout active="products" {...layoutProps}>
      <Head title="Products" />
      <Table.Frame>
        <Table.Scroll>
          <Table.Root>
            <Table.Header>
              <tr>
                <Table.Heading>Product</Table.Heading>
                <Table.Heading>Status</Table.Heading>
                <Table.Heading numeric>Price</Table.Heading>
                <Table.Heading numeric>Stock</Table.Heading>
              </tr>
            </Table.Header>
            <Table.Body>
              {products.map((product, index) => (
                <Table.Row
                  first={index === 0}
                  interactive
                  key={product.id}
                  onClick={() => router.visit(product.url)}
                >
                  <Table.Cell selectable>
                    <div {...stylex.props(styles.productIdentity, styles.productName)}>
                      <Deferred data="productImages" fallback={<ProductImagePlaceholder />}>
                        <ProductThumbnail image={productImages?.[String(product.id)]} />
                      </Deferred>
                      <Link
                        href={product.url}
                        onClick={(event) => event.stopPropagation()}
                        {...stylex.props(styles.productLink)}
                      >
                        {product.name}
                      </Link>
                    </div>
                  </Table.Cell>
                  <Table.Cell>
                    <span {...stylex.props(styles.status, product.status === 'visible' ? styles.visible : styles.hidden)}>
                      {product.status === 'visible' ? 'Visible' : 'Hidden'}
                    </span>
                  </Table.Cell>
                  <Table.Cell numeric selectable>{formatPrice(product.price)}</Table.Cell>
                  <Table.Cell numeric selectable>{product.stock ?? 'Unlimited'}</Table.Cell>
                </Table.Row>
              ))}
              {products.length === 0 && <Table.Empty colSpan={4}>No products yet.</Table.Empty>}
            </Table.Body>
          </Table.Root>
        </Table.Scroll>
        <Table.Pagination data={pagination} itemLabel="products" label="Product pagination" />
      </Table.Frame>
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

function formatPrice(price: Product['price']) {
  const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: price.currency })
  const fractionDigits = formatter.resolvedOptions().maximumFractionDigits
  return formatter.format(Number(price.amount) / (10 ** fractionDigits))
}

const styles = stylex.create({
  productName: { fontWeight: 600 },
  productIdentity: { alignItems: 'center', display: 'flex', gap: 8 },
  productImage: { borderRadius: 3, flexShrink: 0, height: 20, objectFit: 'cover', width: 20 },
  productImagePlaceholder: { backgroundColor: 'var(--color-neutral-100)', borderColor: 'var(--color-neutral-200)', borderRadius: 3, borderStyle: 'solid', borderWidth: 1, display: 'block', flexShrink: 0, height: 20, width: 20 },
  productLink: { borderRadius: 4, color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3, userSelect: 'text' },
  status: { borderRadius: 4, display: 'inline-block', fontSize: 12, fontWeight: 600, paddingBlock: 3, paddingInline: 7 },
  visible: { backgroundColor: '#dcfce7', color: '#166534' },
  hidden: { backgroundColor: 'var(--color-neutral-100)', color: 'var(--color-neutral-600)' },
})
