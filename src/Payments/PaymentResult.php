<?php

namespace Larasell\Larasell\Payments;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $reference = null,
        public ?string $failureMessage = null,
    ) {}
}
