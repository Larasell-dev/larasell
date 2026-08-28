<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Enums\PaymentStatus;

final readonly class PaymentResult
{
    private function __construct(
        public PaymentStatus $status,
        public ?string $reference = null,
        public ?PaymentAction $action = null,
        public ?string $failureMessage = null,
    ) {}

    public static function pending(?string $reference = null, ?PaymentAction $action = null): self
    {
        return new self(PaymentStatus::Pending, $reference, $action);
    }

    public static function succeeded(?string $reference = null): self
    {
        return new self(PaymentStatus::Succeeded, $reference);
    }

    public static function failed(?string $failureMessage = null, ?string $reference = null): self
    {
        return new self(PaymentStatus::Failed, $reference, failureMessage: $failureMessage);
    }
}
