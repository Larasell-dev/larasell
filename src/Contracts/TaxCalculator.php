<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Taxes\TaxCalculationContext;
use Larasell\Larasell\Taxes\TaxResult;

interface TaxCalculator
{
    public function calculate(TaxCalculationContext $context): TaxResult;
}
