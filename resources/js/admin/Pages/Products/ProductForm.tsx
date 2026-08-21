import type { InertiaFormProps } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { createContext, useContext, useState, type ReactNode } from 'react'
import Card, { cardBodySpacing } from '../../Components/Card'
import Checkbox from '../../Components/Checkbox'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Input from '../../Components/Input'
import Inset from '../../Components/Inset'
import Label from '../../Components/Label'
import NumberInput from '../../Components/NumberInput'
import RadioGroup from '../../Components/RadioGroup'
import Select from '../../Components/Select'
import Toggle from '../../Components/Toggle'

export type ProductFormData = {
  name: string
  slug?: string
  description: string
  stock: number | null
  min_quantity: number | null
  max_quantity: number | null
  allow_backorders: boolean
  status: 'visible' | 'hidden'
  price_amount: number
  category_ids: string[]
  option_value_ids: string[]
  image_order?: Array<number | string>
  new_image_ids?: Array<number | string>
}

type ProductFormProps = {
  children: ReactNode
  categories: ProductCategory[]
  productOptions: ProductOption[]
  form: InertiaFormProps<ProductFormData>
}

export type ProductCategory = { label: string; value: string }
export type ProductOptionValue = { id: string; name: string; value: boolean | number | string }
export type ProductOption = { id: string; name: string; type: 'boolean' | 'number' | 'text'; values: ProductOptionValue[] }

type ProductFormContextValue = {
  categories: ProductCategory[]
  productOptions: ProductOption[]
  form: InertiaFormProps<ProductFormData>
}

const ProductFormContext = createContext<ProductFormContextValue | null>(null)

function ProductForm({ categories, children, form, productOptions }: ProductFormProps) {
  return <ProductFormContext value={{ categories, form, productOptions }}>{children}</ProductFormContext>
}

function GeneralSection({ includeSlug = false }: { includeSlug?: boolean }) {
  const form = useProductForm()

  return (
    <Card>
      <Card.Header>
        <Card.Title>General information</Card.Title>
        <Card.Description>{includeSlug ? 'Update the product name, URL slug, and customer-facing description.' : 'Add the product name and customer-facing description.'}</Card.Description>
      </Card.Header>
      <Card.Body>
        <Field invalid={Boolean(form.errors.name)}>
          <Label htmlFor="name">
            Name
            <span aria-hidden="true" {...stylex.props(styles.required)}>*</span>
          </Label>
          <Input autoFocus={!includeSlug} id="name" name="name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
          <Error>{form.errors.name}</Error>
        </Field>
        {includeSlug && (
          <Field invalid={Boolean(form.errors.slug)}>
            <Label htmlFor="slug">Slug</Label>
            <Input id="slug" name="slug" onChange={(event) => form.setData('slug', event.target.value)} required value={form.data.slug ?? ''} />
            <Error>{form.errors.slug}</Error>
          </Field>
        )}
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
  )
}

function PricingSection() {
  const form = useProductForm()

  return (
    <Card>
      <Card.Header><Card.Title>Pricing</Card.Title><Card.Description>Set the product's selling price and currency.</Card.Description></Card.Header>
      <Card.Body>
        <Field invalid={Boolean(form.errors.price_amount)}>
          <Label htmlFor="price_amount">Amount in minor units</Label>
          <NumberInput id="price_amount" min={0} name="price_amount" onValueChange={(value) => form.setData('price_amount', value ?? 0)} value={form.data.price_amount} />
          <Error>{form.errors.price_amount}</Error>
        </Field>
      </Card.Body>
    </Card>
  )
}

function StockSection() {
  const form = useProductForm()

  return (
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
  )
}

function CategoriesSection() {
  const { categories, form } = useProductFormContext()

  return (
    <Card>
      <Card.Header>
        <Card.Title>Categories</Card.Title>
        <Card.Description>Choose the categories customers can use to discover this product.</Card.Description>
      </Card.Header>
      <Card.Body>
        <Field invalid={Boolean(form.errors.category_ids)}>
          <Label htmlFor="category_ids">Product categories</Label>
          <Select
            id="category_ids"
            items={categories}
            multiple
            name="category_ids[]"
            onValueChange={(value) => form.setData('category_ids', value)}
            placeholder="No categories"
            value={form.data.category_ids}
          />
          <Error>{form.errors.category_ids}</Error>
        </Field>
      </Card.Body>
    </Card>
  )
}

function OptionsSection() {
  const { form, productOptions } = useProductFormContext()
  const [activeOptionId, setActiveOptionId] = useState(productOptions[0]?.id ?? null)
  const activeOption = productOptions.find((option) => option.id === activeOptionId) ?? productOptions[0]

  function toggleValue(valueId: string, checked: boolean) {
    form.setData('option_value_ids', checked
      ? [...form.data.option_value_ids, valueId]
      : form.data.option_value_ids.filter((selectedId) => selectedId !== valueId))
  }

  function setBooleanValue(option: ProductOption, valueId: string) {
    const optionValueIds = new Set(option.values.map((value) => value.id))
    const otherValueIds = form.data.option_value_ids.filter((selectedId) => !optionValueIds.has(selectedId))

    form.setData('option_value_ids', valueId === '' ? otherValueIds : [...otherValueIds, valueId])
  }

  return (
    <Card>
      <Card.Header>
        <Card.Title>Product options</Card.Title>
        <Card.Description>Choose the option values that describe this product.</Card.Description>
      </Card.Header>
      <Card.Body>
        <Field invalid={Boolean(form.errors.option_value_ids)}>
          {productOptions.length === 0 ? (
            <p {...stylex.props(styles.optionsEmpty)}>No product options with values are available.</p>
          ) : (
            <Inset
              bottom={cardBodySpacing.paddingBlock}
              left={cardBodySpacing.paddingInline}
              right={cardBodySpacing.paddingInline}
              top={cardBodySpacing.paddingBlock}
            >
              <div {...stylex.props(styles.optionPicker)}>
                <div aria-label="Product options" role="tablist" {...stylex.props(styles.optionGroups)}>
                  {productOptions.map((option) => {
                    const active = option.id === activeOption?.id

                    return (
                      <button
                        aria-controls={`product-option-values-${option.id}`}
                        aria-selected={active}
                        id={`product-option-${option.id}`}
                        key={option.id}
                        onClick={() => setActiveOptionId(option.id)}
                        role="tab"
                        type="button"
                        {...stylex.props(styles.optionGroup, active && styles.optionGroupActive)}
                      >
                        <span {...stylex.props(styles.optionGroupName)}>{option.name}</span>
                      </button>
                    )
                  })}
                </div>
                {activeOption && (
                  <div
                    aria-labelledby={`product-option-${activeOption.id}`}
                    id={`product-option-values-${activeOption.id}`}
                    role="tabpanel"
                    {...stylex.props(styles.optionValues)}
                  >
                    {activeOption.type === 'boolean' ? (
                      <RadioGroup
                        aria-label={`${activeOption.name} state`}
                        items={[{ value: '', label: 'Not selected' }, ...activeOption.values.map((value) => ({
                          label: value.name,
                          value: value.id,
                        }))]}
                        onValueChange={(value) => setBooleanValue(activeOption, value)}
                        value={activeOption.values.find((value) => form.data.option_value_ids.includes(value.id))?.id ?? ''}
                      />
                    ) : activeOption.values.map((value) => (
                        <label key={value.id} {...stylex.props(styles.optionValue)}>
                          <Checkbox
                            checked={form.data.option_value_ids.includes(value.id)}
                            name="option_value_ids[]"
                            onCheckedChange={(checked) => toggleValue(value.id, checked)}
                            value={value.id}
                          />
                          <span>{value.name}</span>
                        </label>
                      ))}
                  </div>
                )}
              </div>
            </Inset>
          )}
          <Error>{form.errors.option_value_ids}</Error>
        </Field>
      </Card.Body>
    </Card>
  )
}

function useProductForm() {
  return useProductFormContext().form
}

function useProductFormContext() {
  const context = useContext(ProductFormContext)
  if (!context) throw new globalThis.Error('Product form sections must be rendered inside ProductForm.')
  return context
}

ProductForm.General = GeneralSection
ProductForm.Pricing = PricingSection
ProductForm.Stock = StockSection
ProductForm.Categories = CategoriesSection
ProductForm.Options = OptionsSection

export default ProductForm

const styles = stylex.create({
  required: { color: 'oklch(50.5% 0.213 27.518)', marginLeft: 3 },
  textarea: { backgroundColor: '#fff', borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)', color: 'var(--color-neutral-900)', fontSize: 15, fontWeight: 500, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 12, resize: 'vertical', width: '100%' },
  quantityGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': '1fr' } },
  checkboxRow: { alignItems: 'flex-start', cursor: 'pointer', display: 'flex', gap: 10 },
  checkboxTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  checkboxDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  settingRow: { alignItems: 'center', cursor: 'pointer', display: 'flex', gap: 16, justifyContent: 'space-between' },
  settingTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  settingDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  optionPicker: { backgroundColor: '#fff', borderRadius: 7, display: 'grid', gridTemplateColumns: { default: 'minmax(150px, 0.4fr) minmax(0, 1fr)', '@media (max-width: 640px)': 'minmax(110px, 0.45fr) minmax(0, 1fr)' }, minHeight: 220, overflow: 'hidden' },
  optionGroups: { backgroundColor: '#fff', borderRightColor: 'var(--color-neutral-200)', borderRightStyle: 'solid', borderRightWidth: 1, display: 'flex', flexDirection: 'column', padding: 6 },
  optionGroup: { alignItems: 'center', backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' }, borderColor: 'transparent', borderRadius: 4, borderStyle: 'solid', borderWidth: 0, color: 'var(--color-neutral-700)', cursor: 'pointer', display: 'flex', fontFamily: 'inherit', fontSize: 14, fontWeight: 500, gap: 8, justifyContent: 'space-between', minHeight: 38, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: -2, outlineStyle: 'solid', outlineWidth: 2, paddingBlock: 8, paddingInline: 10, textAlign: 'left', width: '100%' },
  optionGroupActive: { backgroundColor: 'var(--color-neutral-200)', color: 'var(--color-neutral-950)' },
  optionGroupName: { minWidth: 0, overflowWrap: 'anywhere' },
  optionValues: { alignContent: 'start', backgroundColor: '#fff', display: 'grid', gap: 2, padding: 10 },
  optionValue: { alignItems: 'flex-start', borderRadius: 4, color: 'var(--color-neutral-800)', cursor: 'pointer', display: 'flex', fontSize: 14, fontWeight: 500, gap: 10, minHeight: 38, overflowWrap: 'anywhere', paddingBlock: 8, paddingInline: 8 },
  optionsEmpty: { color: 'var(--color-neutral-500)', fontSize: 14, margin: 0 },
})
