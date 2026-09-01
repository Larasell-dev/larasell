<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Enums\TaxTreatment;
use Larasell\Larasell\Price;

final class NoTaxCalculator implements TaxCalculator
{
    public function calculate(TaxCalculationContext $context): TaxResult
    {
        $lines = array_map(
            fn (TaxableLine $line): TaxLineResult => new TaxLineResult(
                lineIdentifier: $line->identifier,
                category: $line->category,
                treatment: TaxTreatment::NotTaxable,
                taxableAmount: Price::of(0),
                taxAmount: Price::of(0),
                discountAmount: $line->discountAmount,
                amount: $line->discountedAmount(),
            ),
            $context->lines,
        );

        return TaxResult::calculated($context->priceMode, $lines, metadata: ['calculator' => 'none']);
    }
}
