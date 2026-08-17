import { useForm } from '@inertiajs/react'
import type { CSSProperties, FormEvent } from 'react'

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
    <main style={styles.page}>
      <form style={styles.form} onSubmit={submit}>
        <h1 style={styles.heading}>Larasell Admin</h1>

        <label style={styles.label}>
          <span>Email</span>
          <input
            autoComplete="email"
            autoFocus
            onChange={(event) => form.setData('email', event.target.value)}
            style={styles.input}
            type="email"
            value={form.data.email}
          />
        </label>
        {form.errors.email ? (
          <p style={styles.error}>{form.errors.email}</p>
        ) : null}

        <label style={styles.label}>
          <span>Password</span>
          <input
            autoComplete="current-password"
            onChange={(event) => form.setData('password', event.target.value)}
            style={styles.input}
            type="password"
            value={form.data.password}
          />
        </label>
        {form.errors.password ? (
          <p style={styles.error}>{form.errors.password}</p>
        ) : null}

        <button
          disabled={form.processing}
          style={{
            ...styles.button,
            cursor: form.processing ? 'wait' : 'pointer',
            opacity: form.processing ? 0.65 : 1,
          }}
          type="submit"
        >
          Sign in
        </button>
      </form>
    </main>
  )
}

const styles = {
  page: {
    background: '#f6f5f2',
    color: '#202020',
    display: 'grid',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
    padding: '0 16px',
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
    padding: '10px 12px',
  },
  error: {
    color: '#b42318',
    fontSize: 13,
    margin: '-8px 0 0',
  },
  button: {
    background: '#202020',
    border: 0,
    borderRadius: 6,
    color: '#fff',
    font: 'inherit',
    fontWeight: 600,
    padding: '11px 14px',
  },
} satisfies Record<string, CSSProperties>
