<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;

final readonly class TaxJurisdiction
{
    public function __construct(
        public string $identifier,
        public string $name,
        public string $country,
        public ?string $state = null,
        public ?string $county = null,
        public ?string $city = null,
        public ?string $district = null,
    ) {
        if (trim($identifier) === '' || trim($name) === '' || trim($country) === '') {
            throw new InvalidArgumentException('A tax jurisdiction requires an identifier, name, and country.');
        }
    }
}
