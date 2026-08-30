import { Button as BaseButton } from '@base-ui/react/button'
import { Head, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Button from '../../Components/Button'
import Dialog from '../../Components/Dialog'
import DropdownMenu from '../../Components/DropdownMenu'
import EmptyState from '../../Components/EmptyState'
import Icon from '../../Components/Icon'
import Table, { type PaginationData } from '../../Components/Table'

type ProductAttribute = {
  deleteUrl: string
  id: number | string
  name: string
  type: 'boolean' | 'number' | 'text'
  url: string
  valuesCount: number
}

type Props = AdminLayoutProps & {
  pagination: PaginationData
  productAttributeCreateUrl: string
  productAttributes: ProductAttribute[]
}

export default function ProductAttributeIndex({ pagination, productAttributeCreateUrl, productAttributes, ...layoutProps }: Props) {
  const [productAttributeToDelete, setProductAttributeToDelete] = useState<ProductAttribute | null>(null)

  const deleteProductAttribute = () => {
    if (productAttributeToDelete === null) return

    router.delete(productAttributeToDelete.deleteUrl, {
      onSuccess: () => setProductAttributeToDelete(null),
      preserveScroll: true,
    })
  }

  return (
    <AdminLayout active="product-attributes" {...layoutProps}>
      <Head title="Product attributes" />
      {productAttributes.length === 0 ? (
        <div {...stylex.props(styles.emptyStateWrapper)}>
          <EmptyState
            actions={<Button render={<Link href={productAttributeCreateUrl} />}>Create product attribute</Button>}
            description="Product attributes let you define choices such as size, color, or material."
            renderIcon={() => <Icon name="product-attributes" height={24} width={24} />}
            title="No product attributes yet"
          />
        </div>
      ) : (
        <Table.Frame>
          <Table.Scroll>
            <Table.Root>
              <Table.Header>
                <tr>
                  <Table.Heading>Product attribute</Table.Heading>
                  <Table.Heading>Type</Table.Heading>
                  <Table.Heading numeric>Values</Table.Heading>
                  <Table.Heading>
                    <div {...stylex.props(styles.actionsHeading)}>
                      <Button render={<Link href={productAttributeCreateUrl} />}>
                        <Icon height={16} name="plus" width={16} />
                        Create
                      </Button>
                    </div>
                  </Table.Heading>
                </tr>
              </Table.Header>
              <Table.Body>
                {productAttributes.map((productAttribute, index) => (
                  <Table.Row
                    first={index === 0}
                    interactive
                    key={productAttribute.id}
                    onClick={() => router.visit(productAttribute.url)}
                  >
                    <Table.Cell selectable>
                      <Link
                        href={productAttribute.url}
                        onClick={(event) => event.stopPropagation()}
                        {...stylex.props(styles.productAttributeLink)}
                      >
                        {productAttribute.name}
                      </Link>
                    </Table.Cell>
                    <Table.Cell>{formatType(productAttribute.type)}</Table.Cell>
                    <Table.Cell numeric selectable>{productAttribute.type === 'boolean' ? null : productAttribute.valuesCount}</Table.Cell>
                    <Table.Cell>
                      <div onClick={(event) => event.stopPropagation()} {...stylex.props(styles.actions)}>
                        <DropdownMenu
                          align="end"
                          items={[{
                            icon: <Icon height={18} name="trash" width={18} />,
                            label: 'Delete',
                            onClick: () => setProductAttributeToDelete(productAttribute),
                            variant: 'danger',
                          }]}
                          side="bottom"
                          trigger={(open) => (
                            <BaseButton aria-label={`Actions for ${productAttribute.name}`} type="button" {...stylex.props(styles.actionsTrigger, open && styles.actionsTriggerOpen)}>
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
          <Table.Pagination data={pagination} itemLabel="product attributes" label="Product attribute pagination" />
        </Table.Frame>
      )}

      <Dialog
        description={productAttributeToDelete === null
          ? ''
          : `This will permanently delete "${productAttributeToDelete.name}" and all of its values.`}
        onOpenChange={(open) => !open && setProductAttributeToDelete(null)}
        open={productAttributeToDelete !== null}
        title="Delete product attribute?"
      >
        <Button onClick={() => setProductAttributeToDelete(null)} type="button" variant="secondary">
          Cancel
        </Button>
        <Button onClick={deleteProductAttribute} type="button" variant="danger">
          Delete
        </Button>
      </Dialog>
    </AdminLayout>
  )
}

const styles = stylex.create({
  emptyStateWrapper: { height: '100%', marginInline: 'auto', maxWidth: 360, width: '100%' },
  actions: { display: 'flex', justifyContent: 'flex-end' },
  actionsHeading: { display: 'flex', justifyContent: 'flex-end' },
  actionsTrigger: { alignItems: 'center', backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-200)' }, borderColor: 'transparent', borderRadius: 4, borderStyle: 'solid', borderWidth: 0, color: 'var(--color-neutral-600)', cursor: 'pointer', display: 'flex', height: 32, justifyContent: 'center', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 0, width: 32 },
  actionsTriggerOpen: { backgroundColor: 'var(--color-neutral-200)' },
  productAttributeLink: { borderRadius: 4, color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3 },
})

function formatType(type: ProductAttribute['type']) {
  return type.charAt(0).toUpperCase() + type.slice(1)
}
