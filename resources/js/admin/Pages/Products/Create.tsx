import { Head, useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import BackLink from '../../Components/BackLink'
import Button from '../../Components/Button'
import Card from '../../Components/Card'
import Checkbox from '../../Components/Checkbox'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Form from '../../Components/Form'
import FormContainer from '../../Components/FormContainer'
import Input from '../../Components/Input'
import Label from '../../Components/Label'
import NumberInput from '../../Components/NumberInput'
import Select from '../../Components/Select'
import Toggle from '../../Components/Toggle'

type Currency = 'USD' | 'EUR' | 'GBP' | 'CAD' | 'AUD' | 'NZD' | 'CHF' | 'JPY'
type Props = AdminLayoutProps & { productStoreUrl: string }

const currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY']
  .map((currency) => ({ label: currency, value: currency as Currency }))

export default function ProductCreate({ productStoreUrl, ...layoutProps }: Props) {
  const form = useForm({
    name: '',
    description: '',
    stock: null as number | null,
    min_quantity: null as number | null,
    max_quantity: null as number | null,
    allow_backorders: true,
    status: 'visible' as 'visible' | 'hidden',
    price_amount: 0,
    price_currency: 'EUR' as Currency,
  })

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    form.post(productStoreUrl)
  }

  return (
    <AdminLayout active="products" {...layoutProps}>
      <Head title="Create product" />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.pageContent}>
          <BackLink href={layoutProps.productsUrl}>Back to products</BackLink>
          <h1 {...stylex.props(styles.heading)}>Create product</h1>
          <Form onSubmit={submit}>
            <div {...stylex.props(styles.cards)}>
              <Card>
                <Card.Header>
                  <Card.Title>General information</Card.Title>
                  <Card.Description>Add the product name and customer-facing description.</Card.Description>
                </Card.Header>
                <Card.Body>
                  <Field invalid={Boolean(form.errors.name)}>
                    <Label htmlFor="name">Name</Label>
                    <Input autoFocus id="name" name="name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
                    <Error>{form.errors.name}</Error>
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
                <Card.Header><Card.Title>Pricing</Card.Title><Card.Description>Set the product's selling price and currency.</Card.Description></Card.Header>
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
                <Card.Header><Card.Title>Stock</Card.Title><Card.Description>Control availability, order limits, and backorders.</Card.Description></Card.Header>
                <Card.Body>
                  <Field invalid={Boolean(form.errors.stock)}>
                    <Label htmlFor="stock">Available stock</Label>
                    <NumberInput id="stock" min={0} name="stock" onValueChange={(value) => form.setData('stock', value)} placeholder="Unlimited" value={form.data.stock} />
                    <Error>{form.errors.stock}</Error>
                  </Field>
                  <div {...stylex.props(styles.quantityGrid)}>
                    <Field invalid={Boolean(form.errors.min_quantity)}><Label htmlFor="min_quantity">Minimum per order</Label><NumberInput id="min_quantity" min={1} name="min_quantity" onValueChange={(value) => form.setData('min_quantity', value)} placeholder="No minimum" value={form.data.min_quantity} /><Error>{form.errors.min_quantity}</Error></Field>
                    <Field invalid={Boolean(form.errors.max_quantity)}><Label htmlFor="max_quantity">Maximum per order</Label><NumberInput id="max_quantity" min={1} name="max_quantity" onValueChange={(value) => form.setData('max_quantity', value)} placeholder="No maximum" value={form.data.max_quantity} /><Error>{form.errors.max_quantity}</Error></Field>
                  </div>
                  <Field invalid={Boolean(form.errors.allow_backorders)}>
                    <label {...stylex.props(styles.checkboxRow)}>
                      <Checkbox checked={form.data.allow_backorders} name="allow_backorders" onCheckedChange={(checked) => form.setData('allow_backorders', checked)} />
                      <span><strong {...stylex.props(styles.checkboxTitle)}>Allow backorders</strong><span {...stylex.props(styles.checkboxDescription)}>Keep accepting orders when available stock reaches zero.</span></span>
                    </label>
                    <Error>{form.errors.allow_backorders}</Error>
                  </Field>
                </Card.Body>
              </Card>
            </div>
            <div {...stylex.props(styles.actions)}><Button disabled={form.processing} type="submit">Create product</Button></div>
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
  actions: { display: 'flex', justifyContent: 'flex-end' },
  textarea: { backgroundColor: '#fff', borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)', color: 'var(--color-neutral-900)', fontSize: 15, fontWeight: 500, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 12, resize: 'vertical', width: '100%' },
  priceGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'minmax(0, 1fr) 160px', '@media (max-width: 640px)': '1fr' } },
  quantityGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': '1fr' } },
  checkboxRow: { alignItems: 'flex-start', cursor: 'pointer', display: 'flex', gap: 10 },
  checkboxTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  checkboxDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  settingRow: { alignItems: 'center', cursor: 'pointer', display: 'flex', gap: 16, justifyContent: 'space-between' },
  settingTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  settingDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
})
