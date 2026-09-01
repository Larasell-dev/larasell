<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Price;

final readonly class CartTaxEstimate
{
    public function __construct(
        public ?Price $subtotal,
        public Price $discountAmount,
        public ?Price $shippingAmount,
        public TaxResult $tax,
    ) {}

    public function amountBeforeTax(): ?Price
    {
        if ($this->subtotal === null) {
            return null;
        }

        return $this->subtotal
            ->add($this->shippingAmount ?? Price::of(0))
            ->subtract($this->discountAmount);
    }

    public function total(): ?Price
    {
        $amount = $this->amountBeforeTax();

        if ($amount === null) {
            return null;
        }

        if ($this->tax->priceMode === TaxPriceMode::Inclusive) {
            return $amount;
        }

        return $this->tax->status === TaxResultStatus::Unavailable
            ? null
            : $amount->add($this->tax->taxAmount());
    }
}
