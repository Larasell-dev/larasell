import { Link } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'

type Props = AdminLayoutProps & {
  product: {
    id: number | string
    name: string
  }
}

export default function ProductShow({ product, ...layoutProps }: Props) {
  return (
    <AdminLayout active="products" {...layoutProps}>
      <div {...stylex.props(styles.content)}>
        <h1 {...stylex.props(styles.heading)}>{product.name}</h1>
        <Link href={layoutProps.productsUrl} {...stylex.props(styles.backLink)}>Back to products</Link>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  content: { padding: 24 },
  heading: { fontSize: 24, fontWeight: 650, userSelect: 'text' },
  backLink: { color: { default: 'var(--color-brand-700)', ':hover': 'var(--color-brand-900)' }, display: 'inline-block', fontSize: 14, fontWeight: 600, marginTop: 16, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: 'none' },
})
