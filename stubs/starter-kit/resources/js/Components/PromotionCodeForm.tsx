import { Form } from '@inertiajs/react'

const fieldClass = 'h-10 appearance-none border bg-transparent px-3 py-2 focus-visible:focus-ring'

export type CartPromotionCode = {
  applies: boolean
  code: string
  name: string | null
  total: string | null
}

export default function PromotionCodeForm({
  promotionCodes,
}: {
  promotionCodes: CartPromotionCode[]
}) {
  return (
    <section className="flex flex-col gap-4">
      <h2 className="font-semibold">Promotion code</h2>

      <Form
        action="/cart/promotion-codes"
        className="flex flex-col gap-4"
        method="post"
        options={{ preserveScroll: true }}
        resetOnSuccess={['code']}
      >
        {({ errors, processing }) => (
          <>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
              <div className="flex min-w-0 flex-1 flex-col gap-2">
                <label htmlFor="code">Code</label>
                <input className={`${fieldClass} w-full`} id="code" name="code" type="text" required />
              </div>
              <button
                className="h-10 min-w-20 cursor-pointer border border-black bg-black px-3 text-white hover:bg-white hover:text-black focus-visible:focus-ring"
                type="submit"
                disabled={processing}
              >
                Apply
              </button>
            </div>
            {errors.code && <p>{errors.code}</p>}
          </>
        )}
      </Form>

      {promotionCodes.length > 0 && (
        <ul className="flex flex-col gap-2">
          {promotionCodes.map((promotionCode) => (
            <li className="flex items-baseline justify-between gap-4" key={promotionCode.code}>
              <span>
                {promotionCode.code}
                {promotionCode.applies ? (
                  <> {promotionCode.name} ({promotionCode.total})</>
                ) : (
                  <> Not currently applying</>
                )}
              </span>
              <Form
                action="/cart/promotion-codes"
                method="delete"
                options={{ preserveScroll: true }}
              >
                {({ processing }) => (
                  <>
                    <input type="hidden" name="code" value={promotionCode.code} />
                    <button
                      className="cursor-pointer underline hover:no-underline focus:no-underline focus-visible:focus-ring"
                      type="submit"
                      disabled={processing}
                    >
                      Remove
                    </button>
                  </>
                )}
              </Form>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
