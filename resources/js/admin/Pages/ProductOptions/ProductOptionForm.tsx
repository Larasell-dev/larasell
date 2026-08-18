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

export type ProductOptionType = 'boolean' | 'number' | 'text'
export type ProductOptionValue = { id?: number | string; value: number | null | string }
export type ProductOption = {
  name: string
  type: ProductOptionType
  values: ProductOptionValue[]
}

type FormData = {
  name: string
  type: ProductOptionType
  options: Array<ProductOptionValue & { key: string }>
}

type ProductOptionFormContextValue = {
  addOption: () => void
  cancelTypeChange: () => void
  changeType: (type: ProductOptionType) => void
  confirmTypeChange: () => void
  errors: Record<string, string>
  name: string
  options: FormData['options']
  pendingType: ProductOptionType | null
  removeOption: (key: string) => void
  setName: (name: string) => void
  type: ProductOptionType
  updateOption: (key: string, value: ProductOptionValue['value']) => void
}

type Props = {
  action: string
  initialProductOption?: ProductOption
  method: 'patch' | 'post'
}

const productOptionTypes = [
  { label: 'Text', value: 'text' },
  { label: 'Number', value: 'number' },
  { label: 'Boolean', value: 'boolean' },
] as const

const ProductOptionFormContext = createContext<ProductOptionFormContextValue | null>(null)

export default function ProductOptionForm({ action, initialProductOption, method }: Props) {
  const form = useForm<FormData>({
    name: initialProductOption?.name ?? '',
    type: initialProductOption?.type ?? 'text',
    options: initialOptions(initialProductOption),
  })
  const [pendingType, setPendingType] = useState<ProductOptionType | null>(null)

  const emptyOption = (type: ProductOptionType): FormData['options'][number] => ({
    key: crypto.randomUUID(),
    value: type === 'number' ? null : '',
  })

  const addOption = () => form.setData('options', [...form.data.options, emptyOption(form.data.type)])

  const updateOption = (key: string, value: ProductOptionValue['value']) => {
    const options = form.data.options.map((option) => option.key === key ? { ...option, value } : option)

    if (isOptionValueFilled(value) && options.every((option) => isOptionValueFilled(option.value))) {
      options.push(emptyOption(form.data.type))
    }

    form.setData('options', options)
  }

  const removeOption = (key: string) => {
    form.setData('options', form.data.options.filter((option) => option.key !== key))
  }

  const changeType = (nextType: ProductOptionType) => {
    if (nextType === form.data.type) return

    if (form.data.options.some((option) => isOptionValueFilled(option.value))) {
      setPendingType(nextType)
      return
    }

    form.setData((current) => ({ ...current, type: nextType, options: [emptyOption(nextType)] }))
  }

  const confirmTypeChange = () => {
    if (pendingType === null) return

    form.setData((current) => ({ ...current, type: pendingType, options: [emptyOption(pendingType)] }))
    setPendingType(null)
  }

  const submit = () => {
    form.transform((data) => ({
      ...data,
      options: data.type === 'boolean'
        ? []
        : data.options
          .filter((option) => isOptionValueFilled(option.value))
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

  const context: ProductOptionFormContextValue = {
    addOption,
    cancelTypeChange: () => setPendingType(null),
    changeType,
    confirmTypeChange,
    errors: form.errors,
    name: form.data.name,
    options: form.data.options,
    pendingType,
    removeOption,
    setName: (name) => form.setData('name', name),
    type: form.data.type,
    updateOption,
  }

  return (
    <ProductOptionFormContext value={context}>
      <Form onSubmit={handleSubmit}>
        <div {...stylex.props(styles.cards)}>
          <GeneralSection />
          {form.data.type !== 'boolean' && <OptionsSection />}
        </div>
      </Form>
    </ProductOptionFormContext>
  )
}

function GeneralSection() {
  const form = useProductOptionForm()

  return (
    <>
      <Card>
        <Card.Header>
          <Card.Title>General</Card.Title>
          <Card.Description>Set the name and value type for this product option.</Card.Description>
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
              <Select id="type" items={productOptionTypes} name="type" onValueChange={form.changeType} required value={form.type} />
              <Error>{form.errors.type}</Error>
            </Field>
          </div>
        </Card.Body>
      </Card>

      <Dialog
        description="Changing the type will remove the option values you have entered because they cannot be migrated to the new type."
        onOpenChange={(open) => !open && form.cancelTypeChange()}
        open={form.pendingType !== null}
        title="Change product option type?"
      >
        <Button onClick={form.cancelTypeChange} type="button" variant="secondary">Cancel</Button>
        <Button onClick={form.confirmTypeChange} type="button">Change type</Button>
      </Dialog>
    </>
  )
}

function OptionsSection() {
  const form = useProductOptionForm()

  return (
    <Card>
      <div {...stylex.props(styles.optionsHeader)}>
        <Card.Header>
          <Card.Title>Options</Card.Title>
          <Card.Description>Add the choices customers can select.</Card.Description>
        </Card.Header>
        <Button onClick={form.addOption} type="button" variant="secondary">
          <Icon height={16} name="plus" width={16} />
          Add option
        </Button>
      </div>
      <Card.Body>
        <div {...stylex.props(styles.optionsGrid)}>
          {form.options.map((option, index) => (
            <Field invalid={Boolean(form.errors[`options.${index}.value`])} key={option.key}>
              <Label htmlFor={`option-${option.key}`}>Option {index + 1}</Label>
              <div {...stylex.props(styles.optionInput)}>
                {form.type === 'number' ? (
                  <NumberInput
                    id={`option-${option.key}`}
                    name={`options[${index}][value]`}
                    onValueChange={(value) => form.updateOption(option.key, value)}
                    placeholder={index === 0 ? '10' : 'Option value'}
                    value={typeof option.value === 'number' ? option.value : null}
                  />
                ) : (
                  <Input
                    id={`option-${option.key}`}
                    name={`options[${index}][value]`}
                    onChange={(event) => form.updateOption(option.key, event.target.value)}
                    placeholder={index === 0 ? 'Small' : 'Option value'}
                    value={typeof option.value === 'string' ? option.value : ''}
                  />
                )}
                <BaseButton
                  aria-label={`Remove option ${index + 1}`}
                  disabled={form.options.length === 1}
                  onClick={() => form.removeOption(option.key)}
                  title={`Remove option ${index + 1}`}
                  type="button"
                  {...stylex.props(styles.removeButton, form.options.length === 1 && styles.removeButtonDisabled)}
                >
                  <Icon height={18} name="trash" width={18} />
                </BaseButton>
              </div>
              <Error>{form.errors[`options.${index}.value`]}</Error>
            </Field>
          ))}
        </div>
      </Card.Body>
    </Card>
  )
}

function useProductOptionForm() {
  const context = useContext(ProductOptionFormContext)

  if (context === null) throw new globalThis.Error('useProductOptionForm must be used within ProductOptionForm')

  return context
}

function initialOptions(productOption?: ProductOption): FormData['options'] {
  if (!productOption || productOption.values.length === 0) {
    return [{ key: crypto.randomUUID(), value: productOption?.type === 'number' ? null : '' }]
  }

  return productOption.values.map((option) => ({ ...option, key: `option-${option.id}` }))
}

function isOptionValueFilled(value: ProductOptionValue['value']) {
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
