<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Price;

final readonly class TaxAmountCalculator
{
    private const PERCENTAGE_BASE = '1000000';

    public function __construct(private TaxRounding $rounding = new TaxRounding) {}

    public function calculate(Price $amount, TaxRate $rate, TaxPriceMode $priceMode): Price
    {
        $scaledRate = $rate->scaledPercentage();
        $denominator = $priceMode === TaxPriceMode::Inclusive
            ? bcadd(self::PERCENTAGE_BASE, $scaledRate, 0)
            : self::PERCENTAGE_BASE;

        return Price::of($this->rounding->divide(
            bcmul($amount->amount(), $scaledRate, 0),
            $denominator,
        ));
    }
}
