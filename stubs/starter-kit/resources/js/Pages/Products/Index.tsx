import { Head, Link, router } from '@inertiajs/react'
import { useTranslation } from '@larasell-dev/inertia-i18n/react'

type Product = {
  id: number | string
  image: {
    alt: string | null
    url: string
  } | null
  name: string
  price: string
  slug: string
}

type Props = {
  category: {
    name: string
  }
  products: Product[]
  sort: string
}

function ProductIndex({ category, products, sort }: Props) {
  const { t } = useTranslation()

  return (
    <main>
      <Head title={category.name} />
      <h1>{category.name}</h1>

      <label htmlFor="sort">{t('products.sort.label')}</label>{' '}
      <select
        id="sort"
        value={sort}
        onChange={(event) => router.reload({ data: { sort: event.target.value } })}
      >
        <option value="name">{t('products.sort.name')}</option>
        <option value="price_asc">{t('products.sort.price_asc')}</option>
        <option value="price_desc">{t('products.sort.price_desc')}</option>
      </select>

      {products.length === 0 ? (
        <p>{t('products.empty')}</p>
      ) : (
        <ul>
          {products.map((product) => (
            <li key={product.id}>
              <Link href={`/p/${product.slug}`}>
                {product.image && (
                  <img src={product.image.url} alt={product.image.alt ?? product.name} />
                )}
                <h2>{product.name}</h2>
                <p>{product.price}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}

ProductIndex.translation = ['products']

export default ProductIndex
