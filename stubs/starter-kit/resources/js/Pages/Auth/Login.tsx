import { Form, Head, Link } from '@inertiajs/react'

const fieldClass = 'h-10 appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'

export default function Login() {
  return (
    <main className="container mx-auto px-4 py-8">
      <Head title="Log in" />

      <div className="mx-auto max-w-sm">
        <h1 className="text-3xl font-semibold">Log in</h1>

        <Form action="/login" className="mt-8 flex flex-col gap-4" method="post">
          {({ errors, processing }) => (
            <>
              <div className="flex flex-col gap-2">
                <label htmlFor="email">Email</label>
                <input
                  autoComplete="email"
                  autoFocus
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
                  autoComplete="current-password"
                  className={fieldClass}
                  id="password"
                  name="password"
                  required
                  type="password"
                />
              </div>

              <label className="flex cursor-pointer items-center gap-2">
                <span className="relative inline-grid size-5 place-items-center">
                  <input
                    className="peer col-start-1 row-start-1 size-5 appearance-none border bg-transparent checked:border-black checked:bg-black focus-visible:focus-ring"
                    name="remember"
                    type="checkbox"
                    value="1"
                  />
                  <svg
                    aria-hidden="true"
                    className="pointer-events-none col-start-1 row-start-1 size-3.5 text-white opacity-0 peer-checked:opacity-100"
                    fill="none"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M3.5 8.5 6.5 11.5 12.5 4.5"
                      stroke="currentColor"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                    />
                  </svg>
                </span>
                Remember me
              </label>

              <button
                className="h-10 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring"
                disabled={processing}
                type="submit"
              >
                Log in
              </button>
            </>
          )}
        </Form>

        <p className="mt-8">
          Need an account?
          {' '}
          <Link className="underline hover:no-underline focus:no-underline focus-visible:focus-ring" href="/register">Create account</Link>
        </p>
      </div>
    </main>
  )
}
