import { Head, useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import BackLink from '../../Components/BackLink'
import Form from '../../Components/Form'
import FormContainer from '../../Components/FormContainer'
import useUnsavedChanges from '../../Hooks/useUnsavedChanges'
import ProductForm, { type ProductCategory, type ProductFormData } from './ProductForm'

type Props = AdminLayoutProps & { categories: ProductCategory[]; productStoreUrl: string }

export default function ProductCreate({ categories, productStoreUrl, ...layoutProps }: Props) {
  const form = useForm<ProductFormData>({
    name: '',
    description: '',
    stock: null as number | null,
    min_quantity: null as number | null,
    max_quantity: null as number | null,
    allow_backorders: true,
    status: 'visible' as 'visible' | 'hidden',
    price_amount: 0,
    category_ids: [],
  })

  function submit() {
    form.post(productStoreUrl)
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    submit()
  }

  useUnsavedChanges({
    dirty: form.isDirty,
    onReset: () => {
      form.reset()
      form.clearErrors()
    },
    onSave: submit,
    processing: form.processing,
  })

  return (
    <AdminLayout active="products" {...layoutProps}>
      <Head title="Create product" />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.pageContent}>
          <BackLink href={layoutProps.productsUrl}>Back to products</BackLink>
          <h1 {...stylex.props(styles.heading)}>Create product</h1>
          <Form onSubmit={handleSubmit}>
            <ProductForm categories={categories} form={form}>
              <div {...stylex.props(styles.cards)}>
                <ProductForm.General />
                <ProductForm.Pricing />
                <ProductForm.Stock />
                <ProductForm.Categories />
              </div>
            </ProductForm>
          </Form>
        </FormContainer>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginBottom: 52, marginTop: 8 },
  cards: { display: 'grid', gap: 52 },
})
