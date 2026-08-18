import { useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'
import Card from '../../../Components/Card'
import Error from '../../../Components/Error'
import Field from '../../../Components/Field'
import Form from '../../../Components/Form'
import Input from '../../../Components/Input'
import Label from '../../../Components/Label'
import useUnsavedChanges from '../../../Hooks/useUnsavedChanges'

export type Member = { email: string; id: number | string; name: string }

type FormData = {
  email: string
  name: string
  password: string
  password_confirmation: string
}

type Props = {
  action: string
  initialMember?: Member
  method: 'patch' | 'post'
}

export default function MemberForm({ action, initialMember, method }: Props) {
  const form = useForm<FormData>({
    email: initialMember?.email ?? '',
    name: initialMember?.name ?? '',
    password: '',
    password_confirmation: '',
  })
  const editing = initialMember !== undefined

  const submit = () => {
    form[method](action, {
      preserveScroll: true,
      onSuccess: () => {
        if (!editing) return

        const saved = { ...form.data, password: '', password_confirmation: '' }
        form.setDefaults(saved)
        form.setData(saved)
      },
    })
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
    },
    onSave: submit,
    processing: form.processing,
  })

  return (
    <Form errors={form.errors} onSubmit={handleSubmit}>
      <div {...stylex.props(styles.cards)}>
        <Card>
          <Card.Header>
            <Card.Title>Member details</Card.Title>
            <Card.Description>Set the member's name and email address.</Card.Description>
          </Card.Header>
          <Card.Body>
            <Field invalid={Boolean(form.errors.name)} name="name">
              <Label htmlFor="name">Name</Label>
              <Input autoComplete="name" autoFocus id="name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
              <Error>{form.errors.name}</Error>
            </Field>
            <Field invalid={Boolean(form.errors.email)} name="email">
              <Label htmlFor="email">Email</Label>
              <Input autoComplete="email" id="email" onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} />
              <Error>{form.errors.email}</Error>
            </Field>
          </Card.Body>
        </Card>

        <Card>
          <Card.Header>
            <Card.Title>Password</Card.Title>
            <Card.Description>{editing ? 'Leave these fields blank to keep the current password.' : 'Choose a password for this member.'}</Card.Description>
          </Card.Header>
          <Card.Body>
            <Field invalid={Boolean(form.errors.password)} name="password">
              <Label htmlFor="password">Password</Label>
              <Input autoComplete="new-password" id="password" minLength={8} onChange={(event) => form.setData('password', event.target.value)} required={!editing} type="password" value={form.data.password} />
              <Error>{form.errors.password}</Error>
            </Field>
            <Field invalid={Boolean(form.errors.password_confirmation)} name="password_confirmation">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input autoComplete="new-password" id="password_confirmation" minLength={8} onChange={(event) => form.setData('password_confirmation', event.target.value)} required={!editing || form.data.password !== ''} type="password" value={form.data.password_confirmation} />
              <Error>{form.errors.password_confirmation}</Error>
            </Field>
          </Card.Body>
        </Card>
      </div>
    </Form>
  )
}

const styles = stylex.create({
  cards: { display: 'grid', gap: 52 },
})
