import { Head, useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../../Components/AdminLayout'
import BackLink from '../../../Components/BackLink'
import Card from '../../../Components/Card'
import Error from '../../../Components/Error'
import Field from '../../../Components/Field'
import Form from '../../../Components/Form'
import FormContainer from '../../../Components/FormContainer'
import Label from '../../../Components/Label'
import Select from '../../../Components/Select'
import useUnsavedChanges from '../../../Hooks/useUnsavedChanges'

type Currency = { code: string; enabled: boolean }
type Props = AdminLayoutProps & { currencies: Currency[]; updateUrl: string }

export default function CurrencySettings({ currencies, updateUrl, ...layoutProps }: Props) {
  const form = useForm({ currencies: currencies.filter((currency) => currency.enabled).map((currency) => currency.code) })
  const currencyOptions = currencies.map((currency) => ({ label: currency.code, value: currency.code }))

  function submit() {
    if (form.data.currencies.length === 0) return

    form.patch(updateUrl, {
      preserveScroll: true,
      onSuccess: () => form.setDefaults('currencies', form.data.currencies),
    })
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
    <AdminLayout active="settings" {...layoutProps}>
      <Head title="Currencies" />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.content}>
          <header {...stylex.props(styles.header)}>
            <BackLink href={layoutProps.settingsUrl}>Back to settings</BackLink>
            <h1 {...stylex.props(styles.heading)}>Currencies</h1>
          </header>
          <Form errors={form.errors} onSubmit={handleSubmit}>
            <Card>
              <Card.Header>
                <Card.Title>Available currencies</Card.Title>
                <Card.Description>Enabled currencies can be selected when setting a product price.</Card.Description>
              </Card.Header>
              <Card.Body>
                <Field invalid={form.data.currencies.length === 0 || Boolean(form.errors.currencies)}>
                  <Label htmlFor="currencies">Currencies</Label>
                  <Select
                    id="currencies"
                    items={currencyOptions}
                    multiple
                    name="currencies"
                    onValueChange={(value) => form.setData('currencies', value)}
                    placeholder="Select currencies"
                    value={form.data.currencies}
                  />
                  {form.data.currencies.length === 0 && <Error>Enable at least one currency.</Error>}
                  <Error>{form.errors.currencies}</Error>
                </Field>
              </Card.Body>
            </Card>
          </Form>
        </FormContainer>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  content: { paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  header: { marginBottom: 24 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginBlock: 4, marginInline: 0 },
})
