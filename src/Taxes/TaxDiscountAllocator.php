<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Discounts\ProportionalDiscountAllocator;
use Larasell\Larasell\Price;

final readonly class TaxDiscountAllocator
{
    public function __construct(private ProportionalDiscountAllocator $allocator) {}

    /**
     * @param  array<int, TaxableLine>  $lines
     * @return array<int, TaxableLine>
     */
    public function allocate(Price $discount, array $lines): array
    {
        $amounts = [];

        foreach ($lines as $line) {
            $amounts[$line->identifier] = $line->discountedAmount();
        }

        $allocations = [];

        foreach ($this->allocator->allocate($discount, $amounts) as $allocation) {
            $allocations[$allocation->target] = $allocation->amount;
        }

        return array_map(
            fn (TaxableLine $line): TaxableLine => $line->withAdditionalDiscount(
                $allocations[$line->identifier] ?? Price::of(0),
            ),
            $lines,
        );
    }
}
