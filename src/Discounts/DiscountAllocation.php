<?php

namespace Larasell\Larasell\Discounts;

use InvalidArgumentException;
use Larasell\Larasell\Price;

final readonly class DiscountAllocation
{
    public function __construct(
        public string $target,
        public Price $amount,
    ) {
        if (trim($target) === '') {
            throw new InvalidArgumentException('A discount allocation target is required.');
        }

        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A discount allocation amount must be positive.');
        }
    }
}
