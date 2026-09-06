<?php

namespace Larasell\Larasell\Discounts;

use Larasell\Larasell\Price;

final readonly class AppliedDiscount
{
    public function __construct(
        public string $identifier,
        public string $name,
        public Price $amount,
        public ?string $code = null,
    ) {}

    public static function fromResult(DiscountResult $result, Price $amount): self
    {
        return new self(
            identifier: $result->identifier,
            name: $result->name,
            amount: $amount,
            code: $result->code,
        );
    }
}
