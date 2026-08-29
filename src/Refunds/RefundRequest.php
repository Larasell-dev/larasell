<?php

namespace Larasell\Larasell\Refunds;

use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Refund;

final readonly class RefundRequest
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public Payment $payment,
        public Refund $refund,
        public array $options = [],
    ) {}

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
