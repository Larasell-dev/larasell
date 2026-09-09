import { Form, Head, Link } from '@inertiajs/react'

export default function Login() {
  return (
    <main>
      <Head title="Log in" />
      <h1>Log in</h1>

      <Form action="/login" method="post">
        {({ errors, processing }) => (
          <>
            <p>
              <label htmlFor="email">Email</label>
              <input id="email" name="email" type="email" autoComplete="email" autoFocus required />
              {errors.email && <span>{errors.email}</span>}
            </p>

            <p>
              <label htmlFor="password">Password</label>
              <input id="password" name="password" type="password" autoComplete="current-password" required />
            </p>

            <p>
              <label>
                <input name="remember" type="checkbox" value="1" />
                {' '}
                Remember me
              </label>
            </p>

            <button disabled={processing} type="submit">Log in</button>
          </>
        )}
      </Form>

      <p>
        Need an account?
        {' '}
        <Link href="/register">Create account</Link>
      </p>
    </main>
  )
}
