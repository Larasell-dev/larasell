import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import BackLink from '../../Components/BackLink'
import FormContainer from '../../Components/FormContainer'
import ProductOptionForm from './ProductOptionForm'

type Props = AdminLayoutProps & { productOptionStoreUrl: string }

export default function ProductOptionCreate({ productOptionStoreUrl, ...props }: Props) {
  return (
    <AdminLayout active="product-options" {...props}>
      <Head title="Create product option" />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.pageContent}>
          <BackLink href={props.productOptionsUrl}>Back to product options</BackLink>
          <h1 {...stylex.props(styles.heading)}>Create product option</h1>
          <ProductOptionForm action={productOptionStoreUrl} method="post" />
        </FormContainer>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginBottom: 52, marginTop: 8 },
})
