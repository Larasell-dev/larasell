<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Price;

final readonly class TaxComponent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $identifier,
        public string $name,
        public TaxRate $rate,
        public Price $amount,
        public TaxJurisdiction $jurisdiction,
        public array $metadata = [],
    ) {
        if (trim($identifier) === '' || trim($name) === '') {
            throw new InvalidArgumentException('A tax component requires an identifier and name.');
        }

        if ($amount->amount()[0] === '-') {
            throw new InvalidArgumentException('A tax component amount cannot be negative.');
        }
    }
}
