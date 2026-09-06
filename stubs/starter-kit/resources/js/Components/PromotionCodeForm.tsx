import { Form } from '@inertiajs/react'

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
    <section>
      <h2>Promotion code</h2>

      <Form
        action="/cart/promotion-codes"
        method="post"
        options={{ preserveScroll: true }}
        resetOnSuccess={['code']}
      >
        {({ errors, processing }) => (
          <>
            <label htmlFor="code">Code</label>{' '}
            <input id="code" name="code" type="text" required />{' '}
            <button type="submit" disabled={processing}>Apply</button>
            {errors.code && <p>{errors.code}</p>}
          </>
        )}
      </Form>

      {promotionCodes.length > 0 && (
        <ul>
          {promotionCodes.map((promotionCode) => (
            <li key={promotionCode.code}>
              <span>{promotionCode.code}</span>
              {promotionCode.applies ? (
                <span> {promotionCode.name} ({promotionCode.total})</span>
              ) : (
                <span> Not currently applying</span>
              )}
              {' '}
              <Form
                action="/cart/promotion-codes"
                method="delete"
                options={{ preserveScroll: true }}
              >
                {({ processing }) => (
                  <>
                    <input type="hidden" name="code" value={promotionCode.code} />
                    <button type="submit" disabled={processing}>Remove</button>
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
