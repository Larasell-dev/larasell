import { Button as BaseButton } from '@base-ui/react/button'
import { useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { createContext, useContext, useState, type FormEvent, type ReactNode } from 'react'
import Button from '../../Components/Button'
import Card from '../../Components/Card'
import Dialog from '../../Components/Dialog'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Form from '../../Components/Form'
import Icon from '../../Components/Icon'
import Input from '../../Components/Input'
import Label from '../../Components/Label'
import NumberInput from '../../Components/NumberInput'
import Select from '../../Components/Select'
import useUnsavedChanges from '../../Hooks/useUnsavedChanges'

export type ProductAttributeType = 'boolean' | 'number' | 'text'
export type ProductAttributeValue = { id?: number | string; value: boolean | number | null | string }
export type ProductAttribute = {
  name: string
  type: ProductAttributeType
  values: ProductAttributeValue[]
}

type FormData = {
  name: string
  type: ProductAttributeType
  values: Array<ProductAttributeValue & { key: string }>
}

type ProductAttributeFormContextValue = {
  addValue: () => void
  cancelTypeChange: () => void
  changeType: (type: ProductAttributeType) => void
  confirmTypeChange: () => void
  errors: Record<string, string>
  name: string
  values: FormData['values']
  pendingType: ProductAttributeType | null
  removeValue: (key: string) => void
  setName: (name: string) => void
  type: ProductAttributeType
  updateValue: (key: string, value: ProductAttributeValue['value']) => void
}

type Props = {
  action: string
  initialProductAttribute?: ProductAttribute
  method: 'patch' | 'post'
}

const productAttributeTypes = [
  { label: 'Text', value: 'text' },
  { label: 'Number', value: 'number' },
  { label: 'Boolean', value: 'boolean' },
] as const

const ProductAttributeFormContext = createContext<ProductAttributeFormContextValue | null>(null)

export default function ProductAttributeForm({ action, initialProductAttribute, method }: Props) {
  const form = useForm<FormData>({
    name: initialProductAttribute?.name ?? '',
    type: initialProductAttribute?.type ?? 'text',
    values: initialValues(initialProductAttribute),
  })
  const [pendingType, setPendingType] = useState<ProductAttributeType | null>(null)

  const emptyValue = (type: ProductAttributeType): FormData['values'][number] => ({
    key: crypto.randomUUID(),
    value: type === 'number' ? null : '',
  })

  const addValue = () => form.setData('values', [...form.data.values, emptyValue(form.data.type)])

  const updateValue = (key: string, nextValue: ProductAttributeValue['value']) => {
    const values = form.data.values.map((value) => value.key === key ? { ...value, value: nextValue } : value)

    if (isAttributeValueFilled(nextValue) && values.every((value) => isAttributeValueFilled(value.value))) {
      values.push(emptyValue(form.data.type))
    }

    form.setData('values', values)
  }

  const removeValue = (key: string) => {
    form.setData('values', form.data.values.filter((value) => value.key !== key))
  }

  const changeType = (nextType: ProductAttributeType) => {
    if (nextType === form.data.type) return

    if (form.data.values.some((value) => isAttributeValueFilled(value.value))) {
      setPendingType(nextType)
      return
    }

    form.setData((current) => ({ ...current, type: nextType, values: [emptyValue(nextType)] }))
  }

  const confirmTypeChange = () => {
    if (pendingType === null) return

    form.setData((current) => ({ ...current, type: pendingType, values: [emptyValue(pendingType)] }))
    setPendingType(null)
  }

  const submit = () => {
    form.transform((data) => ({
      ...data,
      values: data.type === 'boolean'
        ? []
        : data.values
          .filter((value) => isAttributeValueFilled(value.value))
          .map(({ id, value }) => ({ ...(id === undefined ? {} : { id }), value })),
    }))
    form[method](action, { preserveScroll: true })
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    submit()
  }

  useUnsavedChanges({
    dirty: form.isDirty,
    onReset: () => {
      form.reset()
      form.clearErrors()
      setPendingType(null)
    },
    onSave: submit,
    processing: form.processing,
  })

  const context: ProductAttributeFormContextValue = {
    addValue,
    cancelTypeChange: () => setPendingType(null),
    changeType,
    confirmTypeChange,
    errors: form.errors,
    name: form.data.name,
    values: form.data.values,
    pendingType,
    removeValue,
    setName: (name) => form.setData('name', name),
    type: form.data.type,
    updateValue,
  }

  return (
    <ProductAttributeFormContext value={context}>
      <Form onSubmit={handleSubmit}>
        <div {...stylex.props(styles.cards)}>
          <GeneralSection />
          {form.data.type !== 'boolean' && <ValuesSection />}
        </div>
      </Form>
    </ProductAttributeFormContext>
  )
}

function GeneralSection() {
  const form = useProductAttributeForm()

  return (
    <>
      <Card>
        <Card.Header>
          <Card.Title>General</Card.Title>
          <Card.Description>Set the name and value type for this product attribute.</Card.Description>
        </Card.Header>
        <Card.Body>
          <div {...stylex.props(styles.detailsGrid)}>
            <Field invalid={Boolean(form.errors.name)}>
              <Label htmlFor="name">
                Name
                <span aria-hidden="true" {...stylex.props(styles.required)}>*</span>
              </Label>
              <Input id="name" name="name" onChange={(event) => form.setName(event.target.value)} placeholder="Size" required value={form.name} />
              <Error>{form.errors.name}</Error>
            </Field>
            <Field invalid={Boolean(form.errors.type)}>
              <Label htmlFor="type">Type</Label>
              <Select id="type" items={productAttributeTypes} name="type" onValueChange={form.changeType} required value={form.type} />
              <Error>{form.errors.type}</Error>
            </Field>
          </div>
        </Card.Body>
      </Card>

      <Dialog
        description="Changing the type will remove the values you have entered because they cannot be migrated to the new type."
        onOpenChange={(open) => !open && form.cancelTypeChange()}
        open={form.pendingType !== null}
        title="Change product attribute type?"
      >
        <Button onClick={form.cancelTypeChange} type="button" variant="secondary">Cancel</Button>
        <Button onClick={form.confirmTypeChange} type="button">Change type</Button>
      </Dialog>
    </>
  )
}

function ValuesSection() {
  const form = useProductAttributeForm()

  return (
    <Card>
      <div {...stylex.props(styles.optionsHeader)}>
        <Card.Header>
          <Card.Title>Values</Card.Title>
          <Card.Description>Add the choices customers can select.</Card.Description>
        </Card.Header>
        <Button onClick={form.addValue} type="button" variant="secondary">
          <Icon height={16} name="plus" width={16} />
          Add value
        </Button>
      </div>
      <Card.Body>
        <div {...stylex.props(styles.optionsGrid)}>
          {form.values.map((value, index) => (
            <Field invalid={Boolean(form.errors[`values.${index}.value`])} key={value.key}>
              <Label htmlFor={`value-${value.key}`}>Value {index + 1}</Label>
              <div {...stylex.props(styles.optionInput)}>
                {form.type === 'number' ? (
                  <NumberInput
                    id={`value-${value.key}`}
                    name={`values[${index}][value]`}
                    onValueChange={(nextValue) => form.updateValue(value.key, nextValue)}
                    placeholder={index === 0 ? '10' : 'Value'}
                    value={typeof value.value === 'number' ? value.value : null}
                  />
                ) : (
                  <Input
                    id={`value-${value.key}`}
                    name={`values[${index}][value]`}
                    onChange={(event) => form.updateValue(value.key, event.target.value)}
                    placeholder={index === 0 ? 'Small' : 'Value'}
                    value={typeof value.value === 'string' ? value.value : ''}
                  />
                )}
                <BaseButton
                  aria-label={`Remove value ${index + 1}`}
                  disabled={form.values.length === 1}
                  onClick={() => form.removeValue(value.key)}
                  title={`Remove value ${index + 1}`}
                  type="button"
                  {...stylex.props(styles.removeButton, form.values.length === 1 && styles.removeButtonDisabled)}
                >
                  <Icon height={18} name="trash" width={18} />
                </BaseButton>
              </div>
              <Error>{form.errors[`values.${index}.value`]}</Error>
            </Field>
          ))}
        </div>
      </Card.Body>
    </Card>
  )
}

function useProductAttributeForm() {
  const context = useContext(ProductAttributeFormContext)

  if (context === null) throw new globalThis.Error('useProductAttributeForm must be used within ProductAttributeForm')

  return context
}

function initialValues(productAttribute?: ProductAttribute): FormData['values'] {
  if (!productAttribute || productAttribute.values.length === 0) {
    return [{ key: crypto.randomUUID(), value: productAttribute?.type === 'number' ? null : '' }]
  }

  return productAttribute.values.map((value) => ({ ...value, key: `value-${value.id}` }))
}

function isAttributeValueFilled(value: ProductAttributeValue['value']) {
  return value !== null && (typeof value === 'number' || value.trim() !== '')
}

const styles = stylex.create({
  cards: { display: 'grid', gap: 52 },
  detailsGrid: { alignItems: 'start', display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': 'minmax(0, 1fr)' } },
  optionsHeader: { alignItems: 'start', display: 'flex', gap: 16, justifyContent: 'space-between' },
  optionsGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': 'minmax(0, 1fr)' } },
  optionInput: { alignItems: 'stretch', display: 'grid', gap: 8, gridTemplateColumns: 'minmax(0, 1fr) 40px' },
  removeButton: { alignItems: 'center', backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' }, borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, color: 'var(--color-neutral-600)', cursor: 'pointer', display: 'flex', justifyContent: 'center', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 0 },
  removeButtonDisabled: { color: 'var(--color-neutral-300)', cursor: 'default' },
  required: { color: 'oklch(50.5% 0.213 27.518)', marginLeft: 3 },
})
