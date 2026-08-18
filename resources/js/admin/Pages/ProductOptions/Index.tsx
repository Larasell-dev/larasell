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

type ProductOption = {
  deleteUrl: string
  id: number | string
  name: string
  type: 'boolean' | 'number' | 'text'
  url: string
  valuesCount: number
}

type Props = AdminLayoutProps & {
  pagination: PaginationData
  productOptionCreateUrl: string
  productOptions: ProductOption[]
}

export default function ProductOptionIndex({ pagination, productOptionCreateUrl, productOptions, ...layoutProps }: Props) {
  const [productOptionToDelete, setProductOptionToDelete] = useState<ProductOption | null>(null)

  const deleteProductOption = () => {
    if (productOptionToDelete === null) return

    router.delete(productOptionToDelete.deleteUrl, {
      onSuccess: () => setProductOptionToDelete(null),
      preserveScroll: true,
    })
  }

  return (
    <AdminLayout active="product-options" {...layoutProps}>
      <Head title="Product options" />
      {productOptions.length === 0 ? (
        <div {...stylex.props(styles.emptyStateWrapper)}>
          <EmptyState
            actions={<Button render={<Link href={productOptionCreateUrl} />}>Create product option</Button>}
            description="Product options let you define choices such as size, color, or material."
            renderIcon={() => <Icon name="product-options" height={24} width={24} />}
            title="No product options yet"
          />
        </div>
      ) : (
        <Table.Frame>
          <Table.Scroll>
            <Table.Root>
              <Table.Header>
                <tr>
                  <Table.Heading>Product option</Table.Heading>
                  <Table.Heading>Type</Table.Heading>
                  <Table.Heading numeric>Values</Table.Heading>
                  <Table.Heading>
                    <div {...stylex.props(styles.actionsHeading)}>
                      <Button render={<Link href={productOptionCreateUrl} />}>
                        <Icon height={16} name="plus" width={16} />
                        Create
                      </Button>
                    </div>
                  </Table.Heading>
                </tr>
              </Table.Header>
              <Table.Body>
                {productOptions.map((productOption, index) => (
                  <Table.Row
                    first={index === 0}
                    interactive
                    key={productOption.id}
                    onClick={() => router.visit(productOption.url)}
                  >
                    <Table.Cell selectable>
                      <Link
                        href={productOption.url}
                        onClick={(event) => event.stopPropagation()}
                        {...stylex.props(styles.productOptionLink)}
                      >
                        {productOption.name}
                      </Link>
                    </Table.Cell>
                    <Table.Cell>{formatType(productOption.type)}</Table.Cell>
                    <Table.Cell numeric selectable>{productOption.valuesCount}</Table.Cell>
                    <Table.Cell>
                      <div onClick={(event) => event.stopPropagation()} {...stylex.props(styles.actions)}>
                        <DropdownMenu
                          align="end"
                          items={[{
                            icon: <Icon height={18} name="trash" width={18} />,
                            label: 'Delete',
                            onClick: () => setProductOptionToDelete(productOption),
                            variant: 'danger',
                          }]}
                          side="bottom"
                          trigger={(open) => (
                            <BaseButton aria-label={`Actions for ${productOption.name}`} type="button" {...stylex.props(styles.actionsTrigger, open && styles.actionsTriggerOpen)}>
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
          <Table.Pagination data={pagination} itemLabel="product options" label="Product option pagination" />
        </Table.Frame>
      )}

      <Dialog
        description={productOptionToDelete === null
          ? ''
          : `This will permanently delete "${productOptionToDelete.name}" and all of its values.`}
        onOpenChange={(open) => !open && setProductOptionToDelete(null)}
        open={productOptionToDelete !== null}
        title="Delete product option?"
      >
        <Button onClick={() => setProductOptionToDelete(null)} type="button" variant="secondary">
          Cancel
        </Button>
        <Button onClick={deleteProductOption} type="button" variant="danger">
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
  productOptionLink: { borderRadius: 4, color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3 },
})

function formatType(type: ProductOption['type']) {
  return type.charAt(0).toUpperCase() + type.slice(1)
}
