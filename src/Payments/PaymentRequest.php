<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;

final readonly class PaymentRequest
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public string $method,
        public Order $order,
        public Payment $payment,
        public array $options = [],
    ) {}

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
