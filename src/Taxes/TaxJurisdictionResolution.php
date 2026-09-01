<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxResultStatus;

final readonly class TaxJurisdictionResolution
{
    private function __construct(
        public TaxResultStatus $status,
        public ?TaxJurisdiction $jurisdiction,
        public ?string $reason,
    ) {}

    public static function calculated(TaxJurisdiction $jurisdiction): self
    {
        return new self(TaxResultStatus::Calculated, $jurisdiction, null);
    }

    public static function provisional(TaxJurisdiction $jurisdiction, string $reason): self
    {
        self::assertReason($reason);

        return new self(TaxResultStatus::Provisional, $jurisdiction, $reason);
    }

    public static function unavailable(string $reason): self
    {
        self::assertReason($reason);

        return new self(TaxResultStatus::Unavailable, null, $reason);
    }

    private static function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A non-calculated jurisdiction resolution requires a reason.');
        }
    }
}
