<?php

namespace Larasell\Larasell\Exceptions\Cart;

final class UnavailableShippingOptionException extends CartException
{
    public function __construct(
        public readonly string $shippingOption,
    ) {
        parent::__construct("Shipping option [{$shippingOption}] is not available for this cart.");
    }

    public function reason(): string
    {
        return 'unavailable_shipping_option';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'shipping_option' => $this->shippingOption,
        ];
    }
}
