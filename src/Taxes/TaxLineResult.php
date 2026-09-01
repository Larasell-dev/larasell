<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxTreatment;
use Larasell\Larasell\Price;

final readonly class TaxLineResult
{
    /** @var array<int, TaxComponent> */
    public array $components;

    public Price $discountAmount;

    public Price $amount;

    /** @param array<int, mixed> $components */
    public function __construct(
        public string $lineIdentifier,
        public string $category,
        public TaxTreatment $treatment,
        public Price $taxableAmount,
        public Price $taxAmount,
        array $components = [],
        ?Price $discountAmount = null,
        ?Price $amount = null,
    ) {
        if (trim($lineIdentifier) === '' || trim($category) === '') {
            throw new InvalidArgumentException('A tax line result requires a line identifier and category.');
        }

        $this->discountAmount = $discountAmount ?? Price::of(0);
        $this->amount = $amount ?? $taxableAmount->add($taxAmount);

        if ($taxableAmount->amount()[0] === '-'
            || $taxAmount->amount()[0] === '-'
            || $this->discountAmount->amount()[0] === '-'
            || $this->amount->amount()[0] === '-') {
            throw new InvalidArgumentException('Tax result amounts cannot be negative.');
        }

        $componentTotal = Price::of(0);

        foreach ($components as $component) {
            if (! $component instanceof TaxComponent) {
                throw new InvalidArgumentException('Tax line components must be TaxComponent instances.');
            }

            $componentTotal = $componentTotal->add($component->amount);
        }

        if ($componentTotal->amount() !== $taxAmount->amount()) {
            throw new InvalidArgumentException('Tax component amounts must equal the line tax amount.');
        }

        $this->components = array_values($components);
    }
}
