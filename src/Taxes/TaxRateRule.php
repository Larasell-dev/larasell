<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxTreatment;

final readonly class TaxRateRule
{
    public function __construct(
        public string $identifier,
        public string $name,
        public TaxRate $rate,
        public TaxTreatment $treatment = TaxTreatment::Taxable,
    ) {
        if (trim($identifier) === '' || trim($name) === '') {
            throw new InvalidArgumentException('A tax rate rule requires an identifier and name.');
        }

        if ($treatment === TaxTreatment::Taxable && $rate->percentage() === '0.0000') {
            throw new InvalidArgumentException('A taxable rate rule must have a positive rate.');
        }

        if ($treatment !== TaxTreatment::Taxable && $rate->percentage() !== '0.0000') {
            throw new InvalidArgumentException('A non-taxable rate rule must have a zero rate.');
        }
    }
}
