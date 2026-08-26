<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Enums\PaymentStatus;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $reference = null,
        public ?string $failureMessage = null,
        public PaymentStatus $status = PaymentStatus::Succeeded,
    ) {}

    public static function pending(?string $reference = null): self
    {
        return new self(false, $reference, status: PaymentStatus::Pending);
    }
}
