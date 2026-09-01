<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Price;

final readonly class TaxResult
{
    /** @var array<int, TaxLineResult> */
    public array $lines;

    /**
     * @param  array<int, mixed>  $lines
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public TaxResultStatus $status,
        public TaxPriceMode $priceMode,
        array $lines,
        public ?TaxJurisdiction $jurisdiction,
        public ?string $reason,
        public array $metadata,
    ) {
        $identifiers = [];

        foreach ($lines as $line) {
            if (! $line instanceof TaxLineResult) {
                throw new InvalidArgumentException('Tax result lines must be TaxLineResult instances.');
            }

            if (isset($identifiers[$line->lineIdentifier])) {
                throw new InvalidArgumentException('Tax result line identifiers must be unique.');
            }

            $identifiers[$line->lineIdentifier] = true;
        }

        $this->lines = array_values($lines);
    }

    /**
     * @param  array<int, mixed>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public static function calculated(TaxPriceMode $priceMode, array $lines, ?TaxJurisdiction $jurisdiction = null, array $metadata = []): self
    {
        return new self(TaxResultStatus::Calculated, $priceMode, $lines, $jurisdiction, null, $metadata);
    }

    /**
     * @param  array<int, mixed>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public static function provisional(TaxPriceMode $priceMode, array $lines, string $reason, ?TaxJurisdiction $jurisdiction = null, array $metadata = []): self
    {
        self::assertReason($reason);

        return new self(TaxResultStatus::Provisional, $priceMode, $lines, $jurisdiction, $reason, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public static function unavailable(TaxPriceMode $priceMode, string $reason, array $metadata = []): self
    {
        self::assertReason($reason);

        return new self(TaxResultStatus::Unavailable, $priceMode, [], null, $reason, $metadata);
    }

    public function taxableAmount(): Price
    {
        return array_reduce($this->lines, fn (Price $total, TaxLineResult $line): Price => $total->add($line->taxableAmount), Price::of(0));
    }

    public function taxAmount(): Price
    {
        return array_reduce($this->lines, fn (Price $total, TaxLineResult $line): Price => $total->add($line->taxAmount), Price::of(0));
    }

    private static function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A non-calculated tax result requires a reason.');
        }
    }
}
