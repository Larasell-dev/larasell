<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Price;

final readonly class PaymentRequest
{
    public function __construct(
        public string $orderNumber,
        public Price $amount,
        public string $customerEmail,
    ) {}
}
