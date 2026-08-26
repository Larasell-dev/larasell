<?php

namespace Larasell\Larasell\Shipping;

use JsonSerializable;
use Larasell\Larasell\Price;

final readonly class ShippingOption implements JsonSerializable
{
    /** @param class-string<ShippingMethod> $method */
    public function __construct(
        public string $handle,
        public string $name,
        public Price $price,
        public string $method,
    ) {}

    /** @return array{handle: string, name: string, price: array{amount: string}} */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'price' => $this->price->toArray(),
        ];
    }

    /** @return array{handle: string, name: string, price: array{amount: string}} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
