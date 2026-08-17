import { useForm } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { FormEvent } from 'react'

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
      <form {...stylex.props(styles.form)} onSubmit={submit}>
        <h1 {...stylex.props(styles.heading)}>Larasell Admin</h1>

        <label {...stylex.props(styles.label)}>
          <span>Email</span>
          <input
            autoComplete="email"
            autoFocus
            onChange={(event) => form.setData('email', event.target.value)}
            {...stylex.props(styles.input)}
            type="email"
            value={form.data.email}
          />
        </label>
        {form.errors.email ? (
          <p {...stylex.props(styles.error)}>{form.errors.email}</p>
        ) : null}

        <label {...stylex.props(styles.label)}>
          <span>Password</span>
          <input
            autoComplete="current-password"
            onChange={(event) => form.setData('password', event.target.value)}
            {...stylex.props(styles.input)}
            type="password"
            value={form.data.password}
          />
        </label>
        {form.errors.password ? (
          <p {...stylex.props(styles.error)}>{form.errors.password}</p>
        ) : null}

        <button
          disabled={form.processing}
          {...stylex.props(styles.button, form.processing && styles.processing)}
          type="submit"
        >
          Sign in
        </button>
      </form>
    </main>
  )
}

const styles = stylex.create({
  page: {
    background: '#f6f5f2',
    color: '#202020',
    display: 'grid',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
    paddingBottom: 0,
    paddingLeft: 16,
    paddingRight: 16,
    paddingTop: 0,
    placeItems: 'center',
  },
  form: {
    display: 'grid',
    gap: 16,
    maxWidth: 380,
    width: '100%',
  },
  heading: {
    fontSize: 28,
    fontWeight: 650,
    margin: 0,
  },
  label: {
    display: 'grid',
    fontSize: 14,
    fontWeight: 500,
    gap: 6,
  },
  input: {
    background: '#fff',
    border: '1px solid #c9c6bd',
    borderRadius: 6,
    color: '#202020',
    font: 'inherit',
    paddingBottom: 10,
    paddingLeft: 12,
    paddingRight: 12,
    paddingTop: 10,
  },
  error: {
    color: '#b42318',
    fontSize: 13,
    marginBottom: 0,
    marginLeft: 0,
    marginRight: 0,
    marginTop: -8,
  },
  button: {
    background: '#202020',
    border: 0,
    borderRadius: 6,
    color: '#fff',
    font: 'inherit',
    fontWeight: 600,
    cursor: 'pointer',
    paddingBottom: 11,
    paddingLeft: 14,
    paddingRight: 14,
    paddingTop: 11,
  },
  processing: {
    cursor: 'wait',
    opacity: 0.65,
  },
})
