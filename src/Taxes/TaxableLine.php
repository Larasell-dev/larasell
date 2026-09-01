<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxableLineType;
use Larasell\Larasell\Price;

final readonly class TaxableLine
{
    public Price $discountAmount;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $identifier,
        public Price $amount,
        public string $category,
        public int $quantity = 1,
        public array $metadata = [],
        ?Price $discountAmount = null,
        public TaxableLineType $type = TaxableLineType::Product,
    ) {
        if (trim($identifier) === '') {
            throw new InvalidArgumentException('A taxable line identifier is required.');
        }

        if (trim($category) === '') {
            throw new InvalidArgumentException('A taxable line category is required.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('A taxable line quantity must be at least one.');
        }

        if ($amount->amount()[0] === '-') {
            throw new InvalidArgumentException('A taxable line amount cannot be negative.');
        }

        $this->discountAmount = $discountAmount ?? Price::of(0);

        if ($this->discountAmount->amount()[0] === '-' || $this->discountAmount->greaterThan($amount)) {
            throw new InvalidArgumentException('A taxable line discount must be between zero and the line amount.');
        }
    }

    /** @param array<string, mixed> $metadata */
    public static function shipping(string $identifier, Price $amount, string $category = 'shipping', array $metadata = [], ?Price $discountAmount = null): self
    {
        return new self(
            identifier: $identifier,
            amount: $amount,
            category: $category,
            metadata: $metadata,
            discountAmount: $discountAmount,
            type: TaxableLineType::Shipping,
        );
    }

    public function discountedAmount(): Price
    {
        return $this->amount->subtract($this->discountAmount);
    }

    public function withAdditionalDiscount(Price $discount): self
    {
        return new self(
            identifier: $this->identifier,
            amount: $this->amount,
            category: $this->category,
            quantity: $this->quantity,
            metadata: $this->metadata,
            discountAmount: $this->discountAmount->add($discount),
            type: $this->type,
        );
    }
}
