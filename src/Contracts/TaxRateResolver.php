<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Taxes\TaxJurisdiction;
use Larasell\Larasell\Taxes\TaxRateRule;

interface TaxRateResolver
{
    public function resolve(string $category, TaxJurisdiction $jurisdiction): ?TaxRateRule;
}
