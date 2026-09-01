<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;

final readonly class TaxCalculationContext
{
    /** @var array<int, TaxableLine> */
    public array $lines;

    /**
     * @param  array<int, mixed>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        array $lines,
        public Currency $currency,
        public TaxPriceMode $priceMode,
        public ?Address $shippingAddress = null,
        public ?Address $billingAddress = null,
        public ?Address $originAddress = null,
        public ?string $customerIdentifier = null,
        public ?string $transactionIdentifier = null,
        public array $metadata = [],
    ) {
        $identifiers = [];

        foreach ($lines as $line) {
            if (! $line instanceof TaxableLine) {
                throw new InvalidArgumentException('Tax calculation lines must be TaxableLine instances.');
            }

            if (isset($identifiers[$line->identifier])) {
                throw new InvalidArgumentException('Tax calculation line identifiers must be unique.');
            }

            $identifiers[$line->identifier] = true;
        }

        $this->lines = array_values($lines);
    }
}
