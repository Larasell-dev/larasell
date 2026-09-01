<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Taxes\TaxCalculationContext;
use Larasell\Larasell\Taxes\TaxJurisdictionResolution;

interface TaxJurisdictionResolver
{
    public function resolve(TaxCalculationContext $context): TaxJurisdictionResolution;
}
