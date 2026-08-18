import { Head, useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'
import Button from '../Components/Button'
import Error from '../Components/Error'
import Field from '../Components/Field'
import Form from '../Components/Form'
import Input from '../Components/Input'
import Label from '../Components/Label'

type LoginProps = {
  loginUrl: string
}

export default function Login({ loginUrl }: LoginProps) {
  const form = useForm({
    email: '',
    password: '',
  })

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    form.post(loginUrl)
  }

  return (
    <main {...stylex.props(styles.page)}>
      <Head title="Sign in" />
      <div {...stylex.props(styles.formContainer)}>
        <Form errors={form.errors} onSubmit={submit}>
          <header {...stylex.props(styles.header)}>
            <h1 {...stylex.props(styles.heading)}>Larasell Admin</h1>
            <p {...stylex.props(styles.description)}>
              Sign in to access the Larasell administration area.
            </p>
          </header>

          <Field name="email">
            <Label>Email</Label>
            <Input
              autoComplete="email"
              autoFocus
              onChange={(event) => form.setData('email', event.target.value)}
              type="email"
              value={form.data.email}
            />
            <Error />
          </Field>

          <Field name="password">
            <Label>Password</Label>
            <Input
              autoComplete="current-password"
              onChange={(event) => form.setData('password', event.target.value)}
              type="password"
              value={form.data.password}
            />
            <Error />
          </Field>

          <Button disabled={form.processing} type="submit">
            Sign in
          </Button>
        </Form>
      </div>
    </main>
  )
}

const styles = stylex.create({
  page: {
    backgroundColor: 'var(--color-neutral-50)',
    color: 'var(--color-neutral-950)',
    display: 'grid',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
    paddingBottom: 0,
    paddingLeft: 16,
    paddingRight: 16,
    paddingTop: 0,
    placeItems: 'center',
  },
  formContainer: { maxWidth: 380, width: '100%' },
  heading: {
    fontSize: 28,
    fontWeight: 650,
    margin: 0,
    textAlign: 'center',
  },
  header: {
    display: 'grid',
    gap: 6,
    marginBottom: 8,
  },
  description: {
    color: 'var(--color-neutral-600)',
    fontSize: 14,
    margin: 0,
    textAlign: 'center',
  },
})
