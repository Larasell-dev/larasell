<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Price;

final readonly class PaymentRequest
{
    public function __construct(
        public string $method,
        public string $orderNumber,
        public Price $amount,
        public Currency $currency,
        public string $customerEmail,
    ) {}
}
