<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Address;

final readonly class CartTaxEstimateRequest
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public ?Address $shippingAddress = null,
        public ?Address $billingAddress = null,
        public ?Address $originAddress = null,
        public ?string $customerIdentifier = null,
        public array $metadata = [],
    ) {}
}
