<?php

namespace Larasell\Larasell\Payments;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Price;

final readonly class PaymentLine implements JsonSerializable
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $identifier,
        public string $name,
        public int $quantity,
        public Price $amount,
        public array $metadata = [],
    ) {
        if (trim($identifier) === '') {
            throw new InvalidArgumentException('A payment line identifier is required.');
        }

        if (trim($name) === '') {
            throw new InvalidArgumentException('A payment line name is required.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('A payment line quantity must be at least one.');
        }

        if (Price::of(0)->greaterThan($amount)) {
            throw new InvalidArgumentException('A payment line amount cannot be negative.');
        }
    }

    /** @return array{identifier: string, name: string, quantity: int, amount: array{amount: string}, metadata: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'amount' => $this->amount->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    /** @return array{identifier: string, name: string, quantity: int, amount: array{amount: string}, metadata: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
