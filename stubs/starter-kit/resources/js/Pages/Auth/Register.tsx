import { Form, Head, Link } from '@inertiajs/react'

export default function Register() {
  return (
    <main>
      <Head title="Create account" />
      <h1>Create account</h1>

      <Form action="/register" method="post">
        {({ errors, processing }) => (
          <>
            <p>
              <label htmlFor="name">Name</label>
              <input id="name" name="name" type="text" autoComplete="name" autoFocus required />
              {errors.name && <span>{errors.name}</span>}
            </p>

            <p>
              <label htmlFor="email">Email</label>
              <input id="email" name="email" type="email" autoComplete="email" required />
              {errors.email && <span>{errors.email}</span>}
            </p>

            <p>
              <label htmlFor="password">Password</label>
              <input id="password" name="password" type="password" autoComplete="new-password" required />
              {errors.password && <span>{errors.password}</span>}
            </p>

            <p>
              <label htmlFor="password_confirmation">Confirm password</label>
              <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required />
            </p>

            <button disabled={processing} type="submit">Create account</button>
          </>
        )}
      </Form>

      <p>
        Already have an account?
        {' '}
        <Link href="/login">Log in</Link>
      </p>
    </main>
  )
}
