import { Link, router, useForm } from '@inertiajs/react'
import { Toast } from '@base-ui/react/toast'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion } from 'motion/react'
import { useEffect, useRef, type FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Card from '../../Components/Card'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Form from '../../Components/Form'
import Input from '../../Components/Input'
import Label from '../../Components/Label'
import NumberInput from '../../Components/NumberInput'
import Select from '../../Components/Select'
import Toggle from '../../Components/Toggle'

type Product = {
  id: number | string
  name: string
  slug: string
  description: string | null
  stock: number | null
  minQuantity: number | null
  maxQuantity: number | null
  allowBackorders: boolean
  status: 'visible' | 'hidden'
  price: { amount: string; currency: Currency }
  updateUrl: string
  generalUpdateUrl: string
  stockUpdateUrl: string
}

type Props = AdminLayoutProps & { product: Product }
type Currency = 'USD' | 'EUR' | 'GBP' | 'CAD' | 'AUD' | 'NZD' | 'CHF' | 'JPY'

const currencies: { label: string; value: Currency }[] = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY']
  .map((currency) => ({ label: currency, value: currency as Currency }))

export default function ProductShow({ product, ...layoutProps }: Props) {
  return (
    <Toast.Provider timeout={0}>
      <ProductEditor product={product} {...layoutProps} />
    </Toast.Provider>
  )
}

function ProductEditor({ product, ...layoutProps }: Props) {
  const form = useForm({
    name: product.name,
    slug: product.slug,
    description: product.description ?? '',
    stock: product.stock,
    min_quantity: product.minQuantity,
    max_quantity: product.maxQuantity,
    allow_backorders: product.allowBackorders,
    status: product.status,
    price_amount: Number(product.price.amount) / (10 ** currencyFractionDigits(product.price.currency)),
    price_currency: product.price.currency,
  })
  const { add: addToast, close: closeToast, toasts } = Toast.useToastManager()
  const allowNextVisit = useRef(false)

  useEffect(() => {
    if (form.isDirty) {
      addToast({ id: 'unsaved-product-changes', title: 'Unsaved changes', timeout: 0 })
    } else {
      closeToast('unsaved-product-changes')
    }
  }, [addToast, closeToast, form.isDirty])

  useEffect(() => () => closeToast('unsaved-product-changes'), [closeToast])

  useEffect(() => {
    if (!form.isDirty) {
      return
    }

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
      event.returnValue = ''
    }

    const removeBeforeListener = router.on('before', () => {
      if (allowNextVisit.current) {
        return
      }

      return window.confirm('You have unsaved changes. Are you sure you want to leave this page?')
    })

    window.addEventListener('beforeunload', handleBeforeUnload)

    return () => {
      removeBeforeListener()
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }
  }, [form.isDirty])

  function submitProduct() {
    allowNextVisit.current = true
    form.patch(product.updateUrl, {
      preserveScroll: true,
      onFinish: () => {
        allowNextVisit.current = false
      },
    })
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    submitProduct()
  }

  return (
    <AdminLayout active="products" {...layoutProps}>
      <div {...stylex.props(styles.page)}>
        <div {...stylex.props(styles.pageContent)}>
        <header {...stylex.props(styles.pageHeader)}>
          <div>
            <Link href={layoutProps.productsUrl} {...stylex.props(styles.backLink)}>Back to products</Link>
            <h1 {...stylex.props(styles.heading)}>{product.name}</h1>
          </div>
        </header>

        <Form id="product-form" onSubmit={handleSubmit}>
          <div {...stylex.props(styles.cards)}>
          <Card>
            <Card.Header>
              <Card.Title>General information</Card.Title>
              <Card.Description>Update the product name, URL slug, and customer-facing description.</Card.Description>
            </Card.Header>
            <Card.Body>
                <Field invalid={Boolean(form.errors.name)}>
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" name="name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
                  <Error>{form.errors.name}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.slug)}>
                  <Label htmlFor="slug">Slug</Label>
                  <Input id="slug" name="slug" onChange={(event) => form.setData('slug', event.target.value)} required value={form.data.slug} />
                  <Error>{form.errors.slug}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.description)}>
                  <Label htmlFor="description">Description</Label>
                  <textarea id="description" name="description" onChange={(event) => form.setData('description', event.target.value)} rows={5} value={form.data.description} {...stylex.props(styles.textarea)} />
                  <Error>{form.errors.description}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.status)}>
                  <label {...stylex.props(styles.settingRow)}>
                    <span>
                      <strong {...stylex.props(styles.settingTitle)}>Visible</strong>
                      <span {...stylex.props(styles.settingDescription)}>Show this product to customers in the storefront.</span>
                    </span>
                    <Toggle checked={form.data.status === 'visible'} name="status" onCheckedChange={(checked) => form.setData('status', checked ? 'visible' : 'hidden')} />
                  </label>
                  <Error>{form.errors.status}</Error>
                </Field>
            </Card.Body>
          </Card>

          <Card>
            <Card.Header>
              <Card.Title>Pricing</Card.Title>
              <Card.Description>Set the product's selling price and currency.</Card.Description>
            </Card.Header>
            <Card.Body>
              <div {...stylex.props(styles.priceGrid)}>
                <Field invalid={Boolean(form.errors.price_amount)}>
                  <Label htmlFor="price_amount">Amount</Label>
                  <NumberInput id="price_amount" min={0} name="price_amount" onValueChange={(value) => form.setData('price_amount', value ?? 0)} step="any" value={form.data.price_amount} />
                  <Error>{form.errors.price_amount}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.price_currency)}>
                  <Label htmlFor="price_currency">Currency</Label>
                  <Select id="price_currency" items={currencies} name="price_currency" onValueChange={(value) => form.setData('price_currency', value)} scrollable value={form.data.price_currency} />
                  <Error>{form.errors.price_currency}</Error>
                </Field>
              </div>
            </Card.Body>
          </Card>

          <Card>
            <Card.Header>
              <Card.Title>Stock</Card.Title>
              <Card.Description>Control availability, order limits, and whether sales continue after stock reaches zero.</Card.Description>
            </Card.Header>
            <Card.Body>
                <Field invalid={Boolean(form.errors.stock)}>
                  <Label htmlFor="stock">Available stock</Label>
                  <NumberInput id="stock" min={0} name="stock" onValueChange={(value) => form.setData('stock', value)} placeholder="Unlimited" value={form.data.stock} />
                  <Error>{form.errors.stock}</Error>
                </Field>
                <div {...stylex.props(styles.quantityGrid)}>
                  <Field invalid={Boolean(form.errors.min_quantity)}>
                    <Label htmlFor="min_quantity">Minimum per order</Label>
                    <NumberInput id="min_quantity" min={1} name="min_quantity" onValueChange={(value) => form.setData('min_quantity', value)} placeholder="No minimum" value={form.data.min_quantity} />
                    <Error>{form.errors.min_quantity}</Error>
                  </Field>
                  <Field invalid={Boolean(form.errors.max_quantity)}>
                    <Label htmlFor="max_quantity">Maximum per order</Label>
                    <NumberInput id="max_quantity" min={1} name="max_quantity" onValueChange={(value) => form.setData('max_quantity', value)} placeholder="No maximum" value={form.data.max_quantity} />
                    <Error>{form.errors.max_quantity}</Error>
                  </Field>
                </div>
                <Field invalid={Boolean(form.errors.allow_backorders)}>
                  <label {...stylex.props(styles.checkboxRow)}>
                    <input checked={form.data.allow_backorders} name="allow_backorders" onChange={(event) => form.setData('allow_backorders', event.target.checked)} type="checkbox" {...stylex.props(styles.checkbox)} />
                    <span>
                      <strong {...stylex.props(styles.checkboxTitle)}>Allow backorders</strong>
                      <span {...stylex.props(styles.checkboxDescription)}>Keep accepting orders when available stock reaches zero.</span>
                    </span>
                  </label>
                  <Error>{form.errors.allow_backorders}</Error>
                </Field>
            </Card.Body>
          </Card>
          </div>
        </Form>
        </div>
      </div>
      <Toast.Portal>
        <Toast.Viewport {...stylex.props(styles.toastViewport)}>
          <AnimatePresence>
            {toasts.map((toast) => (
              <motion.div
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: 12 }}
                initial={{ opacity: 0, y: 12 }}
                key={toast.id}
                transition={{ damping: 30, mass: 0.8, stiffness: 400, type: 'spring' }}
                {...stylex.props(styles.toastMotion)}
              >
                <Toast.Root swipeDirection={[]} toast={toast} {...stylex.props(styles.toast)}>
                  <Toast.Content {...stylex.props(styles.toastContent)}>
                    <Toast.Title {...stylex.props(styles.toastTitle)} />
                    <div {...stylex.props(styles.toastActions)}>
                      <button
                        disabled={form.processing}
                        onClick={() => {
                          form.reset()
                          form.clearErrors()
                        }}
                        type="button"
                        {...stylex.props(styles.toastButton, styles.toastResetButton)}
                      >
                        Reset
                      </button>
                      <button disabled={form.processing} onClick={submitProduct} type="button" {...stylex.props(styles.toastButton, styles.toastSaveButton)}>
                        Save
                      </button>
                    </div>
                  </Toast.Content>
                </Toast.Root>
              </motion.div>
            ))}
          </AnimatePresence>
        </Toast.Viewport>
      </Toast.Portal>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { marginInline: 'auto', maxWidth: 960, paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 }, width: '100%' },
  pageHeader: { alignItems: 'center', display: 'flex', justifyContent: 'space-between', marginBottom: 24, minHeight: 48 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginTop: 4, userSelect: 'text' },
  backLink: { color: { default: 'var(--color-brand-700)', ':hover': 'var(--color-brand-900)' }, fontSize: 13, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: 'none' },
  cards: { display: 'grid', gap: 52 },
  textarea: { backgroundColor: '#fff', borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)', color: 'var(--color-neutral-900)', fontSize: 15, fontWeight: 500, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 12, resize: 'vertical', width: '100%' },
  quantityGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': '1fr' } },
  priceGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'minmax(0, 1fr) 160px', '@media (max-width: 640px)': '1fr' } },
  checkboxRow: { alignItems: 'flex-start', cursor: 'pointer', display: 'flex', gap: 10 },
  checkbox: { accentColor: 'var(--color-brand-500)', appearance: 'auto', flexShrink: 0, height: 18, marginTop: 2, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, width: 18 },
  checkboxTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  checkboxDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  settingRow: { alignItems: 'center', cursor: 'pointer', display: 'flex', gap: 16, justifyContent: 'space-between' },
  settingTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  settingDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  toastViewport: { bottom: 24, display: 'flex', justifyContent: 'center', left: 0, outline: 0, pointerEvents: 'none', position: 'fixed', width: '100%', zIndex: 20 },
  toastMotion: { maxWidth: 'calc(100vw - 32px)', pointerEvents: 'auto', width: 420 },
  toast: { backgroundClip: 'padding-box', backgroundColor: 'var(--color-neutral-950)', borderColor: 'rgba(255, 255, 255, 0.16)', borderRadius: 8, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 12px 32px rgba(20, 15, 18, 0.22)', color: '#fff', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, width: '100%' },
  toastContent: { alignItems: 'center', display: 'flex', gap: 20, justifyContent: 'space-between', padding: 12 },
  toastTitle: { fontSize: 14, fontWeight: 650 },
  toastActions: { display: 'flex', flexShrink: 0, gap: 8 },
  toastButton: { borderRadius: 6, cursor: { default: 'pointer', ':disabled': 'wait' }, fontSize: 14, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-300)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, paddingBlock: 7, paddingInline: 12 },
  toastResetButton: { backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-800)' }, borderColor: 'var(--color-neutral-700)', borderStyle: 'solid', borderWidth: 1, color: '#fff' },
  toastSaveButton: { backgroundColor: { default: 'var(--color-brand-500)', ':hover': 'var(--color-brand-600)' }, color: '#fff' },
})

function currencyFractionDigits(currency: Currency) {
  return new Intl.NumberFormat(undefined, { currency, style: 'currency' }).resolvedOptions().maximumFractionDigits
}
