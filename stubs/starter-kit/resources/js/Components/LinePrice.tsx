export type PricedLine = {
  discountTotal: string | null
  total: string
  totalAfterDiscount: string
}

export default function LinePrice({
  discountTotal,
  total,
  totalAfterDiscount,
}: PricedLine) {
  if (discountTotal === null) {
    return <span>{total}</span>
  }

  return (
    <span>
      <s>{total}</s> {totalAfterDiscount} (−{discountTotal})
    </span>
  )
}
