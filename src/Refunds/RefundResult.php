<?php

namespace Larasell\Larasell\Refunds;

use Larasell\Larasell\Enums\RefundStatus;

final readonly class RefundResult
{
    public function __construct(
        public RefundStatus $status,
        public ?string $reference = null,
        public ?string $failureMessage = null,
    ) {}

    public static function pending(?string $reference = null): self
    {
        return new self(RefundStatus::Pending, $reference);
    }

    public static function succeeded(?string $reference = null): self
    {
        return new self(RefundStatus::Succeeded, $reference);
    }

    public static function failed(?string $failureMessage = null, ?string $reference = null): self
    {
        return new self(RefundStatus::Failed, $reference, $failureMessage);
    }

    public static function cancelled(?string $reference = null): self
    {
        return new self(RefundStatus::Cancelled, $reference);
    }
}
