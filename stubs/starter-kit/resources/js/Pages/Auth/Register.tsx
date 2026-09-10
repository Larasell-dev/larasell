import { Form, Head, Link } from '@inertiajs/react'

const fieldClass = 'h-10 appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'

export default function Register() {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title="Create account" />

      <div className="mx-auto max-w-sm">
        <h1 className="text-3xl font-semibold">Create account</h1>

        <Form action="/register" className="mt-8 flex flex-col gap-4" method="post">
          {({ errors, processing }) => (
            <>
              <div className="flex flex-col gap-2">
                <label htmlFor="name">Name</label>
                <input
                  autoComplete="name"
                  autoFocus
                  className={fieldClass}
                  id="name"
                  name="name"
                  required
                  type="text"
                />
                {errors.name && <p>{errors.name}</p>}
              </div>

              <div className="flex flex-col gap-2">
                <label htmlFor="email">Email</label>
                <input
                  autoComplete="email"
                  className={fieldClass}
                  id="email"
                  name="email"
                  required
                  type="email"
                />
                {errors.email && <p>{errors.email}</p>}
              </div>

              <div className="flex flex-col gap-2">
                <label htmlFor="password">Password</label>
                <input
                  autoComplete="new-password"
                  className={fieldClass}
                  id="password"
                  name="password"
                  required
                  type="password"
                />
                {errors.password && <p>{errors.password}</p>}
              </div>

              <div className="flex flex-col gap-2">
                <label htmlFor="password_confirmation">Confirm password</label>
                <input
                  autoComplete="new-password"
                  className={fieldClass}
                  id="password_confirmation"
                  name="password_confirmation"
                  required
                  type="password"
                />
              </div>

              <button
                className="h-10 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring"
                disabled={processing}
                type="submit"
              >
                Create account
              </button>
            </>
          )}
        </Form>

        <p className="mt-8">
          Already have an account?
          {' '}
          <Link className="underline hover:no-underline focus:no-underline focus-visible:focus-ring" href="/login">Log in</Link>
        </p>
      </div>
    </main>
  )
}
