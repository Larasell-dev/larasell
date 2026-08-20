import { Button as BaseButton } from '@base-ui/react/button'
import { Deferred, Head, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Button from '../../Components/Button'
import Dialog from '../../Components/Dialog'
import DropdownMenu from '../../Components/DropdownMenu'
import EmptyState from '../../Components/EmptyState'
import Icon from '../../Components/Icon'
import Table, { type PaginationData } from '../../Components/Table'

type Product = {
  deleteUrl: string
  id: number | string
  name: string
  price: { amount: string }
  stock: number | null
  status: 'visible' | 'hidden'
  url: string
}

type ProductImage = { alt: string | null; url: string }

type Props = AdminLayoutProps & {
  pagination: PaginationData
  productCreateUrl: string
  productImages?: Record<string, ProductImage | null>
  products: Product[]
}

export default function ProductIndex({ pagination, productCreateUrl, productImages, products, ...layoutProps }: Props) {
  const [productToDelete, setProductToDelete] = useState<Product | null>(null)

  const deleteProduct = () => {
    if (productToDelete === null) return

    router.delete(productToDelete.deleteUrl, {
      onSuccess: () => setProductToDelete(null),
      preserveScroll: true,
    })
  }

  return (
    <AdminLayout active="products" {...layoutProps}>
      <Head title="Products" />
      {products.length === 0 ? (
        <div {...stylex.props(styles.emptyStateWrapper)}>
          <EmptyState
            actions={<Button render={<Link href={productCreateUrl} />}>Create product</Button>}
            description="Add a product to start selling it in your storefront."
            renderIcon={() => <Icon name="products" height={24} width={24} />}
            title="No products yet"
          />
        </div>
      ) : <Table.Frame>
        <Table.Scroll>
          <Table.Root>
            <Table.Header>
              <tr>
                <Table.Heading>Product</Table.Heading>
                <Table.Heading>Status</Table.Heading>
                <Table.Heading numeric>Price (minor units)</Table.Heading>
                <Table.Heading numeric>Stock</Table.Heading>
                <Table.Heading>
                  <div {...stylex.props(styles.actionsHeading)}>
                    <Button render={<Link href={productCreateUrl} />}>
                      <Icon height={16} name="plus" width={16} />
                      Create
                    </Button>
                  </div>
                </Table.Heading>
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
                  <Table.Cell numeric selectable>{product.price.amount}</Table.Cell>
                  <Table.Cell numeric selectable>{product.stock ?? 'Unlimited'}</Table.Cell>
                  <Table.Cell>
                    <div onClick={(event) => event.stopPropagation()} {...stylex.props(styles.actions)}>
                      <DropdownMenu
                        align="end"
                        items={[{
                          icon: <Icon height={18} name="trash" width={18} />,
                          label: 'Delete',
                          onClick: () => setProductToDelete(product),
                          variant: 'danger',
                        }]}
                        side="bottom"
                        trigger={(open) => (
                          <BaseButton aria-label={`Actions for ${product.name}`} type="button" {...stylex.props(styles.actionsTrigger, open && styles.actionsTriggerOpen)}>
                            <Icon height={20} name="dots" width={20} />
                          </BaseButton>
                        )}
                      />
                    </div>
                  </Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Root>
        </Table.Scroll>
        <Table.Pagination data={pagination} itemLabel="products" label="Product pagination" />
      </Table.Frame>}

      <Dialog
        description={productToDelete === null ? '' : `This will permanently delete "${productToDelete.name}".`}
        onOpenChange={(open) => !open && setProductToDelete(null)}
        open={productToDelete !== null}
        title="Delete product?"
      >
        <Button onClick={() => setProductToDelete(null)} type="button" variant="secondary">Cancel</Button>
        <Button onClick={deleteProduct} type="button" variant="danger">Delete</Button>
      </Dialog>
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

const styles = stylex.create({
  emptyStateWrapper: { height: '100%', marginInline: 'auto', maxWidth: 360, width: '100%' },
  actions: { display: 'flex', justifyContent: 'flex-end' },
  actionsHeading: { display: 'flex', justifyContent: 'flex-end' },
  actionsTrigger: { alignItems: 'center', backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-200)' }, borderColor: 'transparent', borderRadius: 4, borderStyle: 'solid', borderWidth: 0, color: 'var(--color-neutral-600)', cursor: 'pointer', display: 'flex', height: 32, justifyContent: 'center', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 0, width: 32 },
  actionsTriggerOpen: { backgroundColor: 'var(--color-neutral-200)' },
  productName: { fontWeight: 600 },
  productIdentity: { alignItems: 'center', display: 'flex', gap: 8 },
  productImage: { borderRadius: 3, flexShrink: 0, height: 20, objectFit: 'cover', width: 20 },
  productImagePlaceholder: { backgroundColor: 'var(--color-neutral-100)', borderColor: 'var(--color-neutral-200)', borderRadius: 3, borderStyle: 'solid', borderWidth: 1, display: 'block', flexShrink: 0, height: 20, width: 20 },
  productLink: { borderRadius: 4, color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3, userSelect: 'text' },
  status: { borderRadius: 4, display: 'inline-block', fontSize: 12, fontWeight: 600, paddingBlock: 3, paddingInline: 7 },
  visible: { backgroundColor: '#dcfce7', color: '#166534' },
  hidden: { backgroundColor: 'var(--color-neutral-100)', color: 'var(--color-neutral-600)' },
})
