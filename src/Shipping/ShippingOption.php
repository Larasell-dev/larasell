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
        public bool $requiresAddress = true,
    ) {}

    /** @return array{handle: string, name: string, price: array{amount: string}, requires_address: bool} */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'price' => $this->price->toArray(),
            'requires_address' => $this->requiresAddress,
        ];
    }

    /** @return array{handle: string, name: string, price: array{amount: string}, requires_address: bool} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
